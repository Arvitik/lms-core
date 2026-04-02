<?php

namespace App\Services;

use App\Notification;

class NotificationService
{
    /**
     * Создать уведомление для одного пользователя.
     */
    public static function send($userId, $type, $title, $body, array $data = [])
    {
        Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_read' => false,
        ]);
    }

    /**
     * Создать уведомление сразу нескольким пользователям.
     */
    public static function sendMany(array $userIds, $type, $title, $body, array $data = [])
    {
        $now = \Carbon\Carbon::now();
        $rows = [];
        foreach (array_unique($userIds) as $userId) {
            $rows[] = [
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_read'    => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($rows)) {
            Notification::insert($rows);
        }
    }
}
