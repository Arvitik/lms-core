@extends('templates.base')
@section('head')<title>Уведомления и контрольные</title>@stop
@section('content')
<div class="col-lg-offset-2 col-md-offset-2 col-md-8 col-lg-8" style="margin-top:20px;">

    {{-- Вкладки --}}
    <div style="display:flex;border-bottom:2px solid #ddd;margin-bottom:0;">
        <button id="tab-notif-btn" onclick="switchTab('notif')"
            style="padding:10px 24px;border:none;background:none;font-size:15px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:#555;">
            <span class="glyphicon glyphicon-bullhorn"></span> Уведомление
        </button>
        <button id="tab-exam-btn" onclick="switchTab('exam')"
            style="padding:10px 24px;border:none;background:none;font-size:15px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:#555;">
            <span class="glyphicon glyphicon-calendar"></span> Контрольная работа
        </button>
    </div>

    {{-- ============ ВКЛАДКА: УВЕДОМЛЕНИЕ ============ --}}
    <div id="tab-notif" class="card style-default-light" style="padding:24px;border-radius:0 0 4px 4px;">
        <h3 class="text-default-dark" style="margin-top:0;">
            <span class="glyphicon glyphicon-bullhorn"></span> Отправить уведомление
        </h3>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->has('audience') || $errors->has('title') || $errors->has('body') || $errors->has('group_id') || $errors->has('student_id'))
            <div class="alert alert-danger">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form action="{{ route('broadcast.send') }}" method="POST">
            {{ csrf_field() }}

            <div class="form-group">
                <label>Получатели <span class="text-danger">*</span></label>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px;">
                    @if(Auth::user()->role === 'Админ')
                    <label style="font-weight:normal;cursor:pointer;padding:8px 14px;border:2px solid #ddd;border-radius:20px;" id="lbl-all">
                        <input type="radio" name="audience" value="all" style="margin-right:6px;">
                        <span class="glyphicon glyphicon-globe"></span> Все пользователи
                    </label>
                    @endif
                    <label style="font-weight:normal;cursor:pointer;padding:8px 14px;border:2px solid #ddd;border-radius:20px;" id="lbl-group">
                        <input type="radio" name="audience" value="group" style="margin-right:6px;">
                        <span class="glyphicon glyphicon-education"></span> Группа
                    </label>
                    <label style="font-weight:normal;cursor:pointer;padding:8px 14px;border:2px solid #ddd;border-radius:20px;" id="lbl-student">
                        <input type="radio" name="audience" value="student" style="margin-right:6px;">
                        <span class="glyphicon glyphicon-user"></span> Конкретный студент
                    </label>
                </div>
            </div>

            <div class="form-group" id="group-picker" style="display:none;">
                <label>Группы <span class="text-danger">*</span> <small class="text-muted">(можно отметить несколько)</small></label>
                <div style="border:1px solid #ccc;border-radius:4px;padding:8px 12px;max-height:160px;overflow-y:auto;background:#fff;">
                    @foreach($groups as $g)
                    <div>
                        <label style="font-weight:normal;cursor:pointer;margin:2px 0;">
                            <input type="checkbox" name="group_ids[]" value="{{ $g->group_id }}"
                                style="margin-right:6px;">{{ $g->group_name }}
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>

            <div id="student-section" style="display:none;">
                <div class="form-group">
                    <label>Шаг 1 — Выберите группу:</label>
                    <select id="student-group-select" class="form-control">
                        <option value="">— выберите группу —</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->group_id }}">{{ $g->group_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="student-picker" style="display:none;">
                    <label>Шаг 2 — Выберите студента:</label>
                    <select name="student_id" id="student-select" class="form-control">
                        <option value="">— выберите студента —</option>
                    </select>
                    <span id="student-loading" style="display:none;font-size:13px;color:#888;">
                        <span class="glyphicon glyphicon-refresh"></span> Загрузка...
                    </span>
                    <span id="student-empty" style="display:none;font-size:13px;color:#c0392b;">
                        В этой группе нет студентов.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label>Заголовок <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="{{ old('title') }}"
                       placeholder="Краткий заголовок уведомления" required>
            </div>

            <div class="form-group">
                <label>Текст уведомления <span class="text-danger">*</span></label>
                <textarea name="body" rows="4" class="form-control"
                          placeholder="Текст, который увидят пользователи..." required>{{ old('body') }}</textarea>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary btn-raised">
                    <span class="glyphicon glyphicon-send"></span> Отправить
                </button>
                <a href="{{ route('personal_account') }}" class="btn btn-default" style="margin-left:8px;">Отмена</a>
            </div>
        </form>
    </div>

    {{-- ============ ВКЛАДКА: КОНТРОЛЬНАЯ РАБОТА ============ --}}
    <div id="tab-exam" class="card style-default-light" style="padding:24px;border-radius:0 0 4px 4px;display:none;">
        <h3 class="text-default-dark" style="margin-top:0;">
            <span class="glyphicon glyphicon-calendar"></span> Назначить контрольную работу
        </h3>

        @if(session('success_exam'))
            <div class="alert alert-success">{{ session('success_exam') }}</div>
        @endif
        @if($errors->has('scheduled_date') || $errors->has('exam_title'))
            <div class="alert alert-danger">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form action="{{ route('broadcast.exam.store') }}" method="POST">
            {{ csrf_field() }}

            <div class="form-group">
                <label>Преподаватель <span class="text-danger">*</span></label>
                <select name="teacher_id" class="form-control" required>
                    <option value="">— выберите преподавателя —</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" {{ old('teacher_id') == $t->id ? 'selected' : '' }}>
                            {{ $t->last_name }} {{ $t->first_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Группы <span class="text-danger">*</span> <small class="text-muted">(можно отметить несколько)</small></label>
                <div style="border:1px solid #ccc;border-radius:4px;padding:8px 12px;max-height:160px;overflow-y:auto;background:#fff;">
                    @foreach($groups as $g)
                    <div>
                        <label style="font-weight:normal;cursor:pointer;margin:2px 0;">
                            <input type="checkbox" name="group_ids[]" value="{{ $g->group_id }}"
                                {{ in_array($g->group_id, (array) old('group_ids', [])) ? 'checked' : '' }}
                                style="margin-right:6px;">{{ $g->group_name }}
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="form-group">
                <label>Дата проведения <span class="text-danger">*</span></label>
                <input type="date" name="scheduled_date" class="form-control"
                       value="{{ old('scheduled_date', date('Y-m-d')) }}"
                       min="{{ date('Y-m-d') }}" required>
            </div>

            <div style="display:flex;gap:16px;">
                <div class="form-group" style="flex:1;">
                    <label>Начало <span class="text-danger">*</span></label>
                    <input type="time" name="time_start" class="form-control"
                           value="{{ old('time_start') }}" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Конец <small class="text-muted">(необязательно)</small></label>
                    <input type="time" name="time_end" class="form-control"
                           value="{{ old('time_end') }}">
                </div>
            </div>

            <div class="form-group">
                <label>Название контрольной <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="{{ old('title') }}"
                       placeholder="Например: Контрольная №2 по разделу «Машины Тьюринга»"
                       required>
            </div>

            <div class="form-group">
                <label>Аудитория <span class="text-danger">*</span></label>
                <input type="text" name="room" class="form-control"
                       value="{{ old('room') }}"
                       placeholder="Например: А101" required>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary btn-raised">
                    <span class="glyphicon glyphicon-send"></span> Назначить и уведомить студентов
                </button>
            </div>
        </form>

        {{-- Список назначенных контрольных --}}
        <hr style="margin-top:32px;">
        <h4 class="text-default-dark">
            <span class="glyphicon glyphicon-list"></span> Назначенные контрольные
        </h4>

        @if($schedules->isEmpty())
            <div class="text-center" style="padding:30px 0;color:#aaa;">
                <span class="glyphicon glyphicon-calendar" style="font-size:36px;display:block;margin-bottom:8px;opacity:.3;"></span>
                Контрольных работ пока не назначено
            </div>
        @else
            <table class="table table-hover table-bordered" style="margin-top:12px;">
                <thead class="info">
                    <tr>
                        <th>Дата / Время</th>
                        <th>Название</th>
                        <th>Группы</th>
                        <th>Преподаватель</th>
                        <th>Аудитория</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($schedules as $s)
                    @php $isPast = $s->scheduled_date->isPast(); @endphp
                    <tr @if($isPast) style="opacity:.6;" @endif>
                        <td>
                            <strong @if(!$isPast) style="color:#1976D2;" @endif>
                                {{ $s->scheduled_date->format('d.m.Y') }}
                            </strong>
                            <br><small class="text-muted">
                                {{ $s->time_start ? \Carbon\Carbon::parse($s->time_start)->format('H:i') : '' }}
                                @if($s->time_end)&ndash;{{ \Carbon\Carbon::parse($s->time_end)->format('H:i') }}@endif
                            </small>
                            <br><small class="text-muted">
                                {{ $isPast ? 'Прошла' : $s->scheduled_date->diffForHumans() }}
                            </small>
                        </td>
                        <td><strong>{{ $s->title }}</strong></td>
                        <td style="font-size:13px;">
                            {{ $s->groups->isNotEmpty() ? $s->groups->pluck('group_name')->implode(', ') : '—' }}
                        </td>
                        <td>{{ $s->teacher ? $s->teacher->last_name . ' ' . $s->teacher->first_name : '—' }}</td>
                        <td>{{ $s->room ?? '—' }}</td>
                        <td>
                            <form action="{{ route('broadcast.exam.destroy', $s->id) }}" method="POST">
                                {{ csrf_field() }}
                                {{ method_field('DELETE') }}
                                <button class="btn btn-xs btn-danger"
                                    onclick="return confirm('Удалить контрольную?')">
                                    <span class="glyphicon glyphicon-trash"></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>
@stop

@section('js-down')
<script>
(function(){
    // ---- Переключение вкладок ----
    var activeTab = '{{ request("tab", session("_tab", "notif")) }}';
    @if(session('success_exam'))
        activeTab = 'exam';
    @endif

    function switchTab(tab) {
        activeTab = tab;
        document.getElementById('tab-notif').style.display = tab === 'notif' ? '' : 'none';
        document.getElementById('tab-exam').style.display  = tab === 'exam'  ? '' : 'none';
        var notifBtn = document.getElementById('tab-notif-btn');
        var examBtn  = document.getElementById('tab-exam-btn');
        notifBtn.style.borderBottomColor = tab === 'notif' ? '#1976D2' : 'transparent';
        notifBtn.style.color = tab === 'notif' ? '#1976D2' : '#555';
        examBtn.style.borderBottomColor  = tab === 'exam'  ? '#1976D2' : 'transparent';
        examBtn.style.color = tab === 'exam' ? '#1976D2' : '#555';
    }
    window.switchTab = switchTab;
    switchTab(activeTab);

    // ---- Форма уведомлений ----
    var $radios           = $('input[name="audience"]');
    var $groupPicker      = $('#group-picker');
    var $studentSection   = $('#student-section');
    var $studentGrpSelect = $('#student-group-select');
    var $studentPicker    = $('#student-picker');
    var $studentSelect    = $('#student-select');
    var $loading          = $('#student-loading');
    var $empty            = $('#student-empty');

    $radios.on('change', function(){
        $('label[id^="lbl-"]').css({'border-color':'#ddd','background':''});
        $(this).closest('label').css({'border-color':'#1976D2','background':'#e3f2fd'});
        var val = $(this).val();
        $groupPicker.toggle(val === 'group');
        $studentSection.toggle(val === 'student');
        if (val !== 'student') {
            $studentGrpSelect.val('');
            $studentPicker.hide();
            $studentSelect.html('<option value="">— выберите студента —</option>');
        }
    });

    $studentGrpSelect.on('change', function(){
        var groupId = $(this).val();
        $studentSelect.html('<option value="">— выберите студента —</option>');
        $empty.hide();
        $studentPicker.hide();
        if (!groupId) return;
        $studentPicker.show();
        $loading.show();
        $studentSelect.prop('disabled', true);
        $.ajax({
            url: '{{ route("messages.users_by_group") }}',
            method: 'GET',
            data: { group_id: groupId },
            success: function(users) {
                $loading.hide();
                $studentSelect.prop('disabled', false);
                $studentSelect.html('<option value="">— выберите студента —</option>');
                if (!users || users.length === 0) { $empty.show(); return; }
                $.each(users, function(i, u) {
                    $studentSelect.append($('<option>').val(u.id).text(u.last_name + ' ' + u.first_name));
                });
            },
            error: function() {
                $loading.hide();
                $studentSelect.prop('disabled', false);
                $empty.text('Ошибка загрузки.').show();
            }
        });
    });
})();
</script>
@stop
