@extends('templates.base')

@section('head')
    <title>Новое сообщение</title>
@stop

@section('content')
<div class="col-lg-offset-2 col-md-offset-2 col-md-8 col-lg-8" style="margin-top: 20px;">
    <div class="card style-default-light" style="padding: 24px;">

        <h3 class="text-default-dark" style="margin-top:0;">
            <span class="glyphicon glyphicon-envelope"></span> Новое сообщение
        </h3>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('messages.store') }}" method="POST" id="message-form">
            {{ csrf_field() }}

            {{-- ШАГ 1: выбор группы / категории --}}
            <div class="form-group">
                <label for="group_select">Шаг 1 &mdash; Выберите группу или категорию:</label>
                <select id="group_select" class="form-control">
                    <option value="">— выбрать —</option>
                    @if($isTeacher)
                    <optgroup label="Учебные группы">
                        @foreach($groups as $group)
                            <option value="{{ $group->group_id }}">{{ $group->group_name }}</option>
                        @endforeach
                    </optgroup>
                    @endif
                    <optgroup label="Сотрудники">
                        <option value="teachers">Преподаватели и администраторы</option>
                    </optgroup>
                </select>
            </div>

            {{-- ШАГ 2: выбор конкретного пользователя --}}
            <div class="form-group" id="recipient-group" style="display:none;">
                <label for="to_user_id">Шаг 2 &mdash; Выберите получателя:</label>
                <select name="to_user_id" id="to_user_id" class="form-control">
                    <option value="">— выбрать —</option>
                </select>
                <span id="recipient-loading" style="display:none; color:#888; font-size:13px;">
                    <span class="glyphicon glyphicon-refresh"></span> Загрузка...
                </span>
                <span id="recipient-empty" style="display:none; color:#c0392b; font-size:13px;">
                    В этой категории нет пользователей.
                </span>
            </div>

            {{-- Тема --}}
            <div class="form-group" id="fields-group" style="display:none;">
                <label for="subject">Тема <small class="text-muted">(необязательно)</small>:</label>
                <input type="text" name="subject" id="subject" class="form-control"
                       value="{{ old('subject') }}" placeholder="Например: вопрос по лабораторной работе">
            </div>

            {{-- Текст --}}
            <div class="form-group" id="body-group" style="display:none;">
                <label for="body">Текст сообщения:</label>
                <textarea name="body" id="body" rows="6" class="form-control"
                          required placeholder="Введите текст сообщения...">{{ old('body') }}</textarea>
            </div>

            <div id="submit-group" style="display:none;">
                <button type="submit" class="btn btn-primary btn-raised">
                    <span class="glyphicon glyphicon-send"></span> Отправить
                </button>
                <a href="{{ route('messages.index') }}" class="btn btn-default" style="margin-left:8px;">Отмена</a>
            </div>

        </form>
    </div>
</div>
@stop

@section('js-down')
<script>
(function () {
    var $groupSelect     = $('#group_select');
    var $recipientGroup  = $('#recipient-group');
    var $recipientSelect = $('#to_user_id');
    var $loading         = $('#recipient-loading');
    var $empty           = $('#recipient-empty');
    var $fieldsGroup     = $('#fields-group');
    var $bodyGroup       = $('#body-group');
    var $submitGroup     = $('#submit-group');

    $groupSelect.on('change', function () {
        var groupId = $(this).val();

        $recipientSelect.html('<option value="">— выбрать —</option>');
        $fieldsGroup.hide();
        $bodyGroup.hide();
        $submitGroup.hide();
        $empty.hide();

        if (!groupId) {
            $recipientGroup.hide();
            return;
        }

        $recipientGroup.show();
        $loading.show();
        $recipientSelect.prop('disabled', true);

        $.ajax({
            url: '{{ route("messages.users_by_group") }}',
            method: 'GET',
            data: { group_id: groupId },
            success: function (users) {
                $loading.hide();
                $recipientSelect.prop('disabled', false);
                $recipientSelect.html('<option value="">— выбрать —</option>');

                if (!users || users.length === 0) {
                    $empty.show();
                    return;
                }

                $.each(users, function (i, u) {
                    var label = u.last_name + ' ' + u.first_name;
                    if (u.role) { label += ' (' + u.role + ')'; }
                    $recipientSelect.append($('<option>').val(u.id).text(label));
                });
            },
            error: function () {
                $loading.hide();
                $recipientSelect.prop('disabled', false);
                $empty.text('Ошибка загрузки. Попробуйте снова.').show();
            }
        });
    });

    $recipientSelect.on('change', function () {
        if ($(this).val()) {
            $fieldsGroup.show();
            $bodyGroup.show();
            $submitGroup.show();
        } else {
            $fieldsGroup.hide();
            $bodyGroup.hide();
            $submitGroup.hide();
        }
    });
})();
</script>
@stop
