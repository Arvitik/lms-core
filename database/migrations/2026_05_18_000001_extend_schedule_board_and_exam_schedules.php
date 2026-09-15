<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class ExtendScheduleBoardAndExamSchedules extends Migration
{
    public function up()
    {
        if (Schema::hasTable('exam_schedules')) {
            $addTimeStart = !$this->hasColumn('exam_schedules', 'time_start');
            $addTimeEnd = !$this->hasColumn('exam_schedules', 'time_end');
            $addRoom = !$this->hasColumn('exam_schedules', 'room');

            Schema::table('exam_schedules', function (Blueprint $table) use ($addTimeStart, $addTimeEnd, $addRoom) {
                if ($addTimeStart) {
                    $table->time('time_start')->nullable()->after('scheduled_date');
                }
                if ($addTimeEnd) {
                    $table->time('time_end')->nullable()->after('time_start');
                }
                if ($addRoom) {
                    $table->string('room', 100)->nullable()->after('time_end');
                }
            });
        }

        if (Schema::hasTable('schedule_board_entries')) {
            $addSeriesId = !$this->hasColumn('schedule_board_entries', 'series_id');
            $addAllGroups = !$this->hasColumn('schedule_board_entries', 'all_groups');
            $addExamScheduleId = !$this->hasColumn('schedule_board_entries', 'exam_schedule_id');

            Schema::table('schedule_board_entries', function (Blueprint $table) use ($addSeriesId, $addAllGroups, $addExamScheduleId) {
                if ($addSeriesId) {
                    $table->integer('series_id')->nullable()->after('description');
                }
                if ($addAllGroups) {
                    $table->boolean('all_groups')->default(false)->after('series_id');
                }
                if ($addExamScheduleId) {
                    $table->integer('exam_schedule_id')->nullable()->after('all_groups');
                }
            });
        }

        if (!Schema::hasTable('exam_schedule_groups')) {
            Schema::create('exam_schedule_groups', function (Blueprint $table) {
                $table->integer('exam_schedule_id');
                $table->integer('group_id');
                $table->primary(['exam_schedule_id', 'group_id']);
            });
        }

        if (!Schema::hasTable('schedule_board_entry_groups')) {
            Schema::create('schedule_board_entry_groups', function (Blueprint $table) {
                $table->integer('entry_id');
                $table->integer('group_id');
                $table->primary(['entry_id', 'group_id']);
            });
        }

        if (!Schema::hasTable('exam_schedule_views')) {
            Schema::create('exam_schedule_views', function (Blueprint $table) {
                $table->integer('user_id');
                $table->integer('exam_schedule_id');
                $table->primary(['user_id', 'exam_schedule_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('exam_schedule_views');
        Schema::dropIfExists('schedule_board_entry_groups');
        Schema::dropIfExists('exam_schedule_groups');

        if (Schema::hasTable('schedule_board_entries')) {
            $dropExamScheduleId = $this->hasColumn('schedule_board_entries', 'exam_schedule_id');
            $dropAllGroups = $this->hasColumn('schedule_board_entries', 'all_groups');
            $dropSeriesId = $this->hasColumn('schedule_board_entries', 'series_id');

            Schema::table('schedule_board_entries', function (Blueprint $table) use ($dropExamScheduleId, $dropAllGroups, $dropSeriesId) {
                if ($dropExamScheduleId) {
                    $table->dropColumn('exam_schedule_id');
                }
                if ($dropAllGroups) {
                    $table->dropColumn('all_groups');
                }
                if ($dropSeriesId) {
                    $table->dropColumn('series_id');
                }
            });
        }

        if (Schema::hasTable('exam_schedules')) {
            $dropRoom = $this->hasColumn('exam_schedules', 'room');
            $dropTimeEnd = $this->hasColumn('exam_schedules', 'time_end');
            $dropTimeStart = $this->hasColumn('exam_schedules', 'time_start');

            Schema::table('exam_schedules', function (Blueprint $table) use ($dropRoom, $dropTimeEnd, $dropTimeStart) {
                if ($dropRoom) {
                    $table->dropColumn('room');
                }
                if ($dropTimeEnd) {
                    $table->dropColumn('time_end');
                }
                if ($dropTimeStart) {
                    $table->dropColumn('time_start');
                }
            });
        }
    }

    private function hasColumn($table, $column)
    {
        $table = str_replace('`', '``', $table);
        $column = addslashes($column);
        $result = DB::select("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return !empty($result);
    }
}
