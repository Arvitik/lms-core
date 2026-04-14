<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateScheduleBoardTeachersTable extends Migration
{
    public function up()
    {
        Schema::create('schedule_board_teachers', function (Blueprint $table) {
            $table->integer('teacher_id')->primary();
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedule_board_teachers');
    }
}
