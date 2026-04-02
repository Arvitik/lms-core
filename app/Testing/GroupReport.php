<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GroupReport extends Model
{
    // Laravel будет искать таблицу group_reports
    protected $table = 'group_reports';

    // Отключаем автоматическую установку updated_at, но оставляем created_at
    const UPDATED_AT = null;

    // Если в базе только поле created_at, то выключаем timestamps
    //public $timestamps = false; // вместо предыдущей строки, если не нужен даже created_at автоматом

    // Разрешаем массовое заполнение для этих полей
    protected $fillable = [
        'report_data',
        'created_at',
    ];

    // Кастим поле report_data (TEXT) в массив PHP при чтении/записи
    protected $casts = [
        'report_data' => 'array',
    ];
}
