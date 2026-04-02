@extends('templates.base')
@section('head')<title>Мои контрольные</title>@stop
@section('content')
<div class="col-lg-offset-1 col-md-offset-1 col-md-10 col-lg-10" style="margin-top:20px;">
    <div class="card style-default-light" style="padding:24px;">
        <h3 class="text-default-dark" style="margin-top:0;">
            <span class="glyphicon glyphicon-calendar"></span> Предстоящие контрольные работы
        </h3>

        @if($upcoming->isEmpty())
            <div class="text-center" style="padding:30px 0;color:#aaa;">
                <span class="glyphicon glyphicon-ok-circle" style="font-size:36px;display:block;margin-bottom:8px;opacity:.4;"></span>
                Ближайших контрольных не назначено
            </div>
        @else
            @foreach($upcoming as $s)
            @php
                $daysLeft = \Carbon\Carbon::today()->diffInDays($s->scheduled_date, false);
                $urgency = $daysLeft <= 2 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'info');
                $urgencyColor = $daysLeft <= 2 ? '#f44336' : ($daysLeft <= 7 ? '#FF9800' : '#2196F3');
            @endphp
            <div style="border-left:4px solid {{ $urgencyColor }};padding:14px 16px;margin-bottom:12px;background:#fff;border-radius:0 4px 4px 0;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <div style="font-size:16px;font-weight:600;color:#212121;">
                            <span class="glyphicon glyphicon-pencil" style="color:{{ $urgencyColor }};margin-right:6px;"></span>
                            {{ $s->title }}
                        </div>
                        @if($s->description)
                        <div style="font-size:13px;color:#555;margin-top:4px;">{{ $s->description }}</div>
                        @endif
                        <div style="font-size:12px;color:#888;margin-top:6px;">
                            Назначил: {{ $s->teacher ? $s->teacher->last_name . ' ' . $s->teacher->first_name : '—' }}
                        </div>
                    </div>
                    <div style="text-align:right;min-width:110px;">
                        <div style="font-size:18px;font-weight:700;color:{{ $urgencyColor }};">
                            {{ $s->scheduled_date->format('d.m.Y') }}
                        </div>
                        <div style="font-size:12px;color:#888;">
                            @if($daysLeft == 0) Сегодня!
                            @elseif($daysLeft == 1) Завтра
                            @else через {{ $daysLeft }} дн.
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        @endif

        @if($past->isNotEmpty())
        <hr>
        <h4 class="text-muted">Прошедшие контрольные</h4>
        @foreach($past as $s)
        <div style="border-left:4px solid #ccc;padding:10px 14px;margin-bottom:8px;background:#fafafa;border-radius:0 4px 4px 0;opacity:.75;">
            <strong>{{ $s->title }}</strong>
            <span style="float:right;color:#aaa;font-size:13px;">{{ $s->scheduled_date->format('d.m.Y') }}</span>
        </div>
        @endforeach
        @endif
    </div>
</div>
@stop
