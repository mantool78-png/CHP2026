<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Смена пароля</h1>
    </div>
    <a class="button small secondary" href="/admin">Назад</a>
</section>

<section class="auth-card card">
    <form method="post" action="/admin/password" class="stack">
        <?= csrf_field() ?>
        <label>
            Текущий пароль
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label>
            Новый пароль
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        </label>
        <label>
            Повторите новый пароль
            <input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password">
        </label>
        <button class="button" type="submit">Сменить пароль</button>
    </form>
</section>
