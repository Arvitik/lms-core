<?php
namespace App\Http\Controllers;
use App\Testing\Result;
use App\Testing\Test;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Session;
class PersonalAccount extends Controller{
    private $test;
    function __construct(Test $test){
        $this->test=$test;
    }

    public static function showTestResults(){   //показывает результаты тестов
        $tests = [];
        $names = [];
        $query = Test::select('id_test', 'test_course', 'test_name', 'test_type')->get();
        foreach ($query as $test){
            if ($test->test_course != 'Рыбина'){                    //проверка, что тест открыт и он не из Рыбинских
                array_push($tests, $test->id_test);                                                              //название тренировочного теста состоит из слова "Тренировочный" и
                array_push($names, $test->test_name);                                                            //самого названия теста
            }
        }
        $amount = count($tests);
        $user = Auth::user();
        $results = Result::whereId($user['id'])->get();
        return view('personal_account/personal_account', compact('results', 'amount', 'tests', 'names'));
    }



    public function showAllTests(Request $request){   //показывает результаты всех тестов всех студентов
        $tests = [];
        $names = [];
        $last_names = [];
        $first_names = [];
        $groups = [];
        $test_names = [];
        $result_dates = [];
        $results = [];
        $marks = [];
        $query = $this->test->select('id_test', 'test_course', 'test_name', 'test_type')->get();
        foreach ($query as $test){
            /*
             * проверка, что тест открыт и он не из Рыбинских
             * название тренировочного теста состоит из слова "Тренировочный" и
             * самого названия теста
             */
            if ($test->test_course != 'Рыбина'){
                array_push($tests, $test->id_test);
                array_push($names, $test->test_name);
            }
        }
        $amount = count($tests);
        $resultsQuery = Result::join('tests', 'tests.id_test', '=', 'results.id_test');
        if (!empty($request->test)) {
            $resultsQuery = $resultsQuery->where('tests.id_test', $request->test);
        }
        $resultsQuery = $resultsQuery
            ->leftJoin('users', 'users.id', '=', 'results.id')
            ->leftJoin('groups', 'groups.group_id', '=', 'users.group');

        if (!empty($request->surname)) {
            $resultsQuery = $resultsQuery->where('users.last_name', 'like', '%' . $request->surname . '%');
        }

        if (!empty($request->group)) {
            $resultsQuery = $resultsQuery->where('groups.group_name', 'like', '%' . $request->group . '%');
        }

        if (!empty($request->mark)) {
            $resultsQuery = $resultsQuery->where('results.mark_eu', 'like', '%' . $request->mark . '%');
        }

        $request_test = $request->test;
        $request_surname = $request->surname;
        $request_group = $request->group;
        $request_mark = $request->mark;
        $group_list = \App\Group::where('archived', 0)
            ->orderBy('group_name')
            ->pluck('group_name');


        $resultsQuery = $resultsQuery
            ->select('results.id', 'test_name', 'result_date', 'result', 'mark_eu',
                'users.last_name', 'users.first_name', 'groups.group_name')
            ->orderBy('result_date', 'desc')
            ->paginate(100);
        foreach ($resultsQuery as $res){
            array_push($last_names, $res->last_name);
            array_push($first_names, $res->first_name);
            array_push($groups, $res->group_name);
            array_push($test_names, $res->test_name);
            array_push($result_dates, $res->result_date);
            array_push($results, $res->result);
            array_push($marks, $res->mark_eu);
        }
        return view('personal_account/teacher_account', compact('results', 'last_names',
            'first_names', 'groups', 'test_names', 'result_dates', 'marks', 'amount', 'tests', 'names',
            'resultsQuery', 'request_test', 'request_surname', 'request_group', 'request_mark', 'group_list'));
    }



    
    public function showAttendanceForm(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'Староста') {
            abort(403);
        }

        // Получаем ВСЕ лекции
        $allLectures = DB::table('lectures')
            ->leftJoin('lecture_group_limits', function($join) use ($user) {
                $join->on('lectures.id_lecture', '=', 'lecture_group_limits.id_lecture')
                    ->where('lecture_group_limits.group_id', '=', $user->group);
            })
            ->orderBy('lectures.date', 'asc')
            ->select(
                'lectures.*', 
                'lecture_group_limits.attendance_limit',
                'lecture_group_limits.id_lecture as limit_lecture_id'
            )
            ->get();

        $students = DB::table('users')
            ->where('group', $user->group)
            ->whereIn('role', ['Студент', 'Староста'])
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();

        $lecturesData = [];

