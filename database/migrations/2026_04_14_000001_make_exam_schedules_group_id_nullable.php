<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeExamSchedulesGroupIdNullable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE exam_schedules MODIFY group_id INT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE exam_schedules MODIFY group_id INT NOT NULL');
    }
}
