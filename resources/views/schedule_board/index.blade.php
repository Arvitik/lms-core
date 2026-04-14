@extends('templates.base')

@section('head')
<title>Информационное табло</title>
{!! HTML::style('css/bootstrap.css') !!}
{!! HTML::style('css/materialadmin.css') !!}
{!! HTML::style('css/full.css') !!}
<style>
/* ===== Обёртка ===== */
.board-wrap {
    padding: 20px 10px;
}
.board-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.board-title {
    font-size: 22px;
    font-weight: 700;
    color: #1565C0;
    margin: 0;
}
.week-range {
    font-size: 15px;
    color: #555;
    margin-left: 10px;
}

/* ===== Таблица ===== */
.board-table-wrap {
    overflow-x: auto;
}
.board-table {
    border-collapse: collapse;
    min-width: 700px;
    width: 100%;
    background: #fff;
    box-shadow: 0 1px 6px rgba(0,0,0,.09);
    border-radius: 6px;
    overflow: hidden;
}
.board-table th {
    background: #1565C0;
    color: #fff;
    padding: 10px 12px;
    text-align: center;
    font-size: 13px;
    white-space: nowrap;
    border: 1px solid #1976D2;
}
.board-table th.col-teacher {
    text-align: left;
    min-width: 160px;
    background: #0D47A1;
}
.board-table td {
    border: 1px solid #e0e0e0;
    padding: 6px 8px;
    vertical-align: top;
}
.board-table td.col-teacher {
    font-weight: 600;
    font-size: 13px;
    color: #212121;
    background: #f5f7fa;
    white-space: nowrap;
}
.board-table tbody tr:hover td.col-teacher {
    background: #e8edf5;
}
.board-table td.board-cell {
    min-width: 110px;
    cursor: pointer;
    transition: background .15s;
}
.board-table td.board-cell:hover {
    background: #e3f2fd;
}
.board-table td.board-cell.today {
    background: #fffde7;
}
.board-table td.board-cell.today:hover {
    background: #fff9c4;
}

/* ===== Бейджи в ячейках ===== */
.entry-badge {
    display: inline-block;
    border-radius: 4px;
    padding: 2px 7px;
    font-size: 11px;
    font-weight: 600;
    margin: 1px 0;
    white-space: nowrap;
}
.entry-badge.lec {
    background: #E3F2FD;
    color: #0D47A1;
    border-left: 3px solid #1565C0;
}
.entry-badge.sem {
    background: #E8F5E9;
    color: #1B5E20;
    border-left: 3px solid #388E3C;
}
.entry-badge.zac {
    background: #FBE9E7;
    color: #BF360C;
    border-left: 3px solid #E65100;
}
.entry-badge.kr {
    background: #FCE4EC;
    color: #880E4F;
    border-left: 3px solid #C2185B;
}
.entry-time {
    display: block;
    font-size: 10px;
    font-weight: 400;
    color: #777;
    margin-top: 1px;
}

/* ===== Пустая ячейка ===== */
.cell-empty {
    color: #ccc;
    font-size: 11px;
    text-align: center;
}

/* ===== Модальное окно ===== */
#cell-modal-overlay {
    display: none;
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.55);
    z-index: 9998;
    align-items: center;
    justify-content: center;
}
#cell-modal-overlay.active { display: flex; }
#cell-modal-box {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 8px 40px rgba(0,0,0,.25);
    max-width: 640px;
    width: 96%;
    max-height: 82vh;
    overflow-y: auto;
    padding: 28px 30px;
    position: relative;
}
#cell-modal-close {
    position: absolute; top: 14px; right: 18px;
    font-size: 24px; cursor: pointer; color: #aaa;
    border: none; background: none; line-height: 1;
}
#cell-modal-close:hover { color: #333; }
#cell-modal-content { margin-top: 4px; }
.cell-modal-spinner {
    text-align: center;
    padding: 40px 0;
    color: #90A4AE;
    font-size: 15px;
}

/* ===== Флеш-сообщение ===== */
.board-alert {
    border-radius: 6px;
    padding: 10px 16px;
    margin-bottom: 14px;
    font-size: 14px;
}
</style>
@stop

