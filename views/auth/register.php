<section class="auth-card card">
    <h1>Регистрация участника</h1>
    <?php if (!empty($_SESSION['pending_mini_league_invite'])): ?>
        <p class="muted">После регистрации вы сразу вступите в мини-лигу по приглашению.</p>
    <?php endif; ?>
    <p class="muted">После регистрации админ подтвердит оплату и откроет доступ к прогнозам.</p>
    <form method="post" action="/register" class="stack">
        <?= csrf_field() ?>
        <label>
            Имя
            <input name="name" required maxlength="120" autocomplete="name">
        </label>
        <label>
            Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>
            Пароль
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
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
