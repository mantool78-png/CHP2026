<?php
/** @var array<string,mixed> $activity */
$activity = $activity ?? engagement_home_activity_snapshot();
$lastMatch = $activity['last_match'] ?? null;
$leader = $activity['leader'] ?? null;
$stagePrize = $activity['stage_prize'] ?? null;
$nextDeadline = $activity['next_deadline'] ?? null;
$expert = $activity['expert'] ?? null;
if ($lastMatch === null && $leader === null && $stagePrize === null && $nextDeadline === null && $expert === null) {
    return;
}
?>
<section class="card home-activity-card">
    <div class="home-activity-head">
        <div>
            <p class="eyebrow accent-strong">Сейчас в лиге</p>
            <h2>Активность</h2>
        </div>
        <?php if (!empty($activity['updated_at'])): ?>
            <span class="muted small-print">Обновлено <?= h(date('d.m H:i', strtotime((string) $activity['updated_at']))) ?> МСК</span>
        <?php endif; ?>
    </div>
    <ul class="home-activity-list">
        <?php if ($expert): ?>
            <li>
                <strong>Эксперт тура:</strong>
                <a class="table-link" href="<?= h(participant_url((int) $expert['user_id'], 'home')) ?>"><?= h((string) $expert['name']) ?></a>
                — <?= (int) $expert['match_points'] ?> очков за <?= h((string) $expert['date_label']) ?>
            </li>
        <?php endif; ?>
        <?php if ($leader): ?>
            <li>
                <strong>Текущий лидер турнира:</strong>
                <a class="table-link" href="<?= h(participant_url((int) $leader['user_id'], 'home')) ?>"><?= h((string) $leader['name']) ?></a>
                — <?= (int) $leader['total_points'] ?> очков
            </li>
        <?php endif; ?>
        <?php if ($stagePrize): ?>
            <li>
                <strong>Лидер <?= h((string) $stagePrize['short_label']) ?>:</strong>
                <?php if (!empty($stagePrize['leader'])): ?>
                    <a class="table-link" href="<?= h(participant_url((int) $stagePrize['leader']['user_id'], 'home')) ?>"><?= h((string) $stagePrize['leader']['name']) ?></a>
                    — <?= (int) $stagePrize['leader']['match_points'] ?> очков за этап
                <?php else: ?>
                    <span class="muted">пока не определён</span>
                <?php endif; ?>
                · <a class="table-link" href="/rating/stages?prize=<?= h(urlencode((string) $stagePrize['key'])) ?>">Таблица этапа</a>
            </li>
        <?php endif; ?>
        <?php if ($lastMatch): ?>
            <li>
                После <?= h((string) $lastMatch['label']) ?>:
                <?= (int) $lastMatch['exact_count'] ?> участников с точным счётом (+3),
                <?= (int) $lastMatch['scored_count'] ?> получили очки
            </li>
        <?php endif; ?>
        <?php if ($nextDeadline): ?>
            <li>
                До закрытия прогнозов на <?= h((string) $nextDeadline['label']) ?>:
                <strong><?= h(engagement_format_duration_short((int) ($nextDeadline['seconds_left'] ?? 0))) ?></strong>
                · <a class="table-link" href="<?= h(match_url((int) $nextDeadline['match_id'], 'home')) ?>">Сделать прогноз</a>
            </li>
        <?php endif; ?>
    </ul>
</section>
