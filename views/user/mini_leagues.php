<section class="page-heading">
    <div>
        <p class="eyebrow">Геймификация</p>
        <h1>Мини-лиги</h1>
        <p class="lead">Создайте отдельную таблицу для друзей, коллег или семьи.</p>
    </div>
    <a class="button small secondary" href="/dashboard">В кабинет</a>
</section>

<section class="grid two">
    <div class="card">
        <h2>Создать мини-лигу</h2>
        <form method="post" action="/mini-leagues/create" class="stack">
            <?= csrf_field() ?>
            <label>
                Название
                <input name="name" required maxlength="120" placeholder="Например: Друзья с работы">
            </label>
            <button class="button" type="submit">Создать</button>
        </form>
    </div>

    <div class="card">
        <h2>Вступить по коду</h2>
        <form method="post" action="/mini-leagues/join" class="stack">
            <?= csrf_field() ?>
            <label>
                Код приглашения
                <input name="invite_code" required maxlength="16" placeholder="Например: A1B2C3D4">
            </label>
            <button class="button" type="submit">Вступить</button>
        </form>
        <p class="muted" style="margin-top: 0.75rem;">Если друг прислал <strong>ссылку</strong> — откройте её: после входа вступление произойдёт само, код вводить не нужно.</p>
    </div>
</section>

<section class="card">
    <h2>Мои мини-лиги</h2>
    <?php if (!$leagues): ?>
        <p class="muted">Вы пока не состоите ни в одной мини-лиге.</p>
    <?php else: ?>
        <div class="mini-league-list">
            <?php foreach ($leagues as $league): ?>
                <a class="mini-league-card" href="/mini-league?id=<?= (int) $league['id'] ?>">
                    <strong><?= h($league['name']) ?></strong>
                    <span class="muted">Код: <?= h($league['invite_code']) ?> · участников: <?= (int) $league['members_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
