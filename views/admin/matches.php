<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Матчи и результаты</h1>
    </div>
    <div class="actions">
        <a class="button small" href="/admin/matches/import">Импорт матчей</a>
        <a class="button small secondary" href="/admin">Назад</a>
    </div>
</section>

<section class="card">
    <h2>Добавить матч</h2>
    <form method="post" action="/admin/matches/create" class="admin-form">
        <?= csrf_field() ?>
        <input name="stage" placeholder="Стадия" value="Групповой этап" required>
        <select name="home_team_id" required>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>"><?= h($team['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="away_team_id" required>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>"><?= h($team['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="datetime-local" name="starts_at" required>
        <button class="button" type="submit">Добавить</button>
    </form>
</section>

<section class="card">
    <h2>Расписание</h2>
    <div class="filter-tabs">
        <?php foreach ($stageFilters as $filterKey => $filterLabel): ?>
            <a
                class="filter-tab <?= $activeStage === $filterKey ? 'active' : '' ?>"
                href="/admin/matches<?= $filterKey === 'all' ? '' : '?stage=' . h($filterKey) ?>"
            >
                <?= h($filterLabel) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="muted">Показано матчей: <?= count($matches) ?></p>
    <?php if (!$matches): ?>
        <p class="muted">Матчи еще не добавлены.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Матч</th>
                    <th>Старт</th>
                    <th>Счет</th>
                    <th>Ввод результата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match): ?>
                    <tr>
                        <td><?= h($match['home_team']) ?> — <?= h($match['away_team']) ?></td>
                        <td><?= h(date('d.m.Y H:i', strtotime($match['starts_at']))) ?></td>
                        <td>
                            <?= $match['home_score'] === null ? '—' : (int) $match['home_score'] . ' : ' . (int) $match['away_score'] ?>
                        </td>
                        <td>
                            <form method="post" action="/admin/results" class="result-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                                <input type="number" min="0" name="home_score" value="<?= h($match['home_score'] ?? '') ?>" required>
                                <span>:</span>
                                <input type="number" min="0" name="away_score" value="<?= h($match['away_score'] ?? '') ?>" required>
                                <button class="button small" type="submit">Сохранить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
