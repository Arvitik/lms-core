<?php

namespace App\Http\Controllers;

use App\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Страница «Все уведомления»
     */
    public function index()
    {
        $notifications = Notification::forUser(Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * AJAX: количество непрочитанных + последние 5
     */
    public function getUnread()
    {
        $userId = Auth::id();

        $unreadCount = Notification::forUser($userId)->unread()->count();

        $recent = Notification::forUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'type', 'title', 'body', 'is_read', 'created_at']);

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $recent,
        ]);
    }

    /**
     * Отметить одно уведомление прочитанным
     */
    public function markRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->is_read = true;
        $notification->save();

        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /**
     * Отметить все уведомления прочитанными
     */
    public function markAllRead()
    {
        Notification::forUser(Auth::id())
            ->unread()
            ->update(['is_read' => true]);

        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Все уведомления отмечены как прочитанные.');
    }

    /**
     * Удалить уведомление
     */
    public function destroy($id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->delete();

        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Уведомление удалено.');
    }
}
