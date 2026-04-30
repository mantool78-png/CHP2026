<section class="auth-card card">
    <h1>Регистрация участника</h1>
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
        <button class="button" type="submit">Создать аккаунт</button>
    </form>
</section>
