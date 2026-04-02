<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $table = 'results'; // Указывай явно, если не по соглашению
    protected $fillable = ['student_id', 'test_id', 'result', 'result_date'];
    public $timestamps = false; // если нет created_at/updated_at
}
