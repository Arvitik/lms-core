@extends('templates.base')

@section('content')
<div class="container-fluid px-5">
    <h2>Установка лимита посещаемости на лекцию (по группам)</h2>

    <form action="{{ route('lectures.saveLimit') }}" method="POST">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">

        <div class="form-group">
            <label for="id_lecture">Выберите лекцию:</label>
            <select class="form-control" name="id_lecture" required>
                @foreach ($lectures as $lecture)
                    <option value="{{ $lecture->id_lecture }}">
                        Лекция {{ $lecture->lecture_number }} — {{ \Carbon\Carbon::parse($lecture->date)->format('d.m.Y') }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="group_id">Выберите группу:</label>
            <select class="form-control" name="group_id" required>
                @foreach ($groups as $group)
                    <option value="{{ $group->group_id }}">{{ $group->group_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="attendance_limit">Укажите лимит:</label>
            <input type="number" name="attendance_limit" class="form-control" min="1" required>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</div>
@endsection
