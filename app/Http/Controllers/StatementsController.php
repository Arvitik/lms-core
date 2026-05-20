<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Statements\DAO\ControlWorkPlanDAO;
use App\Statements\DAO\LecturePlanDAO;
use App\Statements\DAO\SectionPlanDAO;
use App\Statements\DAO\SeminarPlanDAO;
use App\Statements\LectureStatement;
use App\Statements\ResultStatement;
use App\Statements\SeminarStatement;
use App\Testing\Test;
use App\TeacherHasGroup;
use App\Group;
use App\User;
use Auth;
use Illuminate\Http\Request;
use App\Question;
use App\Codificator;
use App\Statements\DAO\CoursePlanDAO;
use stdClass;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class StatementsController extends Controller{

    private $course_plan_DAO;
    private $section_plan_DAO;
    private $lecture_plan_DAO;
    private $seminar_plan_DAO;
    private $control_work_plan_DAO;
    private $lecture_statement;
    private $seminar_statement;
    private $result_statement;

    public function __construct(CoursePlanDAO $course_plan_DAO, SectionPlanDAO $section_plan_DAO, LecturePlanDAO $lecture_plan_DAO
        , SeminarPlanDAO $seminar_plan_DAO, ControlWorkPlanDAO $control_work_plan_DAO, LectureStatement $lecture_statement
        , SeminarStatement $seminar_statement, ResultStatement $result_statement)
    {
        $this->course_plan_DAO = $course_plan_DAO;
        $this->section_plan_DAO = $section_plan_DAO;
        $this->lecture_plan_DAO = $lecture_plan_DAO;
        $this->seminar_plan_DAO = $seminar_plan_DAO;
        $this->control_work_plan_DAO = $control_work_plan_DAO;
        $this->lecture_statement = $lecture_statement;
        $this->seminar_statement = $seminar_statement;
        $this->result_statement = $result_statement;
    }

    // возвращает страницу с учебными планами
    public function showCoursePlans() {
        $read_only = true;
        $course_plans = $this->course_plan_DAO->allCoursePlan()
            ->map( function ($course_plan) {
                $exist_statements = $this->course_plan_DAO->existStatements($course_plan->id_course_plan);
                return ['course_plan' => $course_plan,
                    'exist_statements' => $exist_statements];
            });
        return view('personal_account.statements.course_plans.course_plans', compact('course_plans', 'read_only'));
    }

    // возвращает страницу создание нового учебного плана
    public function createCoursePlans() {
        $groups = Group::where(['archived' => 0, 'id_course_plan' => null, 'academic' => 1])->get(['group_name', 'group_id']);
        return view('personal_account.statements.course_plans.create_course_plans', compact('groups', $groups));
    }

    //сохранение учебного плана
    public function storeCoursePlan(Request $request) {
        $validator = $this->course_plan_DAO->getStoreValidate($request);
        if ($validator->passes()) {
            $id_course_plan = $this->course_plan_DAO->storeCoursePlan($request);
            return redirect('course_plan/'.$id_course_plan);
        } else {
            return redirect()->to($this->getRedirectUrl())
                ->withInput($request->input())->withErrors($validator->errors());
        }
    }

    //возвращает конкретный учебный план для просмотра и редактирования
    public function getCoursePlan($id) {
        $course_plan = $this->course_plan_DAO->getCoursePlan($id);
        $read_only = true;
        $tests_control_work = Test::whereTest_type('Контрольный')
            ->where('archived', '<>', '1')
            ->orderByDesc('id_test')
            ->select()
            ->get();
        $all_groups = Group::where(['archived' => 0, 'id_course_plan' => null, 'academic' => 1])->orWhere('id_course_plan', $id)->get(['group_name', 'group_id']);
        $exist_statements = $this->course_plan_DAO->existStatements($id);
        $max_results = $course_plan->getMaxes();
        $max_control = $max_results['max_control'];
        $max_ball_gen = $max_results['max_ball_gen'];
        $max_seminar_pass_ball_gen = $max_results['max_seminar_pass_ball_gen'];
        $max_lecture_ball_gen = $max_results['max_lecture_ball_gen'];
        $max_exam_gen = $max_results['max_exam_gen'];
        return view('personal_account.statements.course_plans.course_plan', compact('course_plan', 'read_only', 'tests_control_work', 'exist_statements',
            'all_groups', 'max_ball_gen', 'max_seminar_pass_ball_gen', 'max_lecture_ball_gen', 'max_control', 'max_exam_gen'));
    }

    //Обновление основной информации об учебном плане
    public function updateCoursePlan(Request $request)
    {
        $validator = $this->course_plan_DAO->getUpdateValidate($request);
        if ($validator->passes()) {
            $this->course_plan_DAO->updateCoursePlan($request);
            return response()->json(['idCoursePlan' => $request->id_course_plan,'courseName' => $request->course_plan_name]);
        } else {
            return response()->json(['error'=>$validator->errors()->all(), 'idCoursePlan' => $request->id_course_plan]);
        }
    }

    //Удаление учебного плана
    public function deleteCoursePlan(Request $request) {
        $this->course_plan_DAO->deleteCoursePlan($request->id_course_plan);
        return redirect('course_plans');
    }

    //Проверка баллов за конт. меропр. всего учебного плана
    public function checkPointsCoursePlan(Request $request) {
        if ($request->ajax()) {
            $validator = $this->course_plan_DAO->checkPointsCoursePlan($request->input('id_course_plan'));
            if ($validator->passes()) {
                return 0;
            } else {
                return response()->json(['error'=>$validator->errors()->all()]);
            }
        }
    }

    //возвращает представление для добавления раздела учебного плана
    public function getAddSection(Request $request) {
        $section_num = $request->input('section_num');
        $id_course_plan = $request->input('id_course_plan');
        $id_section_plan_js = $request->input('id_section_plan_js');
        $section_plan_max_ball = $request->input('section_plan_max_ball');
        $section_plan_max_lecture_ball = $request->input('section_plan_max_lecture_ball');
        $section_plan_max_seminar_pass_ball = $request->input('section_plan_max_seminar_pass_ball');
        return view('personal_account.statements.course_plans.sections.add_section', compact('section_num', 'id_course_plan', 'id_section_plan_js','section_plan_max_ball', 'section_plan_max_lecture_ball','section_plan_max_seminar_pass_ball'));
    }

    //Сохранение раздела учебного плана
    public function storeSection(Request $request) {
        $validator = $this->section_plan_DAO->getValidateStoreSectionPlan($request);
        //Для вставки html через js, используя число сгенерированное js
        $id_section_plan_js = $request->input('id_section_plan_js');
        $section_plan_max_ball = $request->input('section_plan_max_ball');
        $section_plan_max_lecture_ball = $request->input('section_plan_max_lecture_ball');
        $section_plan_max_seminar_pass_ball = $request->input('section_plan_max_seminar_pass_ball');
        if ($validator->passes()) {
            $id_section_plan = $this->section_plan_DAO->storeSectionPlan($request);
            $section_plan = $this->section_plan_DAO->getSectionPlan($id_section_plan);
            $read_only = true;
            $returnHtmlString = view('personal_account.statements.course_plans.sections.view_or_update_section', compact('section_plan',
                'section_plan_max_ball','section_plan_max_seminar_pass_ball','section_plan_max_lecture_ball','read_only'))
                ->render();
            return response()->json(['view'=>$returnHtmlString, 'idSectionPlanJs' => $id_section_plan_js]);
        } else {
            return response()->json(['error'=>$validator->errors()->all(), 'idSectionPlanJs' => $id_section_plan_js]);
        }

    }

    //Обновление  информации о разделе учебного плана
    public function updateSection(Request $request) {
        $validator = $this->section_plan_DAO->getValidateUpdateSectionPlan($request);
        //Для вставки html через js
        $id_section_plan = $request->input('id_section_plan');

        if ($validator->passes()) {
            $this->section_plan_DAO->updateSectionPlan($request);

            return response()->json(['idSectionPlan' => $id_section_plan ,
                'sectionNum' => $request->input('section_num')]);
        } else {
            return response()->json(['error'=>$validator->errors()->all(), 'idSectionPlan' => $id_section_plan]);
        }
    }

    //Удаление раздела
    public function deleteSection(Request $request) {
        $this->section_plan_DAO->deleteSectionPlan($request->input('id_section_plan'));
        return $request->input('id_section_plan');
    }

    // Возвращает представление для добавления семинара или лекции или КМ
    public function getAddLecOrSemOrCW(Request $request) {
        $type_card = $request->input('type_card');
        $view_path = $this->getPathAddViewLecSemCW($type_card);
        $section_item_num = $request->input('section_item_num');
        $tests_control_work = new stdClass();
        $readOnly = false;
        $id_new_section_item_js = $request->input('id_new_section_item_js');
        if($type_card == 'control_work') {
            $tests_control_work = Test::whereTest_type('Контрольный')
                ->where('archived', '<>', '1')
                ->orderByDesc('id_test')
                ->select()
                ->get();
        }
        $returnHtmlString = view('personal_account.statements.course_plans.sections.'.$view_path,
            ['id_new_section_item_js' => $id_new_section_item_js,
                'section_item_num' => $section_item_num, 'tests_control_work' => $tests_control_work, 'readOnly' => $readOnly
            ])->render();

        return response()->json(['view' => $returnHtmlString]);
    }

    //Сохранение семинара или лекции в разделе учебного плана
    public function storeLecOrSemOrCW(Request $request) {
        $type_card = $request->input('type_card');
        $view_path = $this->getViewUpdatePathLecSemCW($type_card);
        $item_section_DAO = $this->getItemSectionDAO($type_card);
        $validator = $item_section_DAO->getStoreValidate($request);
        $id_item_section_js = $request->input('id_item_section_js');
        if ($validator->passes()) {
            $id_item_section_plan = $item_section_DAO->store($request);
            $item_section_plan = $item_section_DAO->get($id_item_section_plan);
            $read_only = true;
            $tests_control_work = new stdClass();
            if($type_card == 'control_work') {
                $tests_control_work = Test::whereTest_type('Контрольный')
                    ->where('archived', '<>', '1')
                    ->orderByDesc('id_test')
                    ->select()
                    ->get();
            }
            $return_html_string = view('personal_account.statements.course_plans.sections.'.$view_path,
                compact('item_section_plan', 'read_only', 'tests_control_work'))
                ->render();
            return response()->json(['view'=>$return_html_string]);
        } else {
            return response()->json(['error'=>$validator->errors()->all()]);
        }

    }

    //Обновление лекции, семинара, контрольн. меропри в разделе учебного плана
    public function updateLecOrSemOrCW(Request $request) {
        $type_item_section = $request->input('type_item_section');
        $item_section_DAO = $this->getItemSectionDAO($type_item_section);
        $validator = $item_section_DAO->getUpdateValidate($request);

        if ($validator->passes()) {
            $item_section_DAO->update($request);
            return 0;
        } else {
            return response()->json(['error'=>$validator->errors()->all()]);
        }

    }

    public function deleteLecOrSemOrCW(Request $request)
    {
        $item_section_DAO = $this->getItemSectionDAO($request->input('type_item_section'));
        $item_section_DAO->delete($request);
        return 0;
    }


    public function getItemSectionDAO ($type_card) {
        $item_section_DAO =  new stdClass();
        switch ($type_card) {
            case 'lecture':
                $item_section_DAO = $this->lecture_plan_DAO;
                break;
            case 'seminar':
                $item_section_DAO = $this->seminar_plan_DAO;
                break;
            case 'control_work':
                $item_section_DAO = $this->control_work_plan_DAO;
                break;
        }
        return $item_section_DAO;
    }

    public function getViewUpdatePathLecSemCW($type_card) {
        if ($type_card == 'lecture') {
            return 'lectures.view_or_update_lecture';
        } else if ($type_card == 'seminar'){
            return 'seminars.view_or_update_seminar';
        } else {
            return 'control_works.view_or_update_control_work';
        }
    }

    public function getPathAddViewLecSemCW($type_card) {
        if ($type_card == 'lecture') {
            return 'lectures.add_lecture';
        } else if ($type_card == 'seminar'){
            return 'seminars.add_seminar';
        } else {
            return 'control_works.add_control_work';
        }
    }

    public function copyCoursePlan(Request $request) {
        $id_new_course_plan = $this->course_plan_DAO->copyCoursePlan($request->input('id_course_plan'));
        return redirect('course_plan/' .  $id_new_course_plan);
    }

    //Возвращает главную страницу для выбора типа ведомости и группы
    public function statements(){
        $user = Auth::user();
        $groups = TeacherHasGroup::where('user_id', $user['id'])
            ->join('groups', 'groups.group_id', '=', 'teacher_has_group.group')
            ->where('groups.archived', 0)
            #->where('group_name', 'LIKE', '%Б22-524%')
            ->get();
        $group_set = Group::where('groups.archived', 0)->where('groups.id_course_plan', '<>', null)->get();
        #$group_set = Group::where('group_name', 'LIKE', '%Б22-524%')->where('groups.id_course_plan', '<>', null)->get();
        return view('personal_account/statements', compact('groups', 'user', 'group_set'));
    }

    //показывает личный кабинет студента, вкладку со статистикой
    public function showPersonalAccount()
    {
        $user = Auth::user();
        $role = $user['role'];
        if ($role === 'Админ' || $role === 'Преподаватель') {
            return AdministrationController::getAdminPanel();
        }
        if ($role === 'Студент' || $role === 'Староста') {
            return $this->showStudentAccount($user);
        }
        return PersonalAccount::showTestResults();
    }

    //показывает личный кабинет студента, вкладку со статистикой
    public function showStudentInfo()
    {
        $user = Auth::user();
        $role = $user['role'];
        if ($role === 'Админ' || $role === 'Преподаватель') {
            $groups = Group::where('groups.archived', 0)->where('groups.id_course_plan', '<>', null)->get();
            return view('personal_account/students', compact('groups'));
        }
        return $this->showStudentAccount($user);
    }

    public function showSpecificStudentAccount($id) {
        $user = Auth::user();
        $role = $user['role'];
        if ($role !== 'Админ' && $role !== 'Преподаватель') {
            return 'Нет доступа';
        }
        $student = User::whereId($id)->first();
        return $this->showStudentAccount($student);
    }

    private function showStudentAccount($user) {
        $id_course_plan = Group::where('group_id', $user->group)->select('id_course_plan')
            ->first()->id_course_plan;
        $course_plan = $this->course_plan_DAO->getCoursePlan( $id_course_plan);
        $statement_lecture = $this->lecture_statement->getStatementByUser($id_course_plan, $user);
        $statement_seminar = $this->seminar_statement->getStatementByUser($id_course_plan, $user);
        $statement_result = $this->result_statement->getResultingStatementByUser($id_course_plan, $user);
        $screenshots = $this->getScreenshots(Auth::user()['id']);
        return view('personal_account/student_account',
            compact('course_plan','statement_lecture','statement_seminar',
                'statement_result', 'user', 'screenshots'));
    }

    private function getScreenshots($userId) {
        $dir = 'screenshots/tests/' . $userId;
        if (!file_exists($dir)) {
            return [];
        }
        $res = glob($dir . '/*.png');
        return preg_filter('/^/', '/', $res);
    }

    public function getStudentsByGroup(Request $request) {
        $data = $this->getData($request);
        $groupId = $data->groupId;
        $users = User::where('group', '=', $groupId)
            ->whereIn('role', ['Студент', 'Староста'])
            ->join('groups', 'groups.group_id', '=', 'users.group')
            ->orderBy('users.last_name', 'asc')
            ->distinct()->get();
        return response()->json($users);
    }

    private function getData(Request $request) {
        return json_decode($request->input('data'), false);
    }

    //Возвращают представление соответствующей ведомости
    public function get_lectures(Request $request){
        $id_group = $request->input('group');
        $data = $this->getStewardAttendanceData($id_group);
        return view('personal_account/statements/steward_lectures', $data);
    }

    public function get_seminars(Request $request){
        \Log::info('get_seminars запущен');
        $id_group = $request->input('group');
        \Log::info('Группа: ' . $id_group);

        $group = Group::where('group_id', $id_group)->select('id_course_plan')->first();
        if (!$group) {
            \Log::error('Группа не найдена!');
            abort(404, 'Группа не найдена');
        }

        $id_course_plan = $group->id_course_plan;
        \Log::info('Курс найден: ' . $id_course_plan);

        $statement_seminar = $this->seminar_statement->getStatementByGroup($id_group);
        \Log::info('Данные по семинарам получены');

        $course_plan = $this->course_plan_DAO->getCoursePlan($id_course_plan);

        return view('personal_account/statements/seminars', compact('course_plan','id_group', 'statement_seminar'));
    }


    private function modifySecOk($statement_result, $course_plan) {
        foreach ($statement_result as $statement) {
            $sec_ok = array();
            foreach ($course_plan->section_plans as $section_plan) {
                $max_points_sum = 0;
                foreach ($section_plan->control_work_plans as $control_work_plan) {
                    $max_points_sum += $control_work_plan->max_points;
                }
                $points_sum = 0;
                foreach ($statement['control_work_groupBy_sections'][$section_plan->section_num] as $control_work_passes) {
                    $points_sum += $control_work_passes->points;
                }
                if ($points_sum >= $max_points_sum * 0.6) {
                    $sec_ok[$section_plan->section_num] = 1;
                } else {
                    $sec_ok[$section_plan->section_num] = 0;
                }
            }
            $statement['sec_ok'] = $sec_ok;
        }
    }

    public function get_resulting(Request $request){
        $id_group = $request->input('group');
        $statement_result = $this->result_statement->getResultingStatementByGroup($id_group);
        $id_course_plan = Group::where('group_id', $id_group)->select('id_course_plan')
            ->first()->id_course_plan;
        $course_plan = $this->course_plan_DAO->getCoursePlan($id_course_plan);
        // $this->modifySecOk($statement_result, $course_plan);
        return view('personal_account/statements/results',
            compact('course_plan','id_group', 'statement_result'));
    }

    // Генерация ведомости
    public function gen_statement(Request $request) {
        // $stat_type - Тип ведомости:
        // 1. credit - зачёт
        // 2. credit-with-grade - зачёт с оценкой
        // 3. exam - экзамен
        // 4. section-evaluation - аттестация разделов
        $stat_type = $request->input('type');
        if (!in_array($stat_type, ['credit', 'credit-with-grade', 'exam', 'section-evaluation'])) {
            return;
        }

        $id_group = $request->input('group');
        $file = $request->file;
        $id_course_plan = Group::where('group_id', $id_group)->select('id_course_plan')
            ->first()->id_course_plan;
        $course_plan = $this->course_plan_DAO->getCoursePlan($id_course_plan);
        $statement_result = $this->result_statement->getResultingStatementByGroup($id_group);
        Storage::disk('local')->put('file.xlsx', file_get_contents($file));

        return $this->result_statement->getExcelLoadOut(
            $course_plan,
            $statement_result,
            '/storage/app/file.xlsx',
            $stat_type);;
    }

    // Отмечает или раз-отмечает студента на лекции (СТАРАЯ СИСТЕМА → НОВАЯ СИСТЕМА)
    public function lecture_mark_present(Request $request){
        // Вызываем старую логику
        $this->lecture_statement->markPresent($request);
        
        $id_user = $request->input('id_user');
        $id_lecture_plan = $request->input('id_lecture_plan');
        $is_presence = $request->input('is_presence');
        $presence = ($is_presence == 'true') ? 1 : 0;
        
        // Получаем группу студента
        $student = DB::table('users')->where('id', $id_user)->first();
        if (!$student) {
            return 0;
        }
        $groupId = $student->group;
        
        // === ВЫЧИСЛЯЕМ id_lecture ДЛЯ НОВОЙ СИСТЕМЫ И ИНТЕРФЕЙСА СТАРОСТЫ ===
        // Находим любой id_lecture_plan для группы
        $anyLecturePlan = DB::table('lecture_passes')
            ->join('users', 'lecture_passes.id_user', '=', 'users.id')
            ->where('users.group', $groupId)
            ->whereNotNull('lecture_passes.id_lecture_plan')
            ->select('lecture_passes.id_lecture_plan')
            ->first();
        
        if ($anyLecturePlan) {
            // Вычисляем base_id (158, 174, 190...)
            $firstEverId = 158;
            $sampleId = $anyLecturePlan->id_lecture_plan;
            $baseId = $sampleId - (($sampleId - $firstEverId) % 16);
            
            // Находим номер лекции в старом плане по id_lecture_plan
            $lectureNum = $id_lecture_plan - $baseId + 1;
            
            // lectureNum соответствует id_lecture в новой системе
            $id_lecture = $lectureNum;
            
            // === СИНХРОНИЗАЦИЯ В НОВУЮ СИСТЕМУ И ИНТЕРФЕЙС СТАРОСТЫ ===
            if ($presence) {
                DB::table('lecture_passes')->updateOrInsert(
                    [
                        'id_user' => $id_user,
                        'id_lecture' => $id_lecture
                    ],
                    [
                        'presence' => 1,
                        'marked_by' => Auth::user()->id
                    ]
                );
                
                // Проверяем и обновляем лимит
                $this->updateLimitAfterMark($id_lecture, $groupId);
            } else {
                // Снимаем отметку в новой системе и интерфейсе старосты
                DB::table('lecture_passes')
                    ->where('id_user', $id_user)
                    ->where('id_lecture', $id_lecture)
                    ->update(['presence' => 0]);
            }
        }
        
        return 0;
    }

    // Отмечает всех на лекции (СТАРАЯ СИСТЕМА → НОВАЯ СИСТЕМА)
    public function lecture_mark_present_all(Request $request){
        $id_lecture_plan = $request->input('id_lecture_plan');
        $id_group = $request->input('id_group');
        
        // Вызываем старую логику
        $this->lecture_statement->markPresentAll($request);
        
        // === СИНХРОНИЗАЦИЯ В НОВУЮ СИСТЕМУ ===
        // Получаем всех студентов группы
        $students = User::where('group', '=', $id_group)
            ->whereIn('role', ['Студент', 'Староста'])
            ->get();
        
        // Находим любой id_lecture_plan для группы
        $anyLecturePlan = DB::table('lecture_passes')
            ->join('users', 'lecture_passes.id_user', '=', 'users.id')
            ->where('users.group', $id_group)
            ->whereNotNull('lecture_passes.id_lecture_plan')
            ->select('lecture_passes.id_lecture_plan')
            ->first();
        
        if ($anyLecturePlan) {
            // Вычисляем base_id и lectureNum
            $firstEverId = 158;
            $sampleId = $anyLecturePlan->id_lecture_plan;
            $baseId = $sampleId - (($sampleId - $firstEverId) % 16);
            $lectureNum = $id_lecture_plan - $baseId + 1;
            $id_lecture = $lectureNum; // id_lecture в новой системе
            
            // Отмечаем всех студентов в новой системе
            foreach ($students as $student) {
                DB::table('lecture_passes')->updateOrInsert(
                    [
                        'id_user' => $student->id,
                        'id_lecture' => $id_lecture
                    ],
                    [
                        'presence' => 1,
                        'marked_by' => Auth::user()->id
                    ]
                );
            }
            
            // Обновляем лимит
            $this->updateLimitAfterMark($id_lecture, $id_group);
        }
        // === КОНЕЦ СИНХРОНИЗАЦИИ ===
        
        return 0;
    }

    // Вспомогательный метод для обновления лимита
    private function updateLimitAfterMark($lectureId, $groupId) {
        $newCount = DB::table('lecture_passes')
            ->join('users', 'lecture_passes.id_user', '=', 'users.id')
            ->where('lecture_passes.id_lecture', $lectureId)
            ->where('users.group', $groupId)
            ->where('lecture_passes.presence', 1)
            ->count();
        
        $existingLimit = DB::table('lecture_group_limits')
            ->where('id_lecture', $lectureId)
            ->where('group_id', $groupId)
            ->first();
        
        if ($existingLimit) {
            if ($newCount > $existingLimit->attendance_limit) {
                DB::table('lecture_group_limits')
                    ->where('id_lecture', $lectureId)
                    ->where('group_id', $groupId)
                    ->update(['attendance_limit' => $newCount]);
            }
        } else {
            DB::table('lecture_group_limits')->insert([
                'id_lecture' => $lectureId,
                'group_id' => $groupId,
                'attendance_limit' => $newCount
            ]);
        }
    }


    public function seminar_mark_present_all(Request $request){
        $this->seminar_statement->markPresentAll($request);
        return 0;
    }

    //Отмечает или раз-отмечает студента на семинаре
    public function seminar_mark_present(Request $request){
        $this->seminar_statement->markPresent($request);
        return 0;
    }

    //Изменяет балл студента за работу на семинаре
    public function classwork_change(Request $request){
        $validator = $this->seminar_statement->getClassworkChangeValidate($request);
        if ($validator->passes()) {
            $this->seminar_statement->classworkChange($request);
            return 0;
        } else {
            return response()->json(['error'=>$validator->errors()->all()]);
        }
    }

    //Отмечает или раз-отмечает студента на Контрольном мероприятии
    public function result_mark_present(Request $request){
        $this->result_statement->markPresent($request);

        $id_user = $request->input('id_user');
        $id_course_plan = $request->input('id_course_plan');
        $user = User::whereId($id_user)->first();
        $statement = $this->result_statement->getResultingStatementByUser($id_course_plan, $user);

        return response()->json([
            'statement' => $statement,
        ]);
    }

    //Изменяет балл студента за контр мероприятие
    public function result_change(Request $request){
        $validator = $this->result_statement->getResultChangeValidate($request);
        if ($validator->passes()) {
            $this->result_statement->resultChange($request);
        }

        $id_user = $request->input('id_user');
        $id_course_plan = $request->input('id_course_plan');
        $user = User::whereId($id_user)->first();
        $statement = $this->result_statement->getResultingStatementByUser($id_course_plan, $user);

        return response()->json([
            'statement' => $statement,
        ]);
    }

    public function result_mark_present_all(Request $request){
        $this->result_statement->markPresentAll($request);
        return 0;
    }



    public function viewStewardAttendance(Request $request)
    {
        $selectedGroupId = $request->input('group');
        return view('view_steward_attendance', $this->getStewardAttendanceData($selectedGroupId));
    }

    private function getStewardAttendanceData($selectedGroupId)
    {
        $groups = DB::table('groups')
            ->where('archived', 0)
            #->where('group_name', 'LIKE', '%Б22-524%')
            ->get();

        $students = $selectedGroupId
                ? DB::table('users')
                    ->where('group', $selectedGroupId)
                    ->where('deleted_at', Null)
                    ->whereIn('role', ['Студент', 'Староста'])
                    ->orderBy('last_name', 'asc')
                    ->get()
                : null;

        $lectures = $selectedGroupId
            ? DB::table('lectures')
                ->orderBy('lecture_number', 'asc')
                ->get()
            : array();

        $limits = array();
        if ($selectedGroupId) {
            foreach ($lectures as $lecture) {
                $limit = DB::table('lecture_group_limits')
                    ->where('group_id', $selectedGroupId)
                    ->where('id_lecture', $lecture->id_lecture)
                    ->first();
                $limits[$lecture->id_lecture] = $limit ? $limit->attendance_limit : null;
            }
        }

        $marks = array();
        $currentAttendanceCount = array();
        
        if ($selectedGroupId) {
            foreach ($lectures as $lecture) {
                $lectureMarks = DB::table('lecture_passes')
                    ->join('users', 'lecture_passes.id_user', '=', 'users.id')
                    ->where('lecture_passes.id_lecture', $lecture->id_lecture)
                    ->where('users.group', $selectedGroupId)
                    ->where('lecture_passes.presence', 1)
                    ->select('users.id as id_user', 'lecture_passes.presence')
                    ->get();

                foreach ($lectureMarks as $mark) {
                    $marks[$mark->id_user][$lecture->id_lecture] = $mark->presence;
                }
                
                $currentAttendanceCount[$lecture->id_lecture] = count($lectureMarks);
            }
        }

        return [
            'groups' => $groups,
            'students' => $students,
            'lectures' => $lectures,
            'marks' => $marks,
            'limits' => $limits,
            'currentAttendanceCount' => $currentAttendanceCount,
            'selectedGroupId' => $selectedGroupId,
        ];
    }




    public function toggleAttendance(Request $request) {
        try {
            $studentId = $request->input('student_id');
            $lectureId = $request->input('lecture_id'); 
            $presence = $request->input('presence');
            
            $student = DB::table('users')->where('id', $studentId)->first();
            $groupId = $student->group;
            
            $userRole = Auth::user()->role;
            $isTeacher = ($userRole === 'Преподаватель' || $userRole === 'Админ');
            
            // === ИСПРАВЛЕНИЕ: Получаем ТЕКУЩЕЕ количество отметок ===
            $currentAttendance = DB::table('lecture_passes')
                ->join('users', 'lecture_passes.id_user', '=', 'users.id')
                ->where('lecture_passes.id_lecture', $lectureId)
                ->where('users.group', $groupId)
                ->where('lecture_passes.presence', 1) // ← Только присутствующие!
                ->count();
            
            if ($presence) {
                // === ПРОВЕРКА ЛИМИТА ТОЛЬКО ДЛЯ СТАРОСТЫ ===
                if (!$isTeacher) {
                    $limit = DB::table('lecture_group_limits')
                        ->where('id_lecture', $lectureId)
                        ->where('group_id', $groupId)
                        ->first();
                    
                    if (!$limit) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Лимит не установлен. Обратитесь к преподавателю.'
                        ]);
                    }
                    
                    // Проверяем, не превысит ли добавление нового студента лимит
                    if ($currentAttendance >= $limit->attendance_limit) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Достигнут лимит отметок.'
                        ]);
                    }
                }
                
                // Сохраняем отметку в НОВОЙ системе
                DB::table('lecture_passes')->updateOrInsert(
                    [
                        'id_user' => $studentId,
                        'id_lecture' => $lectureId
                    ],
                    [
                        'presence' => 1,
                        'marked_by' => Auth::user()->id
                    ]
                );

                // === УВЕДОМЛЕНИЕ: посещаемость отмечена ===
                try {
                    $lecture = DB::table('lectures')->where('id_lecture', $lectureId)->first();
                    $lectureName = $lecture ? $lecture->lecture_name : 'Лекция №' . $lectureId;
                    $markedBy = Auth::user()->first_name . ' ' . Auth::user()->last_name;
                    NotificationService::send(
                        $studentId,
                        'attendance',
                        'Отмечена посещаемость',
                        'Ваше присутствие на лекции «' . $lectureName . '» зафиксировано (' . $markedBy . ').',
                        ['lecture_id' => $lectureId, 'url' => route('personal_account')]
                    );
                } catch (\Exception $ne) {
                    Log::warning('Notification send failed: ' . $ne->getMessage());
                }
                
                // === ИСПРАВЛЕНИЕ: СИНХРОНИЗАЦИЯ в старую систему БЕЗ lecture_mapping ===
                // Находим любой id_lecture_plan для этой группы
                $anyLecturePlan = DB::table('lecture_passes')
                    ->join('users', 'lecture_passes.id_user', '=', 'users.id')
                    ->where('users.group', $groupId)
                    ->whereNotNull('lecture_passes.id_lecture_plan')
                    ->select('lecture_passes.id_lecture_plan')
                    ->first();
                
                if ($anyLecturePlan) {
                    // Вычисляем base_id (158, 174, 190...)
                    $firstEverId = 158;
                    $sampleId = $anyLecturePlan->id_lecture_plan;
                    $baseId = $sampleId - (($sampleId - $firstEverId) % 16);
                    
                    // Находим номер лекции в новой системе
                    $id_lecture_plan = $baseId + ($lectureId - 1);
                    
                    // Сохраняем в старой системе
                    DB::table('lecture_passes')->updateOrInsert(
                        [
                            'id_user' => $studentId,
                            'id_lecture_plan' => $id_lecture_plan
                        ],
                        [
                            'presence' => 1
                        ]
                    );
                }
                
            } else {
                // Убираем отметку в НОВОЙ системе
                DB::table('lecture_passes')
                    ->where('id_user', $studentId)
                    ->where('id_lecture', $lectureId)
                    ->update(['presence' => 0]);
                
                // === ИСПРАВЛЕНИЕ: СИНХРОНИЗАЦИЯ в старую систему БЕЗ lecture_mapping ===
                // Находим любой id_lecture_plan для этой группы
                $anyLecturePlan = DB::table('lecture_passes')
                    ->join('users', 'lecture_passes.id_user', '=', 'users.id')
                    ->where('users.group', $groupId)
                    ->whereNotNull('lecture_passes.id_lecture_plan')
                    ->select('lecture_passes.id_lecture_plan')
                    ->first();
                
                if ($anyLecturePlan) {
                    // Вычисляем base_id
                    $firstEverId = 158;
                    $sampleId = $anyLecturePlan->id_lecture_plan;
                    $baseId = $sampleId - (($sampleId - $firstEverId) % 16);
                    
                    // Находим номер лекции в новой системе
                    $id_lecture_plan = $baseId + ($lectureId - 1);
                    
                    // Обновляем в старой системе
                    DB::table('lecture_passes')
                        ->where('id_user', $studentId)
                        ->where('id_lecture_plan', $id_lecture_plan)
                        ->update(['presence' => 0]);
                }
            }
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

}
