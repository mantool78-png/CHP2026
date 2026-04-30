<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Управление конкурсом</h1>
    </div>
    <div class="actions">
        <a class="button small" href="/admin/users">Участники</a>
        <a class="button small secondary" href="/admin/matches">Матчи</a>
        <a class="button small secondary" href="/admin/teams">Команды</a>
        <a class="button small secondary" href="/admin/password">Сменить пароль</a>
    </div>
</section>

<section class="grid four">
    <div class="card stat">
        <span>Участников</span>
        <strong><?= (int) $stats['participants'] ?></strong>
    </div>
    <div class="card stat">
        <span>Оплачено</span>
        <strong><?= (int) $stats['active'] ?></strong>
    </div>
    <div class="card stat">
        <span>Ожидают</span>
        <strong><?= (int) $stats['pending'] ?></strong>
    </div>
    <div class="card stat">
        <span>Призовой фонд</span>
        <strong><?= number_format($prizePool, 0, ',', ' ') ?> ₽</strong>
    </div>
</section>

<section class="card">
    <h2>Чемпион мира</h2>
    <p class="muted">После финала выберите победителя турнира, чтобы начислить 10 бонусных очков.</p>
    <form method="post" action="/admin/champion" class="champion-form">
        <?= csrf_field() ?>
        <select name="team_id" required>
            <option value="">Выберите команду</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>" <?= (string) $championTeamId === (string) $team['id'] ? 'selected' : '' ?>>
                    <?= h($team['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button" type="submit">Начислить бонус</button>
    </form>
</section>
