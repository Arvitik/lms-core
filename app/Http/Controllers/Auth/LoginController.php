<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        $message = 'Неверный email или пароль.';

        if ($request->expectsJson()) {
            return response()->json(['email' => $message], 422);
        }

        return redirect()->back()
            ->withInput($request->only('email', 'remember'))
            ->with('login_failed', $message);
    }
}
