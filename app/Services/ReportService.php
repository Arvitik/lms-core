<?php

namespace App\Services;

use App\Report;
use App\TeacherHasGroup;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * @param string|null $fromDate format Y-m-d
     * @param string|null $toDate format Y-m-d
     * @return int number of generated teacher reports
     */
    public function generateWeeklyReports($fromDate = null, $toDate = null)
    {
        $hasManualPeriod = !empty($fromDate) && !empty($toDate);
        list($globalFrom, $globalTo) = $this->resolvePeriod($fromDate, $toDate);

        $teacherIds = TeacherHasGroup::distinct()->pluck('user_id')->toArray();
        if (empty($teacherIds)) {
            return 0;
        }

        $teachers = User::whereIn('id', $teacherIds)->get(['id']);
        $generated = 0;

        foreach ($teachers as $teacher) {
            $groupIds = TeacherHasGroup::where('user_id', $teacher->id)->pluck('group')->toArray();
            if (empty($groupIds)) {
                continue;
            }

            // Оставляем только неархивированные группы преподавателя и исключаем группу "Админы"
            $groupIds = DB::table('groups')
                ->whereIn('group_id', $groupIds)
                ->where('archived', 0)
                ->where('group_name', '!=', 'Админы')
                ->pluck('group_id')
                ->toArray();

            if (empty($groupIds)) {
                continue;
            }

            $groupsMap = DB::table('groups')
                ->whereIn('group_id', $groupIds)
                ->pluck('group_name', 'group_id')
                ->toArray();

            $students = User::whereIn('group', $groupIds)
                ->whereNull('deleted_at')
                ->where(function ($query) {
                    $query->where('role', 'Студент')
                          ->orWhere('role', 'Староста');
                })
                ->whereNotIn('id', $teacherIds)
                ->get(['id', 'group', 'first_name', 'last_name']);

            if ($students->isEmpty()) {
                continue;
            }

            $studentIds = $students->pluck('id')->toArray();
            if ($hasManualPeriod) {
                $periodFrom = $globalFrom;
                $periodTo = $globalTo;
            } else {
                list($periodFrom, $periodTo) = $this->resolvePeriodForStudents($studentIds, $globalFrom, $globalTo);
            }

            $results = DB::table('results')
                ->whereIn('id', $studentIds)
                ->whereNotNull('mark_ru')
                ->where('mark_ru', '>=', 0)
                ->whereBetween('result_date', [$periodFrom->toDateTimeString(), $periodTo->toDateTimeString()])
                ->get(['id', 'mark_ru', 'result_date']);

            $allResults = DB::table('results')
                ->whereIn('id', $studentIds)
                ->whereNotNull('mark_ru')
                ->where('mark_ru', '>=', 0)
                ->get(['id', 'mark_ru', 'result_date']);

            $resultsByStudent = $results->groupBy('id');
            $allResultsByStudent = $allResults->groupBy('id');
            $reportData = [];
            $problemCount = 0;
            $inactiveCount = 0;

            foreach ($students as $student) {
                $periodStudentResults = $resultsByStudent->has($student->id) ? $resultsByStudent[$student->id] : collect();
                $allStudentResults = $allResultsByStudent->has($student->id) ? $allResultsByStudent[$student->id] : collect();

                $attempts = $periodStudentResults->count();
                $attemptsTotal = $allStudentResults->count();
                $avgMark = $attemptsTotal > 0 ? round($allStudentResults->avg('mark_ru'), 2) : 0;
                $fails = $attempts > 0 ? $periodStudentResults->where('mark_ru', '<', 3)->count() : 0;
                $lastMark = $attemptsTotal > 0 ? $allStudentResults->sortByDesc('result_date')->first()->mark_ru : null;

                if ($attempts === 0) {
                    $inactiveCount++;
                }

                $dangerReasons = [];
                if ($attemptsTotal > 0 && $avgMark < 3) {
                    $dangerReasons[] = 'Low average mark';
                }
                if ($fails >= 2) {
                    $dangerReasons[] = 'Many failed attempts';
                }

                $danger = !empty($dangerReasons);
                if ($danger) {
                    $problemCount++;
                }

                $reportData[] = [
                    'student' => trim($student->last_name . ' ' . $student->first_name),
                    'group' => isset($groupsMap[$student->group]) ? $groupsMap[$student->group] : (string) $student->group,
                    'avg' => $avgMark,
                    'attempts' => $attempts,
                    'attempts_total' => $attemptsTotal,
                    'fails' => $fails,
                    'last_mark' => $lastMark,
                    'danger' => $danger,
                    'inactive' => $attempts === 0,
                    'danger_reason' => implode('; ', $dangerReasons),
                ];
            }

            usort($reportData, function ($a, $b) {
                if ($a['group'] !== $b['group']) {
                    return strcmp((string) $a['group'], (string) $b['group']);
                }
                if ($a['danger'] !== $b['danger']) {
                    return $a['danger'] ? -1 : 1;
                }
                if ($a['inactive'] !== $b['inactive']) {
                    return $a['inactive'] ? 1 : -1;
                }
                return $a['avg'] <=> $b['avg'];
            });

            Report::create([
                'teacher_id' => $teacher->id,
                'data' => json_encode([
                    'period' => [
                        'from' => $periodFrom->toDateString(),
                        'to' => $periodTo->toDateString(),
                    ],
                    'generated_at' => Carbon::now()->toDateTimeString(),
                    'summary' => [
                        'groups_count' => count($groupIds),
                        'students_total' => count($reportData),
                        'problem_students_total' => $problemCount,
                        'inactive_students_total' => $inactiveCount,
                    ],
                    'students' => $reportData,
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $generated++;
        }

        return $generated;
    }

    /**
     * @param string|null $fromDate format Y-m-d
     * @param string|null $toDate format Y-m-d
     * @return array
     */
    private function resolvePeriod($fromDate = null, $toDate = null)
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

    /**
     * Uses latest week with marks for selected students.
     * Falls back to provided global period.
     *
     * @param array $studentIds
     * @param Carbon $fallbackFrom
     * @param Carbon $fallbackTo
     * @return array
     */
    private function resolvePeriodForStudents(array $studentIds, Carbon $fallbackFrom, Carbon $fallbackTo)
    {
        if (empty($studentIds)) {
            return [$fallbackFrom, $fallbackTo];
        }

        $maxResultDate = DB::table('results')
            ->whereIn('id', $studentIds)
            ->whereNotNull('mark_ru')
            ->where('mark_ru', '>=', 0)
            ->max('result_date');

        if (!$maxResultDate) {
            return [$fallbackFrom, $fallbackTo];
        }

        $anchor = Carbon::parse($maxResultDate);
        return [
            $anchor->copy()->startOfWeek()->startOfDay(),
            $anchor->copy()->endOfWeek()->endOfDay(),
        ];
    }
}
