@extends('templates.base')
@section('head')
<title>Назначить контрольную</title>
{!! HTML::style('css/bootstrap.css') !!}
{!! HTML::style('css/materialadmin.css') !!}
{!! HTML::style('css/full.css') !!}
<style>
.exam-form-card {
    max-width: 620px;
    margin: 28px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,.10);
    padding: 30px 34px;
}
.exam-form-card h3 { font-size:20px;font-weight:700;color:#C62828;margin-bottom:22px; }
.exam-form-card label { font-weight:600;font-size:13px; }
.group-list-box {
    border:1px solid #ddd;border-radius:4px;
    max-height:180px;overflow-y:auto;padding:4px 0;
}
.group-list-box label {
    display:flex;align-items:center;gap:8px;
    padding:5px 12px;cursor:pointer;margin:0;font-weight:normal;
}
.group-list-box label:hover { background:#f3f8ff; }
.group-list-box input[type=checkbox] { width:15px;height:15px;flex-shrink:0; }
.form-actions { margin-top:22px;display:flex;gap:10px; }
</style>
@stop

@section('content')
<div class="container">
<div class="exam-form-card">
    <h3>
        <span class="glyphicon glyphicon-pencil"></span> Назначить контрольную работу
    </h3>

    @if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:16px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form action="{{ route('exam_schedules.store') }}" method="POST">
        {{ csrf_field() }}

        {{-- Преподаватель --}}
        @if(Auth::user()->role === 'Админ')
        <div class="form-group">
            <label>Преподаватель <span class="text-danger">*</span></label>
            <select name="teacher_id" class="form-control" required>
                <option value="">— выберите —</option>
                @foreach($teachers as $t)
                <option value="{{ $t->id }}" {{ old('teacher_id') == $t->id ? 'selected' : '' }}>
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
            <label>Дата проведения <span class="text-danger">*</span></label>
            <input type="date" name="scheduled_date" class="form-control"
                   value="{{ old('scheduled_date', date('Y-m-d')) }}"
                   min="{{ date('Y-m-d') }}" required>
        </div>

        {{-- Время --}}
        <div class="form-group">
            <label>Время</label>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <small style="color:#888;">Начало</small>
                    <input type="time" name="time_start" class="form-control"
                           style="max-width:130px;"
                           value="{{ old('time_start') }}" required>
                </div>
                <div>
                    <small style="color:#888;">Конец <span style="color:#aaa;">(необязательно)</span></small>
                    <input type="time" name="time_end" class="form-control"
                           style="max-width:130px;"
                           value="{{ old('time_end') }}">
                </div>
            </div>
        </div>

        {{-- Аудитория --}}
        <div class="form-group">
            <label>Аудитория <span class="text-danger">*</span></label>
            <input type="text" name="room" class="form-control"
                   style="max-width:200px;"
                   placeholder="Например: А101"
                   value="{{ old('room') }}" required maxlength="100">
        </div>

        {{-- Название --}}
        <div class="form-group">
            <label>Название контрольной <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="{{ old('title') }}"
                   placeholder="Например: Контрольная №2 по разделу «Машины Тьюринга»"
                   required>
        </div>

        {{-- Описание --}}
        <div class="form-group">
            <label>Описание / что повторить <small class="text-muted">(необязательно)</small></label>
            <textarea name="description" rows="2" class="form-control"
                      placeholder="Темы, разделы, рекомендации для подготовки...">{{ old('description') }}</textarea>
        </div>

        {{-- Группы --}}
        <div class="form-group">
            <label>Группы <small class="text-muted">(можно выбрать несколько)</small></label>
            @php $selectedGroups = (array) old('group_ids', []); @endphp
            @if($groups->isEmpty())
                <p style="color:#aaa;font-size:13px;">Нет активных групп</p>
            @else
            <div class="group-list-box">
                @foreach($groups as $g)
                <label>
                    <input type="checkbox" name="group_ids[]" value="{{ $g->group_id }}"
                           {{ in_array($g->group_id, $selectedGroups) ? 'checked' : '' }}>
                    {{ $g->group_name }}
                </label>
                @endforeach
            </div>
            @endif
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-danger btn-raised">
                <span class="glyphicon glyphicon-send"></span> Назначить и уведомить студентов
            </button>
            <a href="{{ route('exam_schedules.index') }}" class="btn btn-default">Отмена</a>
        </div>
    </form>
</div>
</div>
@stop
