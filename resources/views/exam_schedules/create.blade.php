@extends('templates.base')
@section('head')<title>Назначить контрольную</title>@stop
@section('content')
<div class="col-lg-offset-2 col-md-offset-2 col-md-8 col-lg-8" style="margin-top:20px;">
    <div class="card style-default-light" style="padding:24px;">
        <h3 class="text-default-dark" style="margin-top:0;">
            <span class="glyphicon glyphicon-calendar"></span> Назначить контрольную работу
        </h3>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form action="{{ route('exam_schedules.store') }}" method="POST">
            {{ csrf_field() }}

            <div class="form-group">
                <label>Группа <span class="text-danger">*</span></label>
                <select name="group_id" class="form-control" required>
                    <option value="">— выберите группу —</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->group_id }}" {{ old('group_id') == $g->group_id ? 'selected' : '' }}>
                            {{ $g->group_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Дата проведения <span class="text-danger">*</span></label>
                <input type="date" name="scheduled_date" class="form-control"
                       value="{{ old('scheduled_date', date('Y-m-d')) }}"
                       min="{{ date('Y-m-d') }}" required>
            </div>

            <div class="form-group">
                <label>Название контрольной <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="{{ old('title') }}"
                       placeholder="Например: Контрольная №2 по разделу «Машины Тьюринга»"
                       required>
            </div>

            <div class="form-group">
                <label>Описание / что повторить <small class="text-muted">(необязательно)</small></label>
                <textarea name="description" rows="3" class="form-control"
                          placeholder="Темы, разделы, рекомендации для подготовки...">{{ old('description') }}</textarea>
            </div>

            <div class="form-group">
                <label>Связанный тест <small class="text-muted">(необязательно)</small></label>
                <select name="test_id" class="form-control">
                    <option value="">— не выбран —</option>
                    @foreach($tests as $t)
                        <option value="{{ $t->id_test }}" {{ old('test_id') == $t->id_test ? 'selected' : '' }}>
                            {{ $t->test_name }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Если контрольная будет проходить в системе, можно привязать тест</small>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary btn-raised">
                    <span class="glyphicon glyphicon-send"></span> Назначить и уведомить студентов
                </button>
                <a href="{{ route('exam_schedules.index') }}" class="btn btn-default" style="margin-left:8px;">Отмена</a>
            </div>
        </form>
    </div>
</div>
@stop
