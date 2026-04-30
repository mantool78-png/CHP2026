<section class="page-heading">
    <div>
        <p class="eyebrow"><?= h($match['stage']) ?></p>
        <h1><?= h($match['home_team']) ?> — <?= h($match['away_team']) ?></h1>
        <p class="muted"><?= h(date('d.m.Y H:i', strtotime($match['starts_at']))) ?></p>
    </div>
    <?php if ($match['home_score'] !== null): ?>
        <div class="score-big"><?= (int) $match['home_score'] ?> : <?= (int) $match['away_score'] ?></div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Прогнозы участников</h2>
    <?php if (!match_started($match)): ?>
        <p class="muted">Прогнозы откроются после начала матча.</p>
        <p class="muted">Прием прогнозов закроется за <?= (int) config('app.prediction_lock_minutes') ?> минут до стартового свистка.</p>
    <?php elseif (!$predictions): ?>
        <p class="muted">На этот матч пока нет прогнозов.</p>
    <?php else: ?>
        <?php
            $total = max(1, (int) $predictionStats['total']);
            $homePercent = round(((int) $predictionStats['home'] / $total) * 100);
            $drawPercent = round(((int) $predictionStats['draw'] / $total) * 100);
            $awayPercent = round(((int) $predictionStats['away'] / $total) * 100);
        ?>

        <div class="prediction-stats">
            <div class="stat-card">
                <span><?= h($match['home_team']) ?></span>
                <strong><?= (int) $predictionStats['home'] ?></strong>
                <small><?= $homePercent ?>%</small>
            </div>
            <div class="stat-card">
                <span>Ничья</span>
                <strong><?= (int) $predictionStats['draw'] ?></strong>
                <small><?= $drawPercent ?>%</small>
            </div>
            <div class="stat-card">
                <span><?= h($match['away_team']) ?></span>
                <strong><?= (int) $predictionStats['away'] ?></strong>
                <small><?= $awayPercent ?>%</small>
            </div>
        </div>

        <?php if ($predictionStats['popularScores']): ?>
            <h3>Популярные счета</h3>
            <div class="popular-scores">
                <?php foreach ($predictionStats['popularScores'] as $score => $count): ?>
                    <span><?= h($score) ?> · <?= (int) $count ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Прогноз</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($predictions as $prediction): ?>
                        <tr>
                            <td><?= h($prediction['name']) ?></td>
                            <td><?= (int) $prediction['home_score'] ?> : <?= (int) $prediction['away_score'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
