@extends('templates.base')

@section('head')
<title>Управление табло</title>
{!! HTML::style('css/bootstrap.css') !!}
{!! HTML::style('css/materialadmin.css') !!}
{!! HTML::style('css/full.css') !!}
<style>
.manage-wrap {
    padding: 24px 16px;
    max-width: 860px;
    margin: 0 auto;
}
.manage-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 6px rgba(0,0,0,.09);
    padding: 22px 24px;
    margin-bottom: 24px;
}
.manage-section h3 {
    font-size: 17px;
    font-weight: 700;
    color: #1565C0;
    margin: 0 0 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e3f2fd;
}
.teacher-list {
    list-style: none;
    margin: 0 0 12px;
    padding: 0;
}
.teacher-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 10px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}
.teacher-list li:last-child { border-bottom: none; }
.teacher-list li:hover { background: #f9fbff; }
.teacher-list .teacher-name { flex: 1; }
.teacher-list .teacher-role {
    font-size: 11px;
    color: #888;
    margin-right: 10px;
}
.btn-remove {
    color: #c62828;
    background: none;
    border: 1px solid #ef9a9a;
    border-radius: 4px;
    padding: 2px 10px;
    font-size: 12px;
    cursor: pointer;
    white-space: nowrap;
}
.btn-remove:hover { background: #ffebee; }

/* Checkbox list for adding teachers */
.check-list {
    max-height: 260px;
    overflow-y: auto;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-bottom: 12px;
}
.check-list label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 12px;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
    margin: 0;
    font-weight: normal;
}
.check-list label:last-child { border-bottom: none; }
.check-list label:hover { background: #f3f8ff; }
.check-list input[type=checkbox] { width: 16px; height: 16px; flex-shrink: 0; }
.check-list .role-badge {
    font-size: 11px;
    color: #777;
    margin-left: auto;
}
.empty-note {
    color: #aaa;
    font-size: 13px;
    padding: 12px 4px;
    font-style: italic;
}
.select-all-row {
    padding: 6px 12px;
    border-bottom: 1px solid #e0e0e0;
    background: #f5f7fa;
}
.select-all-row label {
    font-size: 12px;
    color: #555;
    cursor: pointer;
    user-select: none;
    margin: 0;
    font-weight: 600;
}
.alert-manage {
    border-radius: 6px;
    padding: 10px 16px;
    margin-bottom: 16px;
    font-size: 14px;
}
</style>
@stop

@section('content')
<div class="manage-wrap">

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <a href="{{ route('schedule_board.index') }}" class="btn btn-default btn-sm">
            &laquo; Назад к табло
        </a>
        <h2 style="margin:0;font-size:20px;font-weight:700;color:#1565C0;">
            Управление табло
        </h2>
    </div>

    @if(session('success'))
    <div class="alert-manage alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert-manage alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- ===== Секция 1: текущие преподаватели на табло ===== --}}
    <div class="manage-section">
        <h3>Преподаватели на табло</h3>

        @if($boardTeachers->isEmpty())
            <p class="empty-note">
                Список пуст — на табло отображаются все преподаватели системы.
                Добавьте конкретных преподавателей ниже, чтобы ограничить список.
            </p>
        @else
        <ul class="teacher-list">
            @foreach($boardTeachers as $t)
            <li>
                <span class="teacher-name">
                    {{ $t->last_name }} {{ $t->first_name }}
                    @if($t->middle_name) {{ $t->middle_name }} @endif
                </span>
                <span class="teacher-role">{{ $t->role }}</span>
                <form method="POST"
                      action="{{ route('schedule_board.teachers.remove', $t->id) }}"
                      style="margin:0;"
                      onsubmit="return confirm('Убрать {{ $t->last_name }} с табло?');">
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <button type="submit" class="btn-remove">Убрать</button>
                </form>
            </li>
            @endforeach
        </ul>
        @endif
    </div>

    {{-- ===== Секция 2: добавить преподавателей ===== --}}
    <div class="manage-section">
        <h3>Добавить преподавателей</h3>

        @if($availableTeachers->isEmpty())
            <p class="empty-note">Все преподаватели уже добавлены на табло.</p>
        @else
        <form method="POST" action="{{ route('schedule_board.teachers.add') }}">
            {{ csrf_field() }}
            <div class="check-list">
                <div class="select-all-row">
                    <label>
                        <input type="checkbox" id="select-all-teachers">
                        &nbsp;Выбрать всех
                    </label>
                </div>
                @foreach($availableTeachers as $t)
                <label>
                    <input type="checkbox" name="teacher_ids[]" value="{{ $t->id }}" class="teacher-cb">
                    {{ $t->last_name }} {{ $t->first_name }}
                    @if($t->middle_name) {{ $t->middle_name }} @endif
                    <span class="role-badge">{{ $t->role }}</span>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                + Добавить выбранных на табло
            </button>
        </form>
        @endif
    </div>


</div>
@stop

@section('js-down')
<script>
(function () {
    var selectAll = document.getElementById('select-all-teachers');
    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.teacher-cb').forEach(function (cb) {
            cb.checked = selectAll.checked;
        });
    });
    // Снять "выбрать всех" если один снят
    document.querySelectorAll('.teacher-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (!this.checked) selectAll.checked = false;
            else {
                var all = document.querySelectorAll('.teacher-cb');
                var checked = document.querySelectorAll('.teacher-cb:checked');
                if (all.length === checked.length) selectAll.checked = true;
            }
        });
    });
})();
</script>
@stop
