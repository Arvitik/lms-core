@php use Carbon\Carbon; @endphp

<style>
.cd-header {
    border-bottom: 2px solid #1565C0;
    padding-bottom: 10px;
    margin-bottom: 18px;
}
.cd-header h4 {
    margin: 0 0 4px;
    font-size: 18px;
    font-weight: 700;
    color: #1565C0;
}
.cd-header .cd-date {
    font-size: 13px;
    color: #666;
}

/* ===== Карточка занятия ===== */
.cd-entry {
    border-radius: 6px;
    padding: 14px 16px;
    margin-bottom: 14px;
    background: #fafafa;
    border-left: 4px solid #ccc;
    position: relative;
}
.cd-entry.lec { border-left-color: #1565C0; background: #f0f4ff; }
.cd-entry.sem { border-left-color: #388E3C; background: #f1faf2; }
.cd-entry.zac { border-left-color: #E65100; background: #fff8f0; }
.cd-entry.kr  { border-left-color: #C2185B; background: #fdf0f5; }

.cd-entry-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.cd-type-badge {
    display: inline-block;
    border-radius: 4px;
    padding: 2px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
}
.cd-type-badge.lec { background: #1565C0; }
.cd-type-badge.sem { background: #388E3C; }
.cd-type-badge.zac { background: #E65100; }
.cd-type-badge.kr  { background: #C2185B; }
.cd-room {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}
.cd-time {
    font-size: 13px;
    color: #777;
    margin-left: auto;
}
.cd-title {
    font-size: 15px;
    font-weight: 600;
    color: #212121;
    margin-bottom: 4px;
}
.cd-group {
    font-size: 12px;
    color: #888;
    margin-bottom: 4px;
}
.cd-desc {
    font-size: 13px;
    color: #555;
    margin-top: 6px;
    line-height: 1.55;
    white-space: pre-wrap;
}
.cd-actions { margin-top: 10px; }
.cd-actions .btn { font-size: 12px; padding: 2px 10px; margin-right: 4px; }

/* ===== Контрольные работы ===== */
.cd-cw-section {
    margin-top: 16px;
    border-top: 1px dashed #bdbdbd;
    padding-top: 12px;
}
.cd-cw-title {
    font-size: 13px;
    font-weight: 700;
    color: #c62828;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.cd-cw-item {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}
.cd-cw-item:last-child { border-bottom: none; }
.cd-cw-date {
    background: #f44336;
    color: #fff;
    border-radius: 4px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}
.cd-cw-date.past { background: #9E9E9E; }
.cd-cw-name { color: #212121; font-weight: 500; }
.cd-cw-desc { color: #777; font-size: 12px; }

/* ===== Пусто ===== */
.cd-empty {
    text-align: center;
    padding: 30px 0;
    color: #aaa;
    font-size: 15px;
}
</style>

{{-- Заголовок --}}
<div class="cd-header">
    <h4>
        <span class="glyphicon glyphicon-user" style="font-size:16px;"></span>
        {{ $teacher ? $teacher->last_name . ' ' . $teacher->first_name : '—' }}
    </h4>
    @php
        $dayNames   = ['Воскресенье','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота'];
        $monthNames = ['','января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
        $cd = Carbon::parse($date);
        $dateFormatted = $dayNames[$cd->dayOfWeek] . ', ' . $cd->day . ' ' . $monthNames[$cd->month] . ' ' . $cd->year;
    @endphp
    <div class="cd-date">{{ $dateFormatted }}</div>
</div>

@if($entries->isEmpty())
    <div class="cd-empty">
        <span class="glyphicon glyphicon-calendar" style="font-size:32px;display:block;margin-bottom:8px;"></span>
        Занятий на этот день не запланировано
        @if($canEdit)
        <br><a href="{{ route('schedule_board.create') }}?teacher_id={{ $teacher->id }}&date={{ $date }}"
               class="btn btn-primary btn-sm" style="margin-top:12px;">
            + Добавить занятие
        </a>
        @endif
    </div>
@else
    @foreach($entries as $entry)
    @php
        $isLec      = $entry->entry_type === 'Лекция';
        $classMap   = ['Лекция'=>'lec','Семинар'=>'sem','Зачет'=>'zac','КР'=>'kr'];
        $entryClass = $classMap[$entry->entry_type] ?? 'sem';
    @endphp
    <div class="cd-entry {{ $entryClass }}">
        <div class="cd-entry-header">
            <span class="cd-type-badge {{ $entryClass }}">{{ $entry->entry_type }}</span>
            <span class="cd-room">
                <span class="glyphicon glyphicon-map-marker"></span> {{ $entry->room }}
            </span>
            <span class="cd-time">
                <span class="glyphicon glyphicon-time"></span>
                {{ Carbon::parse($entry->time_start)->format('H:i') }}
                @if($entry->time_end)
                    — {{ Carbon::parse($entry->time_end)->format('H:i') }}
                @endif
            </span>
        </div>

        <div class="cd-title">{{ $entry->title }}</div>

        @if($entry->all_groups)
        <div class="cd-group">
            <span class="glyphicon glyphicon-education"></span> Группы: <strong>Все группы</strong>
        </div>
        @elseif($entry->groups->isNotEmpty())
        <div class="cd-group">
            <span class="glyphicon glyphicon-education"></span>
            Группы: {{ $entry->groups->pluck('group_name')->implode(', ') }}
        </div>
        @endif

        @if($entry->description)
        <div class="cd-desc">{{ $entry->description }}</div>
        @endif

        {{-- Контрольные работы — только для семинаров --}}
        @if(!$isLec)
            @php
                $entryCw = $controlWorks->where('group_id', $entry->group_id)->values();
                $today   = \Carbon\Carbon::today();
            @endphp
            @if($entryCw->isNotEmpty())
            <div class="cd-cw-section">
                <div class="cd-cw-title">
                    <span class="glyphicon glyphicon-alert"></span>
                    Контрольные работы (±2 нед.)
                </div>
                @foreach($entryCw as $cw)
                @php $cwDate = Carbon::parse($cw->scheduled_date); $isPast = $cwDate->lt($today); @endphp
                <div class="cd-cw-item">
                    <span class="cd-cw-date {{ $isPast ? 'past' : '' }}">
                        {{ $cwDate->format('d.m.Y') }}
                    </span>
                    <span class="cd-cw-name">{{ $cw->title }}</span>
                    @if($cw->description)
                    <span class="cd-cw-desc">— {{ $cw->description }}</span>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        @endif

        @if($canEdit)
        <div class="cd-actions">
            <a href="{{ route('schedule_board.edit', $entry->id) }}" class="btn btn-default">
                <span class="glyphicon glyphicon-pencil"></span> Изменить
            </a>
            <button class="btn btn-danger cell-delete-btn" data-id="{{ $entry->id }}">
                <span class="glyphicon glyphicon-trash"></span> Удалить
            </button>
            @if($entry->series_id)
            <button class="btn btn-warning cell-delete-series-btn" data-id="{{ $entry->id }}"
                    style="font-size:12px;">
                <span class="glyphicon glyphicon-remove-circle"></span> Удалить всю серию
            </button>
            @endif
        </div>
        @endif
    </div>
    @endforeach

    @if($canEdit)
    <div style="margin-top:4px;text-align:right;">
        <a href="{{ route('schedule_board.create') }}?teacher_id={{ $teacher->id }}&date={{ $date }}"
           class="btn btn-primary btn-sm">
            + Ещё занятие на эту дату
        </a>
    </div>
    @endif
@endif
