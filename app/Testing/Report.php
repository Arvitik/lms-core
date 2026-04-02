<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['teacher_id', 'data'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
