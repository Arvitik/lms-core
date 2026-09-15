<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateCurrentControlTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('current_control_schedule')) {
            Schema::create('current_control_schedule', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('group_id')->unsigned();
                $table->integer('teacher_id')->unsigned()->nullable();
                $table->string('type', 20);
                $table->date('lesson_date');
                $table->tinyInteger('weekday')->nullable();
                $table->time('time_start')->nullable();
                $table->time('time_end')->nullable();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->string('auditorium', 100)->nullable();
                $table->string('teacher_name', 255)->nullable();
                $table->string('replacement_teacher', 255)->nullable();
                $table->string('status', 20)->default('active');
                $table->string('import_batch', 64)->nullable();
                $table->string('title', 255);
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dean_group_student_counts')) {
            Schema::create('dean_group_student_counts', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('group_id')->unsigned()->unique();
                $table->integer('expected_count')->unsigned();
                $table->text('source_comment')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('current_control_schedule');
        Schema::dropIfExists('dean_group_student_counts');
    }
}
