<?php

namespace App\Http\Controllers;

use App\Group;
use App\User;
use App\ExamSchedule;
use App\Services\NotificationService;
use App\TeacherHasGroup;
use App\Testing\Test;
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

        $tests = Test::where('test_type', 'Контрольный')->orderBy('test_name')->get(['id_test', 'test_name']);

        if ($user->role === 'Админ') {
            $schedules = ExamSchedule::with(['teacher', 'group'])->orderBy('scheduled_date', 'desc')->get();
        } else {
            $schedules = ExamSchedule::with(['teacher', 'group'])->where('teacher_id', $user->id)->orderBy('scheduled_date', 'desc')->get();
        }

        return view('broadcast_notifications.create', compact('groups', 'students', 'tests', 'schedules'));
    }

    /**
     * Отправить массовое уведомление
     */
    public function send(Request $request)
    {
        $this->validate($request, [
            'audience'  => 'required|in:all,group,student',
            'title'     => 'required|string|max:255',
            'body'      => 'required|string',
            'group_id'  => 'required_if:audience,group|integer|min:1',
            'student_id'=> 'required_if:audience,student|integer|min:1',
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
            $groupId = $request->group_id;

            // Проверка доступа для преподавателя
            if ($user->role !== 'Админ') {
                $allowed = DB::table('teacher_has_group')
                    ->where('user_id', $user->id)->where('group', $groupId)->exists();
                if (!$allowed) abort(403);
            }

            $userIds = User::where('group', $groupId)
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
                ->withErrors(['audience' => 'Не найдено ни одного получателя. Убедитесь, что в группе есть студенты.']);
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
            'group_id'       => 'required|integer',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'test_id'        => 'nullable|integer',
        ]);

        $user = Auth::user();

        if ($user->role !== 'Админ') {
            $allowed = DB::table('teacher_has_group')
                ->where('user_id', $user->id)
                ->where('group', $request->group_id)
                ->exists();
            if (!$allowed) abort(403, 'Нет доступа к этой группе.');
        }

        $schedule = ExamSchedule::create([
            'teacher_id'     => $user->id,
            'group_id'       => $request->group_id,
            'test_id'        => $request->test_id ?: null,
            'title'          => $request->title,
            'description'    => $request->description,
            'scheduled_date' => $request->scheduled_date,
        ]);

        $studentIds = User::where('group', $request->group_id)
            ->whereIn('role', ['Студент', 'Студент-заочник', 'Староста'])
            ->pluck('id')->toArray();

        if (!empty($studentIds)) {
            $group = Group::whereGroup_id($request->group_id)->first();
            $groupName = $group ? $group->group_name : 'вашей группе';
            $date = \Carbon\Carbon::parse($request->scheduled_date)->format('d.m.Y');
            $teacherName = $user->last_name . ' ' . $user->first_name;

            NotificationService::sendMany(
                $studentIds,
                'exam_scheduled',
                'Назначена контрольная работа',
                'В ' . $groupName . ' назначена контрольная «' . $request->title . '» на ' . $date . ' (' . $teacherName . ').',
                ['exam_schedule_id' => $schedule->id, 'url' => route('exam_schedules.student')]
            );
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
