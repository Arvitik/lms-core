@extends('templates.base')

@section('content')
<div class="container-fluid px-4">
    <h2 class="mb-4">Отметка посещаемости лекций</h2>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @foreach($lectures as $lectureData)
    @php
        $limit = $lectureData['groupLimit'];
        $isLimitSet = !is_null($limit);
        $isLimitZero = $isLimitSet && $limit == 0;
        $isLimitPositive = $isLimitSet && $limit > 0;
    @endphp
    
    <div class="card shadow-sm mb-4 border-0 {{ !$isLimitSet ? 'border-secondary' : ($isLimitZero ? 'border-warning' : '') }}">
        <!-- Заголовок с индикацией лимита -->
        <div class="card-header 
            {{ !$isLimitSet ? 'bg-secondary text-white' : ($isLimitZero ? 'bg-warning text-dark' : 'bg-primary text-white') }} 
            py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0 flex-grow-1 me-3">Лекция от {{ \Carbon\Carbon::parse($lectureData['lecture']->date)->format('d.m.Y') }}</h5>
                <div class="d-flex align-items-center">
                    <span class="badge {{ !$isLimitSet ? 'bg-light text-dark' : ($isLimitZero ? 'bg-dark' : 'bg-light text-dark') }} fs-6 me-2">
                        Лекция {{ isset($lectureData['lecture']->lecture_number) ? $lectureData['lecture']->lecture_number : '1' }}
                    </span>
                    @if(!$isLimitSet)
                    <span class="badge bg-secondary fs-6">Лимит не установлен</span>
                    @elseif($isLimitZero)
                    <span class="badge bg-danger fs-6">Лимит: 0 (отключено)</span>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <span class="fw-bold me-2">Лимит посещений:</span>
                        <span class="badge 
                            {{ !$isLimitSet ? 'bg-secondary' : ($isLimitZero ? 'bg-danger' : 'bg-info') }} 
                            fs-6">
                            {{ !$isLimitSet ? 'Не установлен' : $limit }}
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <span class="fw-bold me-2">Уже отмечено:</span>
                        <span class="badge 
                            {{ !$isLimitSet ? 'bg-secondary' : ($lectureData['alreadyMarkedCount'] > $limit ? 'bg-danger' : 'bg-success') }} 
                            fs-6">
                            {{ $lectureData['alreadyMarkedCount'] }}{{ $isLimitSet ? "/{$limit}" : '' }}
                        </span>
                    </div>
                </div>
            </div>

            @if(!$isLimitSet)
            <div class="alert alert-info d-flex align-items-center mb-3">
                <i class="fas fa-info-circle me-2"></i>
                <span>Лимит посещаемости для этой лекции не установлен. Обратитесь к преподавателю.</span>
            </div>
            @elseif($isLimitZero)
            <div class="alert alert-warning d-flex align-items-center mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span>Отметка посещаемости отключена для этой лекции (лимит = 0)</span>
            </div>
            @endif

            @if($isLimitPositive && $lectureData['alreadyMarkedCount'] > $limit)
            <div class="alert alert-warning d-flex align-items-center mb-3">
                <span>Внимание! Отмечено больше студентов, чем разрешено по лимиту.</span>
            </div>
            @endif

            <form action="{{ route('steward.attendance.submit') }}" method="POST" class="lecture-form" 
                  id="form-{{ $lectureData['lecture']->id_lecture }}"
                  {{ !$isLimitPositive ? 'onsubmit="return false;"' : '' }}>
                {{ csrf_field() }}
                <input type="hidden" name="id_lecture" value="{{ $lectureData['lecture']->id_lecture }}">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        @if($isLimitPositive)
                        <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="toggleAll(this)">
                            Выбрать всех
                        </button>
                        <small class="text-muted">Выбрано: <span class="checked-count">0</span>/{{ $limit }}</small>
                        @else
                        <small class="text-muted">
                            {{ !$isLimitSet ? 'Выбор студентов недоступен (лимит не установлен)' : 'Выбор студентов недоступен (лимит = 0)' }}
                        </small>
                        @endif
                    </div>
                    <div class="limit-warning text-danger" style="display: none;">
                        <small>Превышен лимит!</small>
                    </div>
                </div>
                
                <div class="students-grid {{ !$isLimitPositive ? 'opacity-50' : '' }}">
                    @foreach($students as $student)
                    <div class="student-item">
                        <div class="form-check">
                            <input class="form-check-input student-checkbox" 
                                   type="checkbox" 
                                   name="students[]" 
                                   value="{{ $student->id }}"
                                   id="student_{{ $lectureData['lecture']->id_lecture }}_{{ $student->id }}"
                                   {{ in_array($student->id, $lectureData['alreadyMarked']) ? 'checked' : '' }}
                                   {{ !$isLimitPositive ? 'disabled' : '' }}
                                   data-limit="{{ $isLimitSet ? $limit : 0 }}"
                                   onchange="validateSelection(this)">
                            <label class="form-check-label student-label" for="student_{{ $lectureData['lecture']->id_lecture }}_{{ $student->id }}">
                                <span class="student-name">{{ $student->last_name }} {{ $student->first_name }}</span>
                                @if(in_array($student->id, $lectureData['alreadyMarked']))
                                <span class="badge bg-success ms-2">✓</span>
                                @endif
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <div class="mt-4">
                    @if($isLimitPositive)
                    <button type="submit" class="btn btn-success w-100 py-2 fs-5">
                        Сохранить отметки для этой лекции
                    </button>
                    @else
                    <button type="button" class="btn btn-secondary w-100 py-2 fs-5" disabled>
                        {{ !$isLimitSet ? 'Отметка недоступна (лимит не установлен)' : 'Отметка отключена (лимит = 0)' }}
                    </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
    @endforeach

    @if(count($lectures) === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 class="text-muted">Нет доступных лекций</h5>
            <p class="text-muted">Лекции не найдены в системе.</p>
        </div>
    </div>
    @endif
</div>

<style>
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 8px;
    max-height: 400px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background-color: #f8f9fa;
}

