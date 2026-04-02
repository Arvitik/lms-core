<?php

namespace App\Services;

use App\GroupReport;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GroupStatisticsService
{
    public function generateReport()
    {
        // 1) Жёстко задаём так называемую «текущую» дату в 2024 году,
        //    чтобы не упираться в 2025, когда групп уже нет.
        $now = Carbon::create(2024, 10, 2); // 2 июня 2024

        // Учебные годы:
        $currentYear  = 2024;
        $previousYear = 2023;

        // -------------------------------------------------------------------
        // 2) Определяем границы «прошлой недели» относительно $now,
        //    а не текущей реальной даты.
        // -------------------------------------------------------------------
        // Прошлая неделя (понедельник–воскресенье), считая от $now:
        $prevWeekStart  = $now->copy()->subWeek()->startOfWeek(); // понедельник прошлой недели
        $prevWeekEnd    = $now->copy()->subWeek()->endOfWeek();   // воскресенье прошлой недели

        // Неделя до прошлой (понедельник–воскресенье за две недели назад):
        $weekBeforeStart = $now->copy()->subWeeks(2)->startOfWeek();
        $weekBeforeEnd   = $now->copy()->subWeeks(2)->endOfWeek();

        // -------------------------------------------------------------------
        // 3) Групповые префиксы (для 2024 → "Б24", для 2023 → "Б23")
        // -------------------------------------------------------------------
        $yearPrefixCurrent  = 'Б' . substr($currentYear, -2);  // "Б24"
        $yearPrefixPrevious = 'Б' . substr($previousYear, -2); // "Б23"

        // -------------------------------------------------------------------
        // 4) Загружаем группы за эти годы (не заархивированные)
        // -------------------------------------------------------------------
        $groupsCurrent = DB::table('groups')
            ->where('archived', 0)
            ->where('group_name', 'LIKE', $yearPrefixCurrent . '%')
            ->get();

        $groupsPrevious = DB::table('groups')
            ->where('archived', 0)
            ->where('group_name', 'LIKE', $yearPrefixPrevious . '%')
            ->get();

        if ($groupsCurrent->isEmpty() && $groupsPrevious->isEmpty()) {
            return false;
        }

        $allGroups = $groupsCurrent->merge($groupsPrevious);
        $comparison = [];

        foreach ($allGroups as $group) {
            $groupName = $group->group_name;

            // -----------------------------------------------------------------------
            // 5) Список ID студентов (роль "Студент") именно этой группы
            // -----------------------------------------------------------------------
            $studentIds = DB::table('users')
                ->where('group', $group->group_id)
                ->where('role', 'Студент')
                ->pluck('id')
                ->toArray();

            if (empty($studentIds)) {
                // Если студентов нет — заполним нулями
                $comparison[] = [
                    'group_name'      => $groupName,
                    'students_cnt'    => 0,
                    'avg_prev_week'   => 0,
                    'avg_week_before' => 0,
                    'fail_rate_prev'  => '0%',
                    'fail_rate_before'=> '0%',
                    'trend'           => 'нет данных',
                    'recommendation'  => 'В группе нет студентов',
                ];
                continue;
            }

            // -----------------------------------------------------------------------
            // 6) Оценки за «прошлую неделю» (prevWeekStart … prevWeekEnd)
            // -----------------------------------------------------------------------
            $resultsPrevWeek = DB::table('results')
                ->whereIn('id', $studentIds)
                ->whereBetween('result_date', [
                    $prevWeekStart->toDateString() . ' 00:00:00',
                    $prevWeekEnd->toDateString()   . ' 23:59:59'
                ])
                ->get();

            $countPrev     = $resultsPrevWeek->count();
            $avgPrevWeek   = $countPrev > 0
                ? round($resultsPrevWeek->avg('mark_ru'), 2)
                : 0;

            $failsPrev     = $countPrev > 0
                ? $resultsPrevWeek->where('mark_ru', '<', 3)->count()
                : 0;
            $failRatePrev  = $countPrev > 0
                ? round($failsPrev / $countPrev * 100, 2) . '%'
                : '0%';

            // -----------------------------------------------------------------------
            // 7) Оценки за «неделю до прошлой» (weekBeforeStart … weekBeforeEnd)
            // -----------------------------------------------------------------------
            $resultsWeekBefore = DB::table('results')
                ->whereIn('id', $studentIds)
                ->whereBetween('result_date', [
                    $weekBeforeStart->toDateString() . ' 00:00:00',
                    $weekBeforeEnd->toDateString()   . ' 23:59:59'
                ])
                ->get();

            $countBefore    = $resultsWeekBefore->count();
            $avgWeekBefore  = $countBefore > 0
                ? round($resultsWeekBefore->avg('mark_ru'), 2)
                : 0;

            $failsBefore    = $countBefore > 0
                ? $resultsWeekBefore->where('mark_ru', '<', 3)->count()
                : 0;
            $failRateBefore = $countBefore > 0
                ? round($failsBefore / $countBefore * 100, 2) . '%'
                : '0%';

            // -----------------------------------------------------------------------
            // 8) Тренд + рекомендация (сравниваем avgPrevWeek vs avgWeekBefore)
            // -----------------------------------------------------------------------
            $trend = 'Стабильность';
            $rec   = 'Показатели стабильны. Обратите внимание на отдельных студентов.';
            if (
                $avgPrevWeek > $avgWeekBefore
                && floatval(trim($failRatePrev, '%')) < floatval(trim($failRateBefore, '%'))
            ) {
                $trend = 'Улучшение';
                $rec   = 'Успеваемость улучшилась. Продолжайте в том же духе.';
            }
            elseif (
                $avgPrevWeek < $avgWeekBefore
                && floatval(trim($failRatePrev, '%')) > floatval(trim($failRateBefore, '%'))
            ) {
                $trend = 'Ухудшение';
                $rec   = 'Успеваемость ухудшилась. Проведите дополнительные занятия.';
            }

            $comparison[] = [
                'group_name'      => $groupName,
                'students_cnt'    => count($studentIds),
                'avg_prev_week'   => $avgPrevWeek,
                'avg_week_before' => $avgWeekBefore,
                'fail_rate_prev'  => $failRatePrev,
                'fail_rate_before'=> $failRateBefore,
                'trend'           => $trend,
                'recommendation'  => $rec,
            ];
        }

        // -------------------------------------------------------------------
        // 9) Сохраняем отчёт с явной датой created_at
        // -------------------------------------------------------------------
        GroupReport::create([
            'report_data' => [
                'period'     => [
                    'from' => $prevWeekStart->format('Y-m-d'),
                    'to'   => $prevWeekEnd->format('Y-m-d'),
                ],
                'years'      => [
                    'current_year'  => $currentYear,
                    'previous_year' => $previousYear,
                ],
                'comparison' => $comparison,
            ],
            'created_at'  => Carbon::now()->toDateTimeString(), // текущая метка 2 июня 2024
        ]);

        return true;
    }
}
