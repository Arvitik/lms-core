@extends('templates.base')

@section('head')
    <title>Просмотр сообщения</title>
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
@stop

@section('content')
    <div class="container mt-4">
        <h2>Сообщение от {{ $msg->fromUser->last_name }} {{ $msg->fromUser->first_name }}</h2>

        <div class="card card-bordered style-default-light" style="margin-top:20px;">
            <div class="card-head">
                <header>
                    <i class="fa fa-envelope-open fa-fw"></i>&nbsp;
                    @if($msg->subject)
                        {{ $msg->subject }}
                    @else
                        <em>Без темы</em>
                    @endif
                </header>
            </div>
            <div class="card-body style-default-bright">
                <p>{!! nl2br(e($msg->body)) !!}</p>
                <hr>
                <p>
                    <small>Отправлено: {{ $msg->created_at->format('d.m.Y H:i') }}</small>
                </p>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('messages.index') }}" class="btn btn-default">
                ← Вернуться к списку
            </a>

            {{-- Кнопка «Удалить» --}}
            <form action="{{ route('messages.destroy', $msg->id) }}" method="POST" style="display:inline;">
                {{ csrf_field() }}
                {{ method_field('DELETE') }}
                <button class="btn btn-danger" onclick="return confirm('Удалить сообщение?');">
                    Удалить
                </button>
            </form>
        </div>
    </div>
@stop
