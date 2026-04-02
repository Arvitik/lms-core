<?php
/**
 * Created by PhpStorm.
 * User: Ð¡Ñ‚Ð°Ð½Ð¸ÑÐ»Ð°Ð²
 * Date: 05.04.15
 * Time: 16:15
 */
namespace App\Http\Controllers;

use App\Testing\Qtypes\FromCleene;
use App\Testing\Qtypes\QuestionType;
use App\Testing\Qtypes\QuestionTypeFactory;
use App\Testing\Qtypes\Theorem;
use App\Testing\Qtypes\TheoremLike;
use App\Testing\Section;
use App\Testing\Test;
use App\Testing\TestTask;
use App\Testing\Type;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Testing\Question;
use App\Testing\Theme;
use App\Testing\Qtypes\OneChoice;
use App\Testing\Qtypes\MultiChoice;
use App\Testing\Qtypes\FillGaps;
use App\Testing\Qtypes\AccordanceTable;
use App\Testing\Qtypes\YesNo;
use App\Testing\Qtypes\Definition;
use App\Testing\Qtypes\JustAnswer;
use App\Testing\Qtypes\ThreePoints;
use App\Testing\Qtypes\Ram;
use App\Testing\Qtypes\Post;
use App\Testing\TestGeneration\UsualTestGenerator;
use View;

class QuestionController extends Controller {
    private $question;
    function __construct(Question $question){
        $this->question=$question;
    }

    public function index(){
        return redirect()->route('questions_list');
    }

    /** Ð¿ÐµÑ€ÐµÑ…Ð¾Ð´ Ð½Ð° ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñƒ Ñ„Ð¾Ñ€Ð¼Ñ‹ Ð´Ð¾Ð±Ð°Ð²Ð»ÐµÐ½Ð¸Ñ */
    public function create(){
        $types = Type::all();
        return view('questions.teacher.create', compact('types'));
    }

    /** AJAX-Ð¼ÐµÑ‚Ð¾Ð´: Ð¿Ð¾Ð´Ð³Ñ€ÑƒÐ¶Ð°ÐµÑ‚ Ð¸Ð½Ñ‚ÐµÑ€Ñ„ÐµÐ¹Ñ ÑÐ¾Ð·Ð´Ð°Ð½Ð¸Ñ Ð½Ð¾Ð²Ð¾Ð³Ð¾ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ° Ð² Ð·Ð°Ð²Ð¸ÑÐ¸Ð¼Ð¾ÑÑ‚Ð¸ Ð¾Ñ‚ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ð¾Ð³Ð¾ Ñ‚Ð¸Ð¿Ð° Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ° */
    public function getType(Request $request){
        if ($request->ajax()){
            $type = $request->input('choice');
            $typeRow = Type::whereType_name($type)->select('type_code')->first();
            if (!$typeRow) {
                return '';
            }
            $type_code = $typeRow->type_code;
            $sections = Section::all();
            return (String) view(QuestionType::CREATE_VIEW_PREFIX . $type_code, compact('sections'));
        }
    }

    /** ÐžÐ±Ñ€Ð°Ð±Ð¾Ñ‚ÐºÐ° Ñ„Ð¾Ñ€Ð¼Ñ‹ Ð´Ð¾Ð±Ð°Ð²Ð»ÐµÐ½Ð¸Ñ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ° */
    public function add(Request $request){
        $type = $request->input('type');
        $query = Question::max('id_question');                                                                          //Ð¿Ñ€Ð¸Ð¼ÐµÑ€ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ð½Ð¸Ñ Ð°Ð³Ñ€ÐµÐ³Ð°Ñ‚Ð½Ñ‹Ñ… Ñ„ÑƒÐ½ÐºÑ†Ð¸Ð¹!!!
        $id = $query+1;
        $question = QuestionTypeFactory::getQuestionTypeByTypeName($id, $type);
        $question->add($request);
        return redirect()->route('question_create');
    }

    /** AJAX-Ð¼ÐµÑ‚Ð¾Ð´: Ð¤Ð¾Ñ€Ð¼Ð¸Ñ€ÑƒÐµÑ‚ ÑÐ¿Ð¸ÑÐ¾Ðº Ñ‚ÐµÐ¼, ÑÐ¾Ð¾Ñ‚Ð²ÐµÑ‚ÑÑ‚Ð²ÑƒÑŽÑ‰Ð¸Ñ… Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ð¾Ð¼Ñƒ Ñ€Ð°Ð·Ð´ÐµÐ»Ñƒ */
    public function getTheme(Request $request){
        if ($request->ajax()) {
            $section = $request->input('choice');
            if ($section !== 'Ð’ÑÐµ') {
                $sectionRow = Section::whereSection_name($section)->select('section_code')->first();
                $themes_list = $sectionRow ? Theme::whereSection_code($sectionRow->section_code)->select('theme_name')->get() : collect();
            } else {
                $themes_list = null;
            }
            return (String) view('questions.student.getTheme', compact('themes_list'));
        }
    }

