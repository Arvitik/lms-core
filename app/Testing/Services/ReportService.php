<?php

namespace App\Services;

use App\User;
use App\TeacherHasGroup;
use App\StatementsProgress;
use App\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generateWeeklyReports()
    {
        $teachers = User::whereIn('role', ['Преподаватель', 'Админ'])->get();

        foreach ($teachers as $teacher) {
            $reportData = [];

            $groupIds = TeacherHasGroup::where('user_id', $teacher->id)->pluck('group')->toArray();

            if (empty($groupIds)) {
                continue;
            }

            // Оставляем только неархивированные учебные группы (без "Админы")
            $groupIds = DB::table('groups')
                ->whereIn('group_id', $groupIds)
                ->where('archived', 0)
                ->where('group_name', '!=', 'Админы')
                ->pluck('group_id')
                ->toArray();

            if (empty($groupIds)) {
                continue;
            }

            $students = User::whereIn('group', $groupIds)
                ->whereNull('deleted_at')
                ->where(function ($query) {
                    $query->where('role', 'Студент')
                          ->orWhere('role', 'Староста');
                })
                ->get();

            foreach ($students as $student) {
                $grades = StatementsProgress::where('userID', $student->id)
                    ->where('group', $student->group)
                    ->first();

                if (!$grades) {
                    continue;
                }

                $scoreFields = [
                    'control1', 'control2',
                    'test1', 'test1quiz', 'section1',
                    'test2', 'test2quiz', 'section2',
                    'control3', 'test3', 'test3quiz', 'section3',
                    'lastquiz', 'section4',
                ];

                $scores = [];

                foreach ($scoreFields as $field) {
                    $value = $grades->$field;
                    if (is_numeric($value)) {
                        $scores[] = floatval($value);
                    }
                }

                $total = array_sum($scores);
                $count = count($scores);
                $average = $count > 0 ? $total / $count : 0;
                $isProblem = ($total == 0);

                $reportData[] = [
                    'student' => $student->last_name . ' ' . $student->first_name,
                    'group' => $student->group, // ✅ добавлена группа
                    'avg' => round($average, 2),
                    'danger' => $isProblem,
                ];
            }

            if (!empty($reportData)) {
                Report::create([
                    'teacher_id' => $teacher->id,
                    'data' => json_encode($reportData),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }
}
