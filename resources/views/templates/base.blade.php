<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/html" >
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/full.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
    {!! HTML::style('css/material-design-iconic-font.min.css') !!}
    {!! HTML::style('css/materialadmin_demo.css') !!}
    @yield('head')
    {!! HTML::style('css/navbar.css') !!}
    {!! HTML::style('css/modern-theme.css') !!}
    {{-- jQuery --}}
    <script src="{{ asset('js/jquery.min.js') }}"></script>

    {{-- Bootstrap JS (если используется) --}}
    <script src="{{ asset('js/bootstrap.min.js') }}"></script>

    {{-- Остальной JS проекта --}}
    <script src="{{ asset('js/modules.js') }}"></script>
    {{-- Если есть app.js — подключай тоже --}}
    <script src="{{ asset('js/core/source/app.js') }}"></script>

    {{-- ===== TOAST + MODAL ANIMATIONS ===== --}}
    <style>
    /* ---------- TOAST NOTIFICATIONS ---------- */
    #toast-container {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }
    .toast-msg {
        min-width: 280px;
        max-width: 380px;
        padding: 14px 18px 14px 16px;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        display: flex;
        align-items: flex-start;
        gap: 12px;
        pointer-events: all;
        opacity: 0;
        transform: translateY(16px) scale(0.97);
        transition: opacity 280ms ease, transform 280ms ease;
        border-left: 4px solid #ccc;
        font-size: 14px;
        line-height: 1.45;
        color: #1a2a3a;
    }
    .toast-msg.toast-in {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    .toast-msg.toast-out {
        opacity: 0;
        transform: translateY(8px) scale(0.97);
        transition: opacity 220ms ease, transform 220ms ease;
    }
    .toast-msg.toast-success { border-left-color: #2e9e48; }
    .toast-msg.toast-error   { border-left-color: #d64545; }
    .toast-msg.toast-warning { border-left-color: #e69b2f; }
    .toast-msg.toast-info    { border-left-color: #0b7a75; }
    .toast-icon { font-size: 18px; line-height: 1; flex-shrink: 0; margin-top: 1px; }
    .toast-body { flex: 1; }
    .toast-title { font-weight: 700; margin-bottom: 2px; }
    .toast-text  { color: #5c6b80; font-size: 13px; }
    .toast-close {
        background: none; border: none; cursor: pointer;
        color: #aaa; font-size: 16px; padding: 0; line-height: 1;
        flex-shrink: 0; margin-top: 1px;
    }
    .toast-close:hover { color: #444; }

    /* ---------- SMOOTH BOOTSTRAP MODALS ---------- */
    .modal.fade .modal-dialog {
        transform: translateY(-20px) scale(0.97);
        transition: transform 280ms ease, opacity 280ms ease;
        opacity: 0;
    }
    .modal.in .modal-dialog {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    .modal-backdrop.fade { opacity: 0; transition: opacity 250ms ease; }
    .modal-backdrop.in   { opacity: 0.48; }

    /* ---------- NAVBAR ICONS ---------- */
    .navbar-right.navbar-icons {
        display: flex;
        align-items: stretch;
    }
    .navbar-right.navbar-icons > li {
        display: flex;
        align-items: center;
    }
    .navbar-right.navbar-icons > li > a.btn,
    #notif-bell-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 50px;
        min-width: 46px;
        padding: 0 14px;
        line-height: 1;
    }
    .navbar-right.navbar-icons .glyphicon {
        top: 0;
    }

    /* ---------- NOTIFICATION BELL ---------- */
    #notif-bell-btn {
        position: relative;
        cursor: pointer;
        background: none;
        border: none;
        color: inherit;
    }
    .navbar-board-link .glyphicon-calendar,
    #notif-bell-btn .glyphicon-bell {
        font-size: 15px;
    }
    #notif-badge {
        position: absolute;
        top: 4px;
        right: 4px;
        background: #f44336;
        color: #fff;
        border-radius: 50%;
        font-size: 10px;
        font-weight: 700;
        min-width: 17px;
        height: 17px;
        line-height: 17px;
        text-align: center;
        padding: 0 3px;
        display: none;
    }
    #notif-dropdown {
        position: absolute;
        right: 0;
        top: 100%;
        width: 340px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.18);
        z-index: 9999;
        display: none;
    }
    #notif-dropdown .notif-header {
        padding: 10px 14px;
        background: #1976D2;
        color: #fff;
        border-radius: 4px 4px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        font-size: 14px;
    }
    #notif-dropdown .notif-header a { color: #fff; font-size: 12px; font-weight: 400; text-decoration: underline; }
    #notif-list { max-height: 320px; overflow-y: auto; }
    .notif-item {
        padding: 10px 14px;
        border-bottom: 1px solid #eee;
        cursor: default;
        transition: background 0.15s;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-item.unread { background: #e3f2fd; }
    .notif-item:hover { background: #f5f5f5; }
    .notif-item.unread:hover { background: #bbdefb; }
    .notif-item-title { font-weight: 600; font-size: 13px; color: #212121; }
    .notif-item-body  { font-size: 12px; color: #555; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .notif-item-time  { font-size: 11px; color: #aaa; margin-top: 3px; }
    .notif-item-actions { margin-top: 5px; }
    .notif-item-actions .btn { font-size: 11px; padding: 1px 7px; }
    .notif-footer { padding: 8px 14px; text-align: center; border-top: 1px solid #eee; }
    .notif-footer a { font-size: 13px; color: #1976D2; }
    .notif-empty { padding: 30px 14px; text-align: center; color: #aaa; font-size: 13px; }
    #notif-mark-all-btn {
        background: none; border: none; color: #fff;
        font-size: 12px; text-decoration: underline; cursor: pointer; padding: 0;
    }
    </style>

    @yield('js-down')

</head>
<body class="@yield('background', '')">
<div id="base">
    <div class="offcanvas">
        @yield('left-off-canvas')
    </div>
    <section>
        <nav class="navbar navbar-fixed-top style-primary">
            <div class="container">
                <div class="navbar-header">
                    <a class="" href="{{URL::route('home')}}">
                        <img src="{{URL::asset('/img/AT2.png')}}" width="60px" alt="Главная" style=" padding-right: 10px;">
                    </a>
                    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" width="30" height="30" focusable="false"><title>Menu</title><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-miterlimit="10" d="M4 7h22M4 15h22M4 23h22"></path></svg>
                    </button>
                </div>

                <div id="navbar" class="collapse navbar-collapse">
                    <ul class="nav navbar-nav">
                        <li>
                            <a class="btn btn-primary dropdown-toggle" id="dropdownChoiceTestMode" data-toggle="dropdown">
                                <span>Тестирование </span>
                                <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="dropdownChoiceTestMode">
                                <li><a href="{{URL::route('train_tests')}}" class="btn">Тренировочные</a></li>
                                <li><a href="{{URL::route('adaptive_tests')}}" class="btn">Адаптивные</a></li>
                                <li><a href="{{URL::route('control_tests')}}" class="btn">Контрольные</a></li>
                            </ul>
                        </li>
                        <li><a href="{{URL::route('library_index')}}" class="btn">Библиотека</a></li>
                        @if(Auth::check())
                        <li>
                            <a class="btn btn-primary dropdown-toggle" id="dropdownLearningProcess" data-toggle="dropdown">
                                <span>Учебный процесс </span>
                                <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="dropdownLearningProcess">
                                @if(in_array(Auth::user()->role, ['Админ', 'Преподаватель', 'Старший преподаватель']))
                                    <li><a href="{{ route('schedule_board.index') }}" class="btn">Информационное табло</a></li>
                                @endif
                                @if(in_array(Auth::user()->role, ['Админ', 'Преподаватель']))
                                    <li><a href="{{ route('exam_schedules.index') }}" class="btn">Контрольные работы</a></li>
                                    <li><a href="{{ route('broadcast.create') }}" class="btn">Уведомления</a></li>
                                @else
                                    <li><a href="{{ route('exam_schedules.student') }}" class="btn">Мои контрольные</a></li>
                                @endif
                                @if(in_array(Auth::user()->role, ['Студент', 'Староста', 'Студент-заочник']))
                                    <li><a href="{{ route('current_control.student_schedule') }}" class="btn">Моё расписание</a></li>
                                @endif
                            </ul>
                        </li>
                        @endif
						<li>
							<a class="btn btn-primary dropdown-toggle" id="dropdownChoiceEmulator" data-toggle="dropdown">
								<span>Эмуляторы </span>
								<span class="caret"></span>
							</a>
							<ul class="dropdown-menu" aria-labelledby="dropdownChoiceEmulator">
								<li><a href="{{URL::route('mt2')}}" class="btn">Тьюринг</a></li>
								<li><a href="{{URL::route('ham2')}}" class="btn">Марков</a></li>
								<li><a href="{{URL::route('recursion_index')}}" class="btn">Рекурсия</a></li>
								<li><a href="{{URL::route('Post')}}" class="btn">Пост</a></li>
								<li><a href="{{URL::route('MMT')}}" class="btn">Тьюринг (3 ленты)</a></li>
								<li><a href="{{URL::route('RAM')}}" class="btn">RAM</a></li>
							</ul>
						</li>
                    </ul>
                    <ul class="nav navbar-nav navbar-right navbar-icons">
                        {{-- ===== КОНТРОЛЬНЫЕ (студент) ===== --}}
                        @if(Auth::check() && in_array(Auth::user()->role, ['Студент', 'Студент-заочник']))
                        <li>
                            <a href="{{ route('exam_schedules.student') }}" class="btn" title="Мои контрольные">
                                <span class="glyphicon glyphicon-calendar"></span>
                            </a>
                        </li>
                        @endif
                        @if(Auth::check() && in_array(Auth::user()->role, ['Студент', 'Староста', 'Студент-заочник', 'РЎС‚СѓРґРµРЅС‚', 'РЎС‚Р°СЂРѕСЃС‚Р°', 'РЎС‚СѓРґРµРЅС‚-Р·Р°РѕС‡РЅРёРє']))
                        <li>
                            <a href="{{ route('current_control.student_schedule') }}" class="btn" title="Расписание занятий">
                                <span class="glyphicon glyphicon-list-alt"></span>
                            </a>
                        </li>
                        @endif
                        {{-- ===== КОЛОКОЛЬЧИК УВЕДОМЛЕНИЙ ===== --}}
                        @auth
                        <li style="position: relative;">
                            <button id="notif-bell-btn" title="Уведомления">
                                <span class="glyphicon glyphicon-bell"></span>
                                <span id="notif-badge"></span>
                            </button>
                            <div id="notif-dropdown">
                                <div class="notif-header">
                                    <span><span class="glyphicon glyphicon-bell"></span> Уведомления</span>
                                    <button id="notif-mark-all-btn" title="Отметить все как прочитанные">Прочитать все</button>
                                </div>
                                <div id="notif-list">
                                    <div class="notif-empty">Загрузка...</div>
                                </div>
                                <div class="notif-footer">
                                    <a href="{{ route('notifications.index') }}">Все уведомления</a>
                                </div>
                            </div>
                        </li>
                        @endauth
                        {{-- ===== /КОЛОКОЛЬЧИК ===== --}}
                        <li><a href="{{URL::route('personal_account')}}" class="btn"><span class="glyphicon glyphicon-user"></span></a></li>
                        <li><a href="{{URL::route('logout')}}" class="btn"><span class="glyphicon glyphicon-log-out"></span></a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="section-body" style="margin-top: 80px;">
            @yield('content')
        </div>
    </section>
    <div class="offcanvas">
        @yield('right-off-canvas')
    </div>
</div>

{{-- ===== TOAST CONTAINER ===== --}}
<div id="toast-container"></div>

{!! HTML::script('js/modules.js') !!}
@yield('js-down')

{{-- ===== GLOBAL TOAST JS ===== --}}
<script>
window.showToast = function(type, title, text, duration) {
    duration = duration || 4000;
    var icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    var icon  = icons[type] || '🔔';

    var el = document.createElement('div');
    el.className = 'toast-msg toast-' + type;
    el.innerHTML =
        '<span class="toast-icon">' + icon + '</span>' +
        '<div class="toast-body">' +
            (title ? '<div class="toast-title">' + title + '</div>' : '') +
            (text  ? '<div class="toast-text">'  + text  + '</div>' : '') +
        '</div>' +
        '<button class="toast-close" onclick="this.parentNode._closeToast()">&#10005;</button>';

    el._closeToast = function() {
        el.classList.add('toast-out');
        setTimeout(function(){ if(el.parentNode) el.parentNode.removeChild(el); }, 240);
    };

    document.getElementById('toast-container').appendChild(el);
    requestAnimationFrame(function(){
        requestAnimationFrame(function(){ el.classList.add('toast-in'); });
    });

    if(duration > 0) setTimeout(function(){ el._closeToast(); }, duration);
    return el;
};

// Auto-show Laravel session flash messages as toasts
(function(){
    @if(session('success'))
        showToast('success', null, @json(session('success')));
    @endif
    @if(session('error'))
        showToast('error', null, @json(session('error')));
    @endif
    @if(session('warning'))
        showToast('warning', null, @json(session('warning')));
    @endif
    @if(session('info'))
        showToast('info', null, @json(session('info')));
    @endif
})();
</script>

@auth
<script>
(function () {
    var $btn    = $('#notif-bell-btn');
    var $badge  = $('#notif-badge');
    var $drop   = $('#notif-dropdown');
    var $list   = $('#notif-list');
    var isOpen  = false;
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    var icons = {
        'attendance'    : '✅',
        'new_test'      : '📝',
        'new_message'   : '✉️',
        'test_result'   : '🎯',
        'exam_scheduled': '📅',
        'announcement'  : '📢'
    };

    function timeAgo(dateStr) {
        var d = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - d) / 1000);
        if (diff < 60)    return 'только что';
        if (diff < 3600)  return Math.floor(diff/60) + ' мин. назад';
        if (diff < 86400) return Math.floor(diff/3600) + ' ч. назад';
        return Math.floor(diff/86400) + ' д. назад';
    }

    function renderList(notifications) {
        if (!notifications || notifications.length === 0) {
            $list.html('<div class="notif-empty">Уведомлений нет</div>');
            return;
        }
        var html = '';
        notifications.forEach(function(n) {
            var icon = icons[n.type] || '🔔';
            var unreadClass = n.is_read ? '' : 'unread';
            html += '<div class="notif-item ' + unreadClass + '" data-id="' + n.id + '">';
            html += '<div class="notif-item-title">' + icon + ' ' + $('<div>').text(n.title).html() + '</div>';
            html += '<div class="notif-item-body">'  + $('<div>').text(n.body).html()  + '</div>';
            html += '<div class="notif-item-time">'  + timeAgo(n.created_at) + '</div>';
            if (!n.is_read) {
                html += '<div class="notif-item-actions">';
                html += '<button class="btn btn-xs btn-default notif-read-btn" data-id="' + n.id + '">Прочитано</button>';
                html += '</div>';
            }
            html += '</div>';
        });
        $list.html(html);
    }

    function fetchNotifications() {
        $.ajax({
            url: '{{ route("notifications.unread") }}',
            method: 'GET',
            success: function(data) {
                if (data.unread_count > 0) {
                    $badge.text(data.unread_count > 99 ? '99+' : data.unread_count).show();
                } else {
                    $badge.hide();
                }
                if (isOpen) renderList(data.notifications);
            }
        });
    }

    $btn.on('click', function(e) {
        e.stopPropagation();
        if (isOpen) {
            $drop.hide(); isOpen = false;
        } else {
            $drop.show(); isOpen = true;
            $.ajax({
                url: '{{ route("notifications.unread") }}',
                method: 'GET',
                success: function(data) {
                    if (data.unread_count > 0) {
                        $badge.text(data.unread_count > 99 ? '99+' : data.unread_count).show();
                    } else {
                        $badge.hide();
                    }
                    renderList(data.notifications);
                }
            });
        }
    });

    $(document).on('click', function(e) {
        if (isOpen && !$(e.target).closest('#notif-bell-btn, #notif-dropdown').length) {
            $drop.hide(); isOpen = false;
        }
    });

    $list.on('click', '.notif-read-btn', function(e) {
        e.stopPropagation();
        var id = $(this).data('id');
        $.ajax({
            url: '/notifications/' + id + '/read',
            method: 'POST',
            data: { _token: csrfToken },
            success: function() { fetchNotifications(); }
        });
    });

    $('#notif-mark-all-btn').on('click', function(e) {
        e.stopPropagation();
        $.ajax({
            url: '{{ route("notifications.read_all") }}',
            method: 'POST',
            data: { _token: csrfToken },
            success: function() { fetchNotifications(); }
        });
    });

    fetchNotifications();
    setInterval(fetchNotifications, 30000);
})();
</script>
@endauth

</body>
</html>
