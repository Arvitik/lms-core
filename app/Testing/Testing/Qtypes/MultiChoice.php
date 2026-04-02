<?php
/**
 * Created by PhpStorm.
 * User: Ð¡Ñ‚Ð°Ð½Ð¸ÑÐ»Ð°Ð²
 * Date: 30.05.15
 * Time: 15:49
 */

namespace App\Testing\Qtypes;
use App\Mypdf;
use App\Testing\Question;
use App\Testing\Type;
use Illuminate\Http\Request;
use Session;

class MultiChoice extends QuestionType implements Checkable {
    const type_code = 2;

    function __construct($id_question) {
        parent::__construct($id_question);
    }

    private function setAttributes(Request $request) {
        $options = $this->getOptions($request);
        $title = $this->getTitleWithImage($request);

        $variants = $request->input('variants')[0];                                                                     //Ñ„Ð¾Ñ€Ð¼Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ðµ Ð²Ð°Ñ€Ð¸Ð°Ð½Ñ‚Ð¾Ð²
        for ($i=1; $i<count($request->input('variants')); $i++){
            $variants = $variants.';'.$request->input('variants')[$i];
        }
        $eng_variants = $request->input('eng-variants')[0];
        for ($i=1; $i<count($request->input('eng-variants')); $i++){
            $eng_variants = $eng_variants.';'.$request->input('eng-variants')[$i];
        }

        $answers = '';
        $eng_answers = '';
        $flag = false;
        $j = 0;
        while ($flag != true && $j<count($request->input('answers'))){                                                  //Ñ„Ð¾Ñ€Ð¼Ð¸Ñ€Ð¾Ð²Ð°Ð½Ð¸Ðµ Ð¾Ñ‚Ð²ÐµÑ‚Ð¾Ð²
            if (isset($request->input('answers')[$j])){
                $answers = $request->input('variants')[$request->input('answers')[$j]-1];
                $eng_answers = $request->input('eng-variants')[$request->input('answers')[$j]-1];
                $j++;
                break;
            }
            $j++;
        }
        for ($i=$j; $i<count($request->input('answers')); $i++){
            if (isset($request->input('answers')[$i])){
                $answers = $answers.';'.$request->input('variants')[$request->input('answers')[$i]-1];
                $eng_answers = $eng_answers.';'.$request->input('eng-variants')[$request->input('answers')[$i]-1];
            }
        }
        return ['title' => $title['ru_title'], 'variants' => $variants,
                'answer' => $answers, 'points' => $options['points'], 'difficulty' => $options['difficulty'],
                'discriminant' => $options['discriminant'], 'guess' => $options['guess'],
                'pass_time' => $options['pass_time'],
                'control' => $options['control'], 'translated' => $options['translated'],
                'section_code' => $options['section'], 'theme_code' => $options['theme'], 'type_code' => $options['type'],
                'title_eng' => $title['eng_title'], 'variants_eng' => $eng_variants, 'answer_eng' => $eng_answers];
    }

    public function add(Request $request) {
        $data = $this->setAttributes($request);
        Question::insert(array('title' => $data['title'], 'variants' => $data['variants'],
            'answer' => $data['answer'], 'points' => $data['points'], 'difficulty' => $data['difficulty'],
            'discriminant' => $data['discriminant'], 'guess' => $data['guess'],
            'pass_time' => $data['pass_time'],
            'control' => $data['control'], 'translated' => $data['translated'],
            'section_code' => $data['section_code'], 'theme_code' => $data['theme_code'], 'type_code' => $data['type_code'],
            'title_eng' => $data['title_eng'], 'variants_eng' => $data['variants_eng'], 'answer_eng' => $data['answer_eng']));
    }

    public function edit() {
        $question = Question::whereId_question($this->id_question)->first();
        $count = count(explode(";", $question->variants));
        $type_name = Type::whereType_code($question->type_code)->select('type_name')->first()->type_name;
        $images = explode("::", $question->title);
        $variants = explode(";", $question->variants);

        $answers = explode(";", $question->answer);
        $j = 0;
        $num_answers = [];
        for ($i = 0; $i < count($variants); $i++) {
            if ($variants[$i] == $answers[$j]) {
                array_push($num_answers, $i);
                $j++;
            }
        }
        $eng_variants = explode(";", $question->variants_eng);
        return array('question' => $question, 'count' => $count, 'type_name' => $type_name,
            'images' => $images, 'variants' => $variants, 'eng_variants' => $eng_variants, 'num_answers' => $num_answers);
    }

    public function update(Request $request) {
        $data = $this->setAttributes($request);
        Question::whereId_question($this->id_question)->update(
            array('title' => $data['title'], 'variants' => $data['variants'],
                'answer' => $data['answer'], 'points' => $data['points'], 'difficulty' => $data['difficulty'],
                'discriminant' => $data['discriminant'], 'guess' => $data['guess'],
                'pass_time' => $data['pass_time'],
                'control' => $data['control'], 'translated' => $data['translated'],
                'section_code' => $data['section_code'], 'theme_code' => $data['theme_code'], 'type_code' => $data['type_code'],
                'title_eng' => $data['title_eng'], 'variants_eng' => $data['variants_eng'], 'answer_eng' => $data['answer_eng'])
        );
    }

