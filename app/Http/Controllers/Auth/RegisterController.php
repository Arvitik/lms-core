<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Получить валидатор для входящих данных регистрации.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $v = Validator::make(
            $data,
            [
                'first_name'           => ['required','string','min:2','max:50','regex:/^[\p{Cyrillic}A-Za-z][\p{Cyrillic}A-Za-z \'\-]{1,49}$/u'],
                'last_name'            => ['required','string','min:2','max:50','regex:/^[\p{Cyrillic}A-Za-z][\p{Cyrillic}A-Za-z \'\-]{1,49}$/u'],
                'group'                => 'numeric',
                'email'                => 'required|email|max:255|unique:users,email',
                'password'             => 'required|confirmed|min:6',
                'g-recaptcha-response' => 'nullable',
            ],
            [
                'first_name.regex'           => 'Имя должно содержать только буквы, пробел, дефис или апостроф.',
                'last_name.regex'            => 'Фамилия должна содержать только буквы, пробел, дефис или апостроф.',
                'g-recaptcha-response.required' => 'Подтвердите, что вы не робот.', // legacy
            ]
        );

        // Дополнительная антиспам-проверка + валидация reCAPTCHA на стороне сервера
        $v->after(function ($v) use ($data) {
            foreach (['first_name', 'last_name'] as $f) {
                if (!empty($data[$f]) && preg_match('/(.)\1{3,}/u', $data[$f])) {
                    $v->errors()->add($f, 'Слишком много повторяющихся символов.');
                }
            }

            if (empty($data['g-recaptcha-response'])) {
                return;
            }

            $secret = config('recaptcha.secret_key');
            if (empty($secret)) {
                return;
            }

            try {
                $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 5,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => [
                        'secret'   => $secret,
                        'response' => $data['g-recaptcha-response'],
                        'remoteip' => request()->ip(),
                    ],
                ]);
                $result = curl_exec($ch);
                $curlError = curl_errno($ch);
                curl_close($ch);

                if ($curlError || $result === false) {
                    // Сетевая ошибка — не блокируем регистрацию
                    return;
                }

                $body = json_decode($result, true);
                if (empty($body['success'])) {
                    $v->errors()->add('g-recaptcha-response', 'Проверка reCAPTCHA не пройдена. Попробуйте ещё раз.');
                }
            } catch (\Exception $e) {
                // При любой ошибке проверки не блокируем регистрацию
            }
        });

        return $v;
    }
    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'group' => $data['group'],
            'year' => date('Y'),
            'password' => bcrypt($data['password']),
        ]);
    }
}
