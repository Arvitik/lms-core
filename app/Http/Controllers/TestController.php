<?php
/**
 * Created by PhpStorm.
 * User: Ð¡Ñ‚Ð°Ð½Ð¸ÑÐ»Ð°Ð²
 * Date: 19.04.15
 * Time: 16:49
 */
namespace App\Http\Controllers;
use App\Group;
use App\Protocols\TestProtocol;
use App\Statements\Passes\ControlWorkPasses;
use App\Statements\Passes\LecturePasses;
use App\Statements\Plans\ControlWorkPlan;
use App\Statements\Plans\CoursePlan;
use App\Testing\Fine;
use App\Testing\Result;
use App\Testing\Section;
use App\Testing\StructuralRecord;
use App\Testing\Test;
use App\Testing\TestForGroup;
use App\Testing\TestGeneration\GraphBuilder;
use App\Testing\TestGeneration\UsualTestGenerator;
use App\Testing\TestStructure;
use App\Testing\TestTask;
use App\Testing\Theme;
use App\Testing\Type;
use App\User;
use Auth;
use Illuminate\Http\Request;
use App\Testing\Question;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use View;
use App\Statements\DAO\CoursePlanDAO;
use App\Statements\ResultStatement;
use App\Statements\DAO\SectionPlanDAO;
use App\Services\NotificationService;

class TestController extends Controller{
    private $test;
    private $course_plan_DAO;

    function __construct(Test $test ,CoursePlanDAO $course_plan_DAO,SectionPlanDAO $section_plan_DAO){
        $this->test=$test;
        $this->course_plan_DAO = $course_plan_DAO;
        $this->section_plan_DAO = $section_plan_DAO;
    }

    public function trainTests() {
        $tr_tests = [];
        $query = $this->test->whereTest_type('Тренировочный')
            ->whereVisibility(1)->whereArchived(0)->whereOnly_for_print(0)->whereIs_adaptive(0)->get();
        foreach ($query as $test) {
            $groupAvailability = TestForGroup::whereId_group(Auth::user()['group'])
                ->whereId_test($test['id_test'])
                ->select('availability')->first();
            $availability_for_group = $groupAvailability ? $groupAvailability->availability : 0;
            if ($availability_for_group) {
                $test['amount'] = Test::getAmount($test['id_test']);
                $test['attempts'] = Result::whereId_test($test['id_test'])->whereId(Auth::user()['id'])->where('mark_ru', '>=', 0)->count();
                array_push($tr_tests, $test);
            }
        }
        return view('tests.list.train_tests', compact('tr_tests'));
    }

    private $section_plan_DAO;

    public function controlTests() {
        $role = User::whereId(Auth::user()['id'])->select('role')->first()->role;
        $isAdmin = $role === 'Админ' || $role === 'Преподаватель';
        $query = $this->test->whereTest_type('Контрольный')
            ->whereVisibility(1)->whereArchived(0)->whereOnly_for_print(0)->get();
        $ctr_tests = [];
        foreach ($query as $test){
            $groupAvailability = TestForGroup::whereId_group(Auth::user()['group'])
                ->whereId_test($test['id_test'])
                ->select('availability')->first();

            if (!$groupAvailability) {
                continue; // Ð¿Ñ€Ð¾Ð¿ÑƒÑÐºÐ°ÐµÐ¼ ÑÑ‚Ð¾Ñ‚ Ñ‚ÐµÑÑ‚, ÐµÑÐ»Ð¸ Ð½ÐµÑ‚ Ð·Ð°Ð¿Ð¸ÑÐ¸
            }

            $availability_for_group = $groupAvailability->availability;

            if ($availability_for_group/* && $us_state['all_ok'] == 1*/) {
                $fine = Fine::whereId_test($test['id_test'])->whereId(Auth::user()['id'])->select('access')->get();

                $test['access_for_student'] = (count($fine) == 0 || $isAdmin) ? 1 : $fine[0]->access;
                $fineValue = Fine::whereId(Auth::user()['id'])->whereId_test($test['id_test'])->select('fine')->first();
                $fineLevel = $fineValue ? $fineValue->fine : 0;
                $test['max_points'] = Fine::levelToPercent($fineLevel) / 100 * $test['total'];
                $test['amount'] = Test::getAmount($test['id_test']);
                $test['attempts'] = Result::whereId_test($test['id_test'])->whereId(Auth::user()['id'])->where('mark_ru', '>=', 0)->count();
                array_push($ctr_tests, $test);
            }
        }
        return view('tests.list.control_tests', compact('ctr_tests'));
    }