    public function show($count) {
        $parse = $this->variants;
        $variants = explode(";", $parse);
        $new_variants = Question::mixVariants($variants);
        $view = 'tests.show2';
        $array = array('view' => $view, 'arguments' => array('text' => explode('::',$this->text), "variants" => $new_variants, "type" => self::type_code, "id" => $this->id_question, "count" => $count));
        return $array;
    }

    public function check($array) {
        $choices = $array;
        $answers = explode(';', $this->answer);
        $score = 0;
        $step = $this->points/count($answers);
        for ($i=0; $i<count($answers); $i++ ){                                                                          //ÑÑ€Ð°Ð²Ð½Ð¸Ð²Ð°ÐµÐ¼ ÐºÐ°Ð¶Ð´Ñ‹Ð¹ Ð¿Ñ€Ð°Ð²Ð¸Ð»ÑŒÐ½Ñ‹Ð¹ Ð¾Ñ‚Ð²ÐµÑ‚
            for ($j=0; $j<count($choices); $j++){                                                                       // Ñ ÐºÐ°Ð¶Ð´Ñ‹Ð¼ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ñ‹Ð¼
                if ($answers[$i] == $choices[$j]){
                    $buf = $choices[$j];
                    $choices[$j] = $choices[count($choices)-1];                                                         //Ð¼ÐµÐ½ÑÐµÐ¼ Ð¼ÐµÑÑ‚Ð°Ð¼Ð¸ Ð¿Ñ€Ð°Ð²Ð¸Ð»ÑŒÐ½Ñ‹Ð¹ Ð¾Ñ‚Ð²ÐµÑ‚ Ñ Ð¿Ð¾ÑÐ»ÐµÐ´Ð½Ð¸Ð¼ Ð´Ð»Ñ ÑƒÐ´Ð°Ð»ÐµÐ½Ð¸Ñ
                    $choices[count($choices)-1] =  $buf;
                    array_pop($choices);                                                                                //ÑƒÐ´Ð°Ð»ÑÐµÐ¼ Ð¿Ñ€Ð°Ð²Ð¸Ð»ÑŒÐ½Ñ‹Ð¹ Ð¿Ñ€Ð¾Ð²ÐµÑ€ÐµÐ½Ð½Ñ‹Ð¹ Ð²Ð°Ñ€Ð¸Ð°Ð½Ñ‚ Ð¸Ð· Ð¼Ð°ÑÑÐ¸Ð²Ð° Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ñ‹Ñ… Ð¾Ñ‚Ð²ÐµÑ‚Ð¾Ð²
                    $score += $step;
                    break;
                }
            }
        }
        if (!(empty($choices))){                                                                                        //ÐµÑÐ»Ð¸ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ñ‹ Ð»Ð¸ÑˆÐ½Ð¸Ðµ Ð²Ð°Ñ€Ð¸Ð°Ð½Ñ‚Ñ‹
            for ($i=0; $i<count($choices); $i++){
                $score -= $step;
            }
        }
        if ($score > $this->points){                                                                                    //ÐµÑÐ»Ð¸ Ð¿Ñ€Ð¸ Ð¾ÐºÑ€ÑƒÐ³Ð»ÐµÐ½Ð¸Ð¸ Ð¿Ð¾Ð»ÑƒÑ‡Ð¸Ð»Ð¾ÑÑŒ Ð±Ð¾Ð»ÑŒÑˆÐµ Ð¼Ð°ÐºÑÐ¸Ð¼Ð°Ð»ÑŒÐ½Ð¾Ð³Ð¾ Ñ‡Ð¸ÑÐ»Ð° Ð±Ð°Ð»Ð»Ð¾Ð²
            $score = $this->points;
        }
        if ($score < 0){                                                                                                //ÐµÑÐ»Ð¸ ÑƒÑˆÐµÐ» Ð² Ð¼Ð¸Ð½ÑƒÑ
            $score = 0;
        }
        $right_percent = round($score/$this->points*100);
        if (round($score,4) == $this->points){
            $data = array('mark'=>'Ð’ÐµÑ€Ð½Ð¾','score'=> $score, 'id' => $this->id_question, 'points' => $this->points, 'choice' => $array, 'right_percent' => $right_percent);
        }
        else $data = array('mark'=>'ÐÐµÐ²ÐµÑ€Ð½Ð¾','score'=> $score, 'id' => $this->id_question, 'points' => $this->points, 'choice' => $array, 'right_percent' => $right_percent);
        //echo $score.'<br>';
        return $data;
    }

