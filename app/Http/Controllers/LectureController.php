<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; 

class LectureController extends Controller
{
    // === Старый интерфейс (остался для обратной совместимости) ===
    public function showLimitForm()
    {
        $lectures = DB::table('lectures')->orderBy('date', 'asc')->get();
        $groups = DB::table('groups')
            ->where('archived', 0)
            #->where('group_name', 'LIKE', '%Б22-524%')
            ->orderBy('group_name')
            ->get();

        $limits = array();

        return view('lectures.limit_form', array(
            'lectures' => $lectures,
            'groups'   => $groups,
            'limits'   => $limits
        ));
    }
    
    public function saveLimit(Request $request)
    {
        $this->validate($request, array(
            'id_lecture' => 'required|integer',
            'group_id' => 'required|integer',
            'attendance_limit' => 'required|integer|min:1',
        ));

        DB::table('lecture_group_limits')->updateOrInsert(
            array(
                'id_lecture' => $request->id_lecture,
                'group_id'   => $request->group_id,
            ),
            array(
                'attendance_limit' => intval($request->attendance_limit),
            )
        );

        return back()->with('success', 'Лимит установлен.');
    }

    
    // === Новый интерфейс (таблица "Лекция × Группа") ===
    public function showLimitsMatrix()
    {
        $lectures = DB::table('lectures')->orderBy('date', 'asc')->get();

        $groups = DB::table('groups')
            ->where('archived', 0)
            ->where('group_name', '!=', 'Админы')
            ->orderBy('group_name')
            ->get();

        // Загружаем лимиты
        $limits = array();
        $existing = DB::table('lecture_group_limits')
            ->select('id_lecture', 'group_id', 'attendance_limit')
            ->get();

        foreach ($existing as $row) {
            $limits[$row->id_lecture][$row->group_id] = (int)$row->attendance_limit;
        }

        // Получаем текущие отметки
        $currentAttendance = array();
        $groupAttendanceStats = array();

        if ($groups && count($groups) > 0 && $lectures && count($lectures) > 0) {
            $groupIds = array();
            foreach ($groups as $group) {
                $groupIds[] = $group->group_id;
            }

            $lectureIds = array();
            foreach ($lectures as $lecture) {
                $lectureId = isset($lecture->id_lecture) ? $lecture->id_lecture : $lecture->id;
                $lectureIds[] = $lectureId;
            }

            // Получаем все отметки
            $marks = DB::table('lecture_passes')
                ->join('users', 'lecture_passes.id_user', '=', 'users.id')
                ->whereIn('lecture_passes.id_lecture', $lectureIds)
                ->whereIn('users.group', $groupIds)
                ->where('lecture_passes.presence', 1)
                ->select('lecture_passes.id_lecture', 'users.group', DB::raw('COUNT(*) as count'))
                ->groupBy('lecture_passes.id_lecture', 'users.group')
                ->get();

            foreach ($marks as $mark) {
                $currentAttendance[$mark->id_lecture][$mark->group] = (int)$mark->count;
            }

            // === Считаем статистику для каждой группы ===
            foreach ($groups as $group) {
                $groupId = $group->group_id;
        
                $lecturePercentages = array(); // Проценты посещения для каждой лекции
                $lecturesWithLimit = 0; // Количество лекций с лимитом
        
                foreach ($lectures as $lecture) {
                    $lectureId = isset($lecture->id_lecture) ? $lecture->id_lecture : $lecture->id;
            
                    $limit = isset($limits[$lectureId][$groupId]) ? $limits[$lectureId][$groupId] : null;
            
                    // Учитываем только если есть лимит (>0)
                    if ($limit !== null && $limit > 0) {
                        $lecturesWithLimit++;
                        $attendance = isset($currentAttendance[$lectureId][$groupId])
                            ? $currentAttendance[$lectureId][$groupId]
                            : 0;
                        $percentage = min(100, ($attendance / $limit) * 100);
                    
                        $lecturePercentages[] = $percentage;
                    }
                }
        
                // Рассчитываем средний процент
                if ($lecturesWithLimit > 0) {
                    $averagePercentage = array_sum($lecturePercentages) / count($lecturePercentages);
            
                    $groupAttendanceStats[$groupId] = array(
                        'lectures_with_limit' => $lecturesWithLimit,
                        'average_percentage' => round($averagePercentage, 1),
                        'display_text' => round($averagePercentage, 1) . '%'
                    );
                } else {
                    $groupAttendanceStats[$groupId] = array(
                        'lectures_with_limit' => 0,
                        'average_percentage' => 0,
                        'display_text' => 'нет лимитов'
                    );
                }
            }
        }

        return view('lectures.limit_form', array(
            'lectures' => $lectures,
            'groups'   => $groups,
            'limits'   => $limits,
            'currentAttendance' => $currentAttendance,
            'groupAttendanceStats' => $groupAttendanceStats
        ));
    }


    public function saveLimitsMatrix(Request $request)
    {
        $data = $request->input('limits', array());

        // Логируем полученные данные для отладки
        \Log::info('Saving limits data:', $data);

        foreach ($data as $lectureId => $groupData) {
            foreach ($groupData as $groupId => $limit) {
                // Если поле пустое - ПРОПУСКАЕМ, но НЕ УДАЛЯЕМ запись
                if ($limit === null || $limit === '') {
                    continue;
                }

                // Только добавляем/обновляем, никогда не удаляем
                DB::table('lecture_group_limits')->updateOrInsert(
                    [
                        'id_lecture' => (int)$lectureId,
                        'group_id'   => (int)$groupId,
                    ],
                    [
                        'attendance_limit' => (int)$limit,
                    ]
                );
                
                \Log::info("Saved limit: lecture $lectureId, group $groupId, limit $limit");
            }
        }

        return redirect()->route('lectures.showLimitsMatrix')->with('success', 'Лимиты успешно сохранены.');
    }
}
