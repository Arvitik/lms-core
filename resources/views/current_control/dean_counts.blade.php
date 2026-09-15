@extends('templates.base')

@section('head')
    <title>Численность групп по спискам деканата</title>
    <style>
        .dean-counts-page { margin: 76px auto 30px; max-width: 1050px; }
        .dean-counts-page .card-body { padding: 28px; }
        .dean-counts-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
        .dean-counts-table th, .dean-counts-table td { vertical-align: middle !important; }
        .dean-counts-table input { width: 130px; text-align: center; }
        .dean-counts-actions { display: flex; justify-content: flex-end; margin-top: 18px; }
        @media (max-width: 700px) {
            .dean-counts-page { margin-top: 58px; }
            .dean-counts-page .card-body { padding: 16px; }
            .dean-counts-toolbar { align-items: flex-start; flex-direction: column; }
        }
    </style>
@stop

@section('background')
    full
@stop

@section('content')
<div id="main_container">
    <div class="card dean-counts-page">
        <div class="card-body">
            <div class="dean-counts-toolbar">
                <h2 style="margin: 0;">Численность групп по спискам деканата</h2>
                <a href="{{ route('current_control.checker') }}" class="btn btn-default">Вернуться к проверке</a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('current_control.dean_counts.save') }}">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dean-counts-table">
                        <thead>
                            <tr class="info">
                                <th>Группа</th>
                                <th style="width: 220px;">Зарегистрировано на сайте</th>
                                <th style="width: 240px;">По списку деканата</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($groups as $group)
                            @php
                                $savedCount = $counts->get($group->group_id);
                                $registeredCount = $registered->get($group->group_id);
                            @endphp
                            <tr>
                                <th>{{ $group->group_name }}</th>
                                <td>{{ $registeredCount ? $registeredCount->student_count : 0 }}</td>
                                <td>
                                    <input type="number" min="0" name="counts[{{ $group->group_id }}]" class="form-control" value="{{ $savedCount ? $savedCount->expected_count : '' }}" placeholder="Не задано">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Нет доступных учебных групп.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dean-counts-actions">
                    <button type="submit" class="btn btn-primary btn-raised">Сохранить численность</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop
