@extends('templates.base')

@section('head')
<title>Уведомления</title>
<style>
.notif-card {
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 10px;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    border-left: 5px solid #ccc;
    transition: box-shadow .15s;
    position: relative;
}
.notif-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.13); }
.notif-card.unread { background: #f0f7ff; }

.notif-card.type-attendance       { border-left-color: #4CAF50; }
.notif-card.type-new_test         { border-left-color: #FF9800; }
.notif-card.type-new_message      { border-left-color: #9C27B0; }
.notif-card.type-test_result      { border-left-color: #2196F3; }
.notif-card.type-exam_scheduled   { border-left-color: #f44336; }
.notif-card.type-announcement     { border-left-color: #009688; }

.notif-badge-new {
    background: #f44336; color: #fff; border-radius: 10px;
    font-size: 10px; font-weight: 700; padding: 2px 7px;
    vertical-align: middle; margin-left: 6px;
}
.notif-title  { font-weight: 600; font-size: 15px; color: #212121; }
.notif-body   { font-size: 13px; color: #555; margin-top: 3px; }
.notif-meta   { font-size: 11px; color: #aaa; margin-top: 6px; }
.notif-actions { margin-top: 8px; }
.filter-btn { margin-right: 6px; margin-bottom: 6px; }

/* Модальное окно просмотра уведомления */
#notif-modal-overlay {
    display: none;
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.55);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#notif-modal-overlay.active { display: flex; }
#notif-modal-box {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 8px 40px rgba(0,0,0,.25);
    max-width: 620px;
    width: 94%;
    max-height: 80vh;
    overflow-y: auto;
    padding: 30px 32px;
    position: relative;
}
#notif-modal-close {
    position: absolute; top: 14px; right: 18px;
    font-size: 22px; cursor: pointer; color: #aaa; line-height: 1;
    border: none; background: none;
}
#notif-modal-close:hover { color: #333; }
#notif-modal-title { font-size: 20px; font-weight: 700; color: #212121; margin-bottom: 10px; }
#notif-modal-meta  { font-size: 12px; color: #aaa; margin-bottom: 18px; }
#notif-modal-body  { font-size: 16px; color: #333; line-height: 1.7; white-space: pre-wrap; }
</style>
@stop

@section('content')
{{-- Модальное окно --}}
<div id="notif-modal-overlay">
    <div id="notif-modal-box">
        <button id="notif-modal-close" title="Закрыть">&times;</button>
        <div id="notif-modal-icon" style="font-size:32px;margin-bottom:8px;"></div>
        <div id="notif-modal-title"></div>
        <div id="notif-modal-meta"></div>
        <hr style="margin:12px 0 18px;">
        <div id="notif-modal-body"></div>
    </div>
</div>
<div class="col-lg-offset-1 col-md-offset-1 col-md-10 col-lg-10" style="margin-top:20px;">
    <div class="card style-default-light" style="padding:24px;">

        {{-- Заголовок --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
            <h3 class="text-default-dark" style="margin:0;">
                <span class="glyphicon glyphicon-bell"></span> Уведомления
                @php $unread = $notifications->where('is_read', false)->count(); @endphp
                @if($unread > 0)
                    <span class="notif-badge-new">{{ $unread }} новых</span>
                @endif
            </h3>
            @if($notifications->total() > 0)
            <form action="{{ route('notifications.read_all') }}" method="POST" style="margin:0;">
                {{ csrf_field() }}
                <button type="submit" class="btn btn-default btn-sm btn-raised">
                    <span class="glyphicon glyphicon-ok"></span> Прочитать все
                </button>
            </form>
            @endif
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($notifications->isEmpty())
            <div class="text-center" style="padding:60px 0;color:#aaa;">
                <span class="glyphicon glyphicon-bell" style="font-size:48px;display:block;margin-bottom:12px;opacity:.25;"></span>
                <p style="font-size:16px;">Уведомлений пока нет</p>
            </div>
        @else
            @foreach($notifications as $n)
                @php
                    $icons = [
                        'attendance'    => ['icon' => '', 'label' => 'Посещаемость'],
                        'new_test'      => ['icon' => '', 'label' => 'Новый тест'],
                        'new_message'   => ['icon' => '', 'label' => 'Сообщение'],
                        'test_result'   => ['icon' => '', 'label' => 'Результат теста'],
                        'exam_scheduled'=> ['icon' => '', 'label' => 'Контрольная'],
                        'announcement'  => ['icon' => '', 'label' => 'Объявление'],
                    ];
                    $info = $icons[$n->type] ?? ['icon' => '🔔', 'label' => 'Уведомление'];
                    $data = is_string($n->data) ? json_decode($n->data, true) : ($n->data ?? []);
                    $url  = $data['url'] ?? null;
                @endphp
                <div class="notif-card {{ $n->is_read ? '' : 'unread' }} type-{{ $n->type }}">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                        <div style="font-size:22px;line-height:1;padding-top:2px;">{{ $info['icon'] }}</div>
                        <div style="flex:1;">
                            <div class="notif-title">
                                {{ $n->title }}
                                @if(!$n->is_read)<span class="notif-badge-new">Новое</span>@endif
                                <span style="font-size:11px;font-weight:400;color:#aaa;margin-left:8px;">{{ $info['label'] }}</span>
                            </div>
                            <div class="notif-body" style="max-height:48px;overflow:hidden;text-overflow:ellipsis;">{{ $n->body }}</div>
                            <div class="notif-meta">
                                <span class="glyphicon glyphicon-time"></span>
                                {{ $n->created_at->format('d.m.Y H:i') }} &bull; {{ $n->created_at->diffForHumans() }}
                            </div>
                            <div class="notif-actions">
                                <button type="button" class="btn btn-xs btn-info btn-expand-notif"
                                    data-icon="{{ $info['icon'] }}"
                                    data-title="{{ $n->title }}"
                                    data-body="{{ $n->body }}"
                                    data-label="{{ $info['label'] }}"
                                    data-date="{{ $n->created_at->format('d.m.Y H:i') }}">
                                    <span class="glyphicon glyphicon-fullscreen"></span> Развернуть
                                </button>
                                @if(!$n->is_read)
                                    <form action="{{ route('notifications.read', $n->id) }}" method="POST" style="display:inline;">
                                        {{ csrf_field() }}
                                        <button class="btn btn-xs btn-default">
                                            <span class="glyphicon glyphicon-ok"></span> Прочитано
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('notifications.destroy', $n->id) }}" method="POST" style="display:inline;">
                                    {{ csrf_field() }}
                                    {{ method_field('DELETE') }}
                                    <button class="btn btn-xs btn-danger"
                                        onclick="return confirm('Удалить уведомление?')">
                                        <span class="glyphicon glyphicon-trash"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            <div style="margin-top:20px;">{{ $notifications->links() }}</div>
        @endif
    </div>
</div>
@stop

@section('js-down')
<script>
(function(){
    var overlay = document.getElementById('notif-modal-overlay');
    var btnClose = document.getElementById('notif-modal-close');

    document.querySelectorAll('.btn-expand-notif').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('notif-modal-icon').textContent  = btn.dataset.icon;
            document.getElementById('notif-modal-title').textContent = btn.dataset.title;
            document.getElementById('notif-modal-meta').textContent  = btn.dataset.label + ' · ' + btn.dataset.date;
            document.getElementById('notif-modal-body').textContent  = btn.dataset.body;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    function closeModal(){
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    btnClose.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e){
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
@stop
