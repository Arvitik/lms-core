@extends('templates.base')

@section('content')
    <div class="container mt-4 reports-page">
        <h2>Статистика по учебным группам</h2>

        <form action="{{ route('group_statistics.generate') }}" method="POST" class="mb-3">
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
                    <button type="submit" class="btn btn-primary">
                        Сформировать отчет
                    </button>
                    <span class="text-muted" style="margin-left: 10px;">
                        Если даты не указаны, берется последняя завершенная неделя.
                    </span>
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

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Дата создания</th>
                    <th>Период</th>
                    <th>Групп</th>
                    <th>Студентов</th>
                    <th>Проблемных</th>
                    <th>Скачать PDF</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>{{ $report->created_at }}</td>
                        <td>{{ $report->period_from }} - {{ $report->period_to }}</td>
                        <td>{{ $report->groups_count }}</td>
                        <td>{{ $report->students_total }}</td>
                        <td>{{ $report->problem_students_total }}</td>
                        <td>
                            <a href="{{ route('group_statistics.download', $report->id) }}" class="btn btn-success">
                                Скачать PDF
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Еще нет сформированных отчетов.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
