<?php
/** @var array<string,mixed> $participant */
/** @var array<string,mixed>|null $summary */
/** @var list<array<string,mixed>> $predictions */
/** @var int $futurePredictionsCount */
/** @var array<string,mixed>|null $championPrediction */
/** @var bool $championLocked */
/** @var array{url: string, label: string} $back */
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Публичный профиль</p>
        <h1><?= h((string) $participant['name']) ?></h1>
        <p class="lead muted">
            Все открытые прогнозы участника на начавшиеся матчи. Очки начисляются по
            <a class="table-link" href="/rules">правилам</a> после внесения результата.
        </p>
        <?php if (($participant['payment_status'] ?? '') === 'pending_payment'): ?>
            <p class="muted small-print">Участник в пробном режиме (ожидает подтверждения оплаты).</p>
        <?php endif; ?>
    </div>
    <div class="participant-summary-head">
        <a class="button small secondary" href="<?= h($back['url']) ?>">← <?= h($back['label']) ?></a>
        <a class="button small secondary" href="/compare?a=<?= (int) $participant['id'] ?>">Сравнить</a>
    </div>
</section>

<?php if ($summary): ?>
    <section class="grid four">
        <div class="card stat">
            <span>Место</span>
            <strong><?= (int) $summary['rank'] ?> / <?= (int) $summary['total_participants'] ?></strong>
        </div>
        <div class="card stat">
            <span>Итого</span>
            <strong><?= (int) $summary['total_points'] ?></strong>
        </div>
        <div class="card stat">
            <span>Точных</span>
            <strong><?= (int) $summary['exact_scores_count'] ?></strong>
        </div>
        <div class="card stat">
            <span>Исходов</span>
            <strong><?= (int) $summary['outcomes_count'] ?></strong>
        </div>
    </section>
<?php endif; ?>

<?php
$compareUserId = (int) $participant['id'];
require __DIR__ . '/partials/participant_engagement_stats.php';
?>

<section class="card">
    <h2>Прогноз на чемпиона</h2>
    <?php if (!$championLocked): ?>
        <p class="muted">Прогнозы на чемпиона мира откроются после закрытия приёма (до начала 1/8 финала).</p>
    <?php elseif ($championPrediction): ?>
        <p>
            <?= h((string) $championPrediction['team_name']) ?>
            <span class="pill accent"><?= (int) ($championPrediction['points'] ?? 0) ?> очков</span>
        </p>
    <?php else: ?>
        <p class="muted">Участник не выбрал чемпиона мира.</p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Прогнозы на матчи</h2>
    <?php if ($futurePredictionsCount > 0): ?>
        <p class="muted small-print">
            Ещё <?= (int) $futurePredictionsCount ?> <?= ru_times_suffix($futurePredictionsCount) ?> на будущие матчи —
            откроются после стартового свистка.
        </p>
    <?php endif; ?>
    <?php if (!$predictions): ?>
        <p class="muted">Пока нет прогнозов на начавшиеся матчи.</p>
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
                                <a class="table-link" href="<?= h(match_url((int) $prediction['match_id'], 'participant')) ?>">
                                    <?= h((string) $prediction['home_team']) ?> — <?= h((string) $prediction['away_team']) ?>
                                </a>
                                <div class="muted"><?= h((string) $prediction['stage']) ?></div>
                            </td>
                            <td><?= h(date('d.m.Y H:i', strtotime((string) $prediction['starts_at']))) ?></td>
                            <td><?= (int) $prediction['home_score'] ?> : <?= (int) $prediction['away_score'] ?></td>
                            <td>
                                <?php if ($prediction['result_home_score'] === null || $prediction['result_away_score'] === null): ?>
                                    <?php if (($prediction['match_status'] ?? '') === 'live'): ?>
                                        <span class="pill live-pill"><?= h(match_live_pill_label()) ?></span>
                                    <?php else: ?>
                                        <span class="muted">Ожидает результата</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= (int) $prediction['result_home_score'] ?> : <?= (int) $prediction['result_away_score'] ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int) $prediction['points'] ?></strong></td>
                            <td><?= h((string) ($prediction['reason'] ?: 'Ожидает результата')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
