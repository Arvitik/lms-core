{{--
  Виджет обратной связи.
  Параметры:
    $fbType     — 'lecture' | 'test' | 'course'
    $fbTargetId — id лекции или теста (null для курса)
    $fbTitle    — заголовок (напр. "Оцените эту лекцию")
--}}
@php
    $alreadyLeft = \App\Feedback::alreadyLeft(Auth::id(), $fbType, $fbTargetId ?? null);
    $avgRating   = \App\Feedback::avgRating($fbType, $fbTargetId ?? null);
    $cntRating   = \App\Feedback::countFor($fbType, $fbTargetId ?? null);
    $widgetId    = 'fb-widget-' . $fbType . '-' . ($fbTargetId ?? 'course');
@endphp

<div id="{{ $widgetId }}" class="feedback-widget card style-default-light"
     style="margin: 24px 2%; padding: 20px;">

    <h4 class="text-default-dark" style="margin-top:0;">
        {{ $fbTitle ?? 'Оставьте отзыв' }}
    </h4>

    @if($cntRating > 0)
        <p style="color:#888; margin-bottom:12px;">
            Средняя оценка: <strong>{{ $avgRating }}</strong> ⭐
            ({{ $cntRating }} {{ trans_choice('отзыв|отзыва|отзывов', $cntRating) }})
        </p>
    @endif

    @if($alreadyLeft)
        <div class="alert alert-success" style="margin:0;">
            ✓ Вы уже оставили отзыв. Спасибо!
        </div>
    @else
        <form class="feedback-form" data-widget="{{ $widgetId }}"
              data-type="{{ $fbType }}" data-target="{{ $fbTargetId ?? '' }}">
            {{ csrf_field() }}

            {{-- Звёзды --}}
            <div class="feedback-stars" style="margin-bottom:12px; font-size:28px; cursor:pointer;">
                @for($s = 1; $s <= 5; $s++)
                    <span class="fb-star" data-val="{{ $s }}" style="color:#ccc;">★</span>
                @endfor
                <input type="hidden" name="rating" class="fb-rating-input" value="0">
            </div>

            {{-- Комментарий --}}
            <div style="margin-bottom:10px;">
                <textarea name="comment" placeholder="Комментарий (необязательно)"
                          maxlength="1000"
                          style="width:100%; max-width:600px; padding:8px;
                                 border:1px solid #ddd; border-radius:4px;
                                 font-size:14px; resize:vertical; min-height:70px;"></textarea>
            </div>

            {{-- Анонимно --}}
            <div style="margin-bottom:14px;">
                <label style="font-weight:normal; cursor:pointer;">
                    <input type="checkbox" name="is_anonymous" value="1">
                    Оставить анонимно
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-raised"
                    style="min-width:140px;">
                Отправить
            </button>
            <span class="fb-msg" style="margin-left:12px; font-size:14px;"></span>
        </form>
    @endif
</div>

<style>
.fb-star.active { color: #FFC107 !important; }
.fb-star:hover  { color: #FFD54F !important; }
</style>

<script>
(function () {
    var widget = document.getElementById('{{ $widgetId }}');
    if (!widget) return;

    var form   = widget.querySelector('.feedback-form');
    if (!form) return;

    var stars  = form.querySelectorAll('.fb-star');
    var input  = form.querySelector('.fb-rating-input');
    var msg    = form.querySelector('.fb-msg');

    // Подсветка звёзд
    stars.forEach(function (star) {
        star.addEventListener('mouseover', function () {
            var val = parseInt(this.dataset.val);
            stars.forEach(function (s) {
                s.style.color = parseInt(s.dataset.val) <= val ? '#FFC107' : '#ccc';
            });
        });
        star.addEventListener('mouseleave', function () {
            var sel = parseInt(input.value);
            stars.forEach(function (s) {
                s.style.color = parseInt(s.dataset.val) <= sel ? '#FFC107' : '#ccc';
            });
        });
        star.addEventListener('click', function () {
            input.value = this.dataset.val;
            stars.forEach(function (s) {
                s.style.color = parseInt(s.dataset.val) <= parseInt(input.value) ? '#FFC107' : '#ccc';
            });
        });
    });

    // Отправка
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (input.value == '0') {
            msg.style.color = '#e53935';
            msg.textContent = 'Пожалуйста, выберите оценку.';
            return;
        }

        var btn = form.querySelector('button[type=submit]');
        btn.disabled = true;

        var data = new FormData();
        data.append('_token', form.querySelector('[name=_token]').value);
        data.append('target_type', form.dataset.type);
        data.append('target_id',   form.dataset.target);
        data.append('rating',      input.value);
        data.append('comment',     form.querySelector('[name=comment]').value);
        var anonBox = form.querySelector('[name=is_anonymous]');
        data.append('is_anonymous', anonBox && anonBox.checked ? '1' : '0');

        fetch('{{ route("feedback.store") }}', {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            msg.style.color   = '#43a047';
            msg.textContent   = json.message;
            form.style.opacity = '0.5';
            form.style.pointerEvents = 'none';
        })
        .catch(function () {
            msg.style.color = '#e53935';
            msg.textContent = 'Ошибка отправки. Попробуйте позже.';
            btn.disabled = false;
        });
    });
})();
</script>
