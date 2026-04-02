@extends('templates.base')
@section('head')<title>Расписание контрольных</title>@stop
@section('content')
<div class="col-lg-offset-1 col-md-offset-1 col-md-10 col-lg-10" style="margin-top:20px;">
    <div class="card style-default-light" style="padding:24px;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 class="text-default-dark" style="margin:0;">
                <span class="glyphicon glyphicon-calendar"></span> Расписание контрольных работ
            </h3>
            <a href="{{ route('exam_schedules.create') }}" class="btn btn-primary btn-raised">
                <span class="glyphicon glyphicon-plus"></span> Назначить контрольную
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($schedules->isEmpty())
            <div class="text-center" style="padding:40px 0;color:#aaa;">
                <span class="glyphicon glyphicon-calendar" style="font-size:40px;display:block;margin-bottom:10px;opacity:.3;"></span>
                Контрольных работ пока не назначено
            </div>
        @else
            <table class="table table-hover table-bordered">
                <thead class="info">
                    <tr>
                        <th>Дата</th>
                        <th>Название</th>
                        <th>Группа</th>
                        <th>Описание</th>
                        <th>Назначил</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($schedules as $s)
                    @php $past = $s->scheduled_date->isPast(); @endphp
                    <tr @if($past) style="opacity:.6;" @endif>
                        <td>
                            <strong @if(!$past) style="color:#1976D2;" @endif>
                                {{ $s->scheduled_date->format('d.m.Y') }}
                            </strong>
                            @if(!$past)
                                <br><small class="text-muted">{{ $s->scheduled_date->diffForHumans() }}</small>
                            @else
                                <br><small class="text-muted">Прошла</small>
                            @endif
                        </td>
                        <td><strong>{{ $s->title }}</strong></td>
                        <td>{{ $s->group ? $s->group->group_name : '—' }}</td>
                        <td style="max-width:220px;font-size:13px;">{{ $s->description ?: '—' }}</td>
                        <td>{{ $s->teacher ? $s->teacher->last_name . ' ' . $s->teacher->first_name : '—' }}</td>
                        <td>
                            <form action="{{ route('exam_schedules.destroy', $s->id) }}" method="POST">
                                {{ csrf_field() }}
                                {{ method_field('DELETE') }}
                                <button class="btn btn-xs btn-danger"
                                    onclick="return confirm('Удалить контрольную?')">
                                    <span class="glyphicon glyphicon-trash"></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@stop
