@extends('templates.base')
@section('head')
    <meta name="csrf_token" content="{{ csrf_token() }}" />
    <title>Администрирование</title>
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
    {!! HTML::style('css/full.css') !!}
    {!! HTML::style('css/tests_list.css') !!}
    <style>
        .admin-dashboard { max-width: 1180px; margin: 28px auto 48px; padding: 0 20px; }
        .admin-dashboard > h2 { margin: 0 0 24px; color: #17384b; font-size: 30px; font-weight: 500; }
        .admin-section-title { clear: both; margin: 28px 0 12px; padding: 0; border-bottom: 2px solid #0d827e; }
        .admin-section-title h2 { margin: 0 0 8px; color: #17384b; font-size: 20px; font-weight: 600; }
        .admin-links { clear: both; margin: 0; padding: 0; }
        .admin-links .card-body { padding: 0; }
        .admin-links .list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 0; padding: 0; }
        .admin-links .tile { min-height: 64px; border: 1px solid #d7e0e4; background: #fff; list-style: none; }
        .admin-links .tile-content { display: flex; align-items: center; min-height: 64px; padding: 12px 16px; color: #17384b; text-decoration: none; }
        .admin-links .tile-content:hover, .admin-links .tile-content:focus { border-left: 4px solid #0d827e; background: #f1f8f7; color: #075f5c; }
        .admin-links .tile-text { width: 100%; font-size: 15px; line-height: 1.35; }
        @media (max-width: 900px) { .admin-links .list { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .admin-dashboard { padding: 0 12px; } .admin-links .list { grid-template-columns: 1fr; } }
    </style>
@stop

@section('background')
    full
@stop

@section('content')
    <div class="admin-dashboard">
        <h2 class="text-center">Панель управления системой</h2>
            <div class="admin-section-title">
                <h2 class="text-default-bright">Общие административные функции</h2>
            </div>
            <div class="admin-links">
                <div class="card-body no-padding">
                    <ul class="list divider-full-bleed">
                        <li class="tile">
                            <a href="{{ route('statements')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Работа с ведомостями
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('current_control.schedule') }}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Задать расписание группы
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('current_control.checker') }}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Чекер заполнения ведомостей
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('course_plans')}}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Работа с учебными планами
                                </div>
                            </a>
                        </li>
                        @if(Auth::user()['role'] == 'Админ')
                        <li class="tile">
                            <a href="{{ route('change_role')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Работа с пользователями
                            </div>
                            </a>
                        </li>
                        @endif
                        @if(Auth::user()['role'] == 'Админ')
                        <li class="tile">
                            <a href="{{ route('student_info')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Просмотр личного кабинета
                            </div>
                            </a>
                        </li>
                        @endif
                        @if(Auth::user()['role'] == 'Админ')
                        <li class="tile">
                            <a href="{{ route('manage_groups')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Назначить группы
                            </div>
                            </a>
                        </li>
                        @endif
                        @if(Auth::user()['role'] == 'Админ')
                        <li class="tile">
                            <a href="{{ route('manage_news')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Редактировать новости
                            </div>
                            </a>
                        </li>
                        @endif
                        @if(Auth::user()['role'] == 'Админ')
                            <li class="tile">
                                <a href="{{ route('group_set')}}" class="tile-content ink-reaction">
                                    <div class="tile-text">
                                        Редактировать список групп
                                    </div>
                                </a>
                            </li>
                        @endif
                        @if(in_array(Auth::user()['role'], ['Админ', 'Преподаватель']))
                        <li class="tile">
                            <a href="{{ route('lectures.limitForm') }}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Контроль посещаемости
                                </div>
                            </a>
                        </li>
                        @endif
                        @if(in_array(Auth::user()['role'], ['Админ', 'Преподаватель']))
                        {{-- ДИПЛОМ: Сообщения/чат — раскомментировать при разработке диплома
                        <li class="tile">
                            <a href="{{ route('messages.index') }}" class="tile-content ink-reaction">
                                <div class="tile-text">Сообщения от студентов</div>
                            </a>
                        </li>
                        --}}
                        <li class="tile">
                            <a href="{{ route('broadcast.create') }}" class="tile-content ink-reaction">
                                <div class="tile-text">Уведомления и контрольные работы</div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('schedule_board.index') }}" class="tile-content ink-reaction">
                                <div class="tile-text">Информационное табло</div>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div><!--end .card-body -->
            </div>
            <div class="admin-section-title">
                <h2 class="text-default-bright">Модуль тестирования</h2>
            </div>
            <div class="admin-links">
                <div class="card-body no-padding">
                    <ul class="list divider-full-bleed">
                        <li class="tile">
                            <a href="{{ route('question_create')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Добавление вопросов
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('questions_list')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Список всех вопросов
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('test_create')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Добавление тестов
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('tests_list')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Список всех тестов
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('retest_index')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Переписывание тестов
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('all_test_results')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Результаты тестирования
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('generator_index')}}" class="tile-content ink-reaction">
                            <div class="tile-text">
                                Генерация печатных тестов
                            </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('students_level')}}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Уровень подготовки студентов
                                </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('adaptive_test_params')}}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Пересчет параметров адаптивной модели
                                </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('edit_mt_params')}}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Редактирование параметров подсчета баллов для задач на эмулятор Тьюринга
                                </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('edit_ham_params')}}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Редактирование параметров подсчета баллов для задач на эмулятор Маркова
                                </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('testing.reports') }}" class="tile-content ink-reaction">
                                <div class="tile-text">Отчёты</div>
                            </a>
                        </li>

                        <li class="tile">
                            <a href="{{ route('group_statistics.index') }}" class="tile-content ink-reaction">
                                <div class="tile-text">Статистика по учебным группам</div>
                            </a>
                        </li>
                    </ul>
                </div><!--end .card-body -->
            </div>
            <div class="admin-section-title">
                <h2 class="text-default-bright">Электронная библиотека</h2>
            </div>
            <div class="admin-links">
                <div class="card-body no-padding">
                    <ul class="list divider-full-bleed">
                        <li class="tile">
                            <a href="{{ route('library_calendar')}}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Задать даты лекций
                                </div>
                            </a>
                        </li>
                        <li class="tile">
                            <a href="{{ route('teacher_сabinet')}}" class="tile-content ink-reaction">
                                <div class="tile-text">
                                    Бронирование печатных изданий
                                </div>
                            </a>
                        </li>
                    </ul>
                </div><!--end .card-body -->
            </div>
    </div>

@stop
