<div class="register-layout">
<section class="auth-card card">
    <h1>Регистрация участника</h1>
    <?php if (!empty($_SESSION['pending_mini_league_invite'])): ?>
        <p class="muted">После регистрации вы сразу вступите в мини-лигу по приглашению.</p>
    <?php endif; ?>
    <p class="muted">
        Стартовый взнос — <?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽.
        Двое могут оплатить акцию «Приведи друга» одним переводом <?= number_format(referral_pair_entry_fee_rub(), 0, ',', ' ') ?> ₽ (в комментарии — оба email или оба аккаунта).
        После регистрации админ подтвердит оплату и откроет доступ к прогнозам.
    </p>
    <form method="post" action="/register" class="stack">
        <?= csrf_field() ?>
        <label>
            Имя, фамилия (либо ник)
            <input name="name" required maxlength="120" autocomplete="name" placeholder="Например: Иван Петров или FootballFan42">
        </label>
        <p class="muted register-name-hint">Отображается в таблице лидеров. Имена участников не должны повторяться — при совпадении добавьте фамилию или выберите другой ник.</p>
        <label>
            Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <p class="muted register-name-hint">Адрес почты — для входа в аккаунт на этом сайте и связи с организаторами.</p>
        <label>
            Пароль
            <span class="password-input-wrap">
                <input class="password-input-wrap__field" type="password" name="password" required minlength="8" autocomplete="new-password" id="register-password">
                <button type="button" class="password-toggle" aria-label="Показать пароль" aria-pressed="false" aria-controls="register-password">Показать</button>
            </span>
        </label>
        <p class="muted register-name-hint">Придумайте отдельный пароль для входа в личный кабинет на сайте конкурса — это не пароль от вашей почты.</p>
        <label>
            Подтверждение пароля
            <span class="password-input-wrap">
                <input class="password-input-wrap__field" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" id="register-password-confirm">
                <button type="button" class="password-toggle" aria-label="Показать пароль" aria-pressed="false" aria-controls="register-password-confirm">Показать</button>
            </span>
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="terms_accepted" value="1" required>
            <span>
                Я подтверждаю, что мне есть 14 лет, а если мне нет 18 лет — участие согласовано с родителем или законным представителем. Я принимаю
                <a class="table-link" href="/rules" target="_blank" rel="noopener">правила конкурса</a>,
                <a class="table-link" href="/terms" target="_blank" rel="noopener">условия участия</a>
                и
                <a class="table-link" href="/privacy" target="_blank" rel="noopener">обработку персональных данных</a>.
            </span>
        </label>
        <button class="button" type="submit">Создать аккаунт</button>
    </form>
</section>
</div>
<script>
(function () {
    document.querySelectorAll('.password-input-wrap').forEach(function (wrap) {
        var input = wrap.querySelector('.password-input-wrap__field');
        var btn = wrap.querySelector('.password-toggle');
        if (!input || !btn) {
            return;
        }
        btn.addEventListener('click', function () {
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
            btn.setAttribute('aria-label', hidden ? 'Скрыть пароль' : 'Показать пароль');
            btn.textContent = hidden ? 'Скрыть' : 'Показать';
        });
    });
})();
</script>
