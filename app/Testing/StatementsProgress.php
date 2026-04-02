<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StatementsProgress extends Model
{
    protected $table = 'statements_progress'; // Явно указываем имя таблицы

    public $timestamps = false; // Если в таблице нет полей created_at и updated_at

    protected $fillable = [
        'userID',
        'group',
        'control1', 'control2',
        'test1', 'test1quiz', 'section1',
        'test2', 'test2quiz', 'section2',
        'control3', 'test3', 'test3quiz', 'section3',
        'lastquiz', 'section4'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'userID');
    }
}
