<?php namespace App\Http\Controllers;

use App\Group;
use App\News;
use App\Message;
use Auth;

class HomeController extends Controller {

	public function __construct()
	{
		$this->middleware('auth');
	}

	public function index()
	{
		return view('home');
	}

	public function get_home()
	{
		if (Auth::check()) {
			$news = News::where('is_visible', 1)->get();

			// Считаем непрочитанные сообщения, если пользователь преподаватель или админ
			$unreadMessagesCount = 0;
			if (in_array(Auth::user()->role, ['Преподаватель', 'Админ'])) {
				$unreadMessagesCount = \App\Message::where('to_user_id', Auth::id())
					->where('is_read', false)
					->count();
			}

			return view('main', compact('news', 'unreadMessagesCount'));
		}
		else {
			$groups = Group::where('archived', 0)->get();
			return view('welcome', compact('groups'));
		}
	}
}
