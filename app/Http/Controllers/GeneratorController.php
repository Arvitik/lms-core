<?php
/**
 * Created by PhpStorm.
 * User: ÃÂ¡Ã‘â€šÃÂ°ÃÂ½ÃÂ¸Ã‘ÂÃÂ»ÃÂ°ÃÂ²
 * Date: 05.12.15
 * Time: 14:32
 */

namespace App\Http\Controllers;

use App\Mypdf;
use App\Testing\Qtypes\AccordanceTable;
use App\Testing\Qtypes\Definition;
use App\Testing\Qtypes\FillGaps;
use App\Testing\Qtypes\JustAnswer;
use App\Testing\Qtypes\MultiChoice;
use App\Testing\Qtypes\OneChoice;
use App\Testing\Qtypes\QuestionTypeFactory;
use App\Testing\Qtypes\Theorem;
use App\Testing\Qtypes\TheoremLike;
use App\Testing\Qtypes\YesNo;
use App\Testing\Question;
use App\Testing\Test;
use App\Testing\TestGeneration\TestGenerationException;
use App\Testing\TestGeneration\UsualTestGenerator;
use Illuminate\Http\Request;


class GeneratorController extends Controller {

    private function pdfQuestion(Mypdf $fpdf, $id_question, $count, $answered=false, $paper_savings=false){
        $type = Question::whereId_question($id_question)->join('types', 'questions.type_code', '=', 'types.type_code')
                ->first()->type_name;
        $question = QuestionTypeFactory::getQuestionTypeByTypeName($id_question, $type);
        $question->pdf($fpdf, $count, $answered, $paper_savings);
    }

    private function headOfPdf(Mypdf $fpdf, $test_name, $variant, $num_tasks){
        $fpdf->AliasNbPages();
        $fpdf->defaultfooterfontstyle = 'DejaVuSansMono';
        $fpdf->SetFooter('ÃÂ¡Ã‘â€šÃ‘â‚¬ÃÂ°ÃÂ½ÃÂ¸Ã‘â€ ÃÂ° {PAGENO}/{nb}', '');
        $fpdf->AddPage();
        $fpdf->Head($test_name);
        $fpdf->info($variant);                                                                                            // ÃÂ²Ã‘â€¹ÃÂ²ÃÂ¾ÃÂ´ ÃÂ¸ÃÂ½Ã‘â€žÃÂ¾Ã‘â‚¬ÃÂ¼ÃÂ°Ã‘â€ ÃÂ¸ÃÂ¸ ÃÂ¾ ÃÂ±ÃÂ¸ÃÂ»ÃÂµÃ‘â€šÃÂµ
        $fpdf->task_table($num_tasks);                                                                                 // ÃÂ²Ã‘â€¹ÃÂ²ÃÂ¾ÃÂ´ Ã‘â€šÃÂ°ÃÂ±ÃÂ»ÃÂ¸Ã‘â€ Ã‘â€¹ Ã‘â‚¬ÃÂµÃÂ·Ã‘Æ’ÃÂ»Ã‘Å’Ã‘â€šÃÂ°Ã‘â€šÃÂ¾ÃÂ²
    }

    private function footerOfPdf(Mypdf $fpdf, $protocol_num, $protocol_date) {
        $footer = 'Ãâ€˜ÃÂ¸ÃÂ»ÃÂµÃ‘â€šÃ‘â€¹ Ã‘Æ’Ã‘â€šÃÂ²ÃÂµÃ‘â‚¬ÃÂ¶ÃÂ´ÃÂµÃÂ½Ã‘â€¹ ÃÂ½ÃÂ° ÃÂ·ÃÂ°Ã‘ÂÃÂµÃÂ´ÃÂ°ÃÂ½ÃÂ¸ÃÂ¸ ÃÂºÃÂ°Ã‘â€žÃÂµÃÂ´Ã‘â‚¬Ã‘â€¹ "ÃÅ¡ÃÂ¸ÃÂ±ÃÂµÃ‘â‚¬ÃÂ½ÃÂµÃ‘â€šÃÂ¸ÃÂºÃÂ°", ÃÂ¿Ã‘â‚¬ÃÂ¾Ã‘â€šÃÂ¾ÃÂºÃÂ¾ÃÂ» Ã¢â€žâ€“' . $protocol_num . ' ÃÂ¾Ã‘â€š ' . $protocol_date . '.';
        $fpdf->WriteHTML($footer);
    }

    /** Ã‘Æ’ÃÂ´ÃÂ°ÃÂ»Ã‘ÂÃÂµÃ‘â€š ÃÂ´ÃÂ¸Ã‘â‚¬ÃÂµÃÂºÃ‘â€šÃÂ¾Ã‘â‚¬ÃÂ¸Ã‘Å½ ÃÂ²ÃÂ¼ÃÂµÃ‘ÂÃ‘â€šÃÂµ Ã‘Â Ã‘â€žÃÂ°ÃÂ¹ÃÂ»ÃÂ°ÃÂ¼ÃÂ¸ */
    private function delPdf($dir){
        $temp = opendir( $dir );
        while( $d = readdir( $temp ) ){
            if ($d != '.' && $d != '..'){
                unlink($dir.'/'.$d);
            }
        }
        rmdir($dir);
    }

