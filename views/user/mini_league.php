<section class="page-heading">
    <div>
        <p class="eyebrow">Мини-лига</p>
        <h1><?= h($league['name']) ?></h1>
        <p class="lead">
            Код приглашения:
            <strong><?= h($league['invite_code']) ?></strong>
            · участников: <?= (int) $league['members_count'] ?>
        </p>
    </div>
    <a class="button small secondary" href="/mini-leagues">Все мини-лиги</a>
</section>

<section class="card">
    <h2>Таблица мини-лиги</h2>
    <?php if (!$leaders): ?>
        <p class="muted">В мини-лиге пока нет участников.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Участник</th>
                        <th>Очки</th>
                        <th>Матчи</th>
                        <th>Чемпион</th>
                        <th>Точные</th>
                        <th>Исходы</th>
                        <th>Прогнозы</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaders as $index => $leader): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= h($leader['name']) ?></td>
                            <td><strong><?= (int) $leader['total_points'] ?></strong></td>
                            <td><?= (int) $leader['match_points'] ?></td>
                            <td><?= (int) $leader['champion_points'] ?></td>
                            <td><?= (int) $leader['exact_scores_count'] ?></td>
                            <td><?= (int) $leader['outcomes_count'] ?></td>
                            <td><?= (int) $leader['predictions_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
