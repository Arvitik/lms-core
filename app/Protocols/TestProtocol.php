<?php
/**
 * Created by PhpStorm.
 * User: Станислав
 * Date: 20.05.16
 * Time: 2:56
 */

namespace App\Protocols;

use App\Testing\Test;

class TestProtocol extends Protocol {
    const TEST_PROTOCOL_DIR = 'test_protocols/';

    public function setTest($id_test){
        $test = Test::whereId_test($id_test)->select('test_name')->first();
        $this->test = $test ? $test->test_name : 'test_'.$id_test;
    }

    public function setBaseDir(){
        return storage_path('app/'.$this::PROTOCOL_PATH.$this::TEST_PROTOCOL_DIR);
    }
} 
