<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterGroupReportsReportDataToMediumtext extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE group_reports MODIFY report_data MEDIUMTEXT NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE group_reports MODIFY report_data TEXT NOT NULL');
    }
}
