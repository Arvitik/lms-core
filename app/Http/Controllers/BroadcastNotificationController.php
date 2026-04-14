<?php

namespace App\Http\Controllers;

use App\Group;
use App\User;
use App\ExamSchedule;
use App\ScheduleBoardEntry;
use App\Services\NotificationService;
use App\TeacherHasGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BroadcastNotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Форма отправки массового уведомления
     */
    public function create()
    {
        $user = Auth::user();

        if ($user->role === 'Админ') {
            $groups = Group::whereArchived(0)->orderBy('group_name')->get(['group_id', 'group_name']);
            // Все студенты для выбора конкретного
            $students = User::whereIn('role', ['Студент', 'Староста'])
                ->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'group']);
        } else {
            // Преподаватель — только свои группы
            $groupIds = DB::table('teacher_has_group')->where('user_id', $user->id)->pluck('group');
            $groups = Group::whereArchived(0)->whereIn('group_id', $groupIds)->orderBy('group_name')->get(['group_id', 'group_name']);
            $students = User::whereIn('role', ['Студент', 'Староста'])
                ->whereIn('group', $groupIds)
                ->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'group']);
        }

        // Преподаватели с табло для формы КР
        if ($user->role === 'Админ') {
            $boardIds = DB::table('schedule_board_teachers')->pluck('teacher_id');
            $teachers = $boardIds->isEmpty()
                ? User::whereIn('role', ['Преподаватель', 'Админ'])->orderBy('last_name')->get()
                : User::whereIn('id', $boardIds)->orderBy('last_name')->get();
        } else {
            $teachers = collect([$user]);
        }

        $query = ExamSchedule::with(['teacher', 'groups'])->orderBy('scheduled_date', 'desc');
        if ($user->role !== 'Админ') {
            $query->where('teacher_id', $user->id);
        }
        $schedules = $query->get();

        return view('broadcast_notifications.create', compact('groups', 'students', 'teachers', 'schedules'));
    }

    /**
     * Отправить массовое уведомление
     */
    public function send(Request $request)
    {
        $this->validate($request, [
            'audience'    => 'required|in:all,group,student',
            'title'       => 'required|string|max:255',
            'body'        => 'required|string',
            'group_ids'   => 'required_if:audience,group|array|min:1',
            'group_ids.*' => 'integer|min:1',
            'student_id'  => 'required_if:audience,student|integer|min:1',
        ]);

        $user = Auth::user();
        $audience = $request->audience;
        $userIds = [];

        if ($audience === 'all') {
            if ($user->role !== 'Админ') {
                abort(403, 'Только администратор может отправлять всем.');
            }
            $userIds = User::whereNotIn('role', ['Архив', 'Аноним'])
                ->where('id', '!=', $user->id)
                ->pluck('id')->toArray();

        } elseif ($audience === 'group') {
            $groupIds = array_filter(array_map('intval', (array) $request->group_ids));

            // Проверка доступа для преподавателя
            if ($user->role !== 'Админ') {
                foreach ($groupIds as $gid) {
                    $allowed = DB::table('teacher_has_group')
                        ->where('user_id', $user->id)->where('group', $gid)->exists();
                    if (!$allowed) abort(403);
                }
            }

            $userIds = User::whereIn('group', $groupIds)
                ->whereIn('role', ['Студент', 'Староста'])
                ->pluck('id')->toArray();

        } elseif ($audience === 'student') {
            $studentId = $request->student_id;

            // Проверка: преподаватель может писать только студенту из своей группы
            if ($user->role !== 'Админ') {
                $student = User::findOrFail($studentId);
                $allowed = DB::table('teacher_has_group')
                    ->where('user_id', $user->id)->where('group', $student->group)->exists();
                if (!$allowed) abort(403);
            }

            $userIds = [$studentId];
        }

        if (empty($userIds)) {
            return redirect()->route('broadcast.create')
                ->withInput()
                ->withErrors(['audience' => 'Не найдено ни одного получателя. Убедитесь, что в выбранных группах есть студенты.']);
        }

        NotificationService::sendMany(
            $userIds,
            'announcement',
            $request->title,
            $request->body,
            []
        );

        $sent = count($userIds);
        return redirect()->route('broadcast.create')
            ->with('success', 'Уведомление отправлено ' . $sent . ' ' . $this->pluralUsers($sent) . '.');
    }

    /**
     * Назначить контрольную работу и уведомить студентов
     */
    public function storeExam(Request $request)
    {
        $this->validate($request, [
            'teacher_id'     => 'required|integer',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'time_start'     => 'required',
            'time_end'       => 'nullable',
            'room'           => 'required|string|max:100',
            'group_ids'      => 'required|array|min:1',
        ]);

        $user      = Auth::user();
        $teacherId = ($user->role === 'Админ') ? (int) $request->teacher_id : $user->id;
        $groupIds  = array_filter(array_map('intval', (array) $request->input('group_ids', [])));
        $timeEnd   = $request->time_end ?: null;

        $schedule = ExamSchedule::create([
            'teacher_id'     => $teacherId,
            'group_id'       => null,
            'title'          => $request->title,
            'description'    => $request->description,
            'scheduled_date' => $request->scheduled_date,
            'time_start'     => $request->time_start,
            'time_end'       => $timeEnd,
            'room'           => $request->room,
        ]);

        if ($groupIds) {
            $schedule->groups()->sync($groupIds);
        }

        // Запись на доске расписания
        $boardEntry = ScheduleBoardEntry::create([
            'teacher_id'       => $teacherId,
            'group_id'         => null,
            'entry_date'       => $request->scheduled_date,
            'time_start'       => $request->time_start,
            'time_end'         => $timeEnd,
            'entry_type'       => 'КР',
            'room'             => $request->room,
            'title'            => $request->title,
            'description'      => null,
            'series_id'        => null,
            'all_groups'       => 0,
            'exam_schedule_id' => $schedule->id,
        ]);
        if ($groupIds) {
            $boardEntry->groups()->sync($groupIds);
        }

        // Уведомить студентов
        if (!empty($groupIds)) {
            $studentIds = User::whereIn('group', $groupIds)
                ->whereIn('role', ['Студент', 'Студент-заочник', 'Староста'])
                ->pluck('id')->toArray();

            if (!empty($studentIds)) {
                $groupNames  = Group::whereIn('group_id', $groupIds)->pluck('group_name')->implode(', ');
                $date        = Carbon::parse($request->scheduled_date)->format('d.m.Y');
                $teacher     = User::find($teacherId);
                $teacherName = $teacher ? $teacher->last_name . ' ' . $teacher->first_name : '';

                NotificationService::sendMany(
                    $studentIds,
                    'exam_scheduled',
                    'Назначена контрольная работа',
                    'Назначена контрольная «' . $request->title . '» на ' . $date .
                        ' (' . $teacherName . ', ' . $groupNames . ').',
                    ['exam_schedule_id' => $schedule->id, 'url' => route('exam_schedules.student')]
                );
            }
        }

        return redirect()->route('broadcast.create', ['tab' => 'exam'])
            ->with('success_exam', 'Контрольная назначена, студенты уведомлены.');
    }

    /**
     * Удалить контрольную работу
     */
    public function destroyExam($id)
    {
        $schedule = ExamSchedule::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'Админ' && $schedule->teacher_id !== $user->id) {
            abort(403);
        }

        ScheduleBoardEntry::where('exam_schedule_id', $schedule->id)->delete();

        $schedule->delete();

        return redirect()->route('broadcast.create', ['tab' => 'exam'])
            ->with('success_exam', 'Контрольная удалена.');
    }

    private function pluralUsers($n)
    {
        if ($n % 100 >= 11 && $n % 100 <= 19) return 'пользователям';
        switch ($n % 10) {
            case 1: return 'пользователю';
            case 2: case 3: case 4: return 'пользователям';
            default: return 'пользователям';
        }
    }
}
