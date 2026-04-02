<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGroupReportsTable extends Migration
{
    public function up()
    {
        Schema::create('group_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->text('report_data');
            $table->timestamp('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('group_reports');
    }
}
