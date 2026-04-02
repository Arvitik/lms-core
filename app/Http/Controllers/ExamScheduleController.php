<?php

namespace App\Http\Controllers;

use App\ExamSchedule;
use App\Group;
use App\User;
use App\Testing\Test;
use App\TeacherHasGroup;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamScheduleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Список назначенных контрольных (для преподавателя/админа)
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'Админ') {
            $schedules = ExamSchedule::with(['teacher', 'group'])
                ->orderBy('scheduled_date', 'desc')
                ->get();
        } else {
            // Преподаватель видит только свои
            $schedules = ExamSchedule::with(['teacher', 'group'])
                ->where('teacher_id', $user->id)
                ->orderBy('scheduled_date', 'desc')
                ->get();
        }

        return view('exam_schedules.index', compact('schedules'));
    }

    /**
     * Форма назначения контрольной
     */
    public function create()
    {
        $user = Auth::user();

        if ($user->role === 'Админ') {
            $groups = Group::whereArchived(0)->orderBy('group_name')->get(['group_id', 'group_name']);
        } else {
            // Только группы преподавателя
            $groupIds = DB::table('teacher_has_group')->where('user_id', $user->id)->pluck('group');
            $groups = Group::whereArchived(0)->whereIn('group_id', $groupIds)->orderBy('group_name')->get(['group_id', 'group_name']);
        }

        // Контрольные тесты для выбора (необязательно)
        $tests = Test::where('test_type', 'Контрольный')->orderBy('test_name')->get(['id_test', 'test_name']);

        return view('exam_schedules.create', compact('groups', 'tests'));
    }

    /**
     * Сохранить контрольную и уведомить студентов
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'group_id'       => 'required|integer',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'test_id'        => 'nullable|integer',
        ]);

        $user = Auth::user();

        // Проверка прав для преподавателя
        if ($user->role !== 'Админ') {
            $allowed = DB::table('teacher_has_group')
                ->where('user_id', $user->id)
                ->where('group', $request->group_id)
                ->exists();
            if (!$allowed) {
                abort(403, 'Нет доступа к этой группе.');
            }
        }

        $schedule = ExamSchedule::create([
            'teacher_id'     => $user->id,
            'group_id'       => $request->group_id,
            'test_id'        => $request->test_id ?: null,
            'title'          => $request->title,
            'description'    => $request->description,
            'scheduled_date' => $request->scheduled_date,
        ]);

        // Уведомить всех студентов группы
        $studentIds = User::where('group', $request->group_id)
            ->whereIn('role', ['Студент', 'Студент-заочник', 'Староста'])
            ->pluck('id')
            ->toArray();

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

        return redirect()->route('exam_schedules.index')
            ->with('success', 'Контрольная назначена, студенты уведомлены.');
    }

    /**
     * Удалить контрольную
     */
    public function destroy($id)
    {
        $schedule = ExamSchedule::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'Админ' && $schedule->teacher_id !== $user->id) {
            abort(403);
        }

        $schedule->delete();

        return redirect()->route('exam_schedules.index')->with('success', 'Контрольная удалена.');
    }

    /**
     * Вид для студента — предстоящие контрольные своей группы
     */
    public function studentIndex()
    {
        $user = Auth::user();
        $upcoming = ExamSchedule::with(['teacher'])
            ->where('group_id', $user->group)
            ->where('scheduled_date', '>=', \Carbon\Carbon::today()->toDateString())
            ->orderBy('scheduled_date')
            ->get();

        $past = ExamSchedule::with(['teacher'])
            ->where('group_id', $user->group)
            ->where('scheduled_date', '<', \Carbon\Carbon::today()->toDateString())
            ->orderBy('scheduled_date', 'desc')
            ->limit(5)
            ->get();

        // Помечаем все предстоящие как просмотренные
        foreach ($upcoming as $exam) {
            \DB::table('exam_schedule_views')->updateOrInsert(
                ['user_id' => $user->id, 'exam_schedule_id' => $exam->id]
            );
        }

        return view('exam_schedules.student', compact('upcoming', 'past'));
    }

    public function upcomingCount()
    {
        $user = Auth::user();
        $count = ExamSchedule::where('group_id', $user->group)
            ->where('scheduled_date', '>=', \Carbon\Carbon::today()->toDateString())
            ->whereNotIn('id', function($q) use ($user) {
                $q->from('exam_schedule_views')
                  ->where('user_id', $user->id)
                  ->select('exam_schedule_id');
            })
            ->count();
        return response()->json(['count' => $count]);
    }
}
