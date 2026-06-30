<?php
/** @var string $scheduleFilter */
/** @var list<array{key: string, label: string, count: int}> $scheduleFilterTabs */
$scheduleFilter = $scheduleFilter ?? matches_schedule_filter_key(null);
$scheduleFilterTabs = $scheduleFilterTabs ?? matches_schedule_filter_tabs();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Расписание</p>
        <h1>Матчи ЧМ-2026</h1>
        <p class="lead">
            Прогнозы на матчи чемпионата мира по футболу 2026. Время указано по Москве.
            Групповой этап и плей-офф&nbsp;&mdash; на странице <a class="table-link" href="/tournament">«Турнир»</a>.
        </p>
        <?php $lastSync = api_football_last_sync_at(); ?>
        <?php if ($lastSync): ?>
            <p class="muted small-print">Результаты на сайте обновлялись <?= h(date('d.m.Y H:i', strtotime($lastSync))) ?> МСК.</p>
        <?php endif; ?>
    </div>
</section>

<div class="filter-tabs matches-filter-tabs">
    <?php foreach ($scheduleFilterTabs as $tab): ?>
        <a
            class="filter-tab <?= $scheduleFilter === $tab['key'] ? 'active' : '' ?>"
            href="/matches?filter=<?= h($tab['key']) ?>"
        ><?= h($tab['label']) ?><?php if ($tab['count'] > 0): ?> <span class="filter-tab-count"><?= (int) $tab['count'] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$matches): ?>
    <section class="card">
        <p class="muted"><?= h(matches_schedule_empty_message($scheduleFilter)) ?></p>
        <?php if ($scheduleFilter === 'today'): ?>
            <div class="actions">
                <a class="button small secondary" href="/matches?filter=soon">Ближайшие матчи</a>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card">
        <?php if ($scheduleFilter === 'today'): ?>
            <p class="muted small-print matches-filter-hint">Матчи сегодня по Москве и идущие прямо сейчас.</p>
        <?php elseif ($scheduleFilter === 'soon'): ?>
            <p class="muted small-print matches-filter-hint">Предстоящие матчи — с завтрашнего дня и дальше по календарю турнира.</p>
        <?php else: ?>
            <p class="muted small-print matches-filter-hint">Сыгранные матчи, от новых к старым.</p>
        <?php endif; ?>
        <div class="match-list match-list--page">
            <?php foreach ($matches as $match): ?>
                <a class="match-row match-row--page" href="<?= h(match_url((int) $match['id'], 'matches', $scheduleFilter)) ?>">
                    <div class="match-row-main">
                        <span class="match-row-teams">
                            <?php render_match_teams_with_flags($match['home_code'] ?? null, (string) $match['home_team'], $match['away_code'] ?? null, (string) $match['away_team']); ?>
                        </span>
                        <span class="muted match-row-stage"><?= h((string) $match['stage']) ?></span>
                    </div>
                    <div class="match-row-meta">
                        <?php render_match_status_pills($match, true); ?>
                        <time datetime="<?= h(date('c', strtotime((string) $match['starts_at']))) ?>">
                            <?= h(date('d.m.Y H:i', strtotime((string) $match['starts_at']))) ?> МСК
                        </time>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php
$apiFootballWidgetContext = 'matches';
require __DIR__ . '/partials/api_football_widgets.php';
?>

<section class="card">
    <p class="muted">Хотите сделать прогноз? Зарегистрируйтесь&nbsp;&mdash; первые <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов бесплатно.</p>
    <div class="actions">
        <a class="button" href="/register">Начать бесплатно</a>
        <a class="button secondary" href="/tournament">Турнирные таблицы</a>
    </div>
</section>
