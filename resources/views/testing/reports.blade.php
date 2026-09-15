@extends('templates.base')

@section('content')
<style>
.batch-divider {
    display: flex;
    align-items: center;
    margin: 28px 0 16px;
    gap: 12px;
}
.batch-divider-line {
    flex: 1;
    border-top: 2px solid #b2dfdb;
}
.batch-divider-label {
    background: #e0f2f1;
    color: #00695c;
    border: 1px solid #b2dfdb;
    border-radius: 20px;
    padding: 4px 16px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
.batch-divider:first-child { margin-top: 0; }

.report-card {
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.07);
    overflow: hidden;
}
.report-card-header {
    background: #00796B;
    color: #fff;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}
.report-card-header .teacher-name { font-size: 16px; font-weight: 700; }
.report-card-header .report-meta  { font-size: 12px; opacity: 0.85; }
.report-summary {
    display: flex;
    border-bottom: 1px solid #eee;
    background: #f9f9f9;
}
.report-summary-item {
    flex: 1;
    text-align: center;
    padding: 12px 8px;
    border-right: 1px solid #eee;
}
.report-summary-item:last-child { border-right: none; }
.report-summary-item .sum-val { font-size: 22px; font-weight: 700; color: #212121; }
.report-summary-item .sum-lbl { font-size: 11px; color: #777; margin-top: 2px; }
.report-summary-item.danger   .sum-val { color: #c62828; }
.report-summary-item.inactive .sum-val { color: #f57f17; }
.report-summary-item.ok       .sum-val { color: #2e7d32; }
.report-toggle-btn {
    background: none; border: none; color: #00796B;
    font-size: 13px; padding: 8px 18px; cursor: pointer;
    width: 100%; text-align: left; border-bottom: 1px solid #eee;
}
.report-toggle-btn:hover { background: #f0faf8; }
.report-students-table { display: none; }
.report-students-table table { margin: 0; border-radius: 0; }
.badge-danger-student   { background:#ffebee; color:#c62828; border-radius:3px; padding:2px 6px; font-size:11px; font-weight:600; }
.badge-inactive-student { background:#fff8e1; color:#f57f17; border-radius:3px; padding:2px 6px; font-size:11px; font-weight:600; }
.badge-ok-student       { background:#e8f5e9; color:#2e7d32; border-radius:3px; padding:2px 6px; font-size:11px; font-weight:600; }
</style>

<div class="container reports-page">
    <h2 class="text-primary">Отчеты преподавателей</h2>

    <form action="{{ route('testing.reports.generate') }}" method="POST" class="well">
        {{ csrf_field() }}
        <div class="row">
            <div class="col-md-3">
                <label>Период с</label>
                <input type="date" name="from" class="form-control" value="{{ old('from') }}">
            </div>
            <div class="col-md-3">
                <label>Период по</label>
                <input type="date" name="to" class="form-control" value="{{ old('to') }}">
            </div>
            <div class="col-md-6" style="padding-top:25px;">
                <button type="submit" class="btn btn-primary">Сформировать вручную</button>
                <span class="text-muted" style="margin-left:8px;">Без дат берётся последняя завершённая неделя.</span>
            </div>
        </div>
    </form>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @if($batches->isEmpty())
        <div class="alert alert-info">Отчёты пока не сформированы.</div>
    @else
        @foreach($batches as $batchKey => $batchReports)
            @php
                $batchDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $batchKey)->format('d.m.Y H:i');
                // Период берём из первого отчёта пачки
                $firstReport = $batchReports->first();
                $batchFrom   = isset($firstReport->period['from']) ? date('d.m.Y', strtotime($firstReport->period['from'])) : null;
                $batchTo     = isset($firstReport->period['to'])   ? date('d.m.Y', strtotime($firstReport->period['to']))   : null;
                $batchPeriod = ($batchFrom && $batchTo) ? $batchFrom . ' — ' . $batchTo : null;
            @endphp

            <div class="batch-divider">
                <div class="batch-divider-line"></div>
                <div class="batch-divider-label">
                    Формирование: {{ $batchDate }}
                    @if($batchPeriod)
                        &nbsp;&middot;&nbsp; Период: {{ $batchPeriod }}
                        &nbsp;&middot;&nbsp; Отчётов: {{ $batchReports->count() }}
                    @endif
                </div>
                <div class="batch-divider-line"></div>
            </div>

            @foreach($batchReports as $report)
                @php
                    $teacher     = $report->teacher;
                    $teacherName = $teacher ? trim($teacher->last_name . ' ' . $teacher->first_name) : 'Неизвестный';
                    $from        = isset($report->period['from']) ? $report->period['from'] : null;
                    $to          = isset($report->period['to'])   ? $report->period['to']   : null;
                    $periodStr   = ($from && $to) ? date('d.m.Y', strtotime($from)) . ' — ' . date('d.m.Y', strtotime($to)) : '—';
                    $total       = isset($report->students_count) ? $report->students_count : 0;
                    $problems    = isset($report->problem_count)  ? $report->problem_count  : 0;
                    $inactive    = isset($report->inactive_count) ? $report->inactive_count : 0;
                    $normal      = max(0, $total - $problems - $inactive);
                    $students    = isset($report->data['students']) ? $report->data['students'] : [];
                    // grouped уже готов из контроллера
                    $studentsFlat = [];
                    foreach ($report->grouped as $grpName => $grpStudents) {
                        foreach ($grpStudents as $s) {
                            $studentsFlat[] = array_merge((array)$s, ['group' => $grpName]);
                        }
                    }
                @endphp

                <div class="report-card">
                    <div class="report-card-header">
                        <div>
                            <div class="teacher-name">{{ $teacherName }}</div>
                            <div class="report-meta">Период: {{ $periodStr }}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <a href="{{ route('reports.download', $report->id) }}" class="btn btn-sm btn-default" style="color:#fff;border-color:rgba(255,255,255,0.5);">
                                <span class="glyphicon glyphicon-download-alt"></span> PDF
                            </a>
                        </div>
                    </div>

                    <div class="report-summary">
                        <div class="report-summary-item">
                            <div class="sum-val">{{ $total }}</div>
                            <div class="sum-lbl">Студентов</div>
                        </div>
                        <div class="report-summary-item danger">
                            <div class="sum-val">{{ $problems }}</div>
                            <div class="sum-lbl">Проблемных</div>
                        </div>
                        <div class="report-summary-item inactive">
                            <div class="sum-val">{{ $inactive }}</div>
                            <div class="sum-lbl">Неактивных</div>
                        </div>
                        <div class="report-summary-item ok">
                            <div class="sum-val">{{ $normal }}</div>
                            <div class="sum-lbl">В норме</div>
                        </div>
                    </div>

                    @if(!empty($studentsFlat))
                    <button class="report-toggle-btn" onclick="toggleReport(this)">
                        <span class="glyphicon glyphicon-chevron-right"></span>
                        Показать список студентов ({{ count($studentsFlat) }})
                    </button>
                    <div class="report-students-table">
                        <table class="table table-striped table-condensed" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>Студент</th>
                                    <th>Группа</th>
                                    <th>Ср. балл</th>
                                    <th>Попыток (период)</th>
                                    <th>Провалов</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($studentsFlat as $s)
                                <tr>
                                    <td>{{ $s['student'] }}</td>
                                    <td>{{ $s['group'] }}</td>
                                    <td>{{ $s['avg'] }}</td>
                                    <td>{{ $s['attempts'] }}</td>
                                    <td>{{ $s['fails'] }}</td>
                                    <td>
                                        @if(!empty($s['danger']))
                                            <span class="badge-danger-student">&#9888; Проблемный</span>
                                        @elseif(!empty($s['inactive']))
                                            <span class="badge-inactive-student">&#128564; Неактивный</span>
                                        @else
                                            <span class="badge-ok-student">&#10003; Норма</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            @endforeach
        @endforeach
    @endif
</div>

<script>
function toggleReport(btn) {
    var table = btn.nextElementSibling;
    var icon  = btn.querySelector('.glyphicon');
    if (table.style.display === 'block') {
        table.style.display = 'none';
        icon.className = 'glyphicon glyphicon-chevron-right';
    } else {
        table.style.display = 'block';
        icon.className = 'glyphicon glyphicon-chevron-down';
    }
}
</script>
@endsection
