<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h2 { text-align: center; margin-bottom: 10px; }
        h3 { margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: center; }
        th { background: #eee; }
        .left { text-align: left; }
        .small { font-size: 11px; }
    </style>
</head>
<body>
    <h2>Отчет по успеваемости учебных групп</h2>
    <p><strong>Дата формирования:</strong> {{ $generated_at }}</p>
    <p><strong>Текущий период:</strong> {{ isset($period['from']) ? $period['from'] : '—' }} - {{ isset($period['to']) ? $period['to'] : '—' }}</p>
    <p><strong>Предыдущий период:</strong> {{ isset($previous_period['from']) ? $previous_period['from'] : '—' }} - {{ isset($previous_period['to']) ? $previous_period['to'] : '—' }}</p>

    @if(is_array($summary) && count($summary) > 0)
        <table>
            <thead>
                <tr>
                    <th>Групп</th>
                    <th>Студентов</th>
                    <th>Проблемных студентов</th>
                    <th>Неактивных студентов</th>
                    <th>Средний балл (текущий)</th>
                    <th>Средний балл (предыдущий)</th>
                    <th>Δ среднего балла</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ isset($summary['groups_count']) ? $summary['groups_count'] : 0 }}</td>
                    <td>{{ isset($summary['students_total']) ? $summary['students_total'] : 0 }}</td>
                    <td>{{ isset($summary['problem_students_total']) ? $summary['problem_students_total'] : 0 }}</td>
                    <td>{{ isset($summary['inactive_students_total']) ? $summary['inactive_students_total'] : 0 }}</td>
                    <td>{{ isset($summary['avg_mark_current']) ? $summary['avg_mark_current'] : 0 }}</td>
                    <td>{{ isset($summary['avg_mark_previous']) ? $summary['avg_mark_previous'] : 0 }}</td>
                    <td>{{ isset($summary['avg_mark_delta']) ? $summary['avg_mark_delta'] : 0 }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h3>Сравнение по группам</h3>
    <table class="small">
        <thead>
            <tr>
                <th>Группа</th>
                <th>Студентов</th>
                <th>Попыток (тек.)</th>
                <th>Попыток (пред.)</th>
                <th>Ср. балл (тек.)</th>
                <th>Ср. балл (пред.)</th>
                <th>% неуспехов (тек.)</th>
                <th>% неуспехов (пред.)</th>
                <th>Проблемных</th>
                <th>Неактивных</th>
                <th>Тренд</th>
                <th class="left">Рекомендация</th>
            </tr>
        </thead>
        <tbody>
            @if(is_array($comparison) && count($comparison) > 0)
                @foreach($comparison as $row)
                    <tr>
                        <td>{{ isset($row['group_name']) ? $row['group_name'] : '—' }}</td>
                        <td>{{ isset($row['students_cnt']) ? $row['students_cnt'] : 0 }}</td>
                        <td>{{ isset($row['attempts_current']) ? $row['attempts_current'] : 0 }}</td>
                        <td>{{ isset($row['attempts_previous']) ? $row['attempts_previous'] : 0 }}</td>
                        <td>{{ isset($row['avg_current']) ? $row['avg_current'] : 0 }}</td>
                        <td>{{ isset($row['avg_previous']) ? $row['avg_previous'] : 0 }}</td>
                        <td>{{ isset($row['fail_rate_current']) ? $row['fail_rate_current'] : 0 }}%</td>
                        <td>{{ isset($row['fail_rate_previous']) ? $row['fail_rate_previous'] : 0 }}%</td>
                        <td>{{ isset($row['problem_students_count']) ? $row['problem_students_count'] : 0 }}</td>
                        <td>{{ isset($row['inactive_students_count']) ? $row['inactive_students_count'] : 0 }}</td>
                        <td>{{ isset($row['trend']) ? $row['trend'] : '—' }}</td>
                        <td class="left">{{ isset($row['recommendation']) ? $row['recommendation'] : '—' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="12">Нет данных для отображения.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h3>Проблемные студенты</h3>
    @if(is_array($comparison) && count($comparison) > 0)
        @foreach($comparison as $row)
            @php
                $students = isset($row['problematic_students']) && is_array($row['problematic_students'])
                    ? $row['problematic_students']
                    : [];
            @endphp
            <h4>Группа {{ isset($row['group_name']) ? $row['group_name'] : '—' }}</h4>
            @if(count($students) === 0)
                <p>Проблемных студентов не выявлено.</p>
            @else
                <table class="small">
                    <thead>
                        <tr>
                            <th class="left">Студент</th>
                            <th>Попыток</th>
                            <th>Ср. балл</th>
                            <th>Неуспехов</th>
                            <th>Последняя оценка</th>
                            <th class="left">Причина</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr>
                                <td class="left">{{ isset($student['student']) ? $student['student'] : '—' }}</td>
                                <td>{{ isset($student['attempts']) ? $student['attempts'] : 0 }}</td>
                                <td>{{ isset($student['avg_mark']) ? $student['avg_mark'] : 0 }}</td>
                                <td>{{ isset($student['fails']) ? $student['fails'] : 0 }}</td>
                                <td>{{ isset($student['last_mark']) ? $student['last_mark'] : '—' }}</td>
                                <td class="left">{{ isset($student['reason']) ? $student['reason'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endif
</body>
</html>
