<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExtendCurrentControlSchedule extends Migration
{
    public function up()
    {
        $this->addColumnIfMissing('weekday', "TINYINT NULL AFTER lesson_date");
        $this->addColumnIfMissing('time_start', "TIME NULL AFTER weekday");
        $this->addColumnIfMissing('time_end', "TIME NULL AFTER time_start");
        $this->addColumnIfMissing('date_from', "DATE NULL AFTER time_end");
        $this->addColumnIfMissing('date_to', "DATE NULL AFTER date_from");
        $this->addColumnIfMissing('auditorium', "VARCHAR(100) NULL AFTER date_to");
        $this->addColumnIfMissing('teacher_name', "VARCHAR(255) NULL AFTER auditorium");
        $this->addColumnIfMissing('replacement_teacher', "VARCHAR(255) NULL AFTER teacher_name");
        $this->addColumnIfMissing('status', "VARCHAR(20) NOT NULL DEFAULT 'active' AFTER replacement_teacher");
        $this->addColumnIfMissing('import_batch', "VARCHAR(64) NULL AFTER status");
    }

    public function down()
    {
        foreach (array('import_batch', 'status', 'replacement_teacher', 'teacher_name', 'auditorium', 'date_to', 'date_from', 'time_end', 'time_start', 'weekday') as $column) {
            if ($this->columnExists($column)) {
                DB::statement("ALTER TABLE current_control_schedule DROP COLUMN {$column}");
            }
        }
    }

    private function addColumnIfMissing($column, $definition)
    {
        if (!$this->columnExists($column)) {
            DB::statement("ALTER TABLE current_control_schedule ADD COLUMN {$column} {$definition}");
        }
    }

    private function columnExists($column)
    {
        $row = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'current_control_schedule' AND COLUMN_NAME = ?",
            array($column)
        );

        return $row && (int) $row->cnt > 0;
    }
}
