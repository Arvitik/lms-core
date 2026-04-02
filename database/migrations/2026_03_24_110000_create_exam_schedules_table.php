<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExamSchedulesTable extends Migration
{
    public function up()
    {
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('teacher_id');                   // Кто назначил
            $table->integer('group_id');                     // Для какой группы
            $table->integer('test_id')->nullable();          // Конкретный тест (опционально)
            $table->string('title');                         // Название контрольной
            $table->text('description')->nullable();         // Описание / что повторить
            $table->date('scheduled_date');                  // Дата проведения
            $table->timestamps();

            $table->index(['group_id', 'scheduled_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('exam_schedules');
    }
}
