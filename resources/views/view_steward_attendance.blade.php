@extends('templates.base')

@section('content')
@php
    $userRole = Auth::user()->role;
    $isTeacher = ($userRole === 'Преподаватель' || $userRole === 'Админ');
@endphp

<div class="container-fluid px-5">
    <h2>Ведомость: посещаемость по отметкам старост</h2>
    


    <form method="GET" action="{{ route('steward.statements.view') }}">
        <div class="form-group">
            <label for="group">Выберите группу:</label>
            <select name="group" id="group" class="form-control">
                @foreach ($groups as $group)
                    <option value="{{ $group->group_id }}" {{ request('group') == $group->group_id ? 'selected' : '' }}>
                        {{ $group->group_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary mt-2">Показать</button>
    </form>

    @if (isset($students))
        @php
            $sortedStudents = $students->sortBy('last_name');
        @endphp

        <table class="table table-bordered mt-4">
            <thead>
                <tr>
                    <th>Студент</th>
                    @foreach ($lectures as $index => $lecture)
                        @php
                            $currentCount = 0;
                            if (isset($marks)) {
                                foreach ($marks as $studentMarks) {
                                    if (isset($studentMarks[$lecture->id_lecture]) && $studentMarks[$lecture->id_lecture]) {
                                        $currentCount++;
                                    }
                                }
                            }
                            $lectureLimit = isset($limits[$lecture->id_lecture]) ? $limits[$lecture->id_lecture] : null;
                        @endphp
                        <th>
                            {{ $index + 1 }}<br>

                            @if ($lectureLimit !== null)
                                <br><small>Лимит: {{ $currentCount }}/{{ $lectureLimit }}</small>
                            @else
                                <br><small>Лимит не установлен</small>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($sortedStudents as $student)
                    <tr>
                        <td>{{ $student->last_name }} {{ $student->first_name }}</td>
                        @foreach ($lectures as $lecture)
                            @php
                                $currentMark = isset($marks[$student->id][$lecture->id_lecture]) && $marks[$student->id][$lecture->id_lecture];
                                $lectureLimit = isset($limits[$lecture->id_lecture]) ? $limits[$lecture->id_lecture] : null;
                                
                                // Считаем текущее количество отметок для этой лекции
                                $currentAttendanceCount = 0;
                                if (isset($marks)) {
                                    foreach ($marks as $studentMarks) {
                                        if (isset($studentMarks[$lecture->id_lecture]) && $studentMarks[$lecture->id_lecture]) {
                                            $currentAttendanceCount++;
                                        }
                                    }
                                }
                                
                                // Преподаватель может всегда кликать, староста - только в рамках лимита
                                $canToggle = $isTeacher || ($lectureLimit === null) || ($currentAttendanceCount < $lectureLimit) || $currentMark;
                                $isBlocked = !$canToggle;
                            @endphp
                            <td class="attendance-cell" 
                                data-student-id="{{ $student->id }}" 
                                data-lecture-id="{{ $lecture->id_lecture }}"
                                data-can-toggle="{{ $canToggle ? '1' : '0' }}"
                                style="cursor: {{ $canToggle ? 'pointer' : 'not-allowed' }}; background-color: {{ $isBlocked ? '#f8f9fa' : 'transparent' }}"
                                title="{{ $canToggle ? 'Нажмите для изменения отметки' : 'Лимит посещаемости достигнут' }}">
                                
                                @if (isset($marks[$student->id][$lecture->id_lecture]) && $marks[$student->id][$lecture->id_lecture])
                                    <span class="attendance-mark" style="color: green; font-weight: bold;">✔</span>
                                @else
                                    <span class="attendance-mark" style="color: red; font-weight: bold;">✖</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="alert alert-info mt-3">
            <strong>Информация:</strong> 
            <ul class="mb-0">
                @if($isTeacher)
                    <li>Вы работаете в режиме преподавателя - можете ставить отметки сверх лимита</li>
                    <li>При превышении лимита он будет автоматически увеличен</li>
                @else
                    <li>Серые ячейки можно изменять в пределах установленного лимита посещаемости</li>
                    <li>Белые ячейки заблокированы - лимит посещаемости достигнут</li>
                @endif
                <li>Для изменения отметки нажмите на соответствующую ячейку</li>
            </ul>
        </div>
    @endif
</div>

@if (isset($students))
<script>
document.addEventListener('DOMContentLoaded', function() {
    var attendanceCells = document.querySelectorAll('.attendance-cell[data-can-toggle="1"]');
    
    for (var i = 0; i < attendanceCells.length; i++) {
        attendanceCells[i].addEventListener('click', function() {
            var studentId = this.getAttribute('data-student-id');
            var lectureId = this.getAttribute('data-lecture-id');
            var currentMark = this.querySelector('.attendance-mark');
            var isPresent = currentMark.textContent === '✔';
            
            toggleAttendance(studentId, lectureId, !isPresent);
        });
    }
    
    function toggleAttendance(studentId, lectureId, newState) {
        var originalContent = event.target.innerHTML;
        event.target.innerHTML = '<span style="color: blue;">...</span>';
        
        fetch('{{ route("steward.attendance.toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                student_id: studentId,
                lecture_id: lectureId,
                presence: newState
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Ошибка: ' + data.message);
                location.reload();
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Произошла ошибка при обновлении данных');
            location.reload();
        });
    }
});
</script>
@endif
@endsection