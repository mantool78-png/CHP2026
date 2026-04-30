<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Команды</h1>
    </div>
    <a class="button small secondary" href="/admin">Назад</a>
</section>

<section class="card">
    <h2>Добавить команду</h2>
    <form method="post" action="/admin/teams/create" class="team-form">
        <?= csrf_field() ?>
        <input name="name" placeholder="Название, например Аргентина" required maxlength="120">
        <input name="code" placeholder="Код, например ARG" maxlength="12">
        <button class="button" type="submit">Добавить</button>
    </form>
    <p class="muted">Код необязателен, но удобен для импорта матчей и будущих флагов.</p>
</section>

<section class="card">
    <h2>Список команд</h2>
    <?php if (!$teams): ?>
        <p class="muted">Команды пока не добавлены.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Код</th>
                    <th>Используется</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                    <?php
                        $usageCount = (int) $team['matches_count'] + (int) $team['champion_predictions_count'];
                    ?>
                    <tr>
                        <td colspan="4">
                            <form method="post" action="/admin/teams/update" class="team-row">
                                <?= csrf_field() ?>
                                <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
                                <input name="name" value="<?= h($team['name']) ?>" required maxlength="120">
                                <input name="code" value="<?= h($team['code'] ?? '') ?>" maxlength="12">
                                <span class="muted">
                                    <?= (int) $team['matches_count'] ?> матчей,
                                    <?= (int) $team['champion_predictions_count'] ?> прогнозов
                                </span>
                                <button class="button small" type="submit">Сохранить</button>
                            </form>
                            <form method="post" action="/admin/teams/delete" class="delete-team-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
                                <button class="button small danger" type="submit" <?= $usageCount > 0 ? 'disabled' : '' ?>>
                                    Удалить
                                </button>
                                <?php if ($usageCount > 0): ?>
                                    <span class="muted">Удаление недоступно, команда уже используется.</span>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