    /** Ð¡Ð¿Ð¸ÑÐ¾Ðº Ð²ÑÐµÑ… Ð´Ð¾ÑÑ‚ÑƒÐ¿Ð½Ñ‹Ñ… Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ¾Ð² */
    public function editList(){
        $questions = Question::where('section_code', '>', 0)
                                ->where('section_code', '<', 20)
                                ->where('theme_code', '>', 0)
                                ->where('theme_code', '<', 30);
        $questions = $questions->paginate(10);
        $widgets = [];
        foreach ($questions as $question){
            $data = $this->question->show($question['id_question'], '', false);
            $widgets[] =  View::make($data['view'], $data['arguments']);
            $question['section'] = Section::whereSection_code($question['section_code'])->select('section_name')
                                    ->first()->section_name;
            $question['theme'] = Theme::whereTheme_code($question['theme_code'])->select('theme_name')
                                    ->first()->theme_name;
            $question['type'] = Type::whereType_code($question['type_code'])->select('type_name')
                                    ->first()->type_name;
        }
        $sections = Section::where('section_code', '>', 0)->select('section_name')->get();
        $themes = Theme::where('theme_code', '>', 0)->select('theme_name')->get();
        $types = Type::where('type_code', '>', 0)->select('type_name')->get();
        $filter_section = 'Ð’ÑÐµ';
        $filter_theme = null;
        $filter_type = 'Ð’ÑÐµ';
        $filter_query = '';
        $widgetListView = View::make('questions.teacher.question_list', compact('questions', 'sections', 'themes', 'types', 'filter_section', 'filter_theme', 'filter_type', 'filter_query'))->with('widgets', $widgets);
        $response = new Response($widgetListView);
        return $response;
    }

    /** ÐŸÐ¾Ð¸ÑÐº Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ° Ð¿Ð¾ Ñ‚ÐµÐºÑÑ‚Ñƒ, Ñ€Ð°Ð·Ð´ÐµÐ»Ñƒ, Ñ‚ÐµÐ¼Ðµ, Ñ‚Ð¸Ð¿Ñƒ */ 
    public function find(Request $request){
        $questions = new Question();
        $questions = $questions->where('section_code', '>', 0)
                        ->where('section_code', '<', 20)
                        ->where('theme_code', '>', 0)
                        ->where('theme_code', '<', 30);
        if ($request->input('section') != 'Ð’ÑÐµ'){
            $section = Section::whereSection_name($request->input('section'))->select('section_code')->first();
            if ($section) {
                $questions = $questions->whereSection_code($section->section_code);
            }
            if ($request->input('theme') != '$nbsp'){
                $theme = Theme::whereTheme_name($request->input('theme'))->select('theme_code')->first();
                if ($theme) {
                    $questions = $questions->whereTheme_code($theme->theme_code);
                }
            }
        }
        if ($request->input('type') != 'Ð’ÑÐµ'){
            $type = Type::whereType_name($request->input('type'))->select('type_code')->first();
            if ($type) {
                $questions = $questions->whereType_code($type->type_code);
            }
        }
        if ($request->input('title') != ""){
            $questions = $questions->whereRaw('(`title` LIKE "%'.$request->input("title").'%" 
                                              or `variants` LIKE "%'.$request->input("title").'%"
                                              or `answer` LIKE "%'.$request->input("title").'%")');
        }
        $questions = $questions->paginate(10);
        
        $widgets = [];
        foreach ($questions as $question){
            $data = $this->question->show($question['id_question'], '', false);
            $widgets[] =  View::make($data['view'], $data['arguments']);
            $question['section'] = Section::whereSection_code($question['section_code'])->select('section_name')
                                    ->first()->section_name;
            $question['theme'] = Theme::whereTheme_code($question['theme_code'])->select('theme_name')
                                    ->first()->theme_name;
            $question['type'] = Type::whereType_code($question['type_code'])->select('type_name')
                                    ->first()->type_name;
        }
        $sections = Section::where('section_code', '>', 0)->select('section_name')->get();
        $themes = Theme::where('theme_code', '>', 0)->select('theme_name')->get();
        $types = Type::where('type_code', '>', 0)->select('type_name')->get();
        $filter_section = $request->input('section');
        $filter_theme = $request->input('theme');
        $filter_type = $request->input('type');
        $filter_query = $request->input('title');
        $widgetListView = View::make('questions.teacher.question_list', compact('questions',
            'sections', 'themes', 'types',
            'filter_section', 'filter_theme', 'filter_type', 'filter_query'))
            ->with('widgets', $widgets);
        $response = new Response($widgetListView);
        return $response;
    }

    public function profile($id_question) {
        $question = Question::whereId_question($id_question)->first();
        $data = $this->question->show($question['id_question'], '', false);
        $widget =  View::make($data['view'], $data['arguments']);
        $widgetListView = View::make('questions.teacher.profile', compact('question'))->with('widget', $widget);
        return new Response($widgetListView);
    }
    
    /** Ð¤Ð¾Ð¼Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ðµ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñ‹ Ñ€ÐµÐ´Ð°ÐºÑ‚Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ñ */
    public function edit($id_question){
        $question = Question::whereId_question($id_question)->select('type_code', 'section_code')->first();
        if (!$question) {
            return redirect()->route('questions_list');
        }
        $type_code = $question->type_code;
        $type_name = Type::whereType_code($type_code)->select('type_name')->first()->type_name;
        $themes = Theme::whereSection_code($question->section_code)->get();
        $sections = Section::all();

        $question = QuestionTypeFactory::getQuestionTypeByTypeName($id_question, $type_name);
        $data = $question->edit();
        $view_name = QuestionType::EDIT_VIEW_PREFIX . $question->type_code;
        return view($view_name, compact('data', 'sections', 'themes'));
    }

    public function update(Request $request) {
        $id_question = $request->input('id-question');
        $type_code = Question::whereId_question($id_question)->select('type_code')->first()->type_code;
        $type_name = Type::whereType_code($type_code)->select('type_name')->first()->type_name;

        $question = QuestionTypeFactory::getQuestionTypeByTypeName($id_question, $type_name);
        $question->update($request);
        return redirect()->route('questions_list');
    }

    /** Ð£Ð´Ð°Ð»ÐµÐ½Ð¸Ðµ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ° */
    public function delete(Request $request){
        if ($request->ajax()){
            TestTask::whereId_question($request->input('id_question'))->delete();
            Question::whereId_question($request->input('id_question'))->delete();
            return;
        }
    }
}
