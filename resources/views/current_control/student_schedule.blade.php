@extends('templates.base')

@section('head')
    <title>Мое расписание</title>
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
            <h2 class="text-center">Расписание занятий {{ $group ? 'группы ' . $group->group_name : '' }}</h2>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>День</th>
                            <th>Время</th>
                            <th>Тип</th>
                            <th>Занятие</th>
                            <th>Преподаватель</th>
                            <th>Аудитория</th>
                            <th>Период</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedule as $item)
                            <tr class="{{ $item->status === 'canceled' ? 'danger' : ($item->status === 'replacement' ? 'warning' : '') }}">
                                <td>{{ isset($weekdays[$item->weekday]) ? $weekdays[$item->weekday] : '' }}</td>
                                <td>
                                    {{ $item->time_start ? substr($item->time_start, 0, 5) : '' }}
                                    @if($item->time_end)
                                        - {{ substr($item->time_end, 0, 5) }}
                                    @endif
                                </td>
                                <td>{{ isset($types[$item->type]) ? $types[$item->type] : $item->type }}</td>
                                <td>
                                    {{ $item->title }}
                                    @if($item->comment)
                                        <br><small class="text-muted">{{ $item->comment }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $item->teacher_name }}
                                    @if($item->replacement_teacher)
                                        <br><span class="text-warning">Ведет: {{ $item->replacement_teacher }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->auditorium }}</td>
                                <td>
                                    {{ $item->date_from }}
                                    @if($item->date_to)
                                        - {{ $item->date_to }}
                                    @endif
                                </td>
                                <td>{{ isset($statuses[$item->status]) ? $statuses[$item->status] : $item->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-muted">Расписание пока не заполнено.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop
