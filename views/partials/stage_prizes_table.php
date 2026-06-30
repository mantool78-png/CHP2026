<?php
/** @var list<array<string,mixed>> $stagePrizesOverview */
/** @var string $stagePrizesContext */

$stagePrizesOverview = $stagePrizesOverview ?? engagement_stage_prizes_overview();
$stagePrizesContext = $stagePrizesContext ?? 'prizes';
$completedCount = count(array_filter(
    $stagePrizesOverview,
    static fn(array $row): bool => ($row['status'] ?? '') === 'completed'
));
$amountSample = (int) ($stagePrizesOverview[0]['amount_rub'] ?? 0);
?>
<div class="stage-prizes-table-block">
    <p class="stage-prizes-table-lead muted">
        <?= stage_prize_pool_count() ?> независимых призов по
        <?= number_format($amountSample, 0, ',', ' ') ?> ₽
        (всего <?= number_format(stage_prize_pool_total(), 0, ',', ' ') ?> ₽).
        Победитель каждого этапа определяется <strong>только по очкам за матчи этого этапа</strong> —
        место в общем рейтинге не важно.
        <?php if ($completedCount > 0): ?>
            Уже определено: <?= $completedCount ?> из <?= stage_prize_pool_count() ?>.
        <?php endif; ?>
    </p>

    <div class="table-scroll stage-prizes-table-scroll">
        <table class="stage-prizes-table">
            <colgroup>
                <col class="stage-col-stage">
                <col class="stage-col-matches">
                <col class="stage-col-prize">
                <col class="stage-col-status">
                <col class="stage-col-participant">
                <col class="stage-col-points">
            </colgroup>
            <thead>
                <tr>
                    <th>Этап</th>
                    <th>Матчи</th>
                    <th>Приз</th>
                    <th>Статус</th>
                    <th>Участник</th>
                    <th>Очки этапа</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stagePrizesOverview as $prize): ?>
                    <?php
                        $status = (string) ($prize['status'] ?? 'upcoming');
                        $leader = $prize['leader'] ?? null;
                        $profileContext = $stagePrizesContext === 'rating' ? 'rating' : 'prizes';
                    ?>
                    <tr class="stage-prizes-table-row stage-prizes-table-row--<?= h($status) ?>">
                        <td>
                            <strong><?= h((string) $prize['title']) ?></strong>
                            <div class="muted small-print"><?= h((string) $prize['description']) ?></div>
                        </td>
                        <td class="stage-prizes-table-matches">
                            <?php if ((int) ($prize['matches_total'] ?? 0) > 0): ?>
                                <?= (int) $prize['matches_finished'] ?> / <?= (int) $prize['matches_total'] ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="stage-prizes-table-prize"><strong><?= number_format((int) $prize['amount_rub'], 0, ',', ' ') ?> ₽</strong></td>
                        <td class="stage-prizes-table-status">
                            <span class="pill stage-prize-status stage-prize-status--<?= h($status) ?>">
                                <?= h((string) ($prize['status_label'] ?? '')) ?>
                            </span>
                        </td>
                        <td class="stage-prizes-table-participant">
                            <?php if (is_array($leader)): ?>
                                <span class="muted small-print stage-prize-holder-label"><?= h((string) ($prize['holder_label'] ?? '')) ?></span>
                                <a class="table-link" href="<?= h(participant_url((int) $leader['user_id'], $profileContext)) ?>">
                                    <?= h((string) $leader['name']) ?>
                                </a>
                            <?php elseif ($status === 'upcoming'): ?>
                                <span class="muted">Этап ещё не начался</span>
                            <?php else: ?>
                                <span class="muted">Пока нет очков у активных участников</span>
                            <?php endif; ?>
                        </td>
                        <td class="stage-prizes-table-points">
                            <?php if (is_array($leader)): ?>
                                <strong class="stage-prizes-table-points-value"><?= (int) $leader['match_points'] ?></strong>
                                <span class="muted small-print stage-prizes-table-points-detail">
                                    <?= (int) ($leader['exact_scores_count'] ?? 0) ?> т.
                                    / <?= (int) ($leader['outcomes_count'] ?? 0) ?> исх.
                                </span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
