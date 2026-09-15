<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/modern-theme.css') !!}
    @if($captchaEnabled)
        {!! NoCaptcha::renderJs() !!}
    @endif
    <style>
        body { background: #f4f7f8; color: #24313a; }
        .registration-page { max-width: 560px; margin: 40px auto; padding: 0 16px; }
        .registration-panel { background: #fff; border: 1px solid #dce3e7; padding: 28px; }
        .registration-panel h1 { margin: 0 0 24px; font-size: 28px; }
        .registration-actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 22px; }
        .registration-actions .btn { min-width: 160px; }
        .honeypot { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        @media (max-width: 520px) {
            .registration-actions { align-items: stretch; flex-direction: column; }
            .registration-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
<main class="registration-page">
    <div class="registration-panel">
        <h1>Регистрация</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="list-unstyled" style="margin: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            {!! csrf_field() !!}

            <div class="form-group">
                <label for="last_name">Фамилия</label>
                <input id="last_name" class="form-control" type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="50" autocomplete="family-name">
            </div>

            <div class="form-group">
                <label for="first_name">Имя</label>
                <input id="first_name" class="form-control" type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="50" autocomplete="given-name">
            </div>

            <div class="form-group">
                <label for="email">Электронная почта</label>
                <input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
            </div>

            <div class="form-group">
                <label for="group">Группа</label>
                <select id="group" class="form-control" name="group" required>
                    <option value="">Выберите группу</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->group_id }}" {{ (string) old('group') === (string) $group->group_id ? 'selected' : '' }}>
                            {{ $group->group_name }}
                        </option>
                    @endforeach
                </select>
                <p class="help-block">Преподаватели регистрируются в группе «Админы».</p>
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input id="password" class="form-control" type="password" name="password" required minlength="6" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="password_confirmation">Повторите пароль</label>
                <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" required minlength="6" autocomplete="new-password">
            </div>

            <div class="honeypot" aria-hidden="true">
                <label for="website">Не заполняйте это поле</label>
                <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            @if($captchaEnabled)
                <div class="form-group">
                    {!! NoCaptcha::display() !!}
                </div>
            @endif

            <div class="registration-actions">
                <a href="{{ route('home') }}">Вернуться на главную</a>
                <button class="btn btn-primary" type="submit">Зарегистрироваться</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
