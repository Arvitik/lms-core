<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/full.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
    <title>Algorithms theory LMS</title>
</head>
<body class="full-no_access">
<div class="container" style="margin-top:40px;">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <div class="panel panel-default">
                <div class="panel-heading">Восстановление пароля</div>
                <div class="panel-body">
                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif
                    @if (count($errors) > 0)
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif
                    <form method="POST" action="{{ route('passEmailPost') }}">
                        {!! csrf_field() !!}
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Отправить ссылку</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
