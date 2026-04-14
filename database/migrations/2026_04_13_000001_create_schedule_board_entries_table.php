<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateScheduleBoardEntriesTable extends Migration
{
    public function up()
    {
        Schema::create('schedule_board_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('teacher_id');           // Преподаватель
            $table->integer('group_id')->nullable(); // Группа (опционально)
            $table->date('entry_date');              // Дата занятия
            $table->time('time_start');              // Начало
            $table->time('time_end')->nullable();    // Конец
            $table->string('entry_type');            // 'Лекция' или 'Семинар'
            $table->string('room');                  // Аудитория
            $table->string('title');                 // Тема занятия
            $table->text('description')->nullable(); // Дополнительное описание
            $table->timestamps();

            $table->index(['teacher_id', 'entry_date']);
            $table->index(['entry_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedule_board_entries');
    }
}