    public function pdf(Mypdf $fpdf, $count, $answered=false, $paper_savings=false) {
        $text_parse = explode('::', $this->text);
        $text = "";
        for ($i=0; $i < count($text_parse); $i++){                                                                      //Ð¾Ð±Ñ€Ð°Ð±Ð¾Ñ‚ÐºÐ° ÐºÐ°Ñ€Ñ‚Ð¸Ð½ÐºÐ¸ Ð² Ñ‚ÐµÐºÑÑ‚Ðµ Ð²Ð¾Ð¿Ñ€Ð¾ÑÐ°
            if ($i % 2 == 1){
                $text_parse[$i] = '<img src="'.$text_parse[$i].'">';
            }
            $text .= $text_parse[$i];
        }

        $parse = $this->variants;
        $variants = explode(";", $parse);
        $html = '<table><tr><td style="text-decoration: underline; font-size: 130%;">Ð’Ð¾Ð¿Ñ€Ð¾Ñ '.$count;
        $html .= '  Ð’Ñ‹Ð±ÐµÑ€Ð¸Ñ‚Ðµ Ð¾Ð´Ð¸Ð½ Ð¸Ð»Ð¸ Ð½ÐµÑÐºÐ¾Ð»ÑŒÐºÐ¾ Ð²Ð°Ñ€Ð¸Ð°Ð½Ñ‚Ð¾Ð² Ð¾Ñ‚Ð²ÐµÑ‚Ð°</td></tr>';
        $html .= '<tr><td>'.$text.'</td></tr></table>';

        $html .= '<table border="1" style="border-collapse: collapse;" width="100%">';
        if ($answered){                                                                                                 // Ð¿Ð´Ñ„ Ñ Ð¾Ñ‚Ð²ÐµÑ‚Ð°Ð¼Ð¸
            $answers = explode(";", $this->answer);
            $new_variants = Session::get('saved_variants_order');
            for ($i = 0; $i < count($new_variants); $i++){                                                              // Ð¸Ð´ÐµÐ¼ Ð¿Ð¾ Ð²ÑÐµÐ¼ Ð²Ð°Ñ€Ð¸Ð°Ð½Ñ‚Ð°Ð¼
                $html .= '<tr>';
                for ($j = 0; $j < count($answers); $j++){                                                               // Ð¸Ð´ÐµÐ¼ Ð¿Ð¾ Ð²ÑÐµÐ¼ Ð¾Ñ‚Ð²ÐµÑ‚Ð°Ð¼
                    if ($answers[$j] == $new_variants[$i]){                                                             // ÐµÑÐ»Ð¸ Ð²Ð°Ñ€Ð¸Ð°Ð½Ñ‚ ÑÐ¾Ð²Ð¿Ð°Ð» Ñ Ð¾Ñ‚Ð²ÐµÑ‚Ð¾Ð¼
                        $html .= '<td width="5%" align="center">+</td><td width="80%">'.$new_variants[$i].'</td>';
                        break;
                    }
                    else{
                        if ($j == count($answers) - 1){                                                                 // Ð¿Ñ€Ð¾Ð²ÐµÑ€ÑÐµÐ¼, Ð½Ðµ Ð²ÑÐµ Ð»Ð¸ Ð¾Ñ‚Ð²ÐµÑ‚Ñ‹ Ð¿Ñ€Ð¾ÑÐ¼Ð¾Ñ‚Ñ€ÐµÐ½Ñ‹
                            $html .= '<td width="5%"></td><td width="80%">'.$new_variants[$i].'</td>';
                        }
                        else continue;                                                                                  // Ð¸Ð½Ð°Ñ‡Ðµ ÑÐ¼Ð¾Ñ‚Ñ€Ð¸Ð¼ ÑÐ»ÐµÐ´ÑƒÑŽÑ‰Ð¸Ð¹ Ð¾Ñ‚Ð²ÐµÑ‚
                    }
                    $html .= '</tr>';
                }
            }
            Session::forget('saved_variants_order');
        }
        else {                                                                                                          // Ð±ÐµÐ· Ð¾Ñ‚Ð²ÐµÑ‚Ð¾Ð²
            $new_variants = Question::mixVariants($variants);
            Session::put('saved_variants_order', $new_variants);
            foreach ($new_variants as $var){
                $html .= '<tr>';
                $html .= '<td width="5%"></td><td width="80%">'.$var.'</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</table><br>';
        $fpdf->WriteHTML($html);
    }

    public function evalGuess() {
        $variants_number = count(explode(";", $this->variants));
        $right_answers_number = count(explode(";", $this->answer));
        $min_right_answers = ceil(0.6 * $right_answers_number);
        $row_number = 1 << $variants_number;
        $right_sum = 0;
        for ($row = 1; $row < $row_number; $row++) {
            $splitted_binary_matrix_value = str_split(decbin($row));
            $first_column_after_left_leading_zeroes = $variants_number - count($splitted_binary_matrix_value);
            $row_sum = 0;
            $k = 0;
            for ($col = $first_column_after_left_leading_zeroes; $col < $variants_number; $col++) {
                if ($col < $right_answers_number) {
                    $row_sum += $splitted_binary_matrix_value[$k++];
                }
                else {
                    $row_sum -= $splitted_binary_matrix_value[$k++];
                }
            }
            if ($row_sum >= $min_right_answers) {
                $right_sum++;
            }
        }
        return $right_sum / ($row_number - 1);
    }
}
