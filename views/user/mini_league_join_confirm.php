<section class="auth-card card">
    <h1>Вступить в мини-лигу</h1>
    <p class="lead">«<?= h((string) ($league['name'] ?? '')) ?>»</p>
    <p class="muted">Нажмите кнопку ниже, чтобы добавиться в эту группу и отображаться в её таблице.</p>
    <form method="post" action="/mini-leagues/join" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="invite_code" value="<?= h((string) ($league['invite_code'] ?? '')) ?>">
        <button class="button" type="submit">Вступить</button>
    </form>
    <p class="muted" style="margin-top: 1rem;"><a class="table-link" href="/mini-leagues">Отмена, вернуться к списку</a></p>
</section>
