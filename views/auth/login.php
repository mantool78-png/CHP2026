<section class="auth-card card">
    <h1>Вход</h1>
    <?php if (!empty($_SESSION['pending_mini_league_invite'])): ?>
        <p class="muted">Вы перешли по приглашению в мини-лигу — после входа вступление выполнится автоматически.</p>
    <?php endif; ?>
    <form method="post" action="/login" class="stack">
        <?= csrf_field() ?>
        <label>
            Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>
            Пароль
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button" type="submit">Войти</button>
    </form>
    <p class="muted" style="margin-top: 1rem;">Нет аккаунта? <a class="table-link" href="/register">Регистрация</a></p>
</section>
