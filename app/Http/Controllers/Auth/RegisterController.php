<?php

namespace App\Http\Controllers\Auth;

use App\Group;
use App\User;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function showRegistrationForm()
    {
        return view('auth.register', [
            'groups' => $this->registrationGroups(),
            'captchaEnabled' => $this->captchaConfigured(),
        ]);
    }

    public function register(Request $request)
    {
        $validateCaptcha = $this->captchaConfigured();

        try {
            $this->validator($request->all(), $validateCaptcha)->validate();
        } catch (ConnectException $exception) {
            Log::warning('reCAPTCHA is unavailable; registration rejected.', [
                'error' => $exception->getMessage(),
            ]);
            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['g-recaptcha-response' => 'Сервис проверки временно недоступен. Повторите регистрацию позже.']);
        }

        event(new Registered($user = $this->create($request->all())));
        $this->guard()->login($user);

        return $this->registered($request, $user)
            ?: redirect($this->redirectPath());
    }

    /**
     * Получить валидатор для входящих данных регистрации.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data, $validateCaptcha = true)
    {
        $rules = [
            'first_name' => ['required','string','min:2','max:50','regex:/^[\p{Cyrillic}A-Za-z][\p{Cyrillic}A-Za-z \'\-]{1,49}$/u'],
            'last_name' => ['required','string','min:2','max:50','regex:/^[\p{Cyrillic}A-Za-z][\p{Cyrillic}A-Za-z \'\-]{1,49}$/u'],
            'group' => 'required|integer',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|confirmed|min:6',
            'website' => 'max:0',
        ];

        if ($validateCaptcha) {
            $rules['g-recaptcha-response'] = 'required|captcha';
        }

        $v = Validator::make(
            $data,
            $rules,
            [
                'first_name.regex'              => 'Имя должно содержать только буквы, пробел, дефис или апостроф.',
                'last_name.regex'               => 'Фамилия должна содержать только буквы, пробел, дефис или апостроф.',
                'group.required'                 => 'Выберите учебную группу.',
                'g-recaptcha-response.required' => 'Подтвердите, что вы не робот.',
                'g-recaptcha-response.captcha'  => 'Проверка reCAPTCHA не пройдена. Попробуйте ещё раз.',
                'website.max'                    => 'Регистрация отклонена.',
            ]
        );

        $v->after(function ($v) use ($data) {
            foreach (['first_name', 'last_name'] as $f) {
                if (!empty($data[$f]) && preg_match('/(.)\1{3,}/u', $data[$f])) {
                    $v->errors()->add($f, 'Слишком много повторяющихся символов.');
                }
            }

            if (!empty($data['group']) && !$this->registrationGroups()->contains('group_id', (int) $data['group'])) {
                $v->errors()->add('group', 'Выбранная группа недоступна для регистрации.');
            }
        });

        return $v;
    }

    private function registrationGroups()
    {
        return Group::where(function ($query) {
                $query->where(function ($academic) {
                    $academic->where('archived', 0)->where('academic', 1);
                })->orWhere('group_name', 'Админы');
            })
            ->whereNotIn('group_name', ['Преподаватель', 'Преподаватели'])
            ->orderBy('academic', 'asc')
            ->orderBy('group_name', 'asc')
            ->get(['group_id', 'group_name']);
    }

    private function captchaServiceAvailable()
    {
        $host = 'www.google.com';
        $resolved = @gethostbyname($host);

        return $resolved && $resolved !== $host;
    }

    private function captchaConfigured()
    {
        return (bool) config('captcha.secret') && (bool) config('captcha.sitekey');
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
