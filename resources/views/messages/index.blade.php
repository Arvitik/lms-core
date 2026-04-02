@extends('templates.base')

@section('head')
    <title>Входящие сообщения</title>
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
@stop

@section('content')
<div class="container mt-4">
    <h2>Входящие сообщения</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($messages->isEmpty())
        <p>У вас нет входящих сообщений.</p>
    @else
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>От кого</th>
                    <th>Тема</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            @foreach($messages as $msg)
                <tr @if(!$msg->is_read) style="font-weight: bold;" @endif>
                    <td>
                        {{ $msg->fromUser->last_name }} {{ $msg->fromUser->first_name }}
                    </td>
                    <td>
                        <a href="{{ route('messages.show', $msg->id) }}">
                            {{ $msg->subject ?: 'Без темы' }}
                        </a>
                    </td>
                    <td>{{ $msg->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $msg->is_read ? 'Прочитано' : 'Непрочитано' }}</td>
                    <td>
                        @if(!$msg->is_read)
                            <form action="{{ route('messages.read', $msg->id) }}" method="POST" style="display:inline;">
                                {{ csrf_field() }}
                                <button class="btn btn-sm btn-primary">Отметить как прочитанное</button>
                            </form>
                        @endif
                        <form action="{{ route('messages.destroy', $msg->id) }}" method="POST" style="display:inline;">
                            {{ csrf_field() }}
                            {{ method_field('DELETE') }}
                            <button class="btn btn-sm btn-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <a href="{{ route('messages.create') }}" class="btn btn-success">Написать сообщение</a>
</div>
@stop

@section('js-down')
    {!! HTML::script('js/personal_account/messages.js') !!}
@stop
