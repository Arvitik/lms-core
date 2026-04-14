<?php

namespace App\Http\Controllers;

use App\ScheduleBoardEntry;
use App\ExamSchedule;
use App\Group;
use App\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleBoardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Главное расписание — недельная таблица Преподаватель/Дата
     */
    public function index(Request $request)
    {
        $weekStart = $request->get('week')
            ? Carbon::parse($request->get('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        for ($d = $weekStart->copy(); $d->lte($weekEnd); $d->addDay()) {
            $days[] = $d->copy();
        }

        $boardIds = DB::table('schedule_board_teachers')->pluck('teacher_id');
        if ($boardIds->isEmpty()) {
            $teachers = User::where('role', 'Преподаватель')
                ->orderBy('last_name')->orderBy('first_name')->get();
        } else {
            $teachers = User::whereIn('id', $boardIds)
                ->orderBy('last_name')->orderBy('first_name')->get();
        }

        $entries = ScheduleBoardEntry::with(['teacher', 'groups'])
            ->whereBetween('entry_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        // teacher_id => 'Y-m-d' => [entries]
        $entryMap = [];
        foreach ($entries as $entry) {
            $tid = $entry->teacher_id;
            $ds  = Carbon::parse($entry->entry_date)->format('Y-m-d');
            $entryMap[$tid][$ds][] = $entry;
        }

        $prevWeek = $weekStart->copy()->subWeek()->toDateString();
        $nextWeek = $weekStart->copy()->addWeek()->toDateString();

        $canEdit = in_array(Auth::user()->role, ['Преподаватель', 'Админ']);

        return view('schedule_board.index', compact(
            'teachers', 'days', 'entryMap', 'weekStart', 'weekEnd', 'prevWeek', 'nextWeek', 'canEdit'
        ));
    }

    /**
     * Форма создания записи
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['Преподаватель', 'Админ'])) {
            abort(403);
        }

        [$teachers] = $this->teachersAndGroups($user);
        $groups = Group::whereArchived(0)->orderBy('group_name')->get(['group_id', 'group_name']);
        $entry = null;

        $prefillTeacherId = $request->get('teacher_id');
        $prefillDate      = $request->get('date');

        return view('schedule_board.form', compact('teachers', 'groups', 'entry', 'prefillTeacherId', 'prefillDate'));
    }

    /**
     * Сохранить запись
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['Преподаватель', 'Админ'])) {
            abort(403);
        }

        $this->validateEntry($request);

        $teacherId = ($user->role === 'Админ') ? $request->teacher_id : $user->id;
        $timeEnd   = $this->calcTimeEnd($request);
        $allGroups = $request->input('all_groups') ? 1 : 0;
        $groupIds  = $allGroups ? [] : array_filter(array_map('intval', (array) $request->input('group_ids', [])));

        if ($request->recurring && $request->recurring_until) {
            $current = Carbon::parse($request->entry_date);
            $until   = Carbon::parse($request->recurring_until);
            $first = ScheduleBoardEntry::create([
                'teacher_id'  => $teacherId, 'group_id'    => null,
                'entry_date'  => $current->toDateString(),
                'time_start'  => $request->time_start, 'time_end' => $timeEnd,
                'entry_type'  => $request->entry_type, 'room'     => $request->room,
                'title'       => '', 'description' => null,
                'series_id'   => null, 'all_groups' => $allGroups,
            ]);
            $seriesId = $first->id;
            $first->update(['series_id' => $seriesId]);
            if ($groupIds) $first->groups()->sync($groupIds);
            $current->addWeek();
            $count = 1;
            while ($current->lte($until)) {
                $e = ScheduleBoardEntry::create([
                    'teacher_id'  => $teacherId, 'group_id'    => null,
                    'entry_date'  => $current->toDateString(),
                    'time_start'  => $request->time_start, 'time_end' => $timeEnd,
                    'entry_type'  => $request->entry_type, 'room'     => $request->room,
                    'title'       => '', 'description' => null,
                    'series_id'   => $seriesId, 'all_groups' => $allGroups,
                ]);
                if ($groupIds) $e->groups()->sync($groupIds);
                $count++;
                $current->addWeek();
            }
            $week = Carbon::parse($request->entry_date)
                ->startOfWeek(Carbon::MONDAY)->toDateString();
            return redirect()->route('schedule_board.index', ['week' => $week])
                ->with('success', "Создано систематических занятий: {$count}.");
        }

        $krTitle  = $request->entry_type === 'КР' ? trim($request->kr_title) : '';

        $newEntry = ScheduleBoardEntry::create([
            'teacher_id'  => $teacherId, 'group_id'    => null,
            'entry_date'  => $request->entry_date,
            'time_start'  => $request->time_start, 'time_end' => $timeEnd,
            'entry_type'  => $request->entry_type, 'room'     => $request->room,
            'title'       => $krTitle, 'description' => null,
            'series_id'   => null, 'all_groups' => $allGroups,
        ]);
        if ($groupIds) $newEntry->groups()->sync($groupIds);

        // Для КР — создаём ExamSchedule и отправляем уведомления
        if ($request->entry_type === 'КР') {
            $examSchedule = ExamSchedule::create([
                'teacher_id'     => $teacherId,
                'group_id'       => null,
                'title'          => $krTitle,
                'description'    => null,
                'scheduled_date' => $request->entry_date,
                'time_start'     => $request->time_start,
                'time_end'       => $timeEnd,
                'room'           => $request->room,
            ]);
            if ($groupIds) {
                $examSchedule->groups()->sync($groupIds);
            }
            $newEntry->update(['exam_schedule_id' => $examSchedule->id]);

            if (!empty($groupIds)) {
                $studentIds = User::whereIn('group', $groupIds)
                    ->whereIn('role', ['Студент', 'Студент-заочник', 'Староста'])
                    ->pluck('id')->toArray();

                if (!empty($studentIds)) {
                    $groupNames  = Group::whereIn('group_id', $groupIds)->pluck('group_name')->implode(', ');
                    $date        = Carbon::parse($request->entry_date)->format('d.m.Y');
                    $teacher     = User::find($teacherId);
                    $teacherName = $teacher ? $teacher->last_name . ' ' . $teacher->first_name : '';

                    NotificationService::sendMany(
                        $studentIds,
                        'exam_scheduled',
                        'Назначена контрольная работа',
                        'Назначена контрольная «' . $krTitle . '» на ' . $date .
                            ' (' . $teacherName . ', ' . $groupNames . ').',
                        ['exam_schedule_id' => $examSchedule->id]
                    );
                }
            }
        }

        $week = Carbon::parse($request->entry_date)
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        return redirect()->route('schedule_board.index', ['week' => $week])
            ->with('success', 'Занятие добавлено в расписание.');
    }

    /**
     * Форма редактирования
     */
    public function edit($id)
    {
        $entry = ScheduleBoardEntry::findOrFail($id);
        $user  = Auth::user();

        if ($user->role !== 'Админ' && $entry->teacher_id !== $user->id) {
            abort(403);
        }

        [$teachers] = $this->teachersAndGroups($user);
        $groups = Group::whereArchived(0)->orderBy('group_name')->get(['group_id', 'group_name']);

        return view('schedule_board.form', compact('teachers', 'groups', 'entry'));
    }

    /**
     * Обновить запись
     */
    public function update(Request $request, $id)
    {
        $entry = ScheduleBoardEntry::findOrFail($id);
        $user  = Auth::user();

        if ($user->role !== 'Админ' && $entry->teacher_id !== $user->id) {
            abort(403);
        }

        $this->validateEntry($request);

        $teacherId = ($user->role === 'Админ') ? $request->teacher_id : $user->id;

        $allGroups = $request->input('all_groups') ? 1 : 0;
        $groupIds  = $allGroups ? [] : array_filter(array_map('intval', (array) $request->input('group_ids', [])));
        $timeEnd   = $this->calcTimeEnd($request);
        $krTitle   = $request->entry_type === 'КР' ? trim($request->kr_title) : $entry->title;

        $entry->update([
            'teacher_id'  => $teacherId, 'group_id' => null,
            'entry_date'  => $request->entry_date,
            'time_start'  => $request->time_start,
            'time_end'    => $timeEnd,
            'entry_type'  => $request->entry_type,
            'room'        => $request->room,
            'title'       => $krTitle, 'description' => null,
            'all_groups'  => $allGroups,
        ]);
        $entry->groups()->sync($groupIds);

        // Синхронизация с ExamSchedule если это КР
        if ($entry->exam_schedule_id) {
            $examSchedule = ExamSchedule::find($entry->exam_schedule_id);
            if ($examSchedule) {
                $examSchedule->update([
                    'teacher_id'     => $teacherId,
                    'title'          => $krTitle,
                    'scheduled_date' => $request->entry_date,
                    'time_start'     => $request->time_start,
                    'time_end'       => $timeEnd,
                    'room'           => $request->room,
                ]);
                $examSchedule->groups()->sync($groupIds);
            }
        }

        $week = Carbon::parse($request->entry_date)
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        return redirect()->route('schedule_board.index', ['week' => $week])
            ->with('success', 'Запись обновлена.');
    }

    /**
     * Удалить запись
     */
    public function destroy($id)
    {
        $entry = ScheduleBoardEntry::findOrFail($id);
        $user  = Auth::user();

        if ($user->role !== 'Админ' && $entry->teacher_id !== $user->id) {
            abort(403);
        }

        $week = Carbon::parse($entry->entry_date)
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        if ($entry->exam_schedule_id) {
            ExamSchedule::where('id', $entry->exam_schedule_id)->delete();
        }

        $entry->delete();

        return redirect()->route('schedule_board.index', ['week' => $week])
            ->with('success', 'Запись удалена.');
    }

    /**
     * AJAX: Детали ячейки (преподаватель + дата)
     * Возвращает HTML-фрагмент для модального окна
     */
    public function cellDetail(Request $request)
    {
        $teacherId = (int) $request->get('teacher_id');
        $date      = $request->get('date');

        if (!$teacherId || !$date) {
            return response()->json(['error' => 'Bad request'], 400);
        }

        $entries = ScheduleBoardEntry::with(['groups'])
            ->where('teacher_id', $teacherId)
            ->where('entry_date', $date)
            ->orderBy('time_start')
            ->get();

        $teacher = User::find($teacherId);

        // Для семинаров — подгружаем ближайшие контрольные группы
        $controlWorks = collect();
        $seminarGroupIds = $entries->where('entry_type', 'Семинар')
            ->pluck('group_id')->filter()->unique();

        if ($seminarGroupIds->isNotEmpty()) {
            $dateFrom = Carbon::parse($date)->subDays(7)->toDateString();
            $dateTo   = Carbon::parse($date)->addDays(14)->toDateString();
            $controlWorks = ExamSchedule::with(['group'])
                ->whereIn('group_id', $seminarGroupIds)
                ->whereBetween('scheduled_date', [$dateFrom, $dateTo])
                ->orderBy('scheduled_date')
                ->get();
        }

        $canEdit = in_array(Auth::user()->role, ['Преподаватель', 'Админ'])
            && (Auth::user()->role === 'Админ' || Auth::user()->id === $teacherId);

        $html = view('schedule_board.cell_detail', compact(
            'entries', 'teacher', 'controlWorks', 'date', 'canEdit'
        ))->render();

        return response()->json(['html' => $html]);
    }

    /**
     * Удалить всю серию (все занятия с тем же series_id)
     */
    public function destroySeries($id)
    {
        $entry = ScheduleBoardEntry::findOrFail($id);
        $user  = Auth::user();

        if ($user->role !== 'Админ' && $entry->teacher_id !== $user->id) {
            abort(403);
        }

        $week = Carbon::parse($entry->entry_date)
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        if ($entry->series_id) {
            $count = ScheduleBoardEntry::where('series_id', $entry->series_id)->count();
            ScheduleBoardEntry::where('series_id', $entry->series_id)->delete();
            return redirect()->route('schedule_board.index', ['week' => $week])
                ->with('success', "Серия удалена ({$count} занятий).");
        }

        $entry->delete();
        return redirect()->route('schedule_board.index', ['week' => $week])
            ->with('success', 'Запись удалена.');
    }

    // -------------------------------------------------------------------------
    // Управление преподавателями и администраторами
    // -------------------------------------------------------------------------

    public function manageTeachers()
    {
        if (Auth::user()->role !== 'Админ') abort(403);

        $boardIds = DB::table('schedule_board_teachers')->pluck('teacher_id');

        $boardTeachers = User::whereIn('id', $boardIds)
            ->orderBy('last_name')->orderBy('first_name')->get();

        $availableTeachers = User::whereIn('role', ['Преподаватель', 'Админ'])
            ->whereNotIn('id', $boardIds)
            ->orderBy('last_name')->orderBy('first_name')->get();

        return view('schedule_board.manage', compact('boardTeachers', 'availableTeachers'));
    }

    public function addTeachers(Request $request)
    {
        if (Auth::user()->role !== 'Админ') abort(403);

        $ids = $request->input('teacher_ids', []);
        if (empty($ids)) {
            return redirect()->route('schedule_board.manage')
                ->with('error', 'Выберите хотя бы одного преподавателя.');
        }

        foreach ((array) $ids as $id) {
            $intId = (int) $id;
            if (!DB::table('schedule_board_teachers')->where('teacher_id', $intId)->exists()) {
                DB::table('schedule_board_teachers')->insert(['teacher_id' => $intId]);
            }
        }

        return redirect()->route('schedule_board.manage')
            ->with('success', 'Преподаватели добавлены на табло.');
    }

    public function removeTeacher($id)
    {
        if (Auth::user()->role !== 'Админ') abort(403);

        DB::table('schedule_board_teachers')->where('teacher_id', (int) $id)->delete();

        return redirect()->route('schedule_board.manage')
            ->with('success', 'Преподаватель удалён с табло.');
    }

    // -------------------------------------------------------------------------

    private function teachersAndGroups(User $user): array
    {
        if ($user->role === 'Админ') {
            $boardIds = DB::table('schedule_board_teachers')->pluck('teacher_id');
            if ($boardIds->isEmpty()) {
                $teachers = User::whereIn('role', ['Преподаватель', 'Админ'])
                    ->orderBy('last_name')->get();
            } else {
                $teachers = User::whereIn('id', $boardIds)
                    ->orderBy('last_name')->get();
            }
            $groups = Group::whereArchived(0)
                ->orderBy('group_name')->get(['group_id', 'group_name']);
        } else {
            $teachers  = collect([$user]);
            $groupIds  = DB::table('teacher_has_group')
                ->where('user_id', $user->id)->pluck('group');
            $groups = Group::whereArchived(0)
                ->whereIn('group_id', $groupIds)
                ->orderBy('group_name')->get(['group_id', 'group_name']);
        }

        return [$teachers, $groups];
    }

    private function validateEntry(Request $request): void
    {
        $rules = [
            'teacher_id'      => 'required|integer',
            'entry_date'      => 'required|date',
            'time_start'      => 'required',
            'entry_type'      => 'required|in:Лекция,Семинар,Зачет,КР',
            'room'            => 'required|string|max:100',
            'recurring_until' => 'nullable|date|after_or_equal:entry_date',
        ];
        if ($request->entry_type === 'Зачет' || $request->entry_type === 'КР') {
            $rules['time_end'] = 'required';
        }
        if ($request->entry_type === 'КР') {
            $rules['kr_title'] = 'required|string|max:255';
        }
        $this->validate($request, $rules);
    }

    private function calcTimeEnd(Request $request): string
    {
        if (in_array($request->entry_type, ['Зачет', 'КР'])) {
            return substr($request->time_end ?? '', 0, 5) ?: substr($request->time_start, 0, 5);
        }
        $start = Carbon::createFromFormat('H:i', substr($request->time_start, 0, 5));
        return $start->addMinutes(95)->format('H:i');
    }
}