    /** ÃÂ¡ÃÂºÃÂ°Ã‘â€¡ÃÂ¸ÃÂ²ÃÂ°ÃÂ½ÃÂ¸ÃÂµ Ã‘â€žÃÂ°ÃÂ¹ÃÂ»ÃÂ° */
    private function download($filename){
        header("HTTP/1.1 200 OK");
        header("Connection: close");
        header("Content-Transfer-Encoding: binary");
        header("Content-Type: application/zip");
        header("Content-Length: ".filesize($filename));
        header("Content-Disposition: attachment; filename=".$filename);
        readfile($filename);
    }

    public function index(){
        $testNames = [];
        $tests = Test::whereArchived(0)
                    ->select('id_test', 'test_name', 'test_type')->get();
        foreach ($tests as $test){
            if ($test->test_type != 'ÃÂ¢Ã‘â‚¬ÃÂµÃÂ½ÃÂ¸Ã‘â‚¬ÃÂ¾ÃÂ²ÃÂ¾Ã‘â€¡ÃÂ½Ã‘â€¹ÃÂ¹'){
                array_push($testNames, $test->test_name);
            }
        }
        return view('generator.index', compact('testNames'));
    }

    public function pdf() {
        return redirect()->route('generator_index');
    }

    /** Ãâ€œÃÂµÃÂ½ÃÂµÃ‘â‚¬ÃÂ¸Ã‘â‚¬Ã‘Æ’ÃÂµÃ‘â€š pdf Ã‘â€žÃÂ°ÃÂ¹ÃÂ»Ã‘â€¹ Ã‘Â Ã‘â€šÃÂµÃ‘ÂÃ‘â€šÃÂ¾ÃÂ¼ Ã‘Â ÃÂ·ÃÂ°ÃÂ´ÃÂ°ÃÂ½ÃÂ½Ã‘â€¹ÃÂ¼ ÃÂºÃÂ¾ÃÂ»ÃÂ¸Ã‘â€¡ÃÂµÃ‘ÂÃ‘â€šÃÂ²ÃÂ¾ÃÂ¼ ÃÂ²ÃÂ°Ã‘â‚¬ÃÂ¸ÃÂ°ÃÂ½Ã‘â€šÃÂ¾ÃÂ² */
    public function pdfTest(Request $request){
        $test = new Test();

        $test_name = $request->input('test');
        $num_var = $request->input('num-variants');
        $paper_savings = $request->input('paper_savings');
        $id_test = Test::whereTest_name($test_name)->select('id_test')->first()->id_test;
        $amount = $test->getAmount($id_test);                                                                                // ÃÂºÃÂ¾ÃÂ»-ÃÂ²ÃÂ¾ ÃÂ²ÃÂ¾ÃÂ¿Ã‘â‚¬ÃÂ¾Ã‘ÂÃÂ¾Ã‘ÂÃÂ² ÃÂ² Ã‘â€šÃÂµÃ‘ÂÃ‘â€šÃÂµ

                $today = date("Y-m-d H-i-s");
        $baseDir = "archive/pdf_tests";
        $dir = $baseDir.'/'.Mypdf::translit($test_name).' '.$today;

        if (!is_dir($baseDir) && !mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
            throw new \RuntimeException('Cannot create base PDF archive directory: '.$baseDir);
        }

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create PDF output directory: '.$dir);
        }

