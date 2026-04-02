<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $table = 'exam_schedules';

    protected $fillable = ['teacher_id', 'group_id', 'test_id', 'title', 'description', 'scheduled_date'];

    protected $dates = ['scheduled_date'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function test()
    {
        return $this->belongsTo(\App\Testing\Test::class, 'test_id', 'id_test');
    }
}
