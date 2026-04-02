<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Отчет преподавателя</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 24px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: center; }
        th { background-color: #eee; }
        h2, h3, h4 { margin: 8px 0; }
        .left { text-align: left; }
    </style>
</head>
<body>
    @php
        $teacher = $report->teacher;
        $teacherName = $teacher ? ($teacher->last_name . ' ' . $teacher->first_name) : 'Неизвестный';
        $from = isset($period['from']) && $period['from'] ? $period['from'] : '—';
        $to = isset($period['to']) && $period['to'] ? $period['to'] : '—';
    @endphp

    <h2>Еженедельный отчет преподавателя</h2>
    <p><strong>Преподаватель:</strong> {{ $teacherName }}</p>
    <p><strong>Период:</strong> {{ $from }} - {{ $to }}</p>
    <p><strong>Всего студентов:</strong> {{ isset($studentsCount) ? $studentsCount : 0 }}</p>
    <p><strong>Проблемных студентов:</strong> {{ isset($problemCount) ? $problemCount : 0 }}</p>
    <p><strong>Неактивных студентов:</strong> {{ isset($inactiveCount) ? $inactiveCount : 0 }}</p>

    @foreach ($grouped as $group => $students)
        <h4>Группа {{ $group }}</h4>
        <table>
            <thead>
                <tr>
                    <th class="left">Студент</th>
                    <th>Ср. балл</th>
                    <th>Попыток (период)</th>
                    <th>Попыток (всего)</th>
                    <th>Неуспехов (период)</th>
                    <th>Последняя оценка</th>
                    <th>Проблема</th>
                    <th>Неактивен</th>
                    <th class="left">Причина</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $student)
                    <tr style="{{ !empty($student['danger']) ? 'background-color: #fdd;' : '' }}">
                        <td class="left">{{ isset($student['student']) ? $student['student'] : '' }}</td>
                        <td>
                            @if(isset($student['attempts_total']) && (int)$student['attempts_total'] === 0)
                                —
                            @else
                                {{ isset($student['avg']) ? $student['avg'] : '—' }}
                            @endif
                        </td>
                        <td>{{ isset($student['attempts']) ? $student['attempts'] : 0 }}</td>
                        <td>{{ isset($student['attempts_total']) ? $student['attempts_total'] : 0 }}</td>
                        <td>{{ isset($student['fails']) ? $student['fails'] : 0 }}</td>
                        <td>{{ isset($student['last_mark']) ? $student['last_mark'] : '—' }}</td>
                        <td>{{ !empty($student['danger']) ? 'Да' : 'Нет' }}</td>
                        <td>{{ !empty($student['inactive']) ? 'Да' : 'Нет' }}</td>
                        <td class="left">{{ isset($student['danger_reason']) ? $student['danger_reason'] : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
