<?php

namespace App\Http\Controllers;

use App\Group;
use App\ScheduleBoardEntry;
use App\Services\NotificationService;
use App\TeacherHasGroup;
use App\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CurrentControlController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $groups = $this->availableGroups($user);
        $selectedGroupId = $request->input('group');

        if (!$selectedGroupId && count($groups) > 0) {
            $selectedGroupId = $groups[0]->group_id;
        }

        $selectedGroup = $selectedGroupId ? Group::where('group_id', $selectedGroupId)->first() : null;

        $schedule = $selectedGroupId
            ? DB::table('current_control_schedule')
                ->where('group_id', $selectedGroupId)
                ->orderBy('weekday', 'asc')
                ->orderBy('time_start', 'asc')
                ->orderBy('lesson_date', 'asc')
                ->orderBy('id', 'asc')
                ->get()
            : array();

        $deanCount = $selectedGroupId
            ? DB::table('dean_group_student_counts')->where('group_id', $selectedGroupId)->first()
            : null;

        $checker = $selectedGroup ? $this->buildChecker($selectedGroup, $deanCount) : null;

        return view('current_control.index', array(
            'groups' => $groups,
            'selectedGroupId' => $selectedGroupId,
            'selectedGroup' => $selectedGroup,
            'schedule' => $schedule,
            'deanCount' => $deanCount,
            'checker' => $checker,
            'activeTab' => $request->input('tab', 'schedule'),
        ));
    }

    public function storeSchedule(Request $request)
    {
        $this->validateSchedule($request);
        $this->abortIfGroupUnavailable($request->input('group_id'));

        DB::table('current_control_schedule')->insert($this->schedulePayload($request));

        return redirect()->route('current_control.index', array('group' => $request->input('group_id'), 'tab' => 'schedule'))
            ->with('success', 'Занятие добавлено в расписание.');
    }

    public function schedulePage(Request $request)
    {
        $request->merge(array('tab' => 'schedule'));
        return $this->index($request);
    }

    public function checkerPage(Request $request)
    {
        $groups = $this->availableGroups(Auth::user());
        $matrixRows = array();
        $maxWeeks = 0;

        foreach ($groups as $group) {
            $deanCount = DB::table('dean_group_student_counts')
                ->where('group_id', $group->group_id)
                ->first();
            $checker = $this->buildChecker($group, $deanCount);
            $lectures = $this->flattenCheckerSections($checker['lecture_sections']);
            $seminars = $this->flattenCheckerSections($checker['seminar_sections']);
            $weekCount = max(count($lectures), count($seminars));
            $maxWeeks = max($maxWeeks, $weekCount);

            $matrixRows[] = array(
                'group' => $group,
                'checker' => $checker,
                'lectures' => $lectures,
                'seminars' => $seminars,
                'week_count' => $weekCount,
            );
        }

        return view('current_control.checker_overview', compact('matrixRows', 'maxWeeks'));
    }

    public function checkerWeekDetails(Request $request)
    {
        $groupId = (int) $request->input('group_id');
        $weekIndex = (int) $request->input('week');
        $this->abortIfGroupUnavailable($groupId);

        if ($weekIndex < 0 || $weekIndex > 200) {
            abort(422);
        }

        $group = Group::where('group_id', $groupId)->firstOrFail();
        $deanCount = DB::table('dean_group_student_counts')->where('group_id', $groupId)->first();
        $checker = $this->buildChecker($group, $deanCount);
        $lectures = $this->flattenCheckerSections($checker['lecture_sections']);
        $seminars = $this->flattenCheckerSections($checker['seminar_sections']);
        $lecture = isset($lectures[$weekIndex]) ? $lectures[$weekIndex] : null;
        $seminar = isset($seminars[$weekIndex]) ? $seminars[$weekIndex] : null;
        $teachers = DB::table('teacher_has_group')
            ->join('users', 'users.id', '=', 'teacher_has_group.user_id')
            ->where('teacher_has_group.group', $groupId)
            ->whereNull('users.deleted_at')
            ->whereIn('users.role', array('Преподаватель', 'Старший преподаватель', 'Админ'))
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get(array('users.id', 'users.last_name', 'users.first_name'))
            ->map(function ($teacher) {
                return array(
                    'id' => (int) $teacher->id,
                    'name' => trim($teacher->last_name . ' ' . $teacher->first_name),
                );
            })
            ->values();

        return response()->json(array(
            'group' => $group->group_name,
            'week' => $weekIndex + 1,
            'teachers' => $teachers,
            'lecture' => $this->checkerLessonDetails($groupId, 'lecture', $lecture),
            'seminar' => $this->checkerLessonDetails($groupId, 'seminar', $seminar),
        ));
    }

    public function deanCountsPage()
    {
        $groups = $this->availableGroups(Auth::user());
        $counts = DB::table('dean_group_student_counts')->get()->keyBy('group_id');
        $registered = DB::table('users')
            ->whereIn('role', array('Студент', 'Староста', 'РЎС‚СѓРґРµРЅС‚', 'РЎС‚Р°СЂРѕСЃС‚Р°'))
            ->whereNull('deleted_at')
            ->select('group', DB::raw('COUNT(*) as student_count'))
            ->groupBy('group')
            ->get()
            ->keyBy('group');

        return view('current_control.dean_counts', compact('groups', 'counts', 'registered'));
    }

    public function saveDeanCounts(Request $request)
    {
        $counts = (array) $request->input('counts', array());
        $availableGroupIds = $this->availableGroups(Auth::user())->pluck('group_id')->map(function ($id) {
            return (int) $id;
        })->toArray();
        $saved = 0;

        foreach ($counts as $groupId => $expectedCount) {
            $groupId = (int) $groupId;
            if (!in_array($groupId, $availableGroupIds, true) || $expectedCount === '') {
                continue;
            }
            if (filter_var($expectedCount, FILTER_VALIDATE_INT) === false || (int) $expectedCount < 0) {
                return redirect()->route('current_control.dean_counts')
                    ->withErrors(array('counts' => 'Численность должна быть целым неотрицательным числом.'));
            }

            DB::table('dean_group_student_counts')->updateOrInsert(
                array('group_id' => $groupId),
                array(
                    'expected_count' => (int) $expectedCount,
                    'source_comment' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                )
            );
            $saved++;
        }

        return redirect()->route('current_control.dean_counts')
            ->with('success', 'Сохранена численность групп: ' . $saved . '.');
    }

    public function storeBulkSchedule(Request $request)
    {
        $groupId = (int) $request->input('group_id');
        $this->abortIfGroupUnavailable($groupId);

        $rows = $request->input('schedule_rows', array());
        $batch = date('YmdHis') . '_' . Auth::user()->id;
        $inserted = 0;

        foreach ($rows as $row) {
            if (empty($row['title'])) {
                continue;
            }

            $rowRequest = new Request(array_merge($row, array(
                'group_id' => $groupId,
                'import_batch' => $batch,
            )));

            DB::table('current_control_schedule')->insert($this->schedulePayload($rowRequest));
            $inserted++;
        }

        return redirect()->route('current_control.index', array('group' => $groupId, 'tab' => 'schedule'))
            ->with('success', 'Сохранено строк расписания: ' . $inserted . '.');
    }

    public function importSchedule(Request $request)
    {
        $this->validate($request, array(
            'group_id' => 'required|integer',
            'schedule_file' => 'file|max:10240',
            'schedule_files' => 'array|max:20',
            'schedule_files.*' => 'file|max:10240',
            'schedule_text' => 'max:200000',
        ));

        $groupId = (int) $request->input('group_id');
        $this->abortIfGroupUnavailable($groupId);

        $texts = array();
        if (trim((string) $request->input('schedule_text', '')) !== '') {
            $texts[] = $request->input('schedule_text');
        }

        $files = array();
        if ($request->hasFile('schedule_file')) {
            $files[] = $request->file('schedule_file');
        }
        if ($request->hasFile('schedule_files')) {
            $files = array_merge($files, (array) $request->file('schedule_files'));
        }

        foreach ($files as $file) {
            $extension = mb_strtolower($file->getClientOriginalExtension(), 'UTF-8');

            if (!in_array($extension, array('pdf', 'txt', 'html', 'htm', 'csv'))) {
                return redirect()->route('current_control.schedule', array('group' => $groupId))
                    ->withErrors(array('schedule_file' => 'Поддерживаются PDF, TXT, HTML и CSV.'));
            }

            if ($extension === 'pdf') {
                $texts[] = $this->extractPdfText($file->getRealPath());
            } else {
                $texts[] = file_get_contents($file->getRealPath());
            }
        }

        $rows = array();
        foreach ($texts as $text) {
            $rows = array_merge($rows, $this->parseScheduleText($text));
        }
        if (count($rows) === 0) {
            return redirect()->route('current_control.schedule', array('group' => $groupId))
                ->withErrors(array('schedule_file' => 'В файле не найдены строк расписания. Проверьте, что каждая строка содержит время начала и окончания.'));
        }

        $batch = date('YmdHis') . '_' . Auth::user()->id;
        $inserted = 0;
        $skipped = 0;
        $unknownGroups = array();
        $availableGroups = $this->availableGroups(Auth::user())->keyBy('group_name');

        foreach ($rows as $row) {
            if (!$this->isCourseScheduleRow($row)) {
                continue;
            }

            $groupIds = array();
            foreach ($row['group_names'] as $groupName) {
                if ($availableGroups->has($groupName)) {
                    $groupIds[] = (int) $availableGroups->get($groupName)->group_id;
                } else {
                    $unknownGroups[$groupName] = true;
                }
            }
            if (empty($groupIds)) {
                $groupIds[] = $groupId;
            }

            $row['title'] = $this->canonicalCourseTitle($row['title']);
            $row['teacher_id'] = $this->findTeacherId($row['teacher_name']);

            foreach (array_unique($groupIds) as $targetGroupId) {
                if ($this->scheduleRowExists($targetGroupId, $row)) {
                    $skipped++;
                    continue;
                }

                $rowRequest = new Request(array_merge($row, array(
                    'group_id' => $targetGroupId,
                    'import_batch' => $batch,
                )));

                DB::table('current_control_schedule')->insert($this->schedulePayload($rowRequest));
                $this->syncScheduleBoardRow($targetGroupId, $row);
                $inserted++;
            }
        }

        $message = 'Добавлено строк: ' . $inserted . '. Пропущено повторов: ' . $skipped . '.';
        if (!empty($unknownGroups)) {
            $message .= ' Не найдены активные группы: ' . implode(', ', array_keys($unknownGroups)) . '.';
        }

        return redirect()->route('current_control.schedule', array('group' => $groupId))
            ->with('success', $message);
    }

    public function updateSchedule(Request $request, $id)
    {
        $item = DB::table('current_control_schedule')->where('id', $id)->first();
        if (!$item) {
            abort(404);
        }

        $this->abortIfGroupUnavailable($item->group_id);
        $this->validateSchedule($request, false);

        $payload = $this->schedulePayload(new Request(array_merge($request->all(), array('group_id' => $item->group_id))));
        unset($payload['created_at']);

        DB::table('current_control_schedule')->where('id', $id)->update($payload);
        $this->notifyScheduleChanges($item, (object) $payload);

        return redirect()->route('current_control.index', array('group' => $item->group_id, 'tab' => 'schedule'))
            ->with('success', 'Строка расписания обновлена.');
    }

    public function deleteSchedule(Request $request, $id)
    {
        $scheduleItem = DB::table('current_control_schedule')->where('id', $id)->first();
        if (!$scheduleItem) {
            abort(404);
        }

        $this->abortIfGroupUnavailable($scheduleItem->group_id);
        DB::table('current_control_schedule')->where('id', $id)->delete();

        return redirect()->route('current_control.index', array('group' => $scheduleItem->group_id, 'tab' => 'schedule'))
            ->with('success', 'Занятие удалено из расписания.');
    }

    public function saveDeanCount(Request $request)
    {
        $this->validate($request, array(
            'group_id' => 'required|integer',
            'expected_count' => 'required|integer|min:0',
        ));

        $groupId = (int) $request->input('group_id');
        $this->abortIfGroupUnavailable($groupId);

        DB::table('dean_group_student_counts')->updateOrInsert(
            array('group_id' => $groupId),
            array(
                'expected_count' => (int) $request->input('expected_count'),
                'source_comment' => null,
                'updated_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            )
        );

        return redirect()->route('current_control.index', array('group' => $groupId, 'tab' => 'checker'))
            ->with('success', 'Число студентов по списку деканата сохранено.');
    }

    public function studentSchedule()
    {
        $user = Auth::user();
        $group = Group::where('group_id', $user->group)->first();
        $schedule = array();

        if ($group) {
            $schedule = DB::table('current_control_schedule')
                ->where('group_id', $group->group_id)
                ->orderBy('weekday', 'asc')
                ->orderBy('time_start', 'asc')
                ->orderBy('lesson_date', 'asc')
                ->get();
        }

        return view('current_control.student_schedule', array(
            'group' => $group,
            'schedule' => $schedule,
        ));
    }

    private function extractPdfText($path)
    {
        $binary = trim((string) shell_exec('command -v pdftotext'));
        if ($binary === '') {
            abort(500, 'Для загрузки PDF нужен poppler-utils/pdftotext в Docker-контейнере.');
        }

        $command = escapeshellarg($binary) . ' -layout ' . escapeshellarg($path) . ' -';
        $text = shell_exec($command);

        return (string) $text;
    }

    private function parseScheduleText($text)
    {
        $text = (string) $text;
        if (!mb_check_encoding($text, 'UTF-8')) {
            $encoding = mb_detect_encoding($text, array('Windows-1251', 'CP1251', 'KOI8-R', 'ISO-8859-5'), true);
            if ($encoding) {
                $text = mb_convert_encoding($text, 'UTF-8', $encoding);
            }
        }

        $text = preg_replace('/<\s*(br|\/p|\/div|\/tr|\/li)\s*\/?>/iu', "\n", $text);
        $text = preg_replace('/<\s*\/td\s*>/iu', ' ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = str_replace(array("\xC2\xA0", '–', '—'), array(' ', '-', '-'), $text);
        $lines = preg_split('/\r\n|\r|\n/u', $text);
        $rows = array();
        $weekday = null;
        $currentIndex = null;
        $compactText = preg_replace('/\s+/u', '', $text);
        $defaultGroups = array();
        if (preg_match('/Расписаниезаняти(?:й|я)группы([БB]\d{2}-\d{3})/ui', $compactText, $groupMatch)) {
            $defaultGroups = $this->extractGroupNames($groupMatch[1]);
        }
        $defaultPeriod = array(null, null);
        if (preg_match('/Осеннийсеместр(20\d{2})\/(20\d{2})/ui', $compactText, $yearMatch)) {
            $defaultPeriod = array($yearMatch[1] . '-09-01', $yearMatch[1] . '-12-31');
        }

        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line));
            if ($line === '') {
                continue;
            }

            $detectedWeekday = $this->detectWeekday($line);
            if ($detectedWeekday) {
                $weekday = $detectedWeekday;
                $currentIndex = null;
                $line = trim(preg_replace('/^(?:пн|вт|ср|чт|пт|сб|вс|понедельник|вторник|среда|четверг|пятница|суббота|воскресенье)[\s,.:;-]*/ui', '', $line));
                if ($line === '') {
                    continue;
                }
            }

            if (preg_match('/(?:^|\s)(\d{1,2})[:.](\d{2})\s*[-]\s*(\d{1,2})[:.](\d{2})(?:\s+|[,;]\s*)(.+)$/u', $line, $matches)) {
                $tail = trim($matches[5]);
                $period = $this->extractPeriod($tail);
                if (!$period[0]) {
                    $period = $defaultPeriod;
                }
                $rows[] = array(
                    'weekday' => $weekday,
                    'time_start' => sprintf('%02d:%02d', $matches[1], $matches[2]),
                    'time_end' => sprintf('%02d:%02d', $matches[3], $matches[4]),
                    'type' => $this->detectType($tail),
                    'title' => $this->cleanScheduleTitle($tail),
                    'teacher_name' => $this->extractTeacher($tail),
                    'auditorium' => $this->extractAuditorium($tail),
                    'group_names' => array_values(array_unique(array_merge($defaultGroups, $this->extractGroupNames($tail)))),
                    'date_from' => $period[0],
                    'date_to' => $period[1],
                    'status' => 'active',
                    'comment' => '',
                );
                $currentIndex = count($rows) - 1;
                continue;
            }

            if ($currentIndex !== null) {
                $combined = $rows[$currentIndex]['title'] . ' ' . $line;
                $period = $this->extractPeriod($combined);
                $rows[$currentIndex]['title'] = $this->cleanScheduleTitle($combined);
                $rows[$currentIndex]['teacher_name'] = $rows[$currentIndex]['teacher_name'] ?: $this->extractTeacher($combined);
                $rows[$currentIndex]['auditorium'] = $rows[$currentIndex]['auditorium'] ?: $this->extractAuditorium($combined);
                $rows[$currentIndex]['group_names'] = array_values(array_unique(array_merge(
                    $rows[$currentIndex]['group_names'],
                    $this->extractGroupNames($line)
                )));
                $rows[$currentIndex]['date_from'] = $rows[$currentIndex]['date_from'] ?: $period[0];
                $rows[$currentIndex]['date_to'] = $rows[$currentIndex]['date_to'] ?: $period[1];
            }
        }

        $unique = array();
        foreach ($rows as $row) {
            if ($row['title'] === '') {
                continue;
            }
            $key = implode('|', array($row['weekday'], $row['time_start'], $row['time_end'], mb_strtolower($row['title'], 'UTF-8'), implode(',', $row['group_names']), $row['date_from'], $row['date_to']));
            $unique[$key] = $row;
        }

        return array_values($unique);
    }

    private function detectWeekday($line)
    {
        $upper = mb_strtoupper($line, 'UTF-8');
        $map = array(
            'ПОНЕДЕЛЬНИК' => 1,
            'ПН' => 1,
            'ВТОРНИК' => 2,
            'ВТ' => 2,
            'СРЕДА' => 3,
            'СР' => 3,
            'ЧЕТВЕРГ' => 4,
            'ЧТ' => 4,
            'ПЯТНИЦА' => 5,
            'ПТ' => 5,
            'СУББОТА' => 6,
            'СБ' => 6,
            'ВОСКРЕСЕНЬЕ' => 7,
            'ВС' => 7,
        );

        foreach ($map as $name => $number) {
            if (preg_match('/(^|[^\p{L}])'.preg_quote($name, '/').'([^\p{L}]|$)/u', $upper)) {
                return $number;
            }
        }

        return null;
    }

    private function detectType($line)
    {
        if (preg_match('/\b(ПР|ПРАКТИКА|СЕМИНАР|ЛАБ|АУД)\b/ui', $line)) {
            return 'seminar';
        }
        if (preg_match('/\b(КР|КМ|КОНТРОЛ)/ui', $line)) {
            return 'control';
        }

        return 'lecture';
    }

    private function scheduleRowExists($groupId, array $row)
    {
        return DB::table('current_control_schedule')
            ->where('group_id', $groupId)
            ->where('weekday', $row['weekday'])
            ->where('time_start', $row['time_start'])
            ->where('time_end', $row['time_end'])
            ->where('title', $row['title'])
            ->where('date_from', $row['date_from'])
            ->where('date_to', $row['date_to'])
            ->exists();
    }

    private function extractAuditorium($line)
    {
        if (preg_match('/\x{F041}\s*([^\x{F000}-\x{F8FF}\n]+)$/u', $line, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/(?:📍\s*)?(?:каф\.|ауд\.|к\.|K-|К-)\s*[A-Za-zА-Яа-я0-9\-]+/u', $line, $matches)) {
            return trim(str_replace('📍', '', $matches[0]));
        }

        return null;
    }

    private function extractTeacher($line)
    {
        if (preg_match('/\x{F19D}\s*([^\x{F000}-\x{F8FF}\n]+?)(?=\x{F041}|$)/u', $line, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/🎓\s*([^📍\n]+?)(?:\s*(?:📍|каф\.|ауд\.|$))/u', $line, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/([А-ЯЁ][а-яё]+ [А-ЯЁ]\.[А-ЯЁ]\.)/u', $line, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractPeriod($line)
    {
        if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s*[-]\s*(\d{2})\.(\d{2})\.(\d{4})/u', $line, $matches)) {
            return array(
                $matches[3] . '-' . $matches[2] . '-' . $matches[1],
                $matches[6] . '-' . $matches[5] . '-' . $matches[4],
            );
        }

        return array(null, null);
    }

    private function cleanScheduleTitle($line)
    {
        $line = preg_replace('/[■◩]|\[[^\]]+\]|\b(ЛЕК|ПР|ЛАБ|АУД|Ф)\b/u', ' ', $line);
        $line = preg_replace('/[\x{F0C0}\x{F19D}\x{F041}\x{F06A}].*$/u', ' ', $line);
        $line = preg_replace('/\(\d{2}\.\d{2}\.\d{4}\s*[-]\s*\d{2}\.\d{2}\.\d{4}\)/u', ' ', $line);
        $line = preg_replace('/🎓.*$/u', ' ', $line);
        $line = preg_replace('/📍.*$/u', ' ', $line);
        $line = preg_replace('/\s+/u', ' ', $line);

        return trim($line);
    }

    private function extractGroupNames($line)
    {
        preg_match_all('/[БB]\d{2}-\d{3}/u', (string) $line, $matches);
        $groups = array();
        foreach ($matches[0] as $group) {
            $groups[] = preg_replace('/^B/u', 'Б', $group);
        }

        return array_values(array_unique($groups));
    }

    private function isCourseScheduleRow(array $row)
    {
        $title = mb_strtolower(preg_replace('/\s+/u', '', $row['title']), 'UTF-8');

        return mb_strpos($title, 'теорияалгоритмов') !== false
            || mb_strpos($title, 'алгоритмыивычислительнаясложность') !== false;
    }

    private function canonicalCourseTitle($title)
    {
        $compact = mb_strtolower(preg_replace('/\s+/u', '', $title), 'UTF-8');
        if (mb_strpos($compact, 'дискретнаяматематика') !== false && mb_strpos($compact, 'теорияалгоритмов') !== false) {
            return 'Дискретная математика (теория алгоритмов и сложность вычислений)';
        }
        if (mb_strpos($compact, 'алгоритмыивычислительнаясложность') !== false) {
            return 'Алгоритмы и вычислительная сложность';
        }

        return 'Теория алгоритмов и сложность вычислений';
    }

    private function findTeacherId($teacherName)
    {
        if (!$teacherName || !preg_match('/^([А-ЯЁ][а-яё]+)/u', trim($teacherName), $matches)) {
            return null;
        }

        $teacher = User::where('last_name', $matches[1])
            ->whereIn('role', array('Преподаватель', 'Старший преподаватель', 'Админ'))
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->first();

        return $teacher ? (int) $teacher->id : null;
    }

    private function syncScheduleBoardRow($groupId, array $row)
    {
        if (empty($row['teacher_id']) || empty($row['weekday']) || empty($row['time_start']) || empty($row['date_from'])) {
            return;
        }

        $from = Carbon::parse($row['date_from']);
        $to = Carbon::parse($row['date_to'] ?: $row['date_from']);
        $offset = ((int) $row['weekday'] - (int) $from->dayOfWeekIso + 7) % 7;
        $current = $from->copy()->addDays($offset);
        $seriesId = null;
        $entryType = $row['type'] === 'lecture' ? 'Лекция' : ($row['type'] === 'control' ? 'КР' : 'Семинар');

        while ($current->lte($to)) {
            $entry = ScheduleBoardEntry::where('teacher_id', $row['teacher_id'])
                ->where('entry_date', $current->toDateString())
                ->where('time_start', $row['time_start'])
                ->where('entry_type', $entryType)
                ->where('title', $row['title'])
                ->first();

            if (!$entry) {
                $entry = ScheduleBoardEntry::create(array(
                    'teacher_id' => $row['teacher_id'],
                    'group_id' => null,
                    'entry_date' => $current->toDateString(),
                    'time_start' => $row['time_start'],
                    'time_end' => $row['time_end'],
                    'entry_type' => $entryType,
                    'room' => $row['auditorium'] ?: '',
                    'title' => $row['title'],
                    'description' => 'Импортировано из расписания',
                    'series_id' => $seriesId,
                    'all_groups' => 0,
                ));
                if (!$seriesId) {
                    $seriesId = $entry->id;
                    $entry->update(array('series_id' => $seriesId));
                }
            } elseif (!$seriesId) {
                $seriesId = $entry->series_id ?: $entry->id;
            }

            DB::table('schedule_board_entry_groups')->updateOrInsert(array(
                'entry_id' => $entry->id,
                'group_id' => $groupId,
            ));
            $current->addWeek();
        }
    }

    private function validateSchedule(Request $request, $requireGroup = true)
    {
        $rules = array(
            'type' => 'required|in:lecture,seminar,control',
            'lesson_date' => 'date',
            'weekday' => 'integer|min:1|max:7',
            'time_start' => 'date_format:H:i',
            'time_end' => 'date_format:H:i',
            'date_from' => 'date',
            'date_to' => 'date',
            'title' => 'required|max:255',
            'auditorium' => 'max:100',
            'teacher_name' => 'max:255',
            'replacement_teacher' => 'max:255',
            'status' => 'in:active,canceled,moved,replacement',
            'comment' => 'max:2000',
        );

        if ($requireGroup) {
            $rules['group_id'] = 'required|integer';
        }

        $this->validate($request, $rules);
    }

    private function schedulePayload(Request $request)
    {
        $dateFrom = $request->input('date_from') ?: null;
        $lessonDate = $request->input('lesson_date') ?: $dateFrom;

        return array(
            'group_id' => (int) $request->input('group_id'),
            'teacher_id' => $request->input('teacher_id') ?: Auth::user()->id,
            'type' => $request->input('type', 'lecture'),
            'lesson_date' => $lessonDate ?: date('Y-m-d'),
            'weekday' => $request->input('weekday') ?: null,
            'time_start' => $request->input('time_start') ?: null,
            'time_end' => $request->input('time_end') ?: null,
            'date_from' => $dateFrom,
            'date_to' => $request->input('date_to') ?: null,
            'auditorium' => $request->input('auditorium'),
            'teacher_name' => $request->input('teacher_name'),
            'replacement_teacher' => $request->input('replacement_teacher'),
            'status' => $request->input('status', 'active'),
            'import_batch' => $request->input('import_batch'),
            'title' => $request->input('title'),
            'comment' => $request->input('comment'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    private function availableGroups($user)
    {
        $query = Group::where('archived', 0)
            ->where('academic', 1)
            ->orderBy('group_name');

        if ($user->role !== 'Админ' && $user->role !== 'РђРґРјРёРЅ') {
            $groupIds = TeacherHasGroup::where('user_id', $user->id)->pluck('group')->toArray();
            $query->whereIn('group_id', $groupIds);
        }

        return $query->get();
    }

    private function abortIfGroupUnavailable($groupId)
    {
        $groups = $this->availableGroups(Auth::user());
        foreach ($groups as $group) {
            if ((int) $group->group_id === (int) $groupId) {
                return;
            }
        }

        abort(403);
    }

    private function buildChecker($group, $deanCount)
    {
        $studentQuery = DB::table('users')
            ->where('group', $group->group_id)
            ->whereIn('role', array('Студент', 'Староста', 'РЎС‚СѓРґРµРЅС‚', 'РЎС‚Р°СЂРѕСЃС‚Р°'))
            ->whereNull('deleted_at');

        $studentIds = $studentQuery->pluck('id')->toArray();
        $registeredCount = count($studentIds);
        $expectedCount = $deanCount ? (int) $deanCount->expected_count : null;

        $coursePlanId = $group->id_course_plan;
        $lecturePlanIds = array();
        $seminarPlanIds = array();

        if ($coursePlanId) {
            $lecturePlanIds = DB::table('lecture_plans')
                ->join('section_plans', 'section_plans.id_section_plan', '=', 'lecture_plans.id_section_plan')
                ->where('section_plans.id_course_plan', $coursePlanId)
                ->where('section_plans.is_exam', 0)
                ->orderBy('lecture_plans.lecture_plan_num', 'asc')
                ->orderBy('lecture_plans.id_lecture_plan', 'asc')
                ->pluck('lecture_plans.id_lecture_plan')
                ->toArray();

            $seminarPlanIds = DB::table('seminar_plans')
                ->join('section_plans', 'section_plans.id_section_plan', '=', 'seminar_plans.id_section_plan')
                ->where('section_plans.id_course_plan', $coursePlanId)
                ->where('section_plans.is_exam', 0)
                ->orderBy('seminar_plans.seminar_plan_num', 'asc')
                ->orderBy('seminar_plans.id_seminar_plan', 'asc')
                ->pluck('seminar_plans.id_seminar_plan')
                ->toArray();
        }

        $allLecturePlanCount = count($lecturePlanIds);
        $allSeminarPlanCount = count($seminarPlanIds);

        $lectureExpectedRows = count($lecturePlanIds) * $registeredCount;
        $lectureRows = $this->countPassRows('lecture_passes', 'id_lecture_plan', $lecturePlanIds, $studentIds);
        $lecturePresentRows = $this->countPassRows('lecture_passes', 'id_lecture_plan', $lecturePlanIds, $studentIds, true);

        $seminarExpectedRows = count($seminarPlanIds) * $registeredCount;
        $seminarRows = $this->countPassRows('seminar_passes', 'id_seminar_plan', $seminarPlanIds, $studentIds);
        $seminarPresentRows = $this->countPassRows('seminar_passes', 'id_seminar_plan', $seminarPlanIds, $studentIds, true);
        $seminarWorkRows = $this->countSeminarWorkRows($seminarPlanIds, $studentIds);

        return array(
            'registered_count' => $registeredCount,
            'expected_count' => $expectedCount,
            'count_delta' => $expectedCount === null ? null : $registeredCount - $expectedCount,
            'course_plan_missing' => empty($coursePlanId),
            'lecture_total_plans' => $allLecturePlanCount,
            'lecture_plans' => count($lecturePlanIds),
            'lecture_due_lessons' => $allLecturePlanCount,
            'lecture_expected_rows' => $lectureExpectedRows,
            'lecture_rows' => $lectureRows,
            'lecture_missing_rows' => max(0, $lectureExpectedRows - $lectureRows),
            'lecture_present_rows' => $lecturePresentRows,
            'lecture_sections' => $this->buildStatementCheckerSections($coursePlanId, 'lecture', $studentIds, $registeredCount),
            'seminar_total_plans' => $allSeminarPlanCount,
            'seminar_plans' => count($seminarPlanIds),
            'seminar_due_lessons' => $allSeminarPlanCount,
            'seminar_expected_rows' => $seminarExpectedRows,
            'seminar_rows' => $seminarRows,
            'seminar_missing_rows' => max(0, $seminarExpectedRows - $seminarRows),
            'seminar_present_rows' => $seminarPresentRows,
            'seminar_work_rows' => $seminarWorkRows,
            'seminar_sections' => $this->buildStatementCheckerSections($coursePlanId, 'seminar', $studentIds, $registeredCount),
        );
    }

    private function buildStatementCheckerSections($coursePlanId, $type, $studentIds, $registeredCount)
    {
        if (!$coursePlanId) {
            return array();
        }

        $isLecture = $type === 'lecture';
        $planTable = $isLecture ? 'lecture_plans' : 'seminar_plans';
        $planIdColumn = $isLecture ? 'id_lecture_plan' : 'id_seminar_plan';
        $planNumColumn = $isLecture ? 'lecture_plan_num' : 'seminar_plan_num';
        $passTable = $isLecture ? 'lecture_passes' : 'seminar_passes';

        $plans = DB::table('section_plans')
            ->join($planTable, 'section_plans.id_section_plan', '=', $planTable . '.id_section_plan')
            ->where('section_plans.id_course_plan', $coursePlanId)
            ->where('section_plans.is_exam', 0)
            ->orderBy('section_plans.section_num', 'asc')
            ->orderBy($planTable . '.' . $planNumColumn, 'asc')
            ->orderBy($planTable . '.' . $planIdColumn, 'asc')
            ->get(array(
                'section_plans.section_num',
                $planTable . '.' . $planIdColumn . ' as plan_id',
                $planTable . '.' . $planNumColumn . ' as plan_num',
            ));

        $sections = array();
        foreach ($plans as $plan) {
            $planId = $plan->plan_id;
            $planIds = array($planId);
            $filledRows = $this->countPassRows($passTable, $planIdColumn, $planIds, $studentIds);
            $missingRows = max(0, $registeredCount - $filledRows);
            $presentRows = $this->countPassRows($passTable, $planIdColumn, $planIds, $studentIds, true);
            $workRows = $isLecture
                ? null
                : $this->countSeminarWorkRows($planIds, $studentIds);

            if (!isset($sections[$plan->section_num])) {
                $sections[$plan->section_num] = array(
                    'section_num' => $plan->section_num,
                    'items' => array(),
                );
            }

            $sections[$plan->section_num]['items'][] = array(
                'plan_id' => $planId,
                'plan_num' => $plan->plan_num,
                'expected_rows' => $registeredCount,
                'filled_rows' => $filledRows,
                'missing_rows' => $missingRows,
                'present_rows' => $presentRows,
                'work_rows' => $workRows,
                'has_attendance_marks' => $presentRows > 0,
                'has_work_marks' => $workRows === null ? null : $workRows > 0,
            );
        }

        return array_values($sections);
    }

    private function checkerLessonDetails($groupId, $type, $lesson)
    {
        if (!$lesson) {
            return array('available' => false, 'students' => array());
        }

        $students = DB::table('users')
            ->where('group', $groupId)
            ->whereIn('role', array('Студент', 'Староста', 'РЎС‚СѓРґРµРЅС‚', 'РЎС‚Р°СЂРѕСЃС‚Р°'))
            ->whereNull('deleted_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(array('id', 'last_name', 'first_name'));

        $isLecture = $type === 'lecture';
        $table = $isLecture ? 'lecture_passes' : 'seminar_passes';
        $planColumn = $isLecture ? 'id_lecture_plan' : 'id_seminar_plan';
        $passes = DB::table($table)
            ->where($planColumn, $lesson['plan_id'])
            ->whereIn('id_user', $students->pluck('id')->toArray())
            ->get()
            ->keyBy('id_user');

        $rows = array();
        foreach ($students as $student) {
            $pass = $passes->get($student->id);
            $rows[] = array(
                'id' => (int) $student->id,
                'name' => trim($student->last_name . ' ' . $student->first_name),
                'has_record' => $pass !== null,
                'present' => $pass !== null && (int) $pass->presence === 1,
                'work_points' => !$isLecture && $pass !== null ? $pass->work_points : null,
            );
        }

        return array(
            'available' => true,
            'section' => $lesson['section_num'],
            'lesson' => $lesson['plan_num'],
            'students' => $rows,
        );
    }

    private function flattenCheckerSections($sections)
    {
        $items = array();
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $item['section_num'] = $section['section_num'];
                $items[] = $item;
            }
        }

        return $items;
    }

    private function countPassRows($table, $planColumn, $planIds, $studentIds, $onlyPresent = false)
    {
        if (empty($planIds) || empty($studentIds)) {
            return 0;
        }

        $query = DB::table($table)
            ->whereIn($planColumn, $planIds)
            ->whereIn('id_user', $studentIds);

        if ($onlyPresent) {
            $query->where('presence', 1);
        }

        return $query->count();
    }

    private function countSeminarWorkRows($seminarPlanIds, $studentIds)
    {
        if (empty($seminarPlanIds) || empty($studentIds)) {
            return 0;
        }

        return DB::table('seminar_passes')
            ->whereIn('id_seminar_plan', $seminarPlanIds)
            ->whereIn('id_user', $studentIds)
            ->whereNotNull('work_points')
            ->where('work_points', '<>', 0)
            ->count();
    }

    private function notifyScheduleChanges($oldItem, $newItem)
    {
        $changes = $this->scheduleChangeDescriptions($oldItem, $newItem);
        if (empty($changes)) {
            return;
        }

        $studentIds = DB::table('users')
            ->where('group', $oldItem->group_id)
            ->whereIn('role', array('Студент', 'Староста', 'РЎС‚СѓРґРµРЅС‚', 'РЎС‚Р°СЂРѕСЃС‚Р°'))
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        if (empty($studentIds)) {
            return;
        }

        $group = Group::where('group_id', $oldItem->group_id)->first();
        $groupName = $group ? $group->group_name : ('группы ' . $oldItem->group_id);
        $lessonTitle = $newItem->title ?: $oldItem->title;
        $title = 'Изменение в расписании';
        $body = 'В расписании ' . $groupName . ' изменено занятие «' . $lessonTitle . '»: ' . implode('; ', $changes) . '.';

        try {
            NotificationService::sendMany($studentIds, 'schedule_changed', $title, $body, array(
                'group_id' => $oldItem->group_id,
                'schedule_id' => $oldItem->id,
                'lesson_title' => $lessonTitle,
                'changes' => $changes,
                'url' => route('current_control.student_schedule'),
            ));
        } catch (\Exception $exception) {
            Log::warning('Schedule notification send failed: ' . $exception->getMessage());
        }
    }

    private function scheduleChangeDescriptions($oldItem, $newItem)
    {
        $fields = array(
            'time_start' => 'время начала',
            'time_end' => 'время окончания',
            'date_from' => 'дата начала периода',
            'date_to' => 'дата окончания периода',
            'auditorium' => 'аудитория',
            'teacher_name' => 'преподаватель',
            'replacement_teacher' => 'заменяющий преподаватель',
            'status' => 'статус',
            'lesson_date' => 'дата занятия',
        );

        $changes = array();
        foreach ($fields as $field => $label) {
            $oldValue = $this->normalizeScheduleValue(isset($oldItem->{$field}) ? $oldItem->{$field} : null);
            $newValue = $this->normalizeScheduleValue(isset($newItem->{$field}) ? $newItem->{$field} : null);
            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = $label . ': ' . ($oldValue === '' ? 'не указано' : $this->humanScheduleValue($field, $oldValue))
                . ' → ' . ($newValue === '' ? 'не указано' : $this->humanScheduleValue($field, $newValue));
        }

        return $changes;
    }

    private function normalizeScheduleValue($value)
    {
        $value = trim((string) $value);
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 5);
        }

        return $value;
    }

    private function humanScheduleValue($field, $value)
    {
        if ($field === 'status') {
            $statuses = array(
                'active' => 'по расписанию',
                'canceled' => 'отменено',
                'moved' => 'перенесено',
                'replacement' => 'замена преподавателя',
            );

            return isset($statuses[$value]) ? $statuses[$value] : $value;
        }

        return $value;
    }
}
