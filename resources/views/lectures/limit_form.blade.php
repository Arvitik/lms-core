@extends('templates.base')

@section('content')
<div class="container-fluid px-5">
    <h2>Установка лимита посещаемости</h2>

    {{-- уведомление об успешном сохранении --}}
    @if (session('success'))
        <div class="alert alert-success mt-3">
            {{ session('success') }}
        </div>
    @endif

    <style>
        /* Стили для цветов лимитов */
        .limit-cell.gray { background-color: #f8f9fa !important; }
        .limit-cell.red { background-color: #f8d7da !important; border-color: #f5c6cb !important; }
        .limit-cell.yellow { background-color: #fff3cd !important; border-color: #ffeaa7 !important; }
        .limit-cell.green { background-color: #d4edda !important; border-color: #c3e6cb !important; }
        
        /* Стили для строки со статистикой */
        .stats-row { background-color: #e9ecef; font-weight: bold; }
        .stats-cell { padding: 8px !important; }
        .stats-percentage { 
            font-size: 14px; 
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .stats-percentage.high { background-color: #d4edda; color: #155724; }
        .stats-percentage.medium { background-color: #fff3cd; color: #856404; }
        .stats-percentage.low { background-color: #f8d7da; color: #721c24; }
        .stats-percentage.none { background-color: #e9ecef; color: #6c757d; }
        
        .limit-info {
            font-size: 11px;
            margin-top: 2px;
            display: block;
        }
        .limit-info.red { color: #721c24; }
        .limit-info.yellow { color: #856404; }
        .limit-info.green { color: #155724; }
        .limit-info.gray { color: #6c757d; }
        
        /* Чтобы инпуты внутри цветных ячеек были прозрачными */
        .limit-cell input.form-control {
            background-color: transparent !important;
        }
    </style>

    <!-- ДОБАВЛЕНО: Информационная панель -->
    <div class="alert alert-info mt-3">
        <strong>Информация:</strong> В верхней строке отображается среднее выполнение установленного лимита по лекциям: число присутствовавших студентов делится на лимит посещаемости. Пока отметок нет, значение равно 0%.
    </div>

    <form action="{{ route('lectures.saveLimitsMatrix') }}" method="POST">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">

        <div class="table-responsive mt-4">
            <table class="table table-bordered table-striped align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:160px;">Лекция</th>
                        @foreach ($groups as $group)
                            <th>{{ $group->group_name }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    <!-- ДОБАВЛЕНО: Строка со статистикой посещаемости -->
                    <tr class="stats-row">
                        <td class="text-start stats-cell">
                            <strong>Средний % посещения</strong><br>
                            <small>(по лекциям с лимитом)</small>
                        </td>
                        @foreach ($groups as $group)
                            @php
                                $groupId = $group->group_id;
                                $stats = isset($groupAttendanceStats[$groupId]) ? $groupAttendanceStats[$groupId] : null;
            
                                // Определяем класс для цвета
                                $percentageClass = 'none';
                                if ($stats && $stats['lectures_with_limit'] > 0) {
                                    $percentage = $stats['average_percentage'];
                                    if ($percentage >= 80) {
                                        $percentageClass = 'high';
                                    } elseif ($percentage >= 50) {
                                        $percentageClass = 'medium';
                                    } elseif ($percentage > 0) {
                                        $percentageClass = 'low';
                                    }
                                }
                            @endphp
        
                            <td class="stats-cell">
                                @if ($stats && $stats['lectures_with_limit'] > 0)
                                    <div>
                                        <span class="stats-percentage {{ $percentageClass }}">
                                            {{ $stats['display_text'] }}
                                        </span>
                                    </div>
                                    <div style="font-size: 10px; color: #6c757d; margin-top: 3px;">
                                        {{ $stats['lectures_with_limit'] }} лекций с лимитом
                                    </div>
                                @else
                                    <span class="stats-percentage none">
                                        {{ isset($stats['display_text']) ? $stats['display_text'] : 'нет данных' }}
                                    </span>
                                @endif
                            </td>
                        @endforeach
                    </tr>

                    @foreach ($lectures as $lecture)
                        @php
                            // Определяем ID лекции (поле может называться id_lecture или id)
                            $lectureId = isset($lecture->id_lecture) ? $lecture->id_lecture : $lecture->id;
                            // Определяем номер лекции для отображения
                            $lectureNumber = isset($lecture->lecture_number) ? $lecture->lecture_number : $lectureId;
                            // Дата лекции
                            $lectureDate = isset($lecture->date) ? $lecture->date : '';
                        @endphp
                        
                        <tr>
                            <td class="text-start">
                                <strong>Лекция {{ $lectureNumber }}</strong><br>

                            </td>

                            @foreach ($groups as $group)
                                @php
                                    $groupId = $group->group_id;

                                    // Лимит (может быть 0, null или число)
                                    $limit = isset($limits[$lectureId][$groupId])
                                        ? $limits[$lectureId][$groupId]
                                        : null;

                                    // Текущее количество отметок
                                    $current = isset($currentAttendance[$lectureId][$groupId])
                                        ? $currentAttendance[$lectureId][$groupId]
                                        : 0;

                                    // Определяем цвет
                                    $colorClass = 'gray';
                                    $statusText = 'Не установлен';
                                    
                                    if ($limit !== null) {
                                        if ($limit == 0) {
                                            // Лимит = 0 - зеленый (все отметили)
                                            $colorClass = 'green';
                                            $statusText = "{$current}/0";
                                        } elseif ($current == 0) {
                                            // Лимит есть, но никто не отмечен
                                            $colorClass = 'red';
                                            $statusText = "{$current}/{$limit}";
                                        } elseif ($current == $limit) {
                                            // Все по лимиту отмечены
                                            $colorClass = 'green';
                                            $statusText = "{$current}/{$limit}";
                                        } else {
                                            // Кто-то отмечен, но не все
                                            $colorClass = 'yellow';
                                            $statusText = "{$current}/{$limit}";
                                        }
                                    }
                                @endphp

                                <td class="limit-cell {{ $colorClass }}">
                                    <input type="number"
                                           name="limits[{{ $lectureId }}][{{ $groupId }}]"
                                           class="form-control form-control-sm text-center"
                                           value="{{ $limit !== null ? $limit : '' }}"
                                           min="0"
                                           placeholder="{{ $limit !== null ? $limit : '-' }}"
                                           data-lecture="{{ $lectureId }}"
                                           data-group="{{ $groupId }}"
                                           data-current="{{ $current }}">
                                    
                                    @if ($limit !== null || $current > 0)
                                        <span class="limit-info {{ $colorClass }}">
                                            {{ $statusText }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-success">
                💾 Сохранить все лимиты
            </button>
            <a href="{{ route('lectures.showLimitsMatrix') }}" class="btn btn-secondary">
                🔄 Обновить (показать текущие лимиты)
            </a>
        </div>
    </form>
</div>

<script>
// Динамическое обновление цветов при изменении лимита
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[name^="limits"]');
    
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            const td = this.closest('td');
            const current = parseInt(this.dataset.current) || 0;
            const limit = parseInt(this.value) || 0;
            const limitIsNull = this.value === '';
            
            // Обновляем data-current если нужно
            this.dataset.current = current;
            
            // Убираем все цветовые классы
            td.classList.remove('gray', 'red', 'yellow', 'green');
            
            // Находим span с информацией
            let infoSpan = td.querySelector('.limit-info');
            if (!infoSpan) {
                infoSpan = document.createElement('span');
                infoSpan.className = 'limit-info';
                td.appendChild(infoSpan);
            }
            
            let colorClass = 'gray';
            let statusText = 'Не установлен';
            
            if (limitIsNull) {
                colorClass = 'gray';
                statusText = 'Не установлен';
            } else if (limit == 0) {
                colorClass = 'green';
                statusText = `${current}/0`;
            } else if (current == 0) {
                colorClass = 'red';
                statusText = `${current}/${limit}`;
            } else if (current == limit) {
                colorClass = 'green';
                statusText = `${current}/${limit}`;
            } else {
                colorClass = 'yellow';
                statusText = `${current}/${limit}`;
            }
            
            // Применяем цвет
            td.classList.add(colorClass);
            infoSpan.className = `limit-info ${colorClass}`;
            infoSpan.textContent = statusText;
        });
    });
    
    // Логирование при отправке формы
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        console.log('Отправка формы с лимитами...');
    });
});
</script>
@endsection
