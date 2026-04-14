<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeExamSchedulesGroupIdNullable extends Migration
{
    public function up()
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->integer('group_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->integer('group_id')->nullable(false)->change();
        });
    }
}
