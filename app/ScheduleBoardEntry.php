<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScheduleBoardEntry extends Model
{
    protected $table = 'schedule_board_entries';

    protected $fillable = [
        'teacher_id', 'group_id', 'entry_date', 'time_start', 'time_end',
        'entry_type', 'room', 'title', 'description', 'series_id', 'all_groups',
        'exam_schedule_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function groups()
    {
        return $this->belongsToMany(
            Group::class,
            'schedule_board_entry_groups',
            'entry_id',
            'group_id'
        );
    }
}
