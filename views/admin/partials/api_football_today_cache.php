<?php
$todayRows = api_football_configured() ? api_football_cached_today_rows() : [];
$todayCachedAt = api_football_cached_today_at();

if ($todayRows === []) {
    return;
}
?>
<section class="card api-football-today-cache">
    <h2>Матчи ЧМ сегодня (кэш сервера)</h2>
    <p class="muted small-print">
        Ответ API-Football на сегодня, сохранённый cron’ом. Для сверки с расписанием сайта; на зачёт конкурса не влияет.
        <?php if ($todayCachedAt): ?>
            Обновлено <?= h(date('d.m.Y H:i', strtotime($todayCachedAt))) ?> МСК.
        <?php endif; ?>
    </p>
    <ul class="api-football-today-list">
        <?php foreach ($todayRows as $row): ?>
            <li>
                <span class="api-football-today-teams"><?= h($row['home']) ?> — <?= h($row['away']) ?></span>
                <span class="muted"><?= h($row['time']) ?> МСК</span>
                <?php if (in_array($row['status'], ['LIVE', '1H', '2H', 'HT'], true)): ?>
                    <span class="pill live-pill"><?= h(match_live_pill_label()) ?></span>
                <?php endif; ?>
                <span class="api-football-today-score"><?= h($row['score']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
