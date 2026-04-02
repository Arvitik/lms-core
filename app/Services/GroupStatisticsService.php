<?php

namespace App\Services;

use App\GroupReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GroupStatisticsService
{
    /**
     * @param string|null $fromDate format Y-m-d
     * @param string|null $toDate format Y-m-d
     * @return \App\GroupReport|false
     */
    public function generateReport($fromDate = null, $toDate = null)
    {
        list($currentFrom, $currentTo) = $this->resolveCurrentPeriod($fromDate, $toDate);
        list($previousFrom, $previousTo) = $this->resolvePreviousPeriod($currentFrom, $currentTo);

        $groups = DB::table('groups')
            ->where('archived', 0)
            ->where('group_name', '!=', 'Админы')
            ->orderBy('group_name')
            ->get(['group_id', 'group_name']);

        if ($groups->isEmpty()) {
            return false;
        }

        $comparison = [];
        $summary = [
            'groups_count' => 0,
            'students_total' => 0,
            'problem_students_total' => 0,
            'inactive_students_total' => 0,
            'avg_mark_current' => 0,
            'avg_mark_previous' => 0,
        ];

        $currentAvgAccumulator = [];
        $previousAvgAccumulator = [];

        $teacherIds = DB::table('teacher_has_group')->distinct()->pluck('user_id')->toArray();

        foreach ($groups as $group) {
            $students = DB::table('users')
                ->where('group', $group->group_id)
                ->whereNull('deleted_at')
                ->where(function ($query) {
                    $query->where('role', 'Студент')
                          ->orWhere('role', 'Староста');
                })
                ->whereNotIn('id', $teacherIds)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']);

            $groupStats = $this->buildGroupStats(
                $group->group_name,
                $students,
                $currentFrom,
                $currentTo,
                $previousFrom,
                $previousTo
            );

            $comparison[] = $groupStats;
            $summary['groups_count']++;
            $summary['students_total'] += $groupStats['students_cnt'];
            $summary['problem_students_total'] += $groupStats['problem_students_count'];
            $summary['inactive_students_total'] += $groupStats['inactive_students_count'];
            $currentAvgAccumulator[] = $groupStats['avg_current'];
            $previousAvgAccumulator[] = $groupStats['avg_previous'];
        }

        $summary['avg_mark_current'] = round($this->safeAvg($currentAvgAccumulator), 2);
        $summary['avg_mark_previous'] = round($this->safeAvg($previousAvgAccumulator), 2);
        $summary['avg_mark_delta'] = round($summary['avg_mark_current'] - $summary['avg_mark_previous'], 2);

        return GroupReport::create([
            'report_data' => [
                'period' => [
                    'from' => $currentFrom->toDateString(),
                    'to' => $currentTo->toDateString(),
                ],
                'previous_period' => [
                    'from' => $previousFrom->toDateString(),
                    'to' => $previousTo->toDateString(),
                ],
                'comparison' => $comparison,
                'summary' => $summary,
            ],
            'created_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    private function buildGroupStats($groupName, $students, Carbon $currentFrom, Carbon $currentTo, Carbon $previousFrom, Carbon $previousTo)
    {
        $studentIds = $students->pluck('id')->toArray();

        if (empty($studentIds)) {
            return [
                'group_name' => $groupName,
                'students_cnt' => 0,
                'attempts_current' => 0,
                'attempts_previous' => 0,
                'avg_current' => 0,
                'avg_previous' => 0,
                'pass_rate_current' => 0,
                'pass_rate_previous' => 0,
                'fail_rate_current' => 0,
                'fail_rate_previous' => 0,
                'problem_share_percent' => 0,
                'problem_students_count' => 0,
                'inactive_students_count' => 0,
                'trend' => 'Нет данных',
                'recommendation' => 'В группе нет студентов.',
                'problematic_students' => [],
            ];
        }

        $currentResults = DB::table('results')
            ->whereIn('id', $studentIds)
            ->whereNotNull('mark_ru')
            ->where('mark_ru', '>=', 0)
            ->whereBetween('result_date', [$currentFrom->toDateTimeString(), $currentTo->toDateTimeString()])
            ->get(['id', 'mark_ru', 'result_date']);

        $previousResults = DB::table('results')
            ->whereIn('id', $studentIds)
            ->whereNotNull('mark_ru')
            ->where('mark_ru', '>=', 0)
            ->whereBetween('result_date', [$previousFrom->toDateTimeString(), $previousTo->toDateTimeString()])
            ->get(['id', 'mark_ru', 'result_date']);

        $attemptsCurrent = $currentResults->count();
        $attemptsPrevious = $previousResults->count();

        $avgCurrent = $attemptsCurrent > 0 ? round($currentResults->avg('mark_ru'), 2) : 0;
        $avgPrevious = $attemptsPrevious > 0 ? round($previousResults->avg('mark_ru'), 2) : 0;

        $passRateCurrent = $attemptsCurrent > 0
            ? round($currentResults->where('mark_ru', '>=', 3)->count() / $attemptsCurrent * 100, 2)
            : 0;
        $passRatePrevious = $attemptsPrevious > 0
            ? round($previousResults->where('mark_ru', '>=', 3)->count() / $attemptsPrevious * 100, 2)
            : 0;

        $failRateCurrent = $attemptsCurrent > 0 ? round(100 - $passRateCurrent, 2) : 0;
        $failRatePrevious = $attemptsPrevious > 0 ? round(100 - $passRatePrevious, 2) : 0;

        $currentByStudent = $currentResults->groupBy('id');
        $problematicStudents = [];
        $inactiveStudents = 0;

        foreach ($students as $student) {
            $studentResults = $currentByStudent->has($student->id) ? $currentByStudent[$student->id] : collect();
            $attempts = $studentResults->count();
            $avg = $attempts > 0 ? round($studentResults->avg('mark_ru'), 2) : 0;
            $fails = $attempts > 0 ? $studentResults->where('mark_ru', '<', 3)->count() : 0;
            $lastMark = $attempts > 0 ? $studentResults->sortByDesc('result_date')->first()->mark_ru : null;

            $reasons = [];
            if ($attempts === 0) {
                $inactiveStudents++;
                continue;
            }
            if ($attempts > 0 && $avg < 3) {
                $reasons[] = 'Низкий средний балл';
            }
            if ($fails >= 2) {
                $reasons[] = 'Много неуспешных попыток';
            }

            if (!empty($reasons)) {
                $problematicStudents[] = [
                    'student' => trim($student->last_name . ' ' . $student->first_name),
                    'attempts' => $attempts,
                    'avg_mark' => $avg,
                    'fails' => $fails,
                    'last_mark' => $lastMark,
                    'reason' => implode('; ', $reasons),
                ];
            }
        }

        // Avoid oversized JSON in group_reports.report_data (TEXT in legacy DBs).
        // Keep full counters, but store only top problematic students in payload.
        $problemCount = count($problematicStudents);
        usort($problematicStudents, function ($a, $b) {
            if ($a['fails'] === $b['fails']) {
                return $a['avg_mark'] <=> $b['avg_mark'];
            }
            return $b['fails'] <=> $a['fails'];
        });
        $problematicStudents = array_slice($problematicStudents, 0, 20);
        $problemShare = count($students) > 0 ? round($problemCount / count($students) * 100, 2) : 0;
        $trend = $this->detectTrend($avgCurrent, $avgPrevious);
        $recommendation = $this->buildRecommendation($failRateCurrent, $problemShare, $inactiveStudents);

        return [
            'group_name' => $groupName,
            'students_cnt' => count($students),
            'attempts_current' => $attemptsCurrent,
            'attempts_previous' => $attemptsPrevious,
            'avg_current' => $avgCurrent,
            'avg_previous' => $avgPrevious,
            'pass_rate_current' => $passRateCurrent,
            'pass_rate_previous' => $passRatePrevious,
            'fail_rate_current' => $failRateCurrent,
            'fail_rate_previous' => $failRatePrevious,
            'problem_share_percent' => $problemShare,
            'problem_students_count' => $problemCount,
            'inactive_students_count' => $inactiveStudents,
            'trend' => $trend,
            'recommendation' => $recommendation,
            'problematic_students' => $problematicStudents,
        ];
    }

    private function resolveCurrentPeriod($fromDate, $toDate)
    {
        if (!empty($fromDate) && !empty($toDate)) {
            $from = Carbon::parse($fromDate)->startOfDay();
            $to = Carbon::parse($toDate)->endOfDay();
            if ($from->gt($to)) {
                $swap = $from;
                $from = $to->copy()->startOfDay();
                $to = $swap->copy()->endOfDay();
            }
            return [$from, $to];
        }

        $maxResultDate = DB::table('results')
            ->whereNotNull('mark_ru')
            ->where('mark_ru', '>=', 0)
            ->max('result_date');

        if ($maxResultDate) {
            $anchor = Carbon::parse($maxResultDate);
            return [
                $anchor->copy()->startOfWeek()->startOfDay(),
                $anchor->copy()->endOfWeek()->endOfDay(),
            ];
        }

        $lastWeek = Carbon::now()->subWeek();
        return [
            $lastWeek->copy()->startOfWeek()->startOfDay(),
            $lastWeek->copy()->endOfWeek()->endOfDay(),
        ];
    }

    private function resolvePreviousPeriod(Carbon $currentFrom, Carbon $currentTo)
    {
        $days = $currentFrom->copy()->startOfDay()->diffInDays($currentTo->copy()->startOfDay()) + 1;
        $previousTo = $currentFrom->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        return [$previousFrom, $previousTo];
    }

    private function detectTrend($avgCurrent, $avgPrevious)
    {
        $delta = $avgCurrent - $avgPrevious;
        if ($delta > 0.2) {
            return 'Улучшение';
        }
        if ($delta < -0.2) {
            return 'Ухудшение';
        }
        return 'Стабильно';
    }

    private function buildRecommendation($failRateCurrent, $problemShare, $inactiveStudents)
    {
        if ($inactiveStudents > 0) {
            return 'Провести разбор с неактивными студентами и проверить допуск к тестам.';
        }
        if ($failRateCurrent >= 40 || $problemShare >= 35) {
            return 'Назначить консультации и повторный контроль по сложным темам.';
        }
        if ($failRateCurrent >= 20 || $problemShare >= 20) {
            return 'Точечно проработать проблемных студентов и дать дополнительные задания.';
        }
        return 'Ситуация в норме, поддерживать текущий темп работы.';
    }

    private function safeAvg(array $values)
    {
        if (empty($values)) {
            return 0;
        }
        return array_sum($values) / count($values);
    }
}