@section('content')
<div class="board-wrap container-fluid">

    {{-- Флеш --}}
    @if(session('success'))
    <div class="board-alert alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Toolbar --}}
    <div class="board-toolbar">
        <h2 class="board-title">Информационное табло</h2>
        <a href="{{ route('schedule_board.index', ['week' => $prevWeek]) }}"
           class="btn btn-default btn-sm" title="Предыдущая неделя">
            &laquo; Пред.
        </a>
        <span class="week-range">
            {{ $weekStart->format('d.m.Y') }} — {{ $weekEnd->format('d.m.Y') }}
        </span>
        <a href="{{ route('schedule_board.index', ['week' => $nextWeek]) }}"
           class="btn btn-default btn-sm" title="Следующая неделя">
            След. &raquo;
        </a>
        <a href="{{ route('schedule_board.index') }}"
           class="btn btn-default btn-sm">Текущая неделя</a>

        @if($canEdit)
        <a href="{{ route('schedule_board.create') }}"
           class="btn btn-primary btn-sm" style="margin-left:auto;">
            + Добавить занятие
        </a>
        @endif
        @if(Auth::user()->role === 'Админ')
        <a href="{{ route('schedule_board.manage') }}"
           class="btn btn-default btn-sm" title="Управление преподавателями и администраторами">
            &#9881; Управление
        </a>
        @endif
    </div>

    {{-- Таблица --}}
    <div class="board-table-wrap">
        <table class="board-table">
            <thead>
                <tr>
                    <th class="col-teacher">Преподаватель</th>
                    @foreach($days as $day)
                    <th>
                        {{ ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'][$day->dayOfWeekIso - 1] }}<br>
                        <span style="font-weight:400;font-size:12px;">{{ $day->format('d.m') }}</span>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($teachers as $teacher)
                <tr>
                    <td class="col-teacher">
                        {{ $teacher->last_name }} {{ mb_substr($teacher->first_name, 0, 1) }}.
                    </td>
                    @foreach($days as $day)
                    @php
                        $ds       = $day->format('Y-m-d');
                        $cellEntries = $entryMap[$teacher->id][$ds] ?? [];
                        $isToday  = $day->isToday();
                    @endphp
                    <td class="board-cell {{ $isToday ? 'today' : '' }}"
                        data-teacher="{{ $teacher->id }}"
                        data-date="{{ $ds }}"
                        title="{{ $teacher->last_name }}, {{ $day->format('d.m.Y') }}">
                        @if(count($cellEntries))
                            @foreach($cellEntries as $e)
                            @php
                                $badgeMap = ['Лекция'=>'lec','Семинар'=>'sem','Зачет'=>'zac','КР'=>'kr'];
                                $letterMap = ['Лекция'=>'Л','Семинар'=>'С','Зачет'=>'З','КР'=>'КР'];
                                $badgeClass  = $badgeMap[$e->entry_type]  ?? 'sem';
                                $badgeLetter = $letterMap[$e->entry_type] ?? $e->entry_type;
                            @endphp
                            <div class="entry-badge {{ $badgeClass }}">
                                {{ $badgeLetter }} {{ $e->room }}
                                <span class="entry-time">
                                    {{ \Carbon\Carbon::parse($e->time_start)->format('H:i') }}
                                    @if($e->time_end)—{{ \Carbon\Carbon::parse($e->time_end)->format('H:i') }}@endif
                                </span>
                                @if($e->all_groups)
                                <span class="entry-time">Все группы</span>
                                @elseif($e->groups->isNotEmpty())
                                <span class="entry-time">{{ $e->groups->pluck('group_name')->implode(', ') }}</span>
                                @endif
                            </div>
                            @endforeach
                        @else
                            <span class="cell-empty">&ndash;</span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($days) + 1 }}" style="text-align:center;color:#aaa;padding:30px;">
                        Нет преподавателей в системе
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Легенда --}}
    <div style="margin-top:12px;font-size:12px;color:#666;display:flex;gap:16px;flex-wrap:wrap;">
        <span><span class="entry-badge lec">Л А101</span> — Лекция</span>
        <span><span class="entry-badge sem">С Г208</span> — Семинар</span>
        <span><span class="entry-badge zac">З Б305</span> — Зачет</span>
        <span><span class="entry-badge kr">КР А101</span> — Контрольная работа</span>
        <span style="color:#bbb;">Ячейки с жёлтым фоном — сегодня</span>
    </div>
</div>

{{-- Модальное окно деталей ячейки --}}
<div id="cell-modal-overlay">
    <div id="cell-modal-box">
        <button id="cell-modal-close" title="Закрыть">&times;</button>
        <div id="cell-modal-content">
            <div class="cell-modal-spinner">Загрузка...</div>
        </div>
    </div>
</div>
@stop

@section('js-down')
<script>
(function () {
    var overlay = document.getElementById('cell-modal-overlay');
    var content = document.getElementById('cell-modal-content');
    var closeBtn = document.getElementById('cell-modal-close');
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Клик по ячейке
    document.querySelectorAll('.board-cell').forEach(function (cell) {
        cell.addEventListener('click', function () {
            var teacherId = this.dataset.teacher;
            var date      = this.dataset.date;

            content.innerHTML = '<div class="cell-modal-spinner">Загрузка...</div>';
            overlay.classList.add('active');

            $.ajax({
                url: '{{ route("schedule_board.cell") }}',
                method: 'GET',
                data: { teacher_id: teacherId, date: date },
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function (resp) {
                    content.innerHTML = resp.html;
                },
                error: function () {
                    content.innerHTML = '<p style="color:#c00;text-align:center;">Ошибка загрузки данных.</p>';
                }
            });
        });
    });

    // Закрыть модалку
    closeBtn.addEventListener('click', function () {
        overlay.classList.remove('active');
    });
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) overlay.classList.remove('active');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') overlay.classList.remove('active');
    });

    // Делегирование: удаление одной записи
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cell-delete-btn');
        if (!btn) return;
        if (!confirm('Удалить только это занятие?')) return;
        var id   = btn.dataset.id;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/schedule-board/' + id;
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrfToken + '">' +
            '<input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(form);
        form.submit();
    });

    // Делегирование: удаление всей серии
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cell-delete-series-btn');
        if (!btn) return;
        if (!confirm('Удалить все занятия этой серии?')) return;
        var id   = btn.dataset.id;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/schedule-board/' + id + '/series';
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrfToken + '">' +
            '<input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(form);
        form.submit();
    });
})();
</script>
@stop
