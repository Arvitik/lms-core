<?php

namespace App\Http\Controllers;

use App\ExamSchedule;
use App\ScheduleBoardEntry;
use App\Group;
use App\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamScheduleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();

        $query = ExamSchedule::with(['teacher', 'groups'])->orderBy('scheduled_date', 'desc');

        if ($user->role !== 'Админ') {
            $query->where('teacher_id', $user->id);
        }

        $schedules = $query->get();

        return view('exam_schedules.index', compact('schedules'));
    }

    public function create()
    {
        $user = Auth::user();

        // Преподаватели с табло (или все если табло пустое)
        if ($user->role === 'Админ') {
            $boardIds = DB::table('schedule_board_teachers')->pluck('teacher_id');
            if ($boardIds->isEmpty()) {
                $teachers = User::whereIn('role', ['Преподаватель', 'Админ'])
                    ->orderBy('last_name')->get();
            } else {
                $teachers = User::whereIn('id', $boardIds)->orderBy('last_name')->get();
            }
        } else {
            $teachers = collect([$user]);
        }

        $groups = Group::whereArchived(0)->orderBy('group_name')->get(['group_id', 'group_name']);

        return view('exam_schedules.create', compact('groups', 'teachers'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'teacher_id'     => 'required|integer',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'time_start'     => 'required',
            'time_end'       => 'nullable',
            'room'           => 'required|string|max:100',
            'group_ids'      => 'nullable|array',
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

        // Создаём запись в расписании на доске
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

        // Уведомить студентов выбранных групп
        if (!empty($groupIds)) {
            $studentIds = User::whereIn('group', $groupIds)
                ->whereIn('role', ['Студент', 'Студент-заочник', 'Староста'])
                ->pluck('id')->toArray();

            if (!empty($studentIds)) {
                $groupNames = Group::whereIn('group_id', $groupIds)
                    ->pluck('group_name')->implode(', ');
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

        return redirect()->route('exam_schedules.index')
            ->with('success', 'Контрольная назначена, студенты уведомлены.');
    }

    public function destroy($id)
    {
        $schedule = ExamSchedule::findOrFail($id);
        $user     = Auth::user();

        if ($user->role !== 'Админ' && $schedule->teacher_id !== $user->id) {
            abort(403);
        }

        // Удаляем связанную запись на доске
        ScheduleBoardEntry::where('exam_schedule_id', $schedule->id)->delete();

        $schedule->delete();

        return redirect()->route('exam_schedules.index')->with('success', 'Контрольная удалена.');
    }

    public function studentIndex()
    {
        $user    = Auth::user();
        $groupId = $user->group;

        $upcoming = ExamSchedule::with(['teacher', 'groups'])
            ->whereHas('groups', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            })
            ->where('scheduled_date', '>=', Carbon::today()->toDateString())
            ->orderBy('scheduled_date')
            ->get();

        $past = ExamSchedule::with(['teacher', 'groups'])
            ->whereHas('groups', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            })
            ->where('scheduled_date', '<', Carbon::today()->toDateString())
            ->orderBy('scheduled_date', 'desc')
            ->limit(5)
            ->get();

        foreach ($upcoming as $exam) {
            DB::table('exam_schedule_views')->updateOrInsert(
                ['user_id' => $user->id, 'exam_schedule_id' => $exam->id]
            );
        }

        return view('exam_schedules.student', compact('upcoming', 'past'));
    }

    public function upcomingCount()
    {
        $user    = Auth::user();
        $groupId = $user->group;

        $count = ExamSchedule::whereHas('groups', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            })
            ->where('scheduled_date', '>=', Carbon::today()->toDateString())
            ->whereNotIn('id', function ($q) use ($user) {
                $q->from('exam_schedule_views')
                  ->where('user_id', $user->id)
                  ->select('exam_schedule_id');
            })
            ->count();

        return response()->json(['count' => $count]);
    }
}
