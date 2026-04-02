<?php

namespace App\Http\Controllers;

use App\Message;
use App\User;
use App\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;

class MessagesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Список входящих сообщений (для всех авторизованных)
     */
    public function index()
    {
        $userId = Auth::id();

        $messages = Message::where('to_user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('messages.index', compact('messages'));
    }

    /**
     * Форма «Написать сообщение» — двухшаговый выбор получателя
     */
    public function create()
    {
        $role = Auth::user()->role;
        $isTeacher = in_array($role, ['Преподаватель', 'Админ']);

        // Неархивные учебные группы (у которых есть студенты)
        $groups = Group::whereArchived(0)
            ->orderBy('group_name')
            ->get(['group_id', 'group_name']);

        return view('messages.create', compact('groups', 'isTeacher'));
    }

    /**
     * AJAX: вернуть пользователей по группе или категории 'teachers'
     */
    public function getUsersByGroup(Request $request)
    {
        $groupId = $request->input('group_id');

        if ($groupId === 'teachers') {
            $users = User::whereIn('role', ['Преподаватель', 'Админ'])
                ->where('id', '!=', Auth::id())
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'role']);
        } else {
            $users = User::where('group', $groupId)
                ->where('id', '!=', Auth::id())
                ->whereNotNull('role')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'role']);
        }

        return response()->json($users);
    }

    /**
     * Сохранить новое сообщение
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'to_user_id' => 'required|exists:users,id',
            'subject'    => 'nullable|string|max:255',
            'body'       => 'required|string',
        ]);

        // Студент не может писать другому студенту
        $recipient = User::findOrFail($request->input('to_user_id'));
        $senderRole = Auth::user()->role;
        if (!in_array($senderRole, ['Преподаватель', 'Админ'])) {
            if (!in_array($recipient->role, ['Преподаватель', 'Админ'])) {
                return back()->withErrors(['to_user_id' => 'Можно писать только преподавателю или администратору.']);
            }
        }

        Message::create([
            'from_user_id' => Auth::id(),
            'to_user_id'   => $request->input('to_user_id'),
            'subject'      => $request->input('subject'),
            'body'         => $request->input('body'),
            'is_read'      => false,
        ]);

        // === ДИПЛОМ: уведомление о новом сообщении — активировать вместе с чатом ===
        /*
        try {
            $sender = Auth::user();
            $senderName = $sender->first_name . ' ' . $sender->last_name;
            $subject = $request->input('subject') ?: 'Без темы';
            NotificationService::send(
                $request->input('to_user_id'),
                'new_message',
                'Новое сообщение',
                'Вам написал(а) ' . $senderName . ': «' . $subject . '».',
                ['url' => route('messages.index')]
            );
        } catch (\Exception $ne) {
            \Illuminate\Support\Facades\Log::warning('Notification send failed: ' . $ne->getMessage());
        }
        */

        return redirect()->route('messages.index')
                         ->with('success', 'Ваше сообщение отправлено.');
    }

    /**
     * Отметить сообщение как прочитанное
     */
    public function markAsRead($id)
    {
        $msg = Message::findOrFail($id);
        if ($msg->to_user_id !== Auth::id()) {
            abort(403);
        }
        $msg->is_read = true;
        $msg->save();

        return back();
    }

    /**
     * Удалить сообщение
     */
    public function destroy($id)
    {
        $msg = Message::findOrFail($id);
        if ($msg->to_user_id !== Auth::id()) {
            abort(403);
        }
        $msg->delete();

        return redirect()->route('messages.index')->with('success', 'Сообщение удалено.');
    }

    /**
     * Показ полного текста одного сообщения
     */
    public function show($id)
    {
        $msg = Message::findOrFail($id);

        if ($msg->to_user_id !== Auth::id()) {
            abort(403);
        }

        if (!$msg->is_read) {
            $msg->is_read = true;
            $msg->save();
        }

        return view('messages.show', compact('msg'));
    }
}