.student-item {
    padding: 8px 12px;
    background: white;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}

.student-item:hover {
    background-color: #f8f9fa;
    border-color: #007bff;
}

.opacity-50 {
    opacity: 0.6;
}

/* Стили для разных статусов лимита */
.bg-custom-secondary {
    background-color: #6c757d !important;
}
</style>

<script>
// JavaScript код остается таким же, как в предыдущем варианте
document.addEventListener('DOMContentLoaded', function() {
    var forms = document.querySelectorAll('.lecture-form');
    for (var i = 0; i < forms.length; i++) {
        updateCounter(forms[i]);
    }
});

function toggleAll(button) {
    var form = button.closest('.lecture-form');
    var checkboxes = form.querySelectorAll('.student-checkbox:not(:disabled)');
    var allChecked = true;
    
    for (var i = 0; i < checkboxes.length; i++) {
        if (!checkboxes[i].checked) {
            allChecked = false;
            break;
        }
    }
    
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = !allChecked;
    }
    
    button.innerHTML = allChecked ? 'Выбрать всех' : 'Снять всех';
    updateCounter(form);
}

function updateCounter(form) {
    var checkboxes = form.querySelectorAll('.student-checkbox:not(:disabled)');
    var checkedCount = form.querySelectorAll('.student-checkbox:not(:disabled):checked').length;
    var firstCheckbox = checkboxes[0];
    var limit = firstCheckbox ? parseInt(firstCheckbox.getAttribute('data-limit')) : 0;
    var counter = form.querySelector('.checked-count');
    var warning = form.querySelector('.limit-warning');
    
    if (counter) {
        counter.textContent = checkedCount;
        if (checkedCount > limit) {
            counter.className = 'checked-count text-danger fw-bold';
        } else {
            counter.className = 'checked-count';
        }
    }
    
    if (warning) {
        if (checkedCount > limit) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
    }
}

function validateSelection(checkbox) {
    if (checkbox.disabled) return;
    
    var form = checkbox.closest('.lecture-form');
    var checkedCount = form.querySelectorAll('.student-checkbox:not(:disabled):checked').length;
    var limit = parseInt(checkbox.getAttribute('data-limit')) || 0;
    
    if (checkedCount > limit) {
        checkbox.checked = false;
        alert('Превышен лимит! Максимум можно выбрать ' + limit + ' студентов.');
    }
    
    updateCounter(form);
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('student-checkbox') && !e.target.disabled) {
        var form = e.target.closest('.lecture-form');
        var checkedCount = form.querySelectorAll('.student-checkbox:not(:disabled):checked').length;
        var limit = parseInt(e.target.getAttribute('data-limit')) || 0;
        
        if (checkedCount > limit) {
            e.target.checked = false;
            updateCounter(form);
        }
    }
});
</script>
@endsection