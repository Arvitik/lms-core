@extends('templates.base')

@section('head')
    <title>Аналитика обратной связи</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    <style>
        .fb-stat-card {
            background: #fff;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,.12);
        }
        .fb-rating-bar {
            display: inline-block;
            height: 10px;
            background: #FFC107;
            border-radius: 4px;
            vertical-align: middle;
            margin: 0 8px;
        }
        .fb-stars { color: #FFC107; letter-spacing: 1px; }
        .fb-zero  { color: #bbb; font-style: italic; }
        table.fb-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.fb-table th {
            background: #00BCD4; color: #fff;
            padding: 10px 14px; text-align: left;
        }
        table.fb-table td { padding: 9px 14px; border-bottom: 1px solid #eee; }
        table.fb-table tr:hover td { background: #f9f9f9; }
        .badge-type {
            display:inline-block; padding:2px 8px; border-radius:10px;
            font-size:12px; font-weight:600;
        }
        .badge-lecture { background:#e3f2fd; color:#1565C0; }
        .badge-test    { background:#e8f5e9; color:#2E7D32; }
        .badge-course  { background:#fff3e0; color:#E65100; }
        .stars-render  { color:#FFC107; font-size:16px; }
    </style>
@stop

@section('content')
<div class="col-lg-offset-1 col-md-10 col-lg-10" style="margin-top:20px;">

    <h2 class="text-default-dark">Аналитика обратной связи</h2>
    <p class="text-muted">Сводная статистика оценок студентов по лекциям, тестам и курсу в целом.</p>

    {{-- Курс в целом --}}
    <div class="fb-stat-card">
        <h4 style="margin-top:0;">Курс в целом</h4>
        @if($courseStats['cnt'] > 0)
            <span class="stars-render">
                @for($i=1;$i<=5;$i++)
                    {{ $i <= round($courseStats['avg_rating']) ? '★' : '☆' }}
                @endfor
            </span>
            <strong style="font-size:22px; margin-left:8px;">{{ $courseStats['avg_rating'] }}</strong>
            <span class="text-muted" style="margin-left:8px;">/ 5 &nbsp;·&nbsp; {{ $courseStats['cnt'] }} отзывов</span>
        @else
            <span class="fb-zero">Отзывов пока нет</span>
        @endif
    </div>

    {{-- График динамики --}}
    @if(count($dynamics) > 0)
    <div class="fb-stat-card">
        <h4 style="margin-top:0;">Динамика средней оценки (последние 6 месяцев)</h4>
        <canvas id="dynamicsChart" height="80"></canvas>
    </div>
    @endif

    {{-- Лекции --}}
    <div class="fb-stat-card">
        <h4 style="margin-top:0;">Оценки по лекциям</h4>
        @if(count($lectureStats) > 0)
        <table class="fb-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Лекция</th>
                    <th>Средняя оценка</th>
                    <th>Отзывов</th>
                </tr>
            </thead>
            <tbody>
            @foreach($lectureStats as $row)
                <tr>
                    <td>{{ $row->lecture_number }}</td>
                    <td>{{ $row->lecture_name }}</td>
                    <td>
                        @if($row->cnt > 0)
                            <span class="fb-rating-bar" style="width:{{ $row->avg_rating * 20 }}px;"></span>
                            <span class="stars-render" style="font-size:13px;">
                                @for($i=1;$i<=5;$i++){{ $i <= round($row->avg_rating) ? '★' : '☆' }}@endfor
                            </span>
                            {{ $row->avg_rating }}
                        @else
                            <span class="fb-zero">—</span>
                        @endif
                    </td>
                    <td>{{ $row->cnt }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <span class="fb-zero">Отзывов по лекциям пока нет</span>
        @endif
    </div>

    {{-- Тесты --}}
    <div class="fb-stat-card">
        <h4 style="margin-top:0;">Оценки по тестам</h4>
        @if(count($testStats) > 0)
        <table class="fb-table">
            <thead>
                <tr>
                    <th>Тест</th>
                    <th>Тип</th>
                    <th>Средняя оценка</th>
                    <th>Отзывов</th>
                </tr>
            </thead>
            <tbody>
            @foreach($testStats as $row)
                <tr>
                    <td>{{ $row->test_name }}</td>
                    <td><span class="badge-type badge-test">{{ $row->test_type }}</span></td>
                    <td>
                        @if($row->cnt > 0)
                            <span class="fb-rating-bar" style="width:{{ $row->avg_rating * 20 }}px;"></span>
                            <span class="stars-render" style="font-size:13px;">
                                @for($i=1;$i<=5;$i++){{ $i <= round($row->avg_rating) ? '★' : '☆' }}@endfor
                            </span>
                            {{ $row->avg_rating }}
                        @else
                            <span class="fb-zero">—</span>
                        @endif
                    </td>
                    <td>{{ $row->cnt }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <span class="fb-zero">Отзывов по тестам пока нет</span>
        @endif
    </div>

    {{-- Последние отзывы с комментариями --}}
    <div class="fb-stat-card">
        <h4 style="margin-top:0;">Последние отзывы с комментариями</h4>
        @if(count($recentComments) > 0)
        <table class="fb-table">
            <thead>
                <tr>
                    <th>Материал</th>
                    <th>Оценка</th>
                    <th>Комментарий</th>
                    <th>Автор</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
            @foreach($recentComments as $fb)
                <tr>
                    <td>
                        @if($fb->target_type === 'lecture')
                            <span class="badge-type badge-lecture">Лекция</span>
                            {{ $fb->lecture_number ? 'Лекция '.$fb->lecture_number.'. '.$fb->lecture_name : '—' }}
                        @elseif($fb->target_type === 'test')
                            <span class="badge-type badge-test">Тест</span>
                            {{ $fb->test_name ?? '—' }}
                        @else
                            <span class="badge-type badge-course">Курс</span>
                        @endif
                    </td>
                    <td>
                        <span class="stars-render" style="font-size:14px;">
                            @for($i=1;$i<=5;$i++){{ $i <= $fb->rating ? '★' : '☆' }}@endfor
                        </span>
                    </td>
                    <td style="max-width:320px;">{{ $fb->comment }}</td>
                    <td>
                        @if($fb->is_anonymous)
                            <em style="color:#aaa;">Анонимно</em>
                        @else
                            {{ $fb->first_name }} {{ $fb->last_name }}
                        @endif
                    </td>
                    <td style="white-space:nowrap; color:#888; font-size:13px;">
                        {{ \Carbon\Carbon::parse($fb->created_at)->format('d.m.Y') }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <span class="fb-zero">Комментариев пока нет</span>
        @endif
    </div>

</div>
@stop

@section('js-down')
@if(count($dynamics) > 0)
<script>
var ctx = document.getElementById('dynamicsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: {!! json_encode($dynamics->pluck('month')) !!},
        datasets: [{
            label: 'Средняя оценка',
            data: {!! json_encode($dynamics->pluck('avg_rating')) !!},
            borderColor: '#00BCD4',
            backgroundColor: 'rgba(0,188,212,0.1)',
            borderWidth: 2,
            pointRadius: 5,
            pointBackgroundColor: '#00BCD4',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        scales: {
            yAxes: [{ ticks: { min: 1, max: 5, stepSize: 1 } }]
        },
        legend: { display: false }
    }
});
</script>
@endif
@stop
