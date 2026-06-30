<?php $distribution = $distribution ?? prize_distribution(); ?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Рейтинг прогнозистов</p>
        <h1>Таблица участников</h1>
        <p class="lead muted">
            Общий рейтинг конкурса прогнозов на чемпионат мира 2026: очки за матчи и прогноз на чемпиона.
            Правила начисления&nbsp;&mdash; в <a class="table-link" href="/rules">правилах</a>.
            Все прогнозы&nbsp;&mdash; в разделе <a class="table-link" href="/predictions">«Открытые прогнозы»</a>.
        </p>
    </div>
    <div class="leaderboard-prize-pills">
        <div class="pill accent-pill"><?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?></div>
        <div class="pill"><?= number_format($prizePool, 0, ',', ' ') ?> ₽ местам 2–<?= (int) prize_places_count() ?></div>
    </div>
    <div class="actions compact-actions">
        <a class="button small secondary" href="/rating/stages">По турам и этапам</a>
        <a class="button small secondary" href="/compare">Сравнить</a>
    </div>
</section>

<section class="card">
    <div class="participant-summary-head">
        <h2>Призы топ-<?= (int) prize_places_count() ?></h2>
        <a class="button small secondary" href="/prizes">Подробнее</a>
    </div>
    <div class="prize-preview">
        <?php foreach (array_slice($distribution, 0, 3) as $row): ?>
            <div>
                <span><?= (int) $row['place'] ?> место</span>
                <?php if (!empty($row['is_main_prize'])): ?>
                    <strong class="prize-preview-main"><?= h($row['label']) ?></strong>
                    <p>главный приз</p>
                <?php else: ?>
                    <strong><?= number_format((int) $row['amount_rub'], 0, ',', ' ') ?> ₽</strong>
                    <p>денежный приз</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <div class="participant-summary-head">
        <h2>Рейтинг</h2>
        <?php if ($leaders): ?>
            <a class="button small secondary" href="/rating/pdf">Скачать PDF</a>
        <?php endif; ?>
    </div>
    <?php
    $championPredictionsPublic = champion_predictions_public();
    $championTeamsByUser = $championTeamsByUser ?? [];
    ?>
    <?php if (!$leaders): ?>
        <p class="muted">Пока нет зарегистрированных участников.</p>
    <?php else: ?>
        <?php if (!$championPredictionsPublic): ?>
            <p class="muted small-print">
                Колонка «Чемпион мира» заполнится после закрытия приёма прогнозов на чемпиона
                <?php if (!empty($championDeadline = champion_prediction_deadline())): ?>
                    (<?= h(date('d.m.Y H:i', strtotime($championDeadline))) ?> МСК).
                <?php else: ?>
                    .
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Участник</th>
                    <th>Чемпион мира</th>
                    <th>Очки</th>
                    <th>Точные</th>
                    <th>Исходы</th>
                    <th>Прогнозы</th>
                    <th>Очки чемп.</th>
                    <th>Итого</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaders as $index => $leader): ?>
                    <?php $leaderId = (int) $leader['id']; ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <a class="table-link" href="<?= h(participant_url($leaderId, 'rating')) ?>"><?= h($leader['name']) ?></a>
                        </td>
                        <td>
                            <?php if ($championPredictionsPublic && !empty($championTeamsByUser[$leaderId])): ?>
                                <?= h((string) $championTeamsByUser[$leaderId]) ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
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
        </div>
    <?php endif; ?>
</section>