        define('FPDF_FONTPATH','C:\wamp\www\uir\public\fonts');
        try {
            for ($k = 1; $k <= $num_var; $k++){                                                                             // ÃÂ³ÃÂµÃÂ½ÃÂµÃ‘â‚¬ÃÂ¸Ã‘â‚¬Ã‘Æ’ÃÂµÃÂ¼ ÃÂ½ÃÂµÃÂ¾ÃÂ±Ã‘â€¦ÃÂ¾ÃÂ´ÃÂ¸ÃÂ¼ÃÂ¾ÃÂµ Ã‘â€¡ÃÂ¸Ã‘ÂÃÂ»ÃÂ¾ ÃÂ²ÃÂ°Ã‘â‚¬ÃÂ¸ÃÂ°ÃÂ½Ã‘â€šÃÂ¾ÃÂ²
                $fpdf = new Mypdf();
                $answered_fpdf = new Mypdf();
                $this->headOfPdf($fpdf, $test_name, $k, $amount);
                $this->headOfPdf($answered_fpdf, $test_name, $k, $amount);
                $generator = new UsualTestGenerator();
                $generator->generate(Test::whereId_test($id_test)->first());
                for ($i=0; $i<$amount; $i++){                                                                               // ÃÂ¿ÃÂ¾ÃÂºÃÂ°ÃÂ·Ã‘â€¹ÃÂ²ÃÂ°ÃÂµÃÂ¼ ÃÂºÃÂ°ÃÂ¶ÃÂ´Ã‘â€¹ÃÂ¹ ÃÂ²ÃÂ¾ÃÂ¿Ã‘â‚¬ÃÂ¾Ã‘Â ÃÂ¸ÃÂ· Ã‘â€šÃÂµÃ‘ÂÃ‘â€šÃÂ°
                    $id = $generator->chooseQuestion();
                    $this->pdfQuestion($fpdf, $id, $i+1, false, $paper_savings);
                    $this->pdfQuestion($answered_fpdf, $id, $i+1, true);
                }
                if ($request->input('protocol-num') != '') {
                    $this->footerOfPdf($fpdf, $request->input('protocol-num'), $request->input('protocol-date'));
                }
                $fpdf->Output($dir.'/variant'.$k.'.pdf', 'F');
                $answered_fpdf->Output($dir.'/answered_variant'.$k.'.pdf', 'F');
            }
        } catch (TestGenerationException $ex) {
            throw new \Exception('ÃÅ¸Ã‘â‚¬ÃÂ¾ÃÂ¸ÃÂ·ÃÂ¾Ã‘Ë†ÃÂ»ÃÂ° ÃÂ¾Ã‘Ë†ÃÂ¸ÃÂ±ÃÂºÃÂ° ÃÂ¿Ã‘â‚¬ÃÂ¸ ÃÂ³ÃÂµÃÂ½ÃÂµÃ‘â‚¬ÃÂ°Ã‘â€ ÃÂ¸ÃÂ¸ Ã‘â€šÃÂµÃ‘ÂÃ‘â€šÃÂ°. Ãâ€ÃÂ»Ã‘Â Ã‘â‚¬ÃÂµÃ‘Ë†ÃÂµÃÂ½ÃÂ¸Ã‘Â Ã‘ÂÃ‘â€šÃÂ¾ÃÂ³ÃÂ¾ ÃÂ²ÃÂ¾ÃÂ¿Ã‘â‚¬ÃÂ¾Ã‘ÂÃÂ° ÃÂ¿ÃÂ¾ÃÂ¿Ã‘â‚¬ÃÂ¾ÃÂ±Ã‘Æ’ÃÂ¹Ã‘â€šÃÂµ ÃÂ² Ã‘â‚¬ÃÂ°ÃÂ·ÃÂ´ÃÂµÃÂ»ÃÂµ "ÃÂ¡ÃÂ¿ÃÂ¸Ã‘ÂÃÂ¾ÃÂº ÃÂ²Ã‘ÂÃÂµÃ‘â€¦ Ã‘â€šÃÂµÃ‘ÂÃ‘â€šÃÂ¾ÃÂ²" ÃÂ´ÃÂ»Ã‘Â Ã‘ÂÃ‘â€šÃÂ¾ÃÂ³ÃÂ¾ Ã‘â€šÃÂµÃ‘ÂÃ‘â€šÃÂ° ÃÂ·ÃÂ°ÃÂ´ÃÂ°Ã‘â€šÃ‘Å’ ÃÂ³ÃÂ°ÃÂ»ÃÂ¾Ã‘â€¡ÃÂºÃ‘Æ’ ÃÂ½ÃÂ° "ÃÂ¢ÃÂ¾ÃÂ»Ã‘Å’ÃÂºÃÂ¾ ÃÂ´ÃÂ»Ã‘Â ÃÂ¿ÃÂµÃ‘â€¡ÃÂ°Ã‘â€šÃÂ¸".');
        }

        $zip = Mypdf::pdfToZip($dir);                                                                                   // Ã‘ÂÃÂ¾ÃÂ·ÃÂ´ÃÂ°ÃÂµÃÂ¼ ÃÂ°Ã‘â‚¬Ã‘â€¦ÃÂ¸ÃÂ²
        $this->delPdf($dir);                                                                                            // Ã‘Æ’ÃÂ´ÃÂ°ÃÂ»Ã‘ÂÃÂµÃÂ¼ Ã‘ÂÃÂ¾ÃÂ·ÃÂ´ÃÂ°ÃÂ½ÃÂ½Ã‘Æ’Ã‘Å½ ÃÂ¿ÃÂ°ÃÂ¿ÃÂºÃ‘Æ’ Ã‘Â Ã‘â€šÃÂµÃ‘ÂÃ‘â€šÃÂ°ÃÂ¼ÃÂ¸
        $this->download($zip);                                                                                          // Ã‘ÂÃÂºÃÂ°Ã‘â€¡ÃÂ°Ã‘â€šÃ‘Å’ ÃÂ°Ã‘â‚¬Ã‘â€¦ÃÂ¸ÃÂ²
    }
} 