    private function testIsInExamSection($test, $examSectionsBySection) {
        foreach ($examSectionsBySection as $examSections) {
            foreach ($examSections as $examSection) {
                $controlWorkID = $examSection->id_control_work_plan;
                $controlWorkPlan = ControlWorkPlan::where('id_control_work_plan', $controlWorkID)->select('id_test')->first();
                if (empty($controlWorkPlan)) {
                    continue;
                }
                $idTest = $controlWorkPlan->id_test;
                if (empty($idTest)) {
                    continue;
                }
                if ($idTest == $test['id_test']) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Ð³ÐµÐ½ÐµÑ€Ð¸Ñ€ÑƒÐµÑ‚ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñƒ ÑÐ¾Ð·Ð´Ð°Ð½Ð¸Ñ Ð½Ð¾Ð²Ð¾Ð³Ð¾ Ñ‚ÐµÑÑ‚Ð° (ÑˆÐ°Ð³ 1 - Ð¾ÑÐ½Ð¾Ð²Ð½Ñ‹Ðµ Ð½Ð°ÑÑ‚Ñ€Ð¾Ð¹ÐºÐ¸) */
    public function create(){
        $groups = Group::whereArchived(0)->get();
        $test = [
            'test_type' => '',
            'is_adaptive' => 0,
            'visibility' => 0,
            'multilanguage' => 0,
            'only_for_print' => 0,
            'total' => 100,
            'test_time' => 60,
            'max_questions' => 10,
        ];
        return view('tests.create', compact('groups', 'test'));
    }

    public function finishFstCreationStep(Request $request) {
        $general_settings = [];
        $test_for_groups = [];

        $general_settings['test_name'] = $request->input('test-name');
        $general_settings['test_type'] = $request->input('training') ? 'Тренировочный' : 'Контрольный';
        $general_settings['adaptive'] = $request->input('adaptive') ? 1 : 0;
        $general_settings['visibility'] = $request->input('visibility') ? 1 : 0;
        $general_settings['multilanguage'] = $request->input('multilanguage') ? 1 : 0;
        $general_settings['only_for_print'] = $request->input('only-for-print') ? 1 : 0;
        $general_settings['total'] = $request->input('total');
        $general_settings['test_time'] = $request->input('test-time');
        $general_settings['max_questions'] = $request->input('max_questions');

        $availability_input = ($request->input('availability') == null) ? [] : $request->input('availability');

        for ($i = 0; $i < count($request->input('id-group')); $i++) {
            $availability = in_array($request->input('id-group')[$i], $availability_input) ? 1 : 0;
            $test_for_groups[$request->input('id-group')[$i]] = $availability;
        }

        $request->session()->put('general_settings', $general_settings);
        $request->session()->put('test_for_groups', $test_for_groups);

        return redirect()->route('test_create_step2');
    }

    /** Ð³ÐµÐ½ÐµÑ€Ð¸Ñ€ÑƒÐµÑ‚ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñƒ ÑÐ¾Ð·Ð´Ð°Ð½Ð¸Ñ Ð½Ð¾Ð²Ð¾Ð³Ð¾ Ñ‚ÐµÑÑ‚Ð° (ÑˆÐ°Ð³ 2 - ÑÐ¾Ð·Ð´Ð°Ð½Ð¸Ðµ ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€) */
    public function createSndStep(Request $request) {
        $general_settings = $request->session()->get('general_settings');
        if (!$general_settings) {
            return redirect()->route('test_create');
        }
        $structures_data = $request->session()->get('structures_data');
        $sections = [];
        $sections_db = Section::where('section_code', '<', 10)->where('section_code', '>', 0)->select('section_code', 'section_name')->get();
        for ($i=0; $i < sizeof($sections_db); $i++) {
            $sections[$i]['name'] = $sections_db[$i]->section_name;
            $sections[$i]['code'] = $sections_db[$i]->section_code;
            $themes_in_section = Theme::whereSection_code($sections_db[$i]->section_code)->select('theme_name', 'theme_code')->get();
            for ($j=0; $j < count($themes_in_section); $j++) {
                $sections[$i]['themes'][$j] = $themes_in_section[$j];
            }
        }

        if ($general_settings['only_for_print']) {
            $types = Type::all();
        }
        else {
            $types = Type::whereOnly_for_print(0)->get();
        }

        $json_sections = json_encode($sections);
        $json_types = json_encode($types);
        $general_settings = json_encode($general_settings);
        return view('tests.create2', compact('general_settings', 'sections', 'types', 'json_sections', 'json_types', 'structures_data'));
    }

    public function validateTestStructure(Request $request) {
        $general_settings = json_decode($request->input('form')['general-settings']);
        $number_of_structures = count($request->input('form')['sections']);
        
        // ÐŸÑ€Ð¾Ð²ÐµÑ€Ð¸Ñ‚ÑŒ Ð´Ð»Ñ Ð°Ð´Ð°Ð¿Ñ‚Ð¸Ð²Ð½Ð¾Ð³Ð¾, Ñ‡Ñ‚Ð¾ ÑÑƒÐ¼Ð¼Ð°Ñ€Ð½Ð¾Ðµ Ñ‡Ð¸ÑÐ»Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð² ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€ Ð½Ðµ Ð¿Ñ€ÐµÐ²Ñ‹ÑˆÐ°ÐµÑ‚ Ð¼Ð°ÐºÑÐ¸Ð¼Ð°Ð»ÑŒÐ½Ð¾Ð³Ð¾ Ñ‡Ð¸ÑÐ»Ð° Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð² Ð´Ð»Ñ Ñ‚ÐµÑÑ‚Ð°
        $max_questions = $general_settings->max_questions;
        $amount_sum = 0;
        for ($i = 0; $i < $number_of_structures; $i++) {
            $amount = $request->input('form')['number-of-questions'][$i];
            $amount_sum += $amount;
        }
        if ($general_settings->adaptive && $max_questions < $amount_sum) {
            /*
            return response()->json([
                'error' => 'Ð”Ð»Ñ Ð°Ð´Ð°Ð¿Ñ‚Ð¸Ð²Ð½Ð¾Ð³Ð¾ Ñ‚ÐµÑÑ‚Ð° ÑÑƒÐ¼Ð¼Ð°Ñ€Ð½Ð¾Ðµ Ñ‡Ð¸ÑÐ»Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð² Ð² ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€Ð°Ñ… Ð½Ðµ Ð´Ð¾Ð»Ð¶Ð½Ð¾ Ð¿Ñ€ÐµÐ²Ñ‹ÑˆÐ°Ñ‚ÑŒ Ð¼Ð°ÐºÑÐ¸Ð¼Ð°Ð»ÑŒÐ½Ð¾Ðµ Ñ‡Ð¸ÑÐ»Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð² Ð² Ñ‚ÐµÑÑ‚Ðµ',
            ], 400);
            */
            return (String) false;
        }


        // ÐŸÑ€Ð¾Ð²ÐµÑ€ÐºÐ° ÐºÐ¾Ñ€Ñ€ÐµÐºÑ‚Ð½Ð¾ÑÑ‚Ð¸ ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€ Ñ‚ÐµÑÑ‚Ð°
        $restrictions = [];
        $restrictions['test'] = $general_settings;
        for ($i = 0; $i < $number_of_structures; $i++) {
            $restrictions['structures'][$i]['id_structure'] = $i;
            $restrictions['structures'][$i]['amount'] = $request->input('form')['number-of-questions'][$i];
            for ($j = 0; $j < count($request->input('form')['sections'][$i]); $j++) {
                $restrictions['structures'][$i]['sections'][$j]['section_code'] = $request->input('form')['sections'][$i][$j];
                try {
                    $theme_index = $this->getSectionOrderForThemes($request->input('form')['themes'], $restrictions['structures'][$i]['sections'][$j]['section_code'], $i);
                } catch (\Exception $e) {
                    return (String) false;
                }
                $themesForSection = isset($request->input('form')['themes'][$i][$theme_index]) && is_array($request->input('form')['themes'][$i][$theme_index])
                    ? $request->input('form')['themes'][$i][$theme_index] : [];
                for ($k = 0; $k < count($themesForSection); $k++) {
                    $restrictions['structures'][$i]['sections'][$j]['themes'][$k]['theme_code'] = $themesForSection[$k];
                }
            }
            for ($j = 0; $j < count($request->input('form')['types'][$i]); $j++) {
                $restrictions['structures'][$i]['types'][$j]['type_code'] = $request->input('form')['types'][$i][$j];
            }
        }

        try {
            $graph = GraphBuilder::buildGraphFromRestrictions($restrictions);
            $graph->fordFulkersonMaxFlow();
            return (String) $graph->isSaturated();
        } catch (\Throwable $e) {
            return (String) false;
        }
    }

    private function getSectionOrderForThemes($themes, $section_code, $i) {
        $themes_in_db = Theme::whereSection_code($section_code)->select('theme_code')->get();
        $number_of_sections_in_db = Section::where('section_code', '>', 0)->count();
        if (!isset($themes[$i]) || !is_array($themes[$i])) {
            throw new \Exception("Restrictions are invalid!");
        }
        for ($l = 0; $l < $number_of_sections_in_db; $l++) {
            $sectionThemes = isset($themes[$i][$l]) && is_array($themes[$i][$l]) ? $themes[$i][$l] : [];
            for ($n = 0; $n < count($sectionThemes); $n++) {
                foreach ($themes_in_db as $theme_in_db) {
                    if ($theme_in_db->theme_code == $sectionThemes[$n]) {
                        return $l;
                    }
                }
            }
        }
        throw new \Exception("Restrictions are invalid!");
    }

    /** Ð”Ð¾Ð±Ð°Ð²Ð»ÑÐµÑ‚ Ð½Ð¾Ð²Ñ‹Ð¹ Ñ‚ÐµÑÑ‚ Ð² Ð‘Ð” */
    public function add(Request $request){
        $general_settings = $request->session()->get('general_settings');

        Test::insert(array(
            'test_name' => $general_settings['test_name'],
            'test_type' => $general_settings['test_type'],
            'test_time' => $general_settings['test_time'],
            'total' => $general_settings['total'],
            'visibility' => $general_settings['visibility'],
            'multilanguage' => $general_settings['multilanguage'],
            'only_for_print' => $general_settings['only_for_print'],
            'is_adaptive' => $general_settings['adaptive'],
            'max_questions' => $general_settings['max_questions']
        ));

        $id_test = Test::max('id_test');
        $request->session()->get('test_for_groups');
        foreach ($request->session()->get('test_for_groups') as $group_id => $availability) {
            TestForGroup::insert(['id_test' => $id_test, 'id_group' => $group_id, 'availability' => $availability]);
        }

        for ($i = 0; $i < count($request->input('sections')); $i++) {
            $id_structure = TestStructure::max('id_structure') + 1;
            TestStructure::insert(array('id_structure' => $id_structure, 'id_test' => $id_test, 'amount' => $request->input('number-of-questions')[$i]));
            for ($j = 0; $j < count($request->input('sections')[$i]); $j++) {
                $restrictions['structures'][$i]['sections'][$j]['section_code'] = $request->input('sections')[$i][$j];
                $theme_index = $this->getSectionOrderForThemes($request->input('themes'), $request->input('sections')[$i][$j], $i);
                for ($k = 0; $k < count($request->input('themes')[$i][$theme_index]); $k++) {
                    for ($l = 0; $l < count($request->input('types')[$i]); $l++) {
                        StructuralRecord::insert(array(
                            'theme_code' => $request->input('themes')[$i][$theme_index][$k],
                            'section_code' => $request->input('sections')[$i][$j],
                            'type_code' => $request->input('types')[$i][$l],
                            'id_test' => $id_test,
                            'id_structure' => $id_structure
                        ));
                    }
                }
            }
        }
        // === УВЕДОМЛЕНИЕ: новый тест добавлен ===
        try {
            $testName = $general_settings['test_name'];
            $testType = isset($general_settings['test_type']) ? $general_settings['test_type'] : '';
            $groupIds = array_keys($request->session()->get('test_for_groups', []));
            if (!empty($groupIds)) {
                $studentIds = User::whereIn('group', $groupIds)
                    ->whereIn('role', ['Студент', 'Студент-заочник'])
                    ->pluck('id')
                    ->toArray();
                if (!empty($studentIds)) {
                    NotificationService::sendMany(
                        $studentIds,
                        'new_test',
                        'Новый тест доступен',
                        'Добавлен тест «' . $testName . '». Проверьте раздел Тестирование.',
                        ['test_id' => $id_test, 'url' => route('train_tests')]
                    );
                }
            }
        } catch (\Exception $ne) {
            Log::warning('Notification send failed: ' . $ne->getMessage());
        }

        $request->session()->forget('general_settings');
        $request->session()->forget('test_for_groups');
        $request->session()->forget('structures_data');
        return redirect()->route('test_create');
    }

    /** Ð’Ð¾Ð·Ð²Ñ€Ð°Ñ‰Ð°ÐµÑ‚ Ð¸Ð´ÐµÐ½Ñ‚Ð¸Ñ„Ð¸ÐºÐ°Ñ‚Ð¾Ñ€Ñ‹ Ñ‚ÐµÑ… Ñ‚ÐµÑÑ‚Ð¾Ð², ÐºÐ¾Ñ‚Ð¾Ñ€Ñ‹Ðµ Ð´Ð¾ÑÑ‚ÑƒÐ¿Ð½Ñ‹ Ñ…Ð¾Ñ‚Ñ Ð±Ñ‹ Ð´Ð»Ñ Ð¾Ð´Ð½Ð¾Ð¹ Ð½ÐµÐ°Ñ€Ñ…Ð¸Ð²Ð½Ð¾Ð¹ Ð³Ñ€ÑƒÐ¿Ð¿Ñ‹.
     * ÐžÑ‚Ð²ÐµÑ‚ Ð¸Ð¼ÐµÐµÑ‚ ÑÐ»ÐµÐ´ÑƒÑŽÑ‰Ð¸Ð¹ Ð²Ð¸Ð´: assoc(10 => 1, 23 => 1, 50 => 1);
     * Ð’ ÐºÐ°Ñ‡ÐµÑÑ‚Ð²Ðµ Ð·Ð½Ð°Ñ‡ÐµÐ½Ð¸Ð¹ Ð¼Ð¾Ð³ÑƒÑ‚ Ð±Ñ‹Ñ‚ÑŒ Ñ‚Ð¾Ð»ÑŒÐºÐ¾ ÐµÐ´Ð¸Ð½Ð¸Ñ†Ñ‹, Ð¾Ñ‚Ñ€Ð°Ð¶Ð°ÑŽÑ‰Ð¸Ðµ Ñ‚Ð¾Ñ‚ Ñ„Ð°ÐºÑ‚, Ñ‡Ñ‚Ð¾ Ñ‚ÐµÑÑ‚ Ñ id, ÑƒÐºÐ°Ð·Ð°Ð½Ð½Ñ‹Ð¼
     * Ð² ÐºÐ»ÑŽÑ‡Ðµ, ÑÐ²Ð»ÑÐµÑ‚ÑÑ "Ð´Ð¾ÑÑ‚ÑƒÐ¿Ð½Ñ‹Ð¼".
     */
    private function getAvailableTests() {
        $available_tests = TestForGroup::query()
            ->join('groups', 'test_for_group.id_group', 'groups.group_id')
            ->where('groups.archived', '=', 0)
            ->where('test_for_group.availability', '=', 1)
            ->orderBy('test_for_group.id_test')
            ->select('test_for_group.id_test')
            ->distinct()
            ->get();
        $res = [];
        foreach ($available_tests as $test) {
            $res[$test['id_test']] = 1;
        }
        return $res;
    }

    /** Ð¡Ð¿Ð¸ÑÐ¾Ðº Ð²ÑÐµÑ… Ñ‚ÐµÑÑ‚Ð¾Ð² Ð´Ð»Ñ Ð¸Ñ… Ñ€ÐµÐ´Ð°ÐºÑ‚Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ñ Ð¸ Ð·Ð°Ð²ÐµÑ€ÑˆÐµÐ½Ð¸Ñ */
    public function editList(){
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        $available_tests = $this->getAvailableTests();

        $ctr_tests = $this->test->whereTest_type('Контрольный')
            ->where('archived', '<>', '1')
            ->orderByDesc('id_test')
            ->select()
            ->get();
        foreach ($ctr_tests as $test){
            $test['amount'] = Test::getAmount($test['id_test']);
            $test['at_least_one_available'] = array_key_exists($test['id_test'], $available_tests);
        }

        $tr_tests = $this->test->whereTest_type('Тренировочный')
            ->where('archived', '<>', '1')
            ->orderByDesc('id_test')
            ->select()
            ->get();
        foreach ($tr_tests as $test){
            $test['amount'] = Test::getAmount($test['id_test']);
            $test['at_least_one_available'] = array_key_exists($test['id_test'], $available_tests);
        }

        $archived_tests = $this->test->where('archived', 1)
            ->orderByDesc('id_test')
            ->get();

        $id_group = Auth::user()->group;

        return view('personal_account.test_list', compact('ctr_tests', 'tr_tests', 'archived_tests', 'id_group'));
    }

   

    public function profile($id_test) {
        $test = Test::whereId_test($id_test)->first();
        $test['mean'] = Test::getMean($id_test);
        $test['median'] = Test::getMedian($id_test);
        $test['deviation'] = sqrt(Test::getVariance($id_test));
        $test['reliability'] = Test::getReliability($id_test);

        $structures = TestStructure::whereId_test($id_test)->select('id_structure', 'amount')->get();
        $restrictions = [];
        for ($i = 0; $i < count($structures); $i++) {
            $restrictions[$i]['amount'] = $structures[$i]->amount;
            $records = StructuralRecord::whereId_structure($structures[$i]->id_structure)->select('section_code', 'theme_code', 'type_code')->get();
            $sections = [];
            $themes = [];
            $types = [];
            foreach ($records as $record) {
                $section_name = Section::whereSection_code($record->section_code)->select('section_name')->first()->section_name;
                $theme_name = Theme::whereTheme_code($record->theme_code)->select('theme_name')->first()->theme_name;
                $type_name = Type::whereType_code($record->type_code)->select('type_name')->first()->type_name;
                if (!in_array($section_name, $sections)) array_push($sections, $section_name);
                if (!in_array($theme_name, $themes)) array_push($themes, $theme_name);
                if (!in_array($type_name, $types)) array_push($types, $type_name);
            }
            $restrictions[$i]['sections'] = $sections;
            $restrictions[$i]['themes'] = $themes;
            $restrictions[$i]['types'] = $types;

        }

        $groups = Group::whereArchived(0)->whereAcademic(1)->select('group_id', 'group_name')->get();
        return view('tests.profile', compact('test', 'restrictions', 'groups'));
    }

    public function updateSettings(Request $request) {
        Test::whereId_test($request->input('id_test'))->update([
            'visibility' => $request->input('visibility'),
            'only_for_print' => $request->input('only_for_print'),
            'multilanguage' => $request->input('multilanguage'),
            'is_adaptive' => $request->input('adaptive'),
            'max_questions' => $request->input('max_questions')
        ]);
    }

    /** Ð ÐµÐ´Ð°ÐºÑ‚Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ðµ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ð¾Ð³Ð¾ Ñ‚ÐµÑÑ‚Ð° */
    public function edit($id_test){
        $test = Test::whereId_test($id_test)->first();
        $test['is_resolved'] = Test::isResolved($id_test);
        $test['finish_opportunity'] = Test::isFinished($id_test) ? 0 : 1;
        $structures = TestStructure::whereId_test($id_test)->get();
        $test_for_groups = TestForGroup::whereId_test($test->id_test)
            ->join('groups', 'test_for_group.id_group', 'groups.group_id')
            ->where('archived', '=', 0)
            ->get();
        foreach ($test_for_groups as $test_for_group) {
            $test_for_group['group_name'] = Group::whereGroup_id($test_for_group['id_group'])->select('group_name')->first()->group_name;
            $test_for_group['finish_opportunity'] = Test::isFinishedForGroup($id_test, $test_for_group['id_group']) ? 0 : 1;
        }
        return view ('tests.edit', compact('test',  'test_for_groups', 'structures'));
    }

    public function cloneTest(Request $request) {
        if ($request->ajax()) {
            $old_test_id = $request->input('test_id');
            $old_items = Test::whereId_test($old_test_id)->get()->toArray();
            $item = $old_items[0];
            $item['id_test'] = null;
            $item['test_name'] = $item['test_name'] . ' (ÐºÐ¾Ð¿Ð¸Ñ)';
            $new_test_id = Test::insertGetId($item);
            $old_test_structures = TestStructure::whereId_test($old_test_id)->get()->toArray();
            $new_structure_id = TestStructure::max('id_structure');
            foreach ($old_test_structures as $old_test_structure) {
                $new_structure_id++;
                $old_structural_records = StructuralRecord::whereId_test($old_test_structure['id_test']) // whereId_test($old_test_id)
                    ->where('id_structure', '=', $old_test_structure['id_structure'])->get()->toArray();

                $old_test_structure['id_test'] = $new_test_id;
                $old_test_structure['id_structure'] = $new_structure_id;

                TestStructure::insert($old_test_structure);

                foreach ($old_structural_records as $old_structural_record) {
                    $old_structural_record['id_test'] = $new_test_id;
                    $old_structural_record['id_structure'] = $new_structure_id;
                    StructuralRecord::insert($old_structural_record);
                }
            }
            // adding test_for_group
            $groups = Group::whereArchived(0)->get();
            foreach ($groups as $group) {
                TestForGroup::insert(['id_test' => $new_test_id, 'id_group' => $group['group_id'], 'availability' => 0]);
            }
        }
        return null;
    }

    /** ÐŸÑ€Ð¸Ð¼ÐµÐ½ÐµÐ½Ð¸Ðµ Ð¸Ð·Ð¼ÐµÐ½ÐµÐ½Ð¸Ð¹ Ð¿Ð¾ÑÐ»Ðµ Ñ€ÐµÐ´Ð°ÐºÑ‚Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ñ Ñ‚ÐµÑÑ‚Ð° */
    public function update(Request $request){
        $id_test = $request->input('id-test');
        $test_name = $request->input('test-name');
        $test_type = $request->input('test-type');
        $is_adaptive = $request->input('adaptive') ? 1 : 0;
        $visibility = $request->input('visibility') ? 1 : 0;
        $multilanguage = $request->input('multilanguage') ? 1 : 0;
        $only_for_print = $request->input('only-for-print') ? 1 : 0;
        $total = $request->input('total');
        $test_time = $request->input('test-time');
        $max_questions = $request->input('max_questions');
        
        if ($request->input('go-to-create-extended-test') == 0) {
            /* Update test and test_for_group */
            Test::whereId_test($id_test)->update([
                'test_name' => $test_name, 'test_type' => $test_type, 'test_time' => $test_time,
                'total' => $total,'visibility' => $visibility, 'archived' => 0,
                'multilanguage' => $multilanguage, 'only_for_print' => $only_for_print,
                'is_adaptive' => $is_adaptive, 'max_questions' => $max_questions]);

            $availability_input = ($request->input('availability') == null) ? [] : $request->input('availability');

            for ($i = 0; $i < count($request->input('id-group')); $i++) {
                $availability = in_array($request->input('id-group')[$i], $availability_input) ? 1 : 0;
                TestForGroup::whereId_test($id_test)
                    ->whereId_group($request->input('id-group')[$i])
                    ->update(['id_test' => $id_test, 'id_group' => $request->input('id-group')[$i], 'availability' => $availability]);
            }
            
            if($request->input('go-to-edit-structure') == 0){
                return redirect()->route('tests_list');
            }
        }
        
        /* Get general settings for update structure or create new test based on it */
        $general_settings = [];
        $general_settings['id_test'] = $id_test;
        $general_settings['test_name'] = $test_name;
        $general_settings['test_type'] = $test_type;
        $general_settings['only_for_print'] = $only_for_print;
        $request->session()->put('general_settings', $general_settings);
        
        /* Get structures data for id_test */
        $structures = TestStructure::whereId_test($id_test)->select('id_structure', 'amount')->get();
        
        $structures_data = [];
        for ($i = 0; $i < count($structures); $i++) {
            $structures_data[$i]['amount'] = $structures[$i]->amount;
            $records = StructuralRecord::whereId_structure($structures[$i]->id_structure)->select('section_code', 'theme_code', 'type_code')->get();
            
            $sections = [];
            $themes = [];
            $types = [];
            foreach ($records as $record) {
                $section_code = $record->section_code;
                $theme_code = $record->theme_code;
                $type_code = $record->type_code;
                if (!in_array($section_code, $sections)) array_push($sections, $section_code);
                if (!in_array($theme_code, $themes)) array_push($themes, $theme_code);
                if (!in_array($type_code, $types)) array_push($types, $type_code);
            }
            $structures_data[$i]['sections'] = $sections;
            $structures_data[$i]['themes'] = $themes;
            $structures_data[$i]['types'] = $types;
        }
        
        $structures_data = json_encode($structures_data);
        $request->session()->put('structures_data', $structures_data);
        
        
        if ($request->input('go-to-edit-structure')) {
            return redirect()->route('test_edit_structure', ['id_test' => $id_test]);
        }
        else {
            $groups = Group::whereArchived(0)->get();
            $test = Test::whereId_test($id_test)->first();
            return view('tests.create', compact('groups', 'test'));
        }
    }

    public function makeAllControlTestsUnavailable() {
        $this->makeAllTestsUnavailable('control');
    }

    public function makeAllTrainTestsUnavailable() {
        $this->makeAllTestsUnavailable('train');
    }

    private function makeAllTestsUnavailable($test_type) {
        $test_type_real = $test_type === 'control' ? 'Контрольный' : 'Тренировочный';
        TestForGroup::whereAvailability(1)
            ->whereIn('id_test', function($query) use ($test_type_real) {
                $query->from('tests')
                    ->select('id_test')
                    ->where('test_type', '=', $test_type_real);
            })
            ->update(['availability' => 0]);
    }

    /** Ð ÐµÐ´Ð°ÐºÑ‚Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ðµ ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€Ñ‹ Ñ‚ÐµÑÑ‚Ð° */
    public function editStructure(Request $request, $id_test) {
        
        /* Get all sections and themes and types */
        $structures_data = $request->session()->get('structures_data');
        $general_settings = $request->session()->get('general_settings');
        $sections = [];
        $sections_db = Section::where('section_code', '<', 10)->where('section_code', '>', 0)->select('section_code', 'section_name')->get();
        for ($i=0; $i < sizeof($sections_db); $i++) {
            $sections[$i]['name'] = $sections_db[$i]->section_name;
            $sections[$i]['code'] = $sections_db[$i]->section_code;
            $themes_in_section = Theme::whereSection_code($sections_db[$i]->section_code)->select('theme_name', 'theme_code')->get();
            for ($j=0; $j < count($themes_in_section); $j++) {
                $sections[$i]['themes'][$j] = $themes_in_section[$j];
            }
        }

        if ($general_settings['only_for_print']) {
            $types = Type::all();
        }
        else {
            $types = Type::whereOnly_for_print(0)->get();
        }

        $json_sections = json_encode($sections);
        $json_types = json_encode($types);
        $general_settings = json_encode($general_settings);
        
        return view('tests.edit2', compact('general_settings', 'sections', 'types', 'json_sections', 'json_types', 'structures_data'));
    }
    
    public function changeStructure(Request $request) {
        $general_settings = $request->session()->get('general_settings');
        $id_test = $general_settings['id_test'];
        
        StructuralRecord::where('id_test', $id_test)->delete();
        TestStructure::where('id_test', $id_test)->delete();

        for ($i = 0; $i < count($request->input('sections')); $i++) {
            $id_structure = TestStructure::max('id_structure') + 1;
            TestStructure::insert(array('id_structure' => $id_structure, 'id_test' => $id_test, 'amount' => $request->input('number-of-questions')[$i]));
            for ($j = 0; $j < count($request->input('sections')[$i]); $j++) {
                $restrictions['structures'][$i]['sections'][$j]['section_code'] = $request->input('sections')[$i][$j];
                $theme_index = $this->getSectionOrderForThemes($request->input('themes'), $request->input('sections')[$i][$j], $i);
                for ($k = 0; $k < count($request->input('themes')[$i][$theme_index]); $k++) {
                    for ($l = 0; $l < count($request->input('types')[$i]); $l++) {
                        StructuralRecord::insert(array(
                            'theme_code' => $request->input('themes')[$i][$theme_index][$k],
                            'section_code' => $request->input('sections')[$i][$j],
                            'type_code' => $request->input('types')[$i][$l],
                            'id_test' => $id_test,
                            'id_structure' => $id_structure
                        ));
                    }
                }
            }
        }
        $request->session()->forget('general_settings');
        $request->session()->forget('test_for_groups');
        $request->session()->forget('structures_data');
        return redirect()->route('tests_list');
    }

    /** Ð—Ð°Ð²ÐµÑ€ÑˆÐ°ÐµÑ‚ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ñ‹Ð¹ Ñ‚ÐµÑÑ‚ Ð´Ð»Ñ Ð²ÑÐµÑ… ÑƒÑ‡ÐµÐ±Ð½Ñ‹Ñ… Ð³Ñ€ÑƒÐ¿Ð¿ */
    public function finishTest($id_test) {
        Test::finishTest($id_test);
        return redirect()->route('test_edit', $id_test);
    }

    public function finishTestForGroup($id_test, $id_group) {
        Test::finishTestForGroup($id_test, $id_group);
        return redirect()->route('test_edit', $id_test);
    }

    /** Ð¿Ð¾Ð»Ð½Ð¾Ðµ ÑƒÐ´Ð°Ð»ÐµÐ½Ð¸Ðµ, ÐµÑÐ»Ð¸ Ð½Ð¸ÐºÑ‚Ð¾ Ð½Ðµ Ð¿Ñ€Ð¾Ñ…Ð¾Ð´Ð¸Ð» ÐµÐ³Ð¾, Ð¿Ð¾Ð¼ÐµÑ‚ÐºÐ° ÐºÐ°Ðº Ð°Ñ€Ñ…Ð¸Ð²Ð½Ñ‹Ð¹ Ð² Ð¿Ñ€Ð¾Ñ‚Ð¸Ð²Ð½Ð¾Ð¼ ÑÐ»ÑƒÑ‡Ð°Ðµ */
    public function remove($id_test){
        if (!Test::isResolved($id_test)){
            $structures = TestStructure::whereId_test($id_test)->get();
            foreach ($structures as $structure){
                StructuralRecord::whereId_structure($structure['id_structure'])->delete();
            }
            TestStructure::whereId_test($id_test)->delete();
            TestForGroup::whereId_test($id_test)->delete();
            Test::whereId_test($id_test)->delete();
        }
        else {
            Test::whereId_test($id_test)->update(['archived' => 1]);
        }

        // fill null results
        $active_results = Result::whereId_test($id_test)->whereNull('result')->select('id_result')->get();
        foreach ($active_results as $result) {
            Result::whereId_result($result->id_result)->update(['result' => -1, 'mark_ru' => -1, 'mark_eu' => 'test deleted']);
        }

        // make this test unavailable for all groups
        TestForGroup::whereId_test($id_test)->update(['availability' => 0]);

        return redirect()->route('tests_list');
    }

    /** AJAX-Ð¼ÐµÑ‚Ð¾Ð´: Ð¿Ð¾Ð»ÑƒÑ‡Ð°ÐµÑ‚ ÑÐ¿Ð¸ÑÐ¾Ðº Ñ‚ÐµÐ¼ Ñ€Ð°Ð·Ð´ÐµÐ»Ð° */
    public function getTheme(Request $request){
        if ($request->ajax()) {
            $themes_list = [];
            $section = $request->input('choice');
            $sectionRow = Section::whereSection_name($section)->first();
            if (!$sectionRow) {
                return (String) view('tests.getTheme', compact('themes_list'));
            }
            $section_code = $sectionRow->section_code;
            $query = Theme::whereSection_code($section_code)->select('theme_name')->get();
            foreach ($query as $str){
                array_push($themes_list,$str->theme_name);
            }
            return (String) view('tests.getTheme', compact('themes_list'));
        }
    }

    /** AJAX-Ð¼ÐµÑ‚Ð¾Ð´: Ð¿Ð¾ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸ÑŽ Ñ€Ð°Ð·Ð´ÐµÐ»Ð°, Ñ‚ÐµÐ¼Ñ‹, Ñ‚Ð¸Ð¿Ð°, Ñ‚Ð¸Ð¿Ð° Ñ‚ÐµÑÑ‚Ð°, Ð²Ð¾Ð·Ð¼Ð¾Ð¶Ð½Ð¾ÑÑ‚Ð¸ Ð¿ÐµÑ‡Ð°Ñ‚Ð¸ Ð²Ñ‹Ñ‡Ð¸ÑÐ»ÑÐµÑ‚ ÐºÐ¾Ð»Ð¸Ñ‡ÐµÑÑ‚Ð²Ð¾ Ð´Ð¾ÑÑ‚ÑƒÐ¿Ð½Ñ‹Ñ… Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð² Ð² Ð‘Ð” Ð´Ð°Ð½Ð½Ð¾Ð¹ ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€Ñ‹ */
    public function getAmount(Request $request){
        if ($request->ajax()) {
            if ($request->input('training')) {
                $test_type = 'Тренировочный';
            }
            else $test_type = 'Контрольный';
            if ($request->input('printable')) {
                $printable = 1;
            }
            else $printable = 0;
            $sections = $request->input('section');
            $themes = $request->input('theme');
            $types = $request->input('type');
            if (!is_array($sections) || !is_array($themes) || !is_array($types)
                || count($sections) === 0 || count($themes) === 0 || count($types) === 0) {
                return (string) 0;
            }
            $amount = Question::getAmount($sections, $themes, $types, $request->input('test_type'), $printable);
            return (String) $amount;
        }
    }

    /** Ð’ Ñ„Ð¾Ð½Ð¾Ð²Ð¾Ð¼ Ñ€ÐµÐ¶Ð¸Ð¼Ðµ ÑÐ¾Ð·Ð´Ð°Ð½Ð¸Ðµ Ð¿Ñ€Ð¾Ñ‚Ð¾ÐºÐ¾Ð»Ð° Ð¿Ð¾ ÐºÐ¾Ð½Ñ‚Ñ€Ð¾Ð»ÑŒÐ½Ð¾Ð¼Ñƒ Ñ‚ÐµÑÑ‚Ñƒ */
    public function getProtocol(Request $request){
        if ($request->ajax()) {
            try {
                $protocol = new TestProtocol($request->input('id_test'), $request->input('id_user'), $request->input('html_text'));
                $protocol->create();
            } catch (\Exception $e) {
                \Log::error('Protocol generation failed: '.$e->getMessage(), [
                    'id_test' => $request->input('id_test'),
                    'id_user' => $request->input('id_user')
                ]);
                return response()->json(['success' => false, 'message' => 'Не удалось создать протокол.'], 500);
            }
            return response()->json(['success' => true]);
        }
    }

    /** Ð“Ð»Ð°Ð²Ð½Ñ‹Ð¹ Ð¼ÐµÑ‚Ð¾Ð´: Ð³ÐµÐ½ÐµÐ½Ñ€Ð¸Ñ€ÑƒÐµÑ‚ Ð¿Ð¾Ð»Ð¾Ñ‚Ð½Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð² Ð½Ð° ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ðµ Ñ‚ÐµÑÑ‚Ð¾Ð² */
    public function showViews($id_test){
        date_default_timezone_set('Europe/Moscow');
        $result = new Result();
        $user = new User();
        $question = new Question();
        $widgets = [];
        $saved_test = [];

        // Ð˜Ð½Ñ„Ð¾Ñ€Ð¼Ð°Ñ†Ð¸Ñ Ð¾ Ñ‚ÐµÑÑ‚Ðµ (Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ Ð¸ Ñ‚.Ð´.)
        $test = $this->test->whereId_test($id_test)->first();

        // ÐšÐ¾Ð»Ð¸Ñ‡ÐµÑÑ‚Ð²Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð²
        $amount = $this->test->getAmount($id_test);

        // Ð’Ñ€ÐµÐ¼Ñ Ð¿Ñ€Ð¾Ñ…Ð¾Ð¶Ð´ÐµÐ½Ð¸Ñ
        $test_time = $test->test_time;

        // Ð’Ð¸Ð´ Ñ‚ÐµÑÑ‚Ð°: "ÐšÐ¾Ð½Ñ‚Ñ€Ð¾Ð»ÑŒÐ½Ñ‹Ð¹",..
        $test_type = $test->test_type;

        // Ð˜Ð´ÐµÐ½Ñ‚Ð¸Ñ„Ð¸ÐºÐ°Ñ‚Ð¾Ñ€ Ñ€ÐµÐ·ÑƒÐ»ÑŒÑ‚Ð°Ñ‚Ð° ÑƒÐ¶Ðµ Ð·Ð°Ð¿ÑƒÑ‰ÐµÐ½Ð½Ð¾Ð³Ð¾ Ñ‚ÐµÑÑ‚Ð° Ð¸Ð»Ð¸ Ð¶Ðµ -1
        $result_id = Result::getCurrentResult(Auth::user()['id'], $id_test);

        //ÐµÑÐ»Ð¸ Ð¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ñ‚ÐµÐ»ÑŒ Ð½Ðµ Ð¸Ð¼ÐµÐµÑ‚ Ð½Ð°Ñ‡Ð°Ñ‚Ñ‹Ð¹ Ñ‚ÐµÑÑ‚
        //ÐµÑÐ»Ð¸ Ð² Ñ‚ÐµÑÑ‚ Ð·Ð°Ð¹Ð´ÐµÐ½Ð¾ Ð¿ÐµÑ€Ð²Ñ‹Ð¹ Ñ€Ð°Ð·
        if ($result_id == -1) {                                             
            $generator = new UsualTestGenerator();
            $generator->generate($test);
            for ($i=0; $i < $amount; $i++) {
                $id = $generator->chooseQuestion();

                //Ð´Ð¾Ð»Ð¶Ð½Ñ‹ Ð¿Ð¾Ð»ÑƒÑ‡Ð°Ñ‚ÑŒ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ view Ð¸ Ð½ÐµÐ¾Ð±Ñ…Ð¾Ð´Ð¸Ð¼Ñ‹Ðµ Ð¿Ð°Ñ€Ð°Ð¼ÐµÑ‚Ñ€Ñ‹
                $data = $question->show($id, $i+1, false);

                $saved_test[] = $data;
                $widget = View::make($data['view'], $data['arguments']);
                $widget->render();
                $widgets[] = $widget;
            }

            //Ð²Ñ€ÐµÐ¼Ñ ÐºÐ¾Ð½Ñ†Ð°
            $int_end_time =  date('U') + 60*$test_time;

            //Ð¿Ñ€Ð¸Ð¼ÐµÑ€ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ð½Ð¸Ñ Ð°Ð³Ñ€ÐµÐ³Ð°Ñ‚Ð½Ñ‹Ñ… Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¹!!!
            $test = Result::max('id_result');

            //ÑÐ¾Ð·Ð´Ð°ÐµÐ¼ ÑÑ‚Ñ€Ð¾ÐºÑƒ Ð² Ñ‚Ð°Ð±Ð»Ð¸Ñ†Ðµ Ð¿Ñ€Ð¾Ð¹Ð´ÐµÐ½Ð½Ñ‹Ñ… Ñ‚ÐµÑÑ‚Ð¾Ð²
            $result_id = $test+1;

            $query2 = $user->whereEmail(Auth::user()['email'])->select('id')->first();
            $result->id_result = $result_id;
            $result->id = $query2->id;
            $result->id_test = $id_test;
            $result->result_date = date('Y-m-d H:i:s', $int_end_time);
            $saved_test = serialize($saved_test);
            $result->saved_test = $saved_test;
            $result->save();
        }
        else {
            //ÐµÑÐ»Ð¸ Ð±Ñ‹Ð»Ð° Ð¿ÐµÑ€ÐµÐ·Ð°Ð³Ñ€ÑƒÐ¶ÐµÐ½Ð° ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ð° Ñ‚ÐµÑÑ‚Ð° Ð¸Ð»Ð¸ Ñ‚ÐµÑÑ‚ Ð±Ñ‹Ð» Ð¿Ð¾ÐºÐ¸Ð½ÑƒÑ‚

            // ÐŸÐ¾Ð»ÑƒÑ‡Ð¸Ð»Ð¸ Ð¸Ð· Ñ‚Ð°Ð±Ð»Ð¸Ñ†Ñ‹ results ÑÑ‚Ñ€Ð¾ÐºÑƒ Ñ Ð¸Ð´ÐµÐ½Ñ‚Ð¸Ñ„Ð¸ÐºÐ°Ñ‚Ð¾Ñ€Ð¾Ð¼ Ñ€ÐµÐ·ÑƒÐ»ÑŒÑ‚Ð°Ñ‚Ð° $result_id.
            $test = $result->whereId_result($result_id)->first();

            //Ð²Ñ€ÐµÐ¼Ñ Ð¾ÐºÐ¾Ð½Ñ‡Ð°Ð½Ð¸Ñ Ñ‚ÐµÑÑ‚Ð°
            $int_end_time = strtotime($test->result_date);

            $saved_test = $test->saved_test;
            $saved_test = unserialize($saved_test);
            if (!is_array($saved_test) || count($saved_test) < $amount) {
                TestTask::whereId_result($result_id)->delete();
                Result::whereId_result($result_id)->delete();
                return redirect()->route('question_showtest', ['id_test' => $id_test]);
            }
            for ($i=0; $i<$amount; $i++){
                //Log::debug($saved_test[$i]);
                try {
                    $widget = View::make($saved_test[$i]['view'], $saved_test[$i]['arguments']);
                    $widget->render();
                    $widgets[] = $widget;
                } catch (\Exception $e) {
                    Log::warning('Saved test render failed; regenerating', ['id_test' => $id_test, 'id_result' => $result_id, 'error' => $e->getMessage()]);
                    TestTask::whereId_result($result_id)->delete();
                    Result::whereId_result($result_id)->delete();
                    return redirect()->route('question_showtest', ['id_test' => $id_test]);
                }
            }
        }

        //Ñ‚ÐµÐºÑƒÑ‰ÐµÐµ Ð²Ñ€ÐµÐ¼Ñ
        $current_time = date_create();

        //Ð¾ÑÑ‚Ð°Ð²ÑˆÐµÐµÑÑ Ð²Ñ€ÐµÐ¼Ñ
        $int_left_time = $int_end_time - date_format($current_time, 'U');

        //Ð¾ÑÑ‚Ð°Ð»Ð¾ÑÑŒ Ð¼Ð¸Ð½ÑƒÑ‚
        $left_min =  ($int_left_time > 0) ? floor($int_left_time/60) : 0;

        //Ð¾ÑÑ‚Ð°Ð»Ð¾ÑÑŒ ÑÐµÐºÑƒÐ½Ð´
        $left_sec = ($int_left_time > 0) ? $int_left_time % 60 : 0;

        $widgetListView = View::make('questions.student.widget_list',compact('amount', 'id_test','left_min', 'left_sec', 'test_type', 'result_id'))->with('widgets', $widgets);
        $response = new Response($widgetListView);
        return $response;
    }

    public function virtualStudent($id_test) {
        // [0; 100]
        $knowledge_level = 60;

        $result = new Result();
        $user = new User();
        $question = new Question();
        $saved_test = [];

        // Ð˜Ð½Ñ„Ð¾Ñ€Ð¼Ð°Ñ†Ð¸Ñ Ð¾ Ñ‚ÐµÑÑ‚Ðµ (Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ Ð¸ Ñ‚.Ð´.)
        $test = $this->test->whereId_test($id_test)->first();

        // ÐšÐ¾Ð»Ð¸Ñ‡ÐµÑÑ‚Ð²Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð²
        $amount = $this->test->getAmount($id_test);

        // Ð’Ñ€ÐµÐ¼Ñ Ð¿Ñ€Ð¾Ñ…Ð¾Ð¶Ð´ÐµÐ½Ð¸Ñ
        $test_time = $test->test_time;

        // Ð˜Ð´ÐµÐ½Ñ‚Ð¸Ñ„Ð¸ÐºÐ°Ñ‚Ð¾Ñ€ Ñ€ÐµÐ·ÑƒÐ»ÑŒÑ‚Ð°Ñ‚Ð° ÑƒÐ¶Ðµ Ð·Ð°Ð¿ÑƒÑ‰ÐµÐ½Ð½Ð¾Ð³Ð¾ Ñ‚ÐµÑÑ‚Ð° Ð¸Ð»Ð¸ Ð¶Ðµ -1
        $result_id = Result::getCurrentResult(Auth::user()['id'], $id_test);

        $generator = new UsualTestGenerator();
        $generator->generate($test);
        $question_ids = [];
        for ($i=0; $i < $amount; $i++) {
            $id = $generator->chooseQuestion();

            //Ð´Ð¾Ð»Ð¶Ð½Ñ‹ Ð¿Ð¾Ð»ÑƒÑ‡Ð°Ñ‚ÑŒ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ view Ð¸ Ð½ÐµÐ¾Ð±Ñ…Ð¾Ð´Ð¸Ð¼Ñ‹Ðµ Ð¿Ð°Ñ€Ð°Ð¼ÐµÑ‚Ñ€Ñ‹
            $data = $question->show($id, $i+1, false);

            $saved_test[] = $data;

            $question_ids[] = $id;
        }

        //Ð²Ñ€ÐµÐ¼Ñ ÐºÐ¾Ð½Ñ†Ð°
        $int_end_time =  date('U') + 60*$test_time;

        //Ð¿Ñ€Ð¸Ð¼ÐµÑ€ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ð½Ð¸Ñ Ð°Ð³Ñ€ÐµÐ³Ð°Ñ‚Ð½Ñ‹Ñ… Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¹!!!
        $test = Result::max('id_result');

        //ÑÐ¾Ð·Ð´Ð°ÐµÐ¼ ÑÑ‚Ñ€Ð¾ÐºÑƒ Ð² Ñ‚Ð°Ð±Ð»Ð¸Ñ†Ðµ Ð¿Ñ€Ð¾Ð¹Ð´ÐµÐ½Ð½Ñ‹Ñ… Ñ‚ÐµÑÑ‚Ð¾Ð²
        $result_id = $test+1;

        $query2 = $user->whereEmail(Auth::user()['email'])->select('id')->first();
        $result->id_result = $result_id;
        $result->id = $query2->id;
        $result->id_test = $id_test;
        $result->result_date = date('Y-m-d H:i:s', $int_end_time);
        $saved_test = serialize($saved_test);
        $result->saved_test = $saved_test;
        $result->save();

        // check test

        $userId = Auth::user()['id'];
        // id ÐºÐ¾Ð½Ñ‚Ñ€Ð¾Ð»ÑŒÐ½Ð¾Ð¹
        // $id_test = $request->input('id_test');
        
        // ÐŸÐ¾Ð»ÑƒÑ‡Ð°ÐµÐ¼ Ð½Ð°Ñ‡Ð°Ñ‚ÑƒÑŽ Ð¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ñ‚ÐµÐ»ÐµÐ¼ Ð¿Ð¾Ð¿Ñ‹Ñ‚ÐºÑƒ Ð¿Ñ€Ð¾Ñ…Ð¾Ð¶Ð´ÐµÐ½Ð¸Ñ ÐºÐ¾Ð½Ñ‚Ñ€Ð¾Ð»ÑŒÐ½Ð¾Ð¹ Ñ id = $id_test
        $current_test = Result::getCurrentResult($userId, $id_test);
        // if ($current_test == -1) {
        //     return redirect('tests');
        // }
        // Ð§Ð¸ÑÐ»Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð².
        // $amount = $request->input('amount');
        // if ($amount < 2) {
        //     return "Error. Too few questions";
        // }
        //ÑÑƒÐ¼Ð¼Ð° Ð½Ð°Ð±Ñ€Ð°Ð½Ð½Ñ‹Ñ… Ð±Ð°Ð»Ð»Ð¾Ð²
        $score_sum = 0;
        //ÑÑƒÐ¼Ð¼Ð° Ð¼Ð°ÐºÑÐ¸Ð¼Ð°Ð»ÑŒÐ½Ð¾ Ð²Ð¾Ð·Ð¼Ð¾Ð¶Ð½Ñ‹Ñ… Ð±Ð°Ð»Ð»Ð¾Ð²
        $points_sum = 0;
        //Ð·Ð°Ð¿Ð¾Ð¼Ð¸Ð½Ð°ÐµÐ¼ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ñ‹Ðµ Ð²Ð°Ñ€Ð¸Ð°Ð½Ñ‚Ñ‹ Ð¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ñ‚ÐµÐ»Ñ
        $choice = [];
        //ÐŸÑ€Ð¾Ñ†ÐµÐ½Ñ‚ Ð¿Ñ€Ð°Ð²Ð¸Ð»ÑŒÐ½Ð¾ÑÑ‚Ð¸ Ð¾Ñ‚Ð²ÐµÑ‚Ð° Ð½Ð° Ð½ÐµÐ²ÐµÑ€Ð½Ñ‹Ð¹ Ð²Ð¾Ð¿Ñ€Ð¾Ñ
        $right_percent = [];
        $j = 1;
        $question = new Question();

        $query = $this->test->whereId_test($id_test)->select('total', 'test_name', 'test_type')->first();
        $total = $query->total;
        $test_type = $query->test_type;

        $id_user = Result::whereId_result($current_test)
            ->join('users', 'results.id', '=', 'users.id')->select('users.id')->first()->id;

        //Ð¾Ð±Ñ€Ð°Ð±Ð°Ñ‚Ñ‹Ð²Ð°ÐµÐ¼ ÐºÐ°Ð¶Ð´Ñ‹Ð¹ Ð²Ð¾Ð¿Ñ€Ð¾Ñ
        for ($i=0; $i<$amount; $i++) {
            // $data = $request->input($i);

            // $array = json_decode($data);
            $should_be_right = rand(0, 100) < $knowledge_level;

            // $data = $request->input('0'
            $id_question = $question_ids[$i];
            $answer = Question::getAnswer($id_question);
            Log::debug('ÐžÑ‚Ð²ÐµÑ‚ Ð½Ð° Ð²Ð¾Ð¿Ñ€Ð¾Ñ ' . $id_question);
            Log::debug($answer);
            Log::debug(gettype($answer));
            if ($should_be_right) {
                $answer = Question::getAnswer($id_question);
            } else {
                $answer = '';
            }
            $array = array_merge([$id_question], explode(';', $answer));

            $link_to_lecture[$j] = $question->linkToLecture($array[0]);

            // get type of test
            $id_question = $array[0];
            $query1 = Question::whereId_question($id_question)->select('answer','points', 'type_code')->first();
            $type_name = Type::whereType_code($query1['type_code'])->select('type_name')->first()->type_name;

            if ($type_name == 'Ð­Ð¼ÑƒÐ»ÑÑ‚Ð¾Ñ€ Ð¢ÑŒÑŽÑ€Ð¸Ð½Ð³Ð°' || $type_name == 'Ð­Ð¼ÑƒÐ»ÑÑ‚Ð¾Ñ€ ÐœÐ°Ñ€ÐºÐ¾Ð²Ð°') {
                /* Get current saved test */
                $test = Result::whereId_result($current_test)->first();
                $saved_test = $test->saved_test;
                $saved_test = unserialize($saved_test);

                $arguments = $saved_test[$i]['arguments'];
                $debug_counter = $arguments['debug_counter'];
                $solution = $array[2];
                $should_increment_debug_counter = false;
                $check_syntax_counter = $arguments['check_syntax_counter'];
                $run_counter = $arguments['run_counter'];
                $data = $question->check([$id_question, $debug_counter, $check_syntax_counter, $run_counter,
                    $should_increment_debug_counter, $solution]);
            } else {
                $data = $question->check($array);
            }
            $right_or_wrong[$j] = $data['mark'];
            $choice[$j] = $data['choice'];
            $right_percent[$j] = $data['right_percent'];
            TestTask::insert(['points' => $data['score'], 'id_question' => $array[0], 'id_result' => $current_test]);
            $j++;
            $score_sum += $data['score'];                                                                               //ÑÑƒÐ¼Ð¼Ð° Ð½Ð°Ð±Ñ€Ð°Ð½Ð½Ñ‹Ñ… Ð±Ð°Ð»Ð»Ð¾Ð²
            $points_sum += $data['points'];                                                                             //ÑÑƒÐ¼Ð¼Ð° Ð¼Ð°ÐºÑÐ¸Ð¼Ð°Ð»ÑŒÐ½Ð¾ Ð²Ð¾Ð·Ð¼Ð¾Ð¶Ð½Ñ‹Ñ… Ð±Ð°Ð»Ð»Ð¾Ð²
        }
        if ($points_sum != 0){
            $score = $total*$score_sum/$points_sum;
            $score = round($score,1);
        }
        else $score = $total;

        $fineValue = Fine::whereId_test($id_test)->whereId($id_user)->select('fine')->first();
        $fineLevel = is_null($fineValue) ? 0 : $fineValue->fine;
        $fine = Fine::countFactor($fineLevel);      //ÑƒÑ‡Ð¸Ñ‚Ñ‹Ð²Ð°ÐµÐ¼ ÑˆÑ‚Ñ€Ð°Ñ„, ÐµÑÐ»Ð¸ Ð¾Ð½ ÐµÑÑ‚ÑŒ
        $score = $score * $fine;

        $mark_bologna = $this->test->calcMarkBologna($total, $score);                                                         //Ð¾Ñ†ÐµÐ½ÐºÐ¸
        $mark_rus = $this->test->calcMarkRus($total, $score);

        $result = new Result();
        $date = date('Y-m-d H:i:s', time());                                                                            //Ñ‚ÐµÐºÑƒÑ‰ÐµÐµ Ð²Ñ€ÐµÐ¼Ñ
        $widgets = [];
        $query = $result->whereId_result($current_test)->first();                                                       //Ð±ÐµÑ€ÐµÐ¼ ÑÐ¾Ñ…Ñ€Ð°Ð½ÐµÐ½Ð½Ñ‹Ð¹ Ñ‚ÐµÑÑ‚ Ð¸Ð· Ð‘Ð”
        $saved_test = $query->saved_test;
        $saved_test = unserialize($saved_test);

        for ($i=0; $i<$amount; $i++){
            $widgets[] = View::make($saved_test[$i]['view'].'T', $saved_test[$i]['arguments'])->with('choice', $choice[$i+1]);
        }

        if ($test_type != 'Тренировочный'){
            $widgetListView = View::make('tests.ctrresults',compact('total','score','right_or_wrong', 'mark_bologna', 'mark_rus', 'right_percent', 'id_test', 'id_user'))->with('widgets', $widgets);
            $fine = new Fine();
            $fine->updateFine(Auth::user()['id'], $id_test, $mark_rus);                                                 //Ð²Ð½Ð¾ÑÐ¸Ð¼ Ð² Ñ‚Ð°Ð±Ð»Ð¸Ñ†Ñƒ ÑˆÑ‚Ñ€Ð°Ñ„Ð¾Ð² Ð½ÐµÐ¾Ð±Ñ…Ð¾Ð´Ð¸Ð¼ÑƒÑŽ Ð¸Ð½Ñ„Ñƒ
            $fraction_score = $score / $total;
            Test::addToStatements($id_test, $id_user, $fraction_score);                                                          //Ð·Ð°Ð½ÐµÑÐµÐ½Ð¸Ðµ Ð±Ð°Ð»Ð»Ð° Ð² Ð²ÐµÐ´Ð¾Ð¼Ð¾ÑÑ‚ÑŒ
            // $screenshot = $request->input('screenshot');
            // $this->saveTestScreenshot($screenshot, $userId, $id_test);
        } else {                                                                                                          //Ñ‚ÐµÑÑ‚ Ñ‚Ñ€ÐµÐ½Ð¸Ñ€Ð¾Ð²Ð¾Ñ‡Ð½Ñ‹Ð¹
            $widgetListView = View::make('questions.student.training_test',compact('total','score','right_or_wrong', 'mark_bologna', 'mark_rus', 'right_percent', 'link_to_lecture'))->with('widgets', $widgets);
        }
        $result->whereId_result($current_test)->update(['result_date' => $date, 'result' => $score, 'mark_ru' => $mark_rus, 'mark_eu' => $mark_bologna]);

        // === УВЕДОМЛЕНИЕ: результат теста ===
        try {
            $testNameObj = $this->test->whereId_test($id_test)->select('test_name', 'test_type')->first();
            $testLabel = $testNameObj ? $testNameObj->test_name : 'Тест';
            $typeLabel = ($testNameObj && $testNameObj->test_type !== 'Тренировочный') ? 'Контрольный тест' : 'Тренировочный тест';
            NotificationService::send(
                $id_user,
                'test_result',
                'Результат теста',
                $typeLabel . ' «' . $testLabel . '» завершён. Оценка: ' . $mark_rus . ' (' . $mark_bologna . ').',
                ['test_id' => $id_test, 'url' => route('personal_account')]
            );
        } catch (\Exception $ne) {
            Log::warning('Notification send failed: ' . $ne->getMessage());
        }

        return $widgetListView;
    }

    private function saveTestScreenshot($pngBase64Screenshot, $userId, $testId) {
        $img = $pngBase64Screenshot;
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $fileData = base64_decode($img);
        //saving
        $dir = storage_path('app/public/screenshots/tests/' . $userId);
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Unable to create screenshot directory.');
            }
        }
        $today =  date("Y-m-d H-i-s");
        $fileName = $dir . '/' . $testId . '_' . $today . '.png';
        file_put_contents($fileName, $fileData);
    }

    /** ÐŸÑ€Ð¾Ð²ÐµÑ€ÐºÐ° ÐºÐ¾Ð½Ñ‚Ñ€Ð¾Ð»ÑŒÐ½Ð¾Ð¹ (Ñ‚ÐµÑÑ‚Ð°) */
    /** Проверка контрольной (теста) */
    public function checkTest(Request $request) {
        $userId = Auth::user()['id'];
        $id_test = $request->input('id_test');
        $current_test = Result::getCurrentResult($userId, $id_test);
        if ($current_test == -1) {
            return redirect()->route('control_tests');
        }

        $amount = (int) $request->input('amount');
        if ($amount < 1) {
            return redirect()->route('question_showtest', ['id_test' => $id_test]);
        }

        $test = $this->test->whereId_test($id_test)->select('total', 'test_name', 'test_type')->first();
        $resultRow = Result::whereId_result($current_test)->first();
        if (!$test || !$resultRow) {
            return redirect('tests');
        }

        $saved_test = @unserialize($resultRow->saved_test);
        if (!is_array($saved_test) || count($saved_test) == 0) {
            TestTask::whereId_result($current_test)->delete();
            Result::whereId_result($current_test)->delete();
            return redirect()->route('question_showtest', ['id_test' => $id_test]);
        }
        $amount = min($amount, count($saved_test));

        $total = $test->total;
        $test_type = $test->test_type;
        $id_user = $resultRow->id;
        $score_sum = 0;
        $points_sum = 0;
        $choice = [];
        $right_percent = [];
        $right_or_wrong = [];
        $link_to_lecture = [];
        $question = new Question();
        $j = 1;

        TestTask::whereId_result($current_test)->delete();

        for ($i = 0; $i < $amount; $i++) {
            $raw = $request->input($i);
            $array = json_decode($raw, true);
            if (!is_array($array)) {
                $array = [];
            }
            if (!isset($array[0]) && isset($saved_test[$i]['arguments']['id'])) {
                $array = [$saved_test[$i]['arguments']['id']];
            }

            $id_question = isset($array[0]) ? $array[0] : null;
            $query1 = $id_question ? Question::whereId_question($id_question)->select('answer','points', 'type_code')->first() : null;
            if (!$query1) {
                Log::warning('Check test skipped missing question', ['id_test' => $id_test, 'id_result' => $current_test, 'index' => $i, 'id_question' => $id_question]);
                $right_or_wrong[$j] = 'Неверно';
                $choice[$j] = [];
                $right_percent[$j] = 0;
                $j++;
                continue;
            }

            try {
                $link_to_lecture[$j] = $question->linkToLecture($id_question);
            } catch (\Exception $e) {
                $link_to_lecture[$j] = [];
            }

            $type = Type::whereType_code($query1['type_code'])->select('type_name')->first();
            $type_name = $type ? $type->type_name : '';

            try {
                if ($type_name == 'Эмулятор Тьюринга' || $type_name == 'Эмулятор Маркова') {
                    $arguments = isset($saved_test[$i]['arguments']) && is_array($saved_test[$i]['arguments']) ? $saved_test[$i]['arguments'] : [];
                    $debug_counter = isset($arguments['debug_counter']) ? $arguments['debug_counter'] : 0;
                    $check_syntax_counter = isset($arguments['check_syntax_counter']) ? $arguments['check_syntax_counter'] : 0;
                    $run_counter = isset($arguments['run_counter']) ? $arguments['run_counter'] : 0;
                    $solution = isset($array[2]) ? $array[2] : '';
                    $data = $question->check([$id_question, $debug_counter, $check_syntax_counter, $run_counter, false, $solution]);
                } else {
                    $data = $question->check($array);
                }
            } catch (\Exception $e) {
                Log::warning('Check test question failed', ['id_test' => $id_test, 'id_result' => $current_test, 'id_question' => $id_question, 'error' => $e->getMessage()]);
                $data = ['mark' => 'Неверно', 'score' => 0, 'points' => $query1->points, 'choice' => [], 'right_percent' => 0];
            }

            $right_or_wrong[$j] = isset($data['mark']) ? $data['mark'] : 'Неверно';
            $choice[$j] = isset($data['choice']) ? $data['choice'] : [];
            $right_percent[$j] = isset($data['right_percent']) ? $data['right_percent'] : 0;
            TestTask::insert(['points' => isset($data['score']) ? $data['score'] : 0, 'id_question' => $id_question, 'id_result' => $current_test]);
            $j++;
            $score_sum += isset($data['score']) ? $data['score'] : 0;
            $points_sum += isset($data['points']) ? $data['points'] : 0;
        }

        if ($points_sum != 0) {
            $score = round($total * $score_sum / $points_sum, 1);
        } else {
            $score = 0;
        }

        $fineValue = Fine::whereId_test($id_test)->whereId($id_user)->select('fine')->first();
        $fineLevel = is_null($fineValue) ? 0 : $fineValue->fine;
        $score = $score * Fine::countFactor($fineLevel);

        $mark_bologna = $this->test->calcMarkBologna($total, $score);
        $mark_rus = $this->test->calcMarkRus($total, $score);

        $date = date('Y-m-d H:i:s', time());
        $widgets = [];
        for ($i = 0; $i < $amount; $i++) {
            if (!isset($saved_test[$i]['view']) || !isset($saved_test[$i]['arguments']) || !is_array($saved_test[$i]['arguments'])) {
                continue;
            }
            $widgets[] = View::make($saved_test[$i]['view'].'T', $saved_test[$i]['arguments'])->with('choice', isset($choice[$i + 1]) ? $choice[$i + 1] : []);
        }

        if ($test_type != 'Тренировочный') {
            $widgetListView = View::make('tests.ctrresults', compact('total','score','right_or_wrong', 'mark_bologna', 'mark_rus', 'right_percent', 'id_test', 'id_user'))->with('widgets', $widgets);
            $fine = new Fine();
            $fine->updateFine(Auth::user()['id'], $id_test, $mark_rus);
            $fraction_score = $total ? $score / $total : 0;
            Test::addToStatements($id_test, $id_user, $fraction_score);
            $screenshot = $request->input('screenshot');
            if (!empty($screenshot)) {
                $this->saveTestScreenshot($screenshot, $userId, $id_test);
            }
        } else {
            $widgetListView = View::make('questions.student.training_test', compact('total','score','right_or_wrong', 'mark_bologna', 'mark_rus', 'right_percent', 'link_to_lecture'))->with('widgets', $widgets);
        }

        Result::whereId_result($current_test)->update(['result_date' => $date, 'result' => $score, 'mark_ru' => $mark_rus, 'mark_eu' => $mark_bologna]);
        return $widgetListView;
    }

    public function dropTest(Request $request){
        $current_result = Result::getCurrentResult(Auth::user()['id'], $request->input('id_test'));
        if ($current_result != -1) {
            date_default_timezone_set('Europe/Moscow');
            $date = date('Y-m-d H:i:s', time());
            Result::whereId_result($current_result)->update(['result_date' => $date, 'result' => -1, 'mark_ru' => -1, 'mark_eu' => 'drop']);                                 //ÐŸÑ€Ð¸ÑÐ²Ð°Ð¸Ð²Ð°ÐµÐ¼ Ñ€ÐµÐ·ÑƒÐ»ÑŒÑ‚Ð°Ñ‚Ñƒ Ð¸ Ð¾Ñ†ÐµÐ½ÐºÐµ Ð·Ð½Ð°Ñ‡ÐµÐ½Ð¸Ñ -1
        }
        return redirect('home');
    }
    
    /* ÐžÑ‚Ð¾Ð±Ñ€Ð°Ð¶ÐµÐ½Ð¸Ðµ ÑÐ¾ÑÑ‚Ð¾ÑÐ½Ð¸Ñ Ñ‚ÐµÑÑ‚Ð¾Ð² */
    public function monitor(){
        return view('tests.monitor', [
            'structures' => [],
            'test' => [
                'id_test' => 0,
                'test_type' => '',
                'is_resolved' => 0,
            ],
            'group' => [
                'id_group' => 0,
            ],
        ]);
    }

    public function chooseGroup() {
        $groups = Group::whereArchived(0)->get(['group_id', 'group_name']);
        return view('tests.groups_for_test_list', compact('groups'));
    }
    public function archive($id_test)
    {
        if (ControlWorkPlan::where('id_test', $id_test)->exists()) {
            return redirect()->back()->withErrors([
                'Тест используется в учебном плане. Сначала выберите для этого пункта другой тест.'
            ]);
        }

        Test::whereId_test($id_test)->update(['archived' => 1]);
        TestForGroup::whereId_test($id_test)->update(['availability' => 0]);

        return redirect()->back()->with('success', 'Ð¢ÐµÑÑ‚ ÑƒÑÐ¿ÐµÑˆÐ½Ð¾ Ð¾Ñ‚Ð¿Ñ€Ð°Ð²Ð»ÐµÐ½ Ð² Ð°Ñ€Ñ…Ð¸Ð²');
    }

    public function restore($id_test)
    {
        Test::whereId_test($id_test)->update(['archived' => 0]);

        return redirect()->back()->with('success', 'Тест восстановлен из архива. Настройте его видимость и доступность для групп.');
    }
}
