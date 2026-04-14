<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $table = 'exam_schedules';

    protected $fillable = [
        'teacher_id', 'group_id', 'title', 'description',
        'scheduled_date', 'time_start', 'time_end', 'room',
    ];

    protected $dates = ['scheduled_date'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function groups()
    {
        return $this->belongsToMany(
            Group::class,
            'exam_schedule_groups',
            'exam_schedule_id',
            'group_id'
        );
    }
}
