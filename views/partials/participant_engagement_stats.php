<?php
/** @var array<string,mixed>|null $engagementStats */
/** @var list<array<string,mixed>> $engagementBadges */
/** @var int|null $compareUserId */

$engagementStats = $engagementStats ?? null;
$engagementBadges = $engagementBadges ?? [];
$compareUserId = isset($compareUserId) ? (int) $compareUserId : null;

if (!$engagementStats || (int) ($engagementStats['finished_predictions'] ?? 0) < 1) {
    if ($engagementBadges === []) {
        return;
    }
}
?>
<section class="card participant-engagement-stats">
    <div class="participant-summary-head">
        <h2>Личная статистика</h2>
        <?php if ($compareUserId): ?>
            <a class="button small secondary" href="/compare?a=<?= (int) $compareUserId ?>">Сравнить с другом</a>
        <?php endif; ?>
    </div>

    <?php if ($engagementStats && (int) ($engagementStats['finished_predictions'] ?? 0) > 0): ?>
        <div class="grid four">
            <div class="card stat">
                <span>Исходы</span>
                <strong><?= (int) $engagementStats['outcome_rate'] ?>%</strong>
                <p class="muted small-print"><?= (int) $engagementStats['outcomes_count'] ?> из <?= (int) $engagementStats['finished_predictions'] ?></p>
            </div>
            <div class="card stat">
                <span>Точные счета</span>
                <strong><?= (int) $engagementStats['exact_rate'] ?>%</strong>
                <p class="muted small-print"><?= (int) $engagementStats['exact_scores_count'] ?> из <?= (int) $engagementStats['finished_predictions'] ?></p>
            </div>
            <div class="card stat">
                <span>Серия с очками</span>
                <strong><?= (int) $engagementStats['points_streak'] ?></strong>
                <p class="muted small-print">матчей подряд</p>
            </div>
            <div class="card stat">
                <span>Vs толпа</span>
                <strong><?= h((string) $engagementStats['boldness_label']) ?></strong>
                <p class="muted small-print"><?= h((string) ($engagementStats['boldness_caption'] ?? '')) ?></p>
            </div>
        </div>

        <?php if (!empty($engagementStats['rank_history'])): ?>
            <h3 class="engagement-subheading">Место после каждого тура</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>День</th>
                            <th>Место</th>
                            <th>Очки</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($engagementStats['rank_history'] as $row): ?>
                            <tr>
                                <td><?= h((string) $row['date_label']) ?></td>
                                <td><?= (int) $row['rank'] ?> / <?= (int) $row['total_participants'] ?></td>
                                <td><?= (int) $row['points'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($engagementBadges !== []): ?>
        <h3 class="engagement-subheading">Достижения</h3>
        <div class="engagement-badges-grid">
            <?php foreach ($engagementBadges as $badge): ?>
                <div class="engagement-badge earned">
                    <strong><?= h((string) $badge['title']) ?></strong>
                    <p class="muted small-print"><?= h((string) $badge['description']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
