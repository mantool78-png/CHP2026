<?php $distribution = $distribution ?? prize_distribution(); ?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Турнирная таблица</p>
        <h1>Лидеры конкурса</h1>
    </div>
    <div class="pill">Призовой фонд: <?= number_format($prizePool, 0, ',', ' ') ?> ₽</div>
</section>

<section class="card">
    <div class="participant-summary-head">
        <h2>Призы топ-10</h2>
        <a class="button small secondary" href="/prizes">Подробнее</a>
    </div>
    <div class="prize-preview">
        <?php foreach (array_slice($distribution, 0, 3) as $row): ?>
            <div>
                <span><?= (int) $row['place'] ?> место</span>
                <strong><?= (int) $row['percent'] ?>%</strong>
                <p><?= number_format((int) $row['amount'], 0, ',', ' ') ?> ₽ сейчас</p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <?php if (!$leaders): ?>
        <p class="muted">Пока нет активных участников.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Участник</th>
                    <th>Матчи</th>
                    <th>Точные</th>
                    <th>Исходы</th>
                    <th>Прогнозы</th>
                    <th>Чемпион</th>
                    <th>Итого</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaders as $index => $leader): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= h($leader['name']) ?></td>
                        <td><?= (int) $leader['match_points'] ?></td>
                        <td><?= (int) $leader['exact_scores_count'] ?></td>
                        <td><?= (int) $leader['outcomes_count'] ?></td>
                        <td><?= (int) $leader['predictions_count'] ?></td>
                        <td><?= (int) $leader['champion_points'] ?></td>
                        <td><strong><?= (int) $leader['total_points'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
