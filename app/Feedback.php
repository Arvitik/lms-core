<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = [
        'target_type',
        'target_id',
        'user_id',
        'rating',
        'comment',
        'is_anonymous',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Средняя оценка для конкретного объекта (лекция/тест/курс).
     */
    public static function avgRating($type, $targetId = null)
    {
        $q = self::where('target_type', $type);
        if ($targetId !== null) {
            $q->where('target_id', $targetId);
        }
        return round($q->avg('rating'), 2);
    }

    /**
     * Количество отзывов для конкретного объекта.
     */
    public static function countFor($type, $targetId = null)
    {
        $q = self::where('target_type', $type);
        if ($targetId !== null) {
            $q->where('target_id', $targetId);
        }
        return $q->count();
    }

    /**
     * Проверить, оставлял ли пользователь отзыв на данный объект.
     */
    public static function alreadyLeft($userId, $type, $targetId = null)
    {
        $q = self::where('user_id', $userId)->where('target_type', $type);
        if ($targetId !== null) {
            $q->where('target_id', $targetId);
        }
        return $q->exists();
    }
}
