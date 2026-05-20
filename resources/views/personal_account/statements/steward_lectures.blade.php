@php
    $userRole = Auth::user()->role;
    $isTeacher = ($userRole === 'Преподаватель' || $userRole === 'Админ');
@endphp

<style>
    .steward-attendance-table th,
    .steward-attendance-table td {
        vertical-align: middle !important;
        text-align: center;
    }
    .steward-attendance-table th:first-child,
    .steward-attendance-table td:first-child {
        min-width: 150px;
        text-align: left;
    }
    .steward-attendance-table .attendance-mark {
        font-size: 18px;
        line-height: 1;
    }
    .steward-attendance-table .attendance-cell {
        min-width: 56px;
        height: 56px;
    }
</style>

<h2>Ведомость: посещаемость по отметкам старост</h2>
<br>

@if (isset($students) && $students)
    @php
        $sortedStudents = $students->sortBy('last_name');
    @endphp

    <table class="table table-bordered steward-attendance-table">
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
                            $currentAttendanceCount = 0;
                            if (isset($marks)) {
                                foreach ($marks as $studentMarks) {
                                    if (isset($studentMarks[$lecture->id_lecture]) && $studentMarks[$lecture->id_lecture]) {
                                        $currentAttendanceCount++;
                                    }
                                }
                            }
                            $canToggle = $isTeacher || ($lectureLimit === null) || ($currentAttendanceCount < $lectureLimit) || $currentMark;
                            $isBlocked = !$canToggle;
                        @endphp
                        <td class="attendance-cell"
                            data-student-id="{{ $student->id }}"
                            data-lecture-id="{{ $lecture->id_lecture }}"
                            data-present="{{ $currentMark ? '1' : '0' }}"
                            data-can-toggle="{{ $canToggle ? '1' : '0' }}"
                            style="cursor: {{ $canToggle ? 'pointer' : 'not-allowed' }}; background-color: {{ $isBlocked ? '#f8f9fa' : 'transparent' }}"
                            title="{{ $canToggle ? 'Нажмите для изменения отметки' : 'Лимит посещаемости достигнут' }}">

                            @if ($currentMark)
                                <span class="attendance-mark" style="color: green; font-weight: bold;">&#10004;</span>
                            @else
                                <span class="attendance-mark" style="color: red; font-weight: bold;">&#10006;</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="text-muted">Выберите группу и нажмите «Показать».</p>
@endif

@if (isset($students) && $students)
<script>
(function() {
    $(document)
        .off('click.stewardLectures', '.attendance-cell[data-can-toggle="1"]')
        .on('click.stewardLectures', '.attendance-cell[data-can-toggle="1"]', function() {
            var cell = this;
            var studentId = cell.getAttribute('data-student-id');
            var lectureId = cell.getAttribute('data-lecture-id');
            var currentMark = cell.querySelector('.attendance-mark');
            var isPresent = cell.getAttribute('data-present') === '1';

            currentMark.innerHTML = '...';
            currentMark.style.color = 'blue';

            fetch('{{ route("steward.attendance.toggle") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    student_id: studentId,
                    lecture_id: lectureId,
                    presence: !isPresent
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    $('#show').trigger('click');
                } else {
                    alert('Ошибка: ' + data.message);
                    $('#show').trigger('click');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('Произошла ошибка при обновлении данных');
                $('#show').trigger('click');
            });
        });
})();
</script>
@endif
