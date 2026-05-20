@extends('templates.base')
@section('head')
    <title>Работа с пользователями</title>
    {!! HTML::style('css/loading_blur.css') !!}
    <meta name="csrf_token" content="{{ csrf_token() }}" />
    <style>
        .users-card {
            margin: 80px auto 30px;
            max-width: 1360px;
        }
        .users-card .card-body {
            padding: 34px 28px 38px;
        }
        .users-toolbar {
            display: grid;
            grid-template-columns: minmax(220px, 1.1fr) minmax(220px, 1fr) minmax(220px, 1fr) auto;
            gap: 18px 14px;
            align-items: end;
            margin: 26px 0 20px;
        }
        .users-toolbar .form-group {
            min-width: 0;
            margin-bottom: 0;
            padding-top: 18px;
        }
        .users-toolbar label {
            top: -2px;
            left: 0;
            color: #6b7280;
            font-size: 12px;
        }
        .users-toolbar .form-control {
            height: 38px;
            line-height: 38px;
            padding: 7px 10px;
        }
        .users-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .users-table {
            table-layout: fixed;
            width: 100%;
        }
        .users-table th,
        .users-table td {
            vertical-align: middle !important;
        }
        .users-table .check-col { width: 42px; text-align: center; }
        .users-table .group-col { width: 170px; }
        .users-table .name-col { width: 170px; }
        .users-table .email-col { width: 260px; }
        .users-table .role-col { width: 150px; }
        .users-table thead th {
            height: 42px;
            padding: 10px 8px;
            white-space: nowrap;
        }
        .users-table tbody td {
            padding: 8px;
        }
        .users-table input[type="text"],
        .users-table select {
            width: 100%;
            min-width: 0;
            height: 32px;
            padding: 5px 7px;
        }
        .email-cell {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .empty-users {
            padding: 32px;
            color: #777;
            text-align: center;
        }
        @media (max-width: 1100px) {
            .users-toolbar {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 700px) {
            .users-toolbar {
                grid-template-columns: 1fr;
            }
            .users-toolbar .btn {
                width: 100%;
            }
        }
    </style>
@stop

@section('background')
    full
@stop

@section('content')
    <div id="main_container">
        <div class="card users-card">
            <div class="card-body">
                <div class="alert alert-danger print-error-msg" style="display:none">
                    <ul></ul>
                </div>

                <h2 class="text-center">Работа с пользователями</h2>

                <form method="GET" action="{{ route('change_role') }}" class="users-toolbar">
                    <div class="form-group">
                        <select name="group" id="groupSearch" class="form-control">
                            <option value="">Только новые регистрации</option>
                            @foreach($groups as $group)
                                <option value="{{ $group['group_id'] }}" {{ request('group') == $group['group_id'] ? 'selected' : '' }}>
                                    {{ $group['group_name'] }}
                                </option>
                            @endforeach
                        </select>
                        <label for="groupSearch">Группа</label>
                    </div>
                    <div class="form-group">
                        <input type="text" name="email" id="emailInput" class="form-control" value="{{ request('email') }}" placeholder="Введите email">
                        <label for="emailInput">Email</label>
                    </div>
                    <div class="form-group">
                        <input type="text" name="last_name" id="nameInput" class="form-control" value="{{ request('last_name') }}" placeholder="Введите фамилию">
                        <label for="nameInput">Фамилия</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-raised">Найти</button>
                </form>

                <form method="POST" action="{{ route('users.bulk_action') }}" class="form" id="forma">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">

                    <div class="users-actions">
                        <button type="submit" name="action" value="delete" class="btn btn-danger btn-raised">Удалить</button>
                        <button type="submit" name="action" value="set_student" class="btn btn-success btn-raised">Сделать студентом</button>
                        <button type="submit" name="action" value="make_monitor" class="btn btn-warning btn-raised">Сделать старостой</button>
                        <button type="submit" name="action" value="set_teacher" class="btn btn-primary btn-raised">Сделать преподавателем</button>
                        <button type="submit" name="action" value="set_average" class="btn btn-default btn-raised">Сделать обычным</button>
                        @if(Auth::user()['role'] == 'Админ')
                            <button type="submit" name="action" value="set_admin" class="btn btn-raised" style="background-color:#7e22ce;color:white;">Сделать админом</button>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-condensed table-bordered users-table">
                            <thead>
                                <tr class="info">
                                    <th class="check-col"><input type="checkbox" id="select-all"></th>
                                    <th class="group-col">Группа</th>
                                    <th class="name-col">Фамилия</th>
                                    <th class="name-col">Имя</th>
                                    <th class="email-col">Email</th>
                                    <th class="role-col">Роль</th>
                                </tr>
                            </thead>
                            <tbody id="target">
                            @forelse($query as $user)
                                <tr id="{{ $user['id'] }}">
                                    <td class="check-col">
                                        <input type="checkbox" name="selected_users[]" value="{{ $user['id'] }}">
                                    </td>
                                    <td>
                                        <select name="group-select" class="form-control" size="1" onchange="changeGroup(this, {{ $user['id'] }})">
                                            @foreach($groups as $group)
                                                <option value="{{ $group['group_id'] }}" {{ (int)$user->group === (int)$group['group_id'] ? 'selected' : '' }}>
                                                    {{ $group['group_name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" value="{{ $user['last_name'] }}" name="{{ $user['id'] }}" class="l_name_change">
                                    </td>
                                    <td>
                                        <input type="text" value="{{ $user['first_name'] }}" name="{{ $user['id'] }}" class="f_name_change">
                                    </td>
                                    <td class="email-cell" title="{{ $user['email'] }}">
                                        {{ $user['email'] }}
                                    </td>
                                    <td>
                                        {{ $user['role'] ?: 'Новая регистрация' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="empty-users">
                                        Нет пользователей для отображения.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="overlay" class="none">
        <div class="loading-pulse"></div>
    </div>
@stop

@section('js-down')
    {!! HTML::script('js/personal_account/change_user_role.js') !!}
    <script>
        (function () {
            var selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    var checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
                    for (var i = 0; i < checkboxes.length; i++) {
                        checkboxes[i].checked = selectAll.checked;
                    }
                });
            }

            var form = document.getElementById('forma');
            if (form) {
                form.addEventListener('submit', function (event) {
                    if (!document.querySelector('input[name="selected_users[]"]:checked')) {
                        event.preventDefault();
                        alert('Выберите хотя бы одного пользователя.');
                    }
                });
            }
        })();
    </script>
@stop
