@extends('templates.base')

@section('content')
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
            <div class="col-md-6" style="padding-top: 25px;">
                <button type="submit" class="btn btn-primary">Сформировать вручную</button>
                <span class="text-muted" style="margin-left: 8px;">Без дат берется последняя завершенная неделя.</span>
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
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Преподаватель</th>
                <th>Дата создания</th>
                <th>Период</th>
                <th>Студентов</th>
                <th>Проблемных</th>
                <th>Неактивных</th>
                <th>Скачать PDF</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $report)
                @php
                    $teacher = $report->teacher;
                    $teacherName = $teacher ? $teacher->last_name . ' ' . $teacher->first_name : 'Неизвестный';
                    $from = isset($report->period['from']) && $report->period['from'] ? $report->period['from'] : '—';
                    $to = isset($report->period['to']) && $report->period['to'] ? $report->period['to'] : '—';
                @endphp
                <tr>
                    <td>{{ $teacherName }}</td>
                    <td>{{ $report->created_at }}</td>
                    <td>{{ $from }} - {{ $to }}</td>
                    <td>{{ isset($report->students_count) ? $report->students_count : 0 }}</td>
                    <td>{{ isset($report->problem_count) ? $report->problem_count : 0 }}</td>
                    <td>{{ isset($report->inactive_count) ? $report->inactive_count : 0 }}</td>
                    <td>
                        <a href="{{ route('reports.download', $report->id) }}" class="btn btn-sm btn-primary">
                            Скачать PDF
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Отчеты пока не сформированы.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
