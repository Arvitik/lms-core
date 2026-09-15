@extends('templates.base')

@section('head')
    <title>Проверка заполнения ведомостей</title>
    <style>
        .checker-page { margin: 76px auto 30px; max-width: 1800px; }
        .checker-page .card-body { padding: 28px; }
        .checker-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
        .checker-table { table-layout: fixed; min-width: 1100px; margin-bottom: 0; }
        .checker-table th, .checker-table td { vertical-align: middle !important; text-align: center; }
        .checker-table .group-column { width: 125px; position: sticky; left: 0; z-index: 4; background: #fff; }
        .checker-table .count-column { width: 105px; }
        .checker-table .expected-column { width: 115px; }
        .checker-table .week-column { width: 155px; }
        .checker-table thead .group-column { background: #d9edf7; }
        .checker-cell { display: block; width: 100%; min-height: 58px; padding: 5px; border: 0; border-left: 4px solid #9ca3af; text-align: left; color: inherit; background: #f7f7f7; cursor: pointer; }
        .checker-cell:hover, .checker-cell:focus { box-shadow: inset 0 0 0 2px #0f918b; outline: none; }
        .checker-cell.is-filled { border-left-color: #2e9d55; background: #edf8f0; }
        .checker-cell.is-empty { border-left-color: #e2a500; background: #fff8dc; }
        .checker-cell.is-missing { border-left-color: #d64545; background: #fceeee; }
        .checker-cell-line { display: block; white-space: nowrap; font-size: 12px; line-height: 1.55; }
        .checker-cell-line strong { display: inline-block; width: 20px; }
        .checker-delta { display: block; margin-top: 4px; font-size: 11px; }
        .checker-actions { white-space: nowrap; }
        .lesson-detail-table td, .lesson-detail-table th { text-align: left !important; }
        .lesson-detail-table .status-present { color: #257942; font-weight: bold; }
        .lesson-detail-table .status-absent { color: #b33232; }
        .lesson-detail-table .status-missing { color: #8a6d3b; }
        @media (max-width: 800px) {
            .checker-page { margin-top: 58px; }
            .checker-page .card-body { padding: 16px; }
            .checker-toolbar { align-items: flex-start; flex-direction: column; }
        }
    </style>
@stop

@section('background')
    full
@stop

@section('content')
<div id="main_container">
    <div class="card checker-page">
        <div class="card-body">
            <div class="checker-toolbar">
                <h2 style="margin: 0;">Проверка заполнения ведомостей</h2>
                <a href="{{ route('current_control.dean_counts') }}" class="btn btn-primary btn-raised">Задать численность по спискам деканата</a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered checker-table">
                    <thead>
                        <tr class="info">
                            <th class="group-column">Группа</th>
                            <th class="count-column">На сайте</th>
                            <th class="expected-column">По списку</th>
                            @for($week = 1; $week <= $maxWeeks; $week++)
                                <th class="week-column">Неделя {{ $week }}</th>
                            @endfor
                            <th style="width: 95px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($matrixRows as $row)
                        @php
                            $checker = $row['checker'];
                            $delta = $checker['count_delta'];
                        @endphp
                        <tr>
                            <th class="group-column">{{ $row['group']->group_name }}</th>
                            <td>{{ $checker['registered_count'] }}</td>
                            <td>
                                <strong>{{ $checker['expected_count'] === null ? '—' : $checker['expected_count'] }}</strong>
                                @if($delta !== null)
                                    <span class="checker-delta {{ $delta == 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $delta == 0 ? 'совпадает' : (($delta > 0 ? '+' : '') . $delta) }}
                                    </span>
                                @endif
                            </td>
                            @for($index = 0; $index < $maxWeeks; $index++)
                                @php
                                    $lecture = isset($row['lectures'][$index]) ? $row['lectures'][$index] : null;
                                    $seminar = isset($row['seminars'][$index]) ? $row['seminars'][$index] : null;
                                    $missing = ($lecture && $lecture['missing_rows'] > 0) || ($seminar && $seminar['missing_rows'] > 0);
                                    $filled = ($lecture && $lecture['has_attendance_marks']) || ($seminar && ($seminar['has_attendance_marks'] || $seminar['has_work_marks']));
                                    $stateClass = $missing ? 'is-missing' : ($filled ? 'is-filled' : 'is-empty');
                                @endphp
                                <td>
                                    @if($lecture || $seminar)
                                        <button type="button" class="checker-cell checker-week-trigger {{ $stateClass }}" data-group-id="{{ $row['group']->group_id }}" data-week="{{ $index }}" aria-label="Открыть сведения за неделю {{ $index + 1 }} для группы {{ $row['group']->group_name }}">
                                            @if($lecture)
                                                <span class="checker-cell-line"><strong>Л:</strong> {{ $lecture['present_rows'] }}/{{ $lecture['expected_rows'] }} присутствуют</span>
                                            @else
                                                <span class="checker-cell-line text-muted"><strong>Л:</strong> нет</span>
                                            @endif
                                            @if($seminar)
                                                <span class="checker-cell-line"><strong>С:</strong> {{ $seminar['present_rows'] }}/{{ $seminar['expected_rows'] }}, баллы {{ $seminar['work_rows'] }}/{{ $seminar['expected_rows'] }}</span>
                                            @else
                                                <span class="checker-cell-line text-muted"><strong>С:</strong> нет</span>
                                            @endif
                                            @if($missing)
                                                <span class="checker-cell-line text-danger">Не созданы строки ведомости</span>
                                            @elseif(!$filled)
                                                <span class="checker-cell-line text-warning">Отметок пока нет</span>
                                            @endif
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endfor
                            <td class="checker-actions">
                                <a href="{{ route('current_control.index', array('group' => $row['group']->group_id, 'tab' => 'checker')) }}" class="btn btn-info btn-sm" title="Подробно"><i class="md md-open-in-new"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $maxWeeks + 4 }}">Нет доступных учебных групп.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="checker-week-modal" tabindex="-1" role="dialog" aria-labelledby="checker-week-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Закрыть"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="checker-week-title">Посещение за неделю</h4>
            </div>
            <div class="modal-body">
                <div id="checker-week-loading" class="text-center">Загрузка...</div>
                <div id="checker-week-error" class="alert alert-danger" style="display: none;"></div>
                <div id="checker-week-content" style="display: none;">
                    <p><strong>Преподаватель:</strong> <span id="checker-week-teachers"></span></p>
                    <div class="row">
                        <div class="col-md-6">
                            <h4 id="checker-lecture-title">Лекция</h4>
                            <div id="checker-lecture-list"></div>
                        </div>
                        <div class="col-md-6">
                            <h4 id="checker-seminar-title">Семинар</h4>
                            <div id="checker-seminar-list"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    function renderLesson(target, titleTarget, label, lesson, showPoints) {
        var $target = $(target).empty();
        if (!lesson.available) {
            $(titleTarget).text(label);
            $target.append($('<p class="text-muted"></p>').text('Занятие не предусмотрено.'));
            return;
        }

        $(titleTarget).text(label + ' ' + lesson.lesson + ' (раздел ' + lesson.section + ')');
        var $table = $('<table class="table table-condensed table-striped lesson-detail-table"><thead><tr><th style="width: 38px;">№</th><th>Студент</th><th>Статус</th></tr></thead><tbody></tbody></table>');
        if (showPoints) {
            $table.find('thead tr').append('<th style="width: 75px;">Баллы</th>');
        }

        $.each(lesson.students, function (index, student) {
            var status = student.has_record ? (student.present ? 'Присутствовал' : 'Отсутствовал') : 'Строка не создана';
            var statusClass = student.has_record ? (student.present ? 'status-present' : 'status-absent') : 'status-missing';
            var $row = $('<tr></tr>');
            $row.append($('<td></td>').text(index + 1));
            $row.append($('<td></td>').text(student.name));
            $row.append($('<td></td>').addClass(statusClass).text(status));
            if (showPoints) {
                $row.append($('<td></td>').text(student.work_points === null ? '—' : student.work_points));
            }
            $table.find('tbody').append($row);
        });
        $target.append($table);
    }

    $('.checker-week-trigger').on('click', function () {
        var $modal = $('#checker-week-modal');
        $('#checker-week-loading').show();
        $('#checker-week-error, #checker-week-content').hide();
        $modal.modal('show');

        $.get('{{ route('current_control.checker.week') }}', {
            group_id: $(this).data('group-id'),
            week: $(this).data('week')
        }).done(function (data) {
            $('#checker-week-title').text(data.group + ': неделя ' + data.week);
            var teacherNames = $.map(data.teachers || [], function (teacher) { return teacher.name; });
            $('#checker-week-teachers').text(teacherNames.length ? teacherNames.join(', ') : 'не назначен');
            renderLesson('#checker-lecture-list', '#checker-lecture-title', 'Лекция', data.lecture, false);
            renderLesson('#checker-seminar-list', '#checker-seminar-title', 'Семинар', data.seminar, true);
            $('#checker-week-loading').hide();
            $('#checker-week-content').show();
        }).fail(function () {
            $('#checker-week-loading').hide();
            $('#checker-week-error').text('Не удалось загрузить сведения о посещении.').show();
        });
    });
});
</script>
@stop
