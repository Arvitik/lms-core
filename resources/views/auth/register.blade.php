<!— resources/views/auth/register.blade.php —>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

<form method="POST" action="/auth/register">
    {!! csrf_field() !!}

    <div>
        First Name
        <input type="text" name="first_name" value="{{ old('first_name') }}">
        @if ($errors->has('first_name'))
            <div style="color: red;">{{ $errors->first('first_name') }}</div>
        @endif
    </div>

    <div>
        Last Name
        <input type="text" name="last_name" value="{{ old('last_name') }}">
        @if ($errors->has('last_name'))
            <div style="color: red;">{{ $errors->first('last_name') }}</div>
        @endif
    </div>

    <div>
        Email
        <input type="email" name="email" value="{{ old('email') }}">
        @if ($errors->has('email'))
            <div style="color: red;">{{ $errors->first('email') }}</div>
        @endif
    </div>

    <div>
        Group
        <input type="number" name="group" value="{{ old('group') }}">
        @if ($errors->has('group'))
            <div style="color: red;">{{ $errors->first('group') }}</div>
        @endif
    </div>

    <div>
        Password
        <input type="password" name="password">
        @if ($errors->has('password'))
            <div style="color: red;">{{ $errors->first('password') }}</div>
        @endif
    </div>

    <div>
        Confirm Password
        <input type="password" name="password_confirmation">
    </div>

    <div style="margin-top: 15px;">
        <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}"></div>
        @if ($errors->has('g-recaptcha-response'))
            <div style="color: red;">{{ $errors->first('g-recaptcha-response') }}</div>
        @endif
    </div>

    <div style="margin-top: 15px;">
        <button type="submit">Register</button>
    </div>
</form>

</body>
</html>

