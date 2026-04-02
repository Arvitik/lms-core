<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    // Laravel по умолчанию связывает эту модель с таблицей 'messages'
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'subject',
        'body',
        'is_read',
    ];

    // Связь «кто отправил»
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    // Связь «кому адресовано»
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
