<?php

namespace App\Http\Controllers;

use App\Message;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessagesController extends Controller
{
    public function __construct()
    {
        // Только авторизованные
        $this->middleware('auth');

        // Только «Преподаватель» или «Админ»
        $this->middleware(function ($request, $next) {
            $role = Auth::user()->role;
            if ($role !== 'Преподаватель' && $role !== 'Админ') {
                abort(403);
            }
            return $next($request);
        });
    }

    /**
     * Список входящих сообщений
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
     * Форма «Создать сообщение»
     */
    public function create()
    {
        $recipients = User::whereIn('role', ['Админ', 'Преподаватель'])
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'role']);

        return view('messages.create', compact('recipients'));
    }

    /**
     * Сохраняем новое сообщение
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'to_user_id' => 'required|exists:users,id',
            'subject'    => 'nullable|string|max:255',
            'body'       => 'required|string',
        ]);

        Message::create([
            'from_user_id' => Auth::id(),
            'to_user_id'   => $request->input('to_user_id'),
            'subject'      => $request->input('subject'),
            'body'         => $request->input('body'),
            'is_read'      => false,
        ]);

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

        return back()->with('success', 'Сообщение удалено.');
    }

    /**
     * Показ полного текста одного сообщения
     */
    public function show($id)
    {
        $msg = Message::findOrFail($id);

        // Проверяем, что получатель — текущий пользователь
        if ($msg->to_user_id !== Auth::id()) {
            abort(403);
        }

        // Если сообщение ещё не прочитано — отметим как прочитанное
        if (! $msg->is_read) {
            $msg->is_read = true;
            $msg->save();
        }

        return view('messages.show', compact('msg'));
    }
}
