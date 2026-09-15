@extends('templates.base')

@section('head')
    <title>&#1056;&#1072;&#1089;&#1087;&#1080;&#1089;&#1072;&#1085;&#1080;&#1077;</title>
@stop

@section('content')
@php
    $weekdays = array(1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье');
    $types = array('lecture' => 'Лекция', 'seminar' => 'Семинар', 'control' => 'Контроль');
    $statuses = array('active' => 'По расписанию', 'canceled' => 'Отменено', 'moved' => 'Перенесено', 'replacement' => 'Замена преподавателя');
@endphp

<div id="main_container">
    <div class="card col-lg-12 col-md-12">
        <div class="card-body">
            <h2 class="text-center">&#1056;&#1072;&#1089;&#1087;&#1080;&#1089;&#1072;&#1085;&#1080;&#1077;</h2>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="GET" action="{{ route('current_control.index') }}" class="form-inline" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="group">Группа</label>
                    <select name="group" id="group" class="form-control">
                        @foreach($groups as $group)
                            <option value="{{ $group->group_id }}" {{ (int)$selectedGroupId === (int)$group->group_id ? 'selected' : '' }}>
                                {{ $group->group_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <button type="submit" class="btn btn-primary btn-raised">Показать</button>
            </form>

            @if (!$selectedGroup)
                <div class="alert alert-warning">Нет доступных учебных групп.</div>
            @else
                @if ($activeTab === 'schedule')
                    <div class="panel panel-default" style="margin-top: 20px;">
                        <div class="panel-heading">
                            <strong>Загрузить расписание из PDF/HTML/TXT</strong>
                        </div>
                        <div class="panel-body">
                            <form method="POST" action="{{ route('current_control.schedule.import') }}" enctype="multipart/form-data">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="group_id" value="{{ $selectedGroupId }}">

                                <div class="form-group">
                                    <label>Файлы расписаний</label>
                                    <input type="file" name="schedule_files[]" class="form-control" accept=".pdf,.html,.htm,.txt" multiple>
                                </div>

                                <div class="form-group">
                                    <label>Или вставьте текст страницы расписания</label>
                                    <textarea name="schedule_text" class="form-control" rows="5" placeholder="Можно вставить текст из расписания, если PDF не распознался"></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary btn-raised">Распознать и загрузить</button>
                            </form>
                        </div>
                    </div>

                    <div class="panel panel-default" style="margin-top: 20px;">
                        <div class="panel-heading text-center">
                            <strong>&#1044;&#1086;&#1073;&#1072;&#1074;&#1080;&#1090;&#1100; &#1079;&#1072;&#1085;&#1103;&#1090;&#1080;&#1077; &#1074;&#1088;&#1091;&#1095;&#1085;&#1091;&#1102;</strong>
                        </div>
                        <div class="panel-body">
                            <form method="POST" action="{{ route('current_control.schedule.store') }}">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="group_id" value="{{ $selectedGroupId }}">

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1044;&#1077;&#1085;&#1100; &#1085;&#1077;&#1076;&#1077;&#1083;&#1080;</label>
                                            <select name="weekday" class="form-control">
                                                <option value=""></option>
                                                @foreach($weekdays as $dayId => $dayName)
                                                    <option value="{{ $dayId }}">{{ $dayName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1042;&#1088;&#1077;&#1084;&#1103; &#1085;&#1072;&#1095;&#1072;&#1083;&#1072;</label>
                                            <input type="time" name="time_start" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1042;&#1088;&#1077;&#1084;&#1103; &#1086;&#1082;&#1086;&#1085;&#1095;&#1072;&#1085;&#1080;&#1103;</label>
                                            <input type="time" name="time_end" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1058;&#1080;&#1087;</label>
                                            <select name="type" class="form-control" required>
                                                @foreach($types as $typeId => $typeName)
                                                    <option value="{{ $typeId }}">{{ $typeName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>&#1053;&#1072;&#1079;&#1074;&#1072;&#1085;&#1080;&#1077; &#1079;&#1072;&#1085;&#1103;&#1090;&#1080;&#1103;</label>
                                            <input type="text" name="title" class="form-control" maxlength="255" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1055;&#1088;&#1077;&#1087;&#1086;&#1076;&#1072;&#1074;&#1072;&#1090;&#1077;&#1083;&#1100;</label>
                                            <input type="text" name="teacher_name" class="form-control" maxlength="255">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1040;&#1091;&#1076;&#1080;&#1090;&#1086;&#1088;&#1080;&#1103;</label>
                                            <input type="text" name="auditorium" class="form-control" maxlength="100">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1044;&#1072;&#1090;&#1072; &#1085;&#1072;&#1095;&#1072;&#1083;&#1072; / &#1076;&#1072;&#1090;&#1072; &#1079;&#1072;&#1085;&#1103;&#1090;&#1080;&#1103;</label>
                                            <input type="date" name="date_from" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1044;&#1072;&#1090;&#1072; &#1086;&#1082;&#1086;&#1085;&#1095;&#1072;&#1085;&#1080;&#1103;</label>
                                            <input type="date" name="date_to" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1057;&#1090;&#1072;&#1090;&#1091;&#1089;</label>
                                            <select name="status" class="form-control">
                                                @foreach($statuses as $statusId => $statusName)
                                                    <option value="{{ $statusId }}">{{ $statusName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&#1047;&#1072;&#1084;&#1077;&#1085;&#1103;&#1102;&#1097;&#1080;&#1081; &#1087;&#1088;&#1077;&#1087;&#1086;&#1076;&#1072;&#1074;&#1072;&#1090;&#1077;&#1083;&#1100;</label>
                                            <input type="text" name="replacement_teacher" class="form-control" maxlength="255">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>&#1050;&#1086;&#1084;&#1084;&#1077;&#1085;&#1090;&#1072;&#1088;&#1080;&#1081;</label>
                                    <input type="text" name="comment" class="form-control">
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-success btn-raised">&#1044;&#1086;&#1073;&#1072;&#1074;&#1080;&#1090;&#1100; &#1074; &#1088;&#1072;&#1089;&#1087;&#1080;&#1089;&#1072;&#1085;&#1080;&#1077;</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="panel panel-info" style="margin-top: 20px;">
                        <div class="panel-heading">
                            <strong>Актуальное расписание группы {{ $selectedGroup->group_name }}</strong>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>День</th>
                                        <th>Время</th>
                                        <th>Тип</th>
                                        <th>Название</th>
                                        <th>Преподаватель</th>
                                        <th>Аудитория</th>
                                        <th>Период</th>
                                        <th>Статус/замена</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($schedule as $item)
                                        <tr class="{{ $item->status === 'canceled' ? 'danger' : ($item->status === 'replacement' ? 'warning' : '') }}">
                                            <form method="POST" action="{{ route('current_control.schedule.update', $item->id) }}">
                                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                <input type="hidden" name="_method" value="PATCH">
                                                <td>
                                                    <select name="weekday" class="form-control">
                                                        <option value=""></option>
                                                        @foreach($weekdays as $dayId => $dayName)
                                                            <option value="{{ $dayId }}" {{ (int)$item->weekday === (int)$dayId ? 'selected' : '' }}>{{ $dayName }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="time" name="time_start" value="{{ $item->time_start ? substr($item->time_start, 0, 5) : '' }}" class="form-control">
                                                    <input type="time" name="time_end" value="{{ $item->time_end ? substr($item->time_end, 0, 5) : '' }}" class="form-control" style="margin-top: 4px;">
                                                </td>
                                                <td>
                                                    <select name="type" class="form-control">
                                                        @foreach($types as $typeId => $typeName)
                                                            <option value="{{ $typeId }}" {{ $item->type === $typeId ? 'selected' : '' }}>{{ $typeName }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="title" value="{{ $item->title }}" class="form-control">
                                                    <input type="text" name="comment" value="{{ $item->comment }}" class="form-control" placeholder="Комментарий" style="margin-top: 4px;">
                                                </td>
                                                <td><input type="text" name="teacher_name" value="{{ $item->teacher_name }}" class="form-control"></td>
                                                <td><input type="text" name="auditorium" value="{{ $item->auditorium }}" class="form-control"></td>
                                                <td>
                                                    <input type="date" name="date_from" value="{{ $item->date_from }}" class="form-control">
                                                    <input type="date" name="date_to" value="{{ $item->date_to }}" class="form-control" style="margin-top: 4px;">
                                                </td>
                                                <td>
                                                    <select name="status" class="form-control">
                                                        @foreach($statuses as $statusId => $statusName)
                                                            <option value="{{ $statusId }}" {{ $item->status === $statusId ? 'selected' : '' }}>{{ $statusName }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" name="replacement_teacher" value="{{ $item->replacement_teacher }}" class="form-control" placeholder="Кто ведет вместо" style="margin-top: 4px;">
                                                </td>
                                                <td>
                                                    <button type="submit" class="btn btn-primary btn-xs">Сохранить</button>
                                            </form>
                                                    <form method="POST" action="{{ route('current_control.schedule.delete', $item->id) }}" style="margin-top: 4px;">
                                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                        <input type="hidden" name="_method" value="DELETE">
                                                        <button type="submit" class="btn btn-danger btn-xs">Удалить</button>
                                                    </form>
                                                </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-muted">Расписание для группы пока не заполнено.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-10 col-md-offset-1">
                            <div class="panel panel-default">
                                <div class="panel-heading text-center">
                                    <strong>Сверка со списком деканата</strong>
                                </div>
                                <div class="panel-body">
                                    <form method="POST" action="{{ route('current_control.dean_count.save') }}">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="hidden" name="group_id" value="{{ $selectedGroupId }}">
                                        <div class="form-group">
                                            <label>Студентов по списку деканата</label>
                                            <input type="number" name="expected_count" class="form-control" min="0" value="{{ $deanCount ? $deanCount->expected_count : '' }}" required>
                                        </div>
                                        <button type="submit" class="btn btn-success btn-raised">Сохранить число</button>
                                    </form>
                                </div>
                            </div>
                            @if($checker)
                                <div class="panel panel-info" style="margin-top: 20px;">
                                    <div class="panel-heading text-center"><strong>Чекер заполнения ведомостей</strong></div>
                                    <table class="table table-bordered text-center" style="text-align: center;">
                                        <tbody>
                                            <tr><th class="text-center">Зарегистрировано студентов на сайте</th><td>{{ $checker['registered_count'] }}</td></tr>
                                            <tr><th class="text-center">По списку деканата</th><td>{{ $checker['expected_count'] === null ? 'Не задано' : $checker['expected_count'] }}</td></tr>
                                            <tr>
                                                <th class="text-center">Расхождение</th>
                                                <td>
                                                    @if($checker['count_delta'] === null)
                                                        <span class="text-muted">Нет данных</span>
                                                    @elseif($checker['count_delta'] == 0)
                                                        <span class="text-success">Совпадает</span>
                                                    @else
                                                        <span class="text-danger">{{ $checker['count_delta'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <ul class="nav nav-tabs nav-justified" style="margin-top: 18px; text-align: center;">
                                        <li class="active"><a href="#checker-lectures" data-toggle="tab">&#1051;&#1077;&#1082;&#1094;&#1080;&#1080;</a></li>
                                        <li><a href="#checker-seminars" data-toggle="tab">&#1057;&#1077;&#1084;&#1080;&#1085;&#1072;&#1088;&#1099;</a></li>
                                    </ul>
                                    <div class="tab-content" style="padding-top: 16px; text-align: center;">
                                        <div class="tab-pane active" id="checker-lectures">
                                    <div style="margin: 18px 0 10px; text-align: center;">
                                        <a href="{{ route('statements') }}" class="btn btn-primary btn-sm btn-raised">
                                            &#1054;&#1090;&#1082;&#1088;&#1099;&#1090;&#1100; &#1088;&#1072;&#1073;&#1086;&#1090;&#1091; &#1089; &#1074;&#1077;&#1076;&#1086;&#1084;&#1086;&#1089;&#1090;&#1103;&#1084;&#1080;
                                        </a>
                                    </div>

                                    @forelse($checker['lecture_sections'] as $section)
                                        <div class="panel panel-default" style="margin: 0 auto 12px; text-align: center;">
                                            <div class="panel-heading text-center"><strong>&#1056;&#1072;&#1079;&#1076;&#1077;&#1083; {{ $section['section_num'] }}</strong></div>
                                            <table class="table table-bordered table-hover text-center" style="margin-bottom: 0; text-align: center;">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="width: 90px;">&#1053;&#1086;&#1084;&#1077;&#1088;</th>
                                                        <th class="text-center">&#1047;&#1072;&#1087;&#1080;&#1089;&#1077;&#1081; &#1074; &#1074;&#1077;&#1076;&#1086;&#1084;&#1086;&#1089;&#1090;&#1080;</th>
                                                        <th class="text-center">&#1054;&#1090;&#1084;&#1077;&#1095;&#1077;&#1085;&#1086; &#1087;&#1088;&#1080;&#1089;&#1091;&#1090;&#1089;&#1090;&#1074;&#1091;&#1102;&#1097;&#1080;&#1093;</th>
                                                        <th class="text-center">&#1057;&#1090;&#1072;&#1090;&#1091;&#1089;</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($section['items'] as $item)
                                                        <tr>
                                                            <td>{{ $item['plan_num'] }}</td>
                                                            <td>{{ $item['filled_rows'] }} / {{ $item['expected_rows'] }}</td>
                                                            <td>{{ $item['present_rows'] }}</td>
                                                            <td>
                                                                @if($item['missing_rows'] > 0)
                                                                    <span class="label label-danger">&#1085;&#1077; &#1089;&#1086;&#1079;&#1076;&#1072;&#1085;&#1086; {{ $item['missing_rows'] }}</span>
                                                                @elseif($item['has_attendance_marks'])
                                                                    <span class="label label-success">&#1077;&#1089;&#1090;&#1100; &#1086;&#1090;&#1084;&#1077;&#1090;&#1082;&#1080;</span>
                                                                @else
                                                                    <span class="label label-warning">&#1086;&#1090;&#1084;&#1077;&#1090;&#1086;&#1082; &#1085;&#1077;&#1090;</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @empty
                                        <div class="alert alert-warning">&#1042; &#1091;&#1095;&#1077;&#1073;&#1085;&#1086;&#1084; &#1087;&#1083;&#1072;&#1085;&#1077; &#1085;&#1077;&#1090; &#1083;&#1077;&#1082;&#1094;&#1080;&#1081;.</div>
                                    @endforelse

                                        </div>
                                        <div class="tab-pane" id="checker-seminars">

                                    @forelse($checker['seminar_sections'] as $section)
                                        <div class="panel panel-default" style="margin: 0 auto 12px; text-align: center;">
                                            <div class="panel-heading text-center"><strong>&#1056;&#1072;&#1079;&#1076;&#1077;&#1083; {{ $section['section_num'] }}</strong></div>
                                            <table class="table table-bordered table-hover text-center" style="margin-bottom: 0; text-align: center;">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="width: 90px;">&#1053;&#1086;&#1084;&#1077;&#1088;</th>
                                                        <th class="text-center">&#1047;&#1072;&#1087;&#1080;&#1089;&#1077;&#1081; &#1074; &#1074;&#1077;&#1076;&#1086;&#1084;&#1086;&#1089;&#1090;&#1080;</th>
                                                        <th class="text-center">&#1054;&#1090;&#1084;&#1077;&#1095;&#1077;&#1085;&#1086; &#1087;&#1088;&#1080;&#1089;&#1091;&#1090;&#1089;&#1090;&#1074;&#1091;&#1102;&#1097;&#1080;&#1093;</th>
                                                        <th class="text-center">&#1053;&#1077;&#1085;&#1091;&#1083;&#1077;&#1074;&#1099;&#1093; &#1086;&#1094;&#1077;&#1085;&#1086;&#1082;</th>
                                                        <th class="text-center">&#1057;&#1090;&#1072;&#1090;&#1091;&#1089;</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($section['items'] as $item)
                                                        <tr>
                                                            <td>{{ $item['plan_num'] }}</td>
                                                            <td>{{ $item['filled_rows'] }} / {{ $item['expected_rows'] }}</td>
                                                            <td>{{ $item['present_rows'] }}</td>
                                                            <td>{{ $item['work_rows'] }}</td>
                                                            <td>
                                                                @if($item['missing_rows'] > 0)
                                                                    <span class="label label-danger">&#1085;&#1077; &#1089;&#1086;&#1079;&#1076;&#1072;&#1085;&#1086; {{ $item['missing_rows'] }}</span>
                                                                @elseif($item['has_attendance_marks'] || $item['has_work_marks'])
                                                                    <span class="label label-success">&#1077;&#1089;&#1090;&#1100; &#1079;&#1072;&#1087;&#1086;&#1083;&#1085;&#1077;&#1085;&#1080;&#1077;</span>
                                                                @else
                                                                    <span class="label label-warning">&#1086;&#1090;&#1084;&#1077;&#1090;&#1086;&#1082; &#1080; &#1086;&#1094;&#1077;&#1085;&#1086;&#1082; &#1085;&#1077;&#1090;</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @empty
                                        <div class="alert alert-warning">&#1042; &#1091;&#1095;&#1077;&#1073;&#1085;&#1086;&#1084; &#1087;&#1083;&#1072;&#1085;&#1077; &#1085;&#1077;&#1090; &#1089;&#1077;&#1084;&#1080;&#1085;&#1072;&#1088;&#1086;&#1074;.</div>
                                    @endforelse
                                        </div>
                                    </div>
                                </div>

                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@stop
