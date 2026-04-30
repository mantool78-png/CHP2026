<section class="page-heading">
    <div>
        <p class="eyebrow">Личный кабинет</p>
        <h1>Мои очки</h1>
        <p class="lead">История ваших прогнозов, результатов матчей и начисленных баллов.</p>
    </div>
    <a class="button small secondary" href="/dashboard">Назад в кабинет</a>
</section>

<section class="grid four">
    <div class="card stat">
        <span>Итого</span>
        <strong><?= (int) $totalPoints ?></strong>
    </div>
    <div class="card stat">
        <span>За матчи</span>
        <strong><?= (int) $matchPoints ?></strong>
    </div>
    <div class="card stat">
        <span>Точных счетов</span>
        <strong><?= (int) $exactScores ?></strong>
    </div>
    <div class="card stat">
        <span>Исходов</span>
        <strong><?= (int) $outcomes ?></strong>
    </div>
</section>

<section class="card">
    <h2>Прогноз на чемпиона</h2>
    <?php if ($championPrediction): ?>
        <p>
            <?= h($championPrediction['team_name']) ?>
            <span class="pill accent"><?= (int) $championPoints ?> очков</span>
        </p>
    <?php else: ?>
        <p class="muted">Прогноз на чемпиона пока не выбран.</p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Достижения</h2>
    <div class="badges-grid">
        <?php foreach ($badges as $badge): ?>
            <div class="badge-card <?= $badge['earned'] ? 'earned' : 'locked' ?>">
                <span><?= $badge['earned'] ? 'Получено' : 'В процессе' ?></span>
                <strong><?= h($badge['title']) ?></strong>
                <p><?= h($badge['description']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <h2>Прогнозы на матчи</h2>
    <?php if (!$predictions): ?>
        <p class="muted">Вы пока не сделали ни одного прогноза.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Матч</th>
                        <th>Дата</th>
                        <th>Прогноз</th>
                        <th>Результат</th>
                        <th>Очки</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($predictions as $prediction): ?>
                        <tr>
                            <td>
                                <a class="table-link" href="/match?id=<?= (int) $prediction['match_id'] ?>">
                                    <?= h($prediction['home_team']) ?> — <?= h($prediction['away_team']) ?>
                                </a>
                                <div class="muted"><?= h($prediction['stage']) ?></div>
                            </td>
                            <td><?= h(date('d.m.Y H:i', strtotime($prediction['starts_at']))) ?></td>
                            <td><?= (int) $prediction['home_score'] ?> : <?= (int) $prediction['away_score'] ?></td>
                            <td>
                                <?php if ($prediction['result_home_score'] === null || $prediction['result_away_score'] === null): ?>
                                    <span class="muted">Матч не завершен</span>
                                <?php else: ?>
                                    <?= (int) $prediction['result_home_score'] ?> : <?= (int) $prediction['result_away_score'] ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int) $prediction['points'] ?></strong></td>
                            <td><?= h($prediction['reason'] ?: 'Ожидает результата') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
