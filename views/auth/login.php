<section class="auth-card card">
    <h1>Вход</h1>
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
</section>
