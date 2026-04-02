@extends('templates.base')

@section('head')
    <title>Управление пользователями</title>
@endsection

@section('content')
<div class="container-fluid px-5">
    <h2>Пользователи</h2>

    {{-- Поисковая форма --}}
    <form method="GET" action="{{ route('manage_users') }}" class="form-inline" style="margin-bottom: 20px;">
        <div class="form-group">
            <input type="text" name="email" class="form-control" placeholder="Email" value="{{ request('email') }}">
        </div>
        <div class="form-group" style="margin-left: 10px;">
            <input type="text" name="last_name" class="form-control" placeholder="Фамилия" value="{{ request('last_name') }}">
        </div>
        <div class="form-group" style="margin-left: 10px;">
            <select name="group" class="form-control">
                <option value="">Все группы</option>
                @foreach($groups as $group)
                    <option value="{{ $group->group_id }}" @if(request('group') == $group->group_id) selected @endif>
                        {{ $group->group_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Найти</button>
    </form>

    {{-- Форма действий --}}
    <form method="POST" action="{{ route('users.bulk_action') }}">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">

        {{-- Кнопки действий --}}
        <div class="btn-toolbar mb-3" role="toolbar">
            <div class="btn-group" role="group">
                <button type="submit" name="action" value="delete" class="btn btn-danger me-2">Удалить</button>
                <button type="submit" name="action" value="make_monitor" class="btn btn-warning me-2">Сделать старостой</button>
                <button type="submit" name="action" value="set_teacher" class="btn btn-primary me-2">Сделать преподавателем</button>
                <button type="submit" name="action" value="set_average" class="btn btn-light me-2">Сделать обычным</button>
                <button type="submit" name="action" value="set_student" class="btn btn-success me-2">Сделать студентом</button>
                <button type="submit" name="action" value="set_senior_teacher" class="btn btn-info me-2">Сделать старшим преподавателем</button>
                <button type="submit" name="action" value="set_admin" class="btn me-2" style="background-color:#7e22ce; color:white;">Сделать админом</button>
            </div>
        </div>



        {{-- Таблица пользователей --}}
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Фамилия</th>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Группа</th>
                    <th>Роль</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td><input type="checkbox" name="selected_users[]" value="{{ $user->id }}"></td>
                    <td>{{ $user->last_name }}</td>
                    <td>{{ $user->first_name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->group_name }}</td>
                    <td>{{ $user->role }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </form>
</div>
@endsection

@section('js-down')
<script>
document.getElementById('select-all').addEventListener('click', function () {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>
@endsection
