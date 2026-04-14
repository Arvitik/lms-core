@extends('templates.base')

@section('head')
<title>{{ isset($entry) ? 'Редактировать занятие' : 'Добавить занятие' }}</title>
{!! HTML::style('css/bootstrap.css') !!}
{!! HTML::style('css/materialadmin.css') !!}
{!! HTML::style('css/full.css') !!}
<style>
.form-card {
    max-width: 560px;
    margin: 30px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,.10);
    padding: 32px 36px;
}
.form-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: #1565C0;
    margin-bottom: 24px;
}
.form-group label { font-weight: 600; font-size: 13px; }
.type-radios { display: flex; gap: 18px; margin-top: 6px; }
.type-radios label {
    display: flex; align-items: center; gap: 6px;
    font-size: 14px; font-weight: 500; cursor: pointer;
}
.form-actions { margin-top: 24px; display: flex; gap: 10px; }
.time-preview {
    display: inline-block;
    margin-top: 6px;
    font-size: 15px;
    font-weight: 700;
    color: #1565C0;
    letter-spacing: .3px;
}
.recurring-box {
    background: #f3f8ff;
    border: 1px solid #bbdefb;
    border-radius: 6px;
    padding: 14px 16px;
    margin-top: 8px;
}
.recurring-box label { font-size: 13px; color: #555; }
</style>
@stop

@section('content')
<div class="container">
    <div class="form-card">
        <h3>
            <span class="glyphicon glyphicon-calendar"></span>
            {{ isset($entry) ? 'Редактировать занятие' : 'Добавить занятие в расписание' }}
        </h3>

        @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(isset($entry))
        <form method="POST" action="{{ route('schedule_board.update', $entry->id) }}">
            {{ csrf_field() }}
            {{ method_field('PUT') }}
        @else
        <form method="POST" action="{{ route('schedule_board.store') }}">
            {{ csrf_field() }}
        @endif

            {{-- Преподаватель (только для Админа) --}}
            @if(Auth::user()->role === 'Админ')
            <div class="form-group">
                <label for="teacher_id">Преподаватель</label>
                <select name="teacher_id" id="teacher_id" class="form-control" required>
                    <option value="">— выберите —</option>
                    @foreach($teachers as $t)
                    @php
                        $selectedTeacher = old('teacher_id', $entry->teacher_id ?? $prefillTeacherId ?? '');
                    @endphp
                    <option value="{{ $t->id }}"
                        {{ $selectedTeacher == $t->id ? 'selected' : '' }}>
                        {{ $t->last_name }} {{ $t->first_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            @else
            <input type="hidden" name="teacher_id" value="{{ Auth::user()->id }}">
            @endif

            {{-- Дата --}}
            <div class="form-group">
                <label for="entry_date">Дата занятия</label>
                <input type="date" name="entry_date" id="entry_date" class="form-control"
                       value="{{ old('entry_date', isset($entry) ? \Carbon\Carbon::parse($entry->entry_date)->format('Y-m-d') : ($prefillDate ?? '')) }}"
                       required>
            </div>

            {{-- Тип занятия --}}
            <div class="form-group">
                <label>Тип занятия</label>
                <div class="type-radios">
                    <label>
                        <input type="radio" name="entry_type" value="Лекция" class="type-radio"
                               {{ old('entry_type', $entry->entry_type ?? 'Лекция') === 'Лекция' ? 'checked' : '' }}>
                        <span>Лекция</span>
                    </label>
                    <label>
                        <input type="radio" name="entry_type" value="Семинар" class="type-radio"
                               {{ old('entry_type', $entry->entry_type ?? '') === 'Семинар' ? 'checked' : '' }}>
                        <span>Семинар</span>
                    </label>
                    <label>
                        <input type="radio" name="entry_type" value="Зачет" class="type-radio"
                               {{ old('entry_type', $entry->entry_type ?? '') === 'Зачет' ? 'checked' : '' }}>
                        <span>Зачет</span>
                    </label>
                    <label>
                        <input type="radio" name="entry_type" value="КР" class="type-radio"
                               {{ old('entry_type', $entry->entry_type ?? '') === 'КР' ? 'checked' : '' }}>
                        <span>КР</span>
                    </label>
                </div>
            </div>

            {{-- Название КР (только для типа КР) --}}
            <div class="form-group" id="kr-title-group" style="display:none;">
                <label for="kr_title">Название контрольной <span class="text-danger">*</span></label>
                <input type="text" name="kr_title" id="kr_title" class="form-control"
                       placeholder="Например: Контрольная №2 по разделу «Машины Тьюринга»"
                       value="{{ old('kr_title', isset($entry) ? $entry->title : '') }}"
                       maxlength="255">
            </div>

            {{-- Время начала + авто/ручной конец --}}
            <div class="form-group">
                <label for="time_start">Время начала</label>
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <input type="time" name="time_start" id="time_start" class="form-control"
                           style="max-width:140px;"
                           value="{{ old('time_start', $entry->time_start ?? '') }}" required>
                    <span class="time-preview" id="time-preview"></span>
                </div>
            </div>

            {{-- Время конца (только для Зачета) --}}
            <div class="form-group" id="time-end-group" style="display:none;">
                <label for="time_end">Время окончания</label>
                <input type="time" name="time_end" id="time_end" class="form-control"
                       style="max-width:140px;"
                       value="{{ old('time_end', $entry->time_end ?? '') }}">
            </div>

            {{-- Группы --}}
            <div class="form-group">
                <label>Группы <small style="color:#999;">(необязательно)</small></label>
                @php
                    $isAllGroups    = old('all_groups', isset($entry) ? $entry->all_groups : 0);
                    $selectedGroups = old('group_ids', isset($entry) ? $entry->groups->pluck('group_id')->toArray() : []);
                @endphp
                <div style="border:1px solid #ddd;border-radius:4px;padding:4px 0;">
                    {{-- Все группы --}}
                    <label id="label-all-groups"
                           style="display:flex;align-items:center;gap:8px;padding:6px 12px;font-weight:700;cursor:pointer;margin:0;background:#f3f8ff;border-bottom:1px solid #e0e0e0;">
                        <input type="checkbox" id="cb-all-groups" name="all_groups" value="1"
                               {{ $isAllGroups ? 'checked' : '' }}
                               style="width:15px;height:15px;flex-shrink:0;">
                        Все группы
                    </label>
                    {{-- Конкретные группы --}}
                    <div id="group-list" style="max-height:180px;overflow-y:auto;{{ $isAllGroups ? 'opacity:.4;pointer-events:none;' : '' }}">
                        @foreach($groups as $g)
                        <label style="display:flex;align-items:center;gap:8px;padding:5px 12px;font-weight:normal;cursor:pointer;margin:0;">
                            <input type="checkbox" name="group_ids[]" value="{{ $g->group_id }}"
                                   class="cb-group"
                                   {{ (!$isAllGroups && in_array($g->group_id, (array)$selectedGroups)) ? 'checked' : '' }}
                                   style="width:15px;height:15px;flex-shrink:0;">
                            {{ $g->group_name }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Аудитория --}}
            <div class="form-group">
                <label for="room">Аудитория</label>
                <input type="text" name="room" id="room" class="form-control"
                       placeholder="Например: А101, Г208"
                       value="{{ old('room', $entry->room ?? '') }}" required maxlength="100">
            </div>

            {{-- Систематически (только при создании, не для КР) --}}
            @if(!isset($entry))
            <div class="form-group" id="recurring-group">
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="recurring" name="recurring" value="1"
                           {{ old('recurring') ? 'checked' : '' }}
                           style="width:16px;height:16px;">
                    <label for="recurring" style="margin:0;cursor:pointer;font-weight:600;font-size:13px;">
                        Назначить систематически (каждую неделю в этот день)
                    </label>
                </div>
                <div class="recurring-box" id="recurring-box" style="{{ old('recurring') ? '' : 'display:none;' }}margin-top:10px;">
                    <label for="recurring_until">Повторять до:</label>
                    <input type="date" name="recurring_until" id="recurring_until" class="form-control"
                           style="max-width:200px;margin-top:4px;"
                           value="{{ old('recurring_until') }}">
                    <small style="color:#777;display:block;margin-top:6px;">
                        Будут созданы занятия каждую неделю в выбранный день до указанной даты включительно.
                    </small>
                </div>
            </div>
            @endif

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="glyphicon glyphicon-ok"></span>
                    {{ isset($entry) ? 'Сохранить изменения' : 'Добавить' }}
                </button>
                <a href="{{ route('schedule_board.index') }}" class="btn btn-default">Отмена</a>
            </div>
        </form>
    </div>
</div>
@stop

@section('js-down')
<script>
(function () {
    var timeInput    = document.getElementById('time_start');
    var timeEndInput = document.getElementById('time_end');
    var timeEndGroup = document.getElementById('time-end-group');
    var preview      = document.getElementById('time-preview');
    var recurringCb  = document.getElementById('recurring');
    var recurringBox = document.getElementById('recurring-box');
    var typeRadios   = document.querySelectorAll('.type-radio');

    function addMinutes(timeStr, mins) {
        if (!timeStr) return '';
        var parts = timeStr.split(':');
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        var total = h * 60 + m + mins;
        var nh = Math.floor(total / 60) % 24;
        var nm = total % 60;
        return (nh < 10 ? '0' : '') + nh + ':' + (nm < 10 ? '0' : '') + nm;
    }

    function isZachet() {
        var checked = document.querySelector('.type-radio:checked');
        return checked && (checked.value === 'Зачет' || checked.value === 'КР');
    }

    function updatePreview() {
        if (isZachet()) {
            var end = timeEndInput ? timeEndInput.value : '';
            preview.textContent = (timeInput.value && end)
                ? timeInput.value + ' \u2014 ' + end
                : '';
        } else {
            var val = timeInput.value;
            if (val) {
                preview.textContent = val + ' \u2014 ' + addMinutes(val, 95);
            } else {
                preview.textContent = '';
            }
        }
    }

    var krTitleGroup  = document.getElementById('kr-title-group');
    var krTitleInput  = document.getElementById('kr_title');
    var recurringGroup = document.getElementById('recurring-group');

    function isKR() {
        var checked = document.querySelector('.type-radio:checked');
        return checked && checked.value === 'КР';
    }

    function onTypeChange() {
        if (isZachet()) {
            if (timeEndGroup) timeEndGroup.style.display = '';
            if (timeEndInput) timeEndInput.required = true;
        } else {
            if (timeEndGroup) timeEndGroup.style.display = 'none';
            if (timeEndInput) { timeEndInput.required = false; timeEndInput.value = ''; }
        }

        // Поле названия — только для КР
        if (krTitleGroup) krTitleGroup.style.display = isKR() ? '' : 'none';
        if (krTitleInput) krTitleInput.required = isKR();

        // Блок «Систематически» — скрыть для КР
        if (recurringGroup) recurringGroup.style.display = isKR() ? 'none' : '';

        updatePreview();
    }

    typeRadios.forEach(function (r) {
        r.addEventListener('change', onTypeChange);
    });

    if (timeInput) {
        timeInput.addEventListener('input', updatePreview);
    }
    if (timeEndInput) {
        timeEndInput.addEventListener('input', updatePreview);
    }

    // Инициализация
    onTypeChange();
    updatePreview();

    if (recurringCb) {
        recurringCb.addEventListener('change', function () {
            recurringBox.style.display = this.checked ? '' : 'none';
        });
    }

    // «Все группы» — блокирует отдельные чекбоксы
    var cbAllGroups = document.getElementById('cb-all-groups');
    var groupList   = document.getElementById('group-list');
    if (cbAllGroups && groupList) {
        cbAllGroups.addEventListener('change', function () {
            if (this.checked) {
                groupList.style.opacity = '.4';
                groupList.style.pointerEvents = 'none';
                groupList.querySelectorAll('.cb-group').forEach(function (cb) { cb.checked = false; });
            } else {
                groupList.style.opacity = '';
                groupList.style.pointerEvents = '';
            }
        });
    }
})();
</script>
@stop