        foreach ($allLectures as $lecture) {
            // Считаем только студентов с presence = 1
            $alreadyMarked = DB::table('lecture_passes')
                ->join('users', 'lecture_passes.id_user', '=', 'users.id')
                ->where('lecture_passes.id_lecture', $lecture->id_lecture)
                ->where('lecture_passes.presence', 1)  // ← ВАЖНО: только presence = 1
                ->where('users.group', $user->group)
                ->pluck('lecture_passes.id_user')
                ->toArray();

            $alreadyMarkedCount = count($alreadyMarked);

            // Определяем лимит
            $groupLimit = $lecture->limit_lecture_id ? $lecture->attendance_limit : null;

            $lecturesData[] = [
                'lecture' => $lecture,
                'alreadyMarked' => $alreadyMarked,
                'alreadyMarkedCount' => $alreadyMarkedCount,
                'groupLimit' => $groupLimit,
            ];
        }

        return view('personal_account.steward_attendance', [
            'lectures' => $lecturesData,
            'students' => $students,
        ]);
    }




    public function submitAttendance(Request $request)
    {
        $this->validate($request, [
            'id_lecture' => 'required|integer',
            'students' => 'array',
        ]);

        $user = auth()->user();
        $lectureId = $request->input('id_lecture');
        $selectedStudentIds = $request->input('students', []);

        // Проверяем, что все выбранные студенты из текущей группы
        $validStudentIds = DB::table('users')
            ->where('group', $user->group)
            ->whereIn('id', $selectedStudentIds)
            ->pluck('id')
            ->toArray();

        if (count($selectedStudentIds) !== count($validStudentIds)) {
            return back()->withErrors("Обнаружены студенты не из вашей группы.");
        }

        // Получаем лимит посещаемости
        $groupLimit = DB::table('lecture_group_limits')
            ->where('id_lecture', $lectureId)
            ->where('group_id', $user->group)
            ->value('attendance_limit');

        if ($groupLimit !== null && count($selectedStudentIds) > $groupLimit) {
            return back()->withErrors("Превышен лимит по группе. Максимум: {$groupLimit} студентов.");
        }

        // === СИНХРОНИЗАЦИЯ СО СТАРОЙ СИСТЕМОЙ ===
        $id_lecture_plan = null;
        $anyLecturePlan = DB::table('lecture_passes')
            ->join('users', 'lecture_passes.id_user', '=', 'users.id')
            ->where('users.group', $user->group)
            ->whereNotNull('lecture_passes.id_lecture_plan')
            ->select('lecture_passes.id_lecture_plan')
            ->first();

        if ($anyLecturePlan) {
            $firstEverId = 158;
            $sampleId = $anyLecturePlan->id_lecture_plan;
            $baseId = $sampleId - (($sampleId - $firstEverId) % 16);
            $id_lecture_plan = $baseId + ($lectureId - 1);
        }
        // === КОНЕЦ СИНХРОНИЗАЦИИ ===

        // === УПРОЩЁННАЯ ЛОГИКА: сбросить всех → отметить выбранных ===
        
        // 1. Сбрасываем ВСЕ отметки этой группы на 0 (НОВАЯ система)
        DB::table('lecture_passes')
            ->join('users', 'lecture_passes.id_user', '=', 'users.id')
            ->where('lecture_passes.id_lecture', $lectureId)
            ->where('users.group', $user->group)
            ->update(['lecture_passes.presence' => 0]);
        
        // 2. Сбрасываем ВСЕ отметки в СТАРОЙ системе
        if ($id_lecture_plan) {
            DB::table('lecture_passes')
                ->join('users', 'lecture_passes.id_user', '=', 'users.id')
                ->where('lecture_passes.id_lecture_plan', $id_lecture_plan)
                ->where('users.group', $user->group)
                ->update(['lecture_passes.presence' => 0]);
        }
        
        // 3. Ставим presence = 1 только для выбранных студентов
        foreach ($selectedStudentIds as $studentId) {
            // НОВАЯ СИСТЕМА
            DB::table('lecture_passes')->updateOrInsert(
                [
                    'id_user' => $studentId,
                    'id_lecture' => $lectureId,
                ],
                [
                    'presence' => 1,
                    'marked_by' => $user->id,
                ]
            );
            
            // СТАРАЯ СИСТЕМА
            if ($id_lecture_plan) {
                DB::table('lecture_passes')->updateOrInsert(
                    [
                        'id_user' => $studentId,
                        'id_lecture_plan' => $id_lecture_plan,
                    ],
                    [
                        'presence' => 1,
                    ]
                );
            }
        }

        // Обновляем лимит в новой системе
        $newCount = count($selectedStudentIds);
        $existingLimit = DB::table('lecture_group_limits')
            ->where('id_lecture', $lectureId)
            ->where('group_id', $user->group)
            ->first();

        if ($existingLimit) {
            if ($newCount > $existingLimit->attendance_limit) {
                DB::table('lecture_group_limits')
                    ->where('id_lecture', $lectureId)
                    ->where('group_id', $user->group)
                    ->update(['attendance_limit' => $newCount]);
            }
        } else {
            DB::table('lecture_group_limits')->insert([
                'id_lecture' => $lectureId,
                'group_id' => $user->group,
                'attendance_limit' => $newCount
            ]);
        }

        return redirect()->back()->with('success', 'Отметки для лекции успешно обновлены.');
    }



}