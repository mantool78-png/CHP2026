<?php
/** @var string $viewMode */
/** @var string|null $prizeKey */
/** @var array<string,mixed>|null $prizeOverview */
/** @var list<array<string,mixed>> $stagePrizesOverview */
/** @var string $tab */
/** @var string|null $day */
/** @var list<string> $days */
/** @var list<array<string,mixed>> $leaders */
/** @var array<string,mixed>|null $expert */
/** @var string $tabLabel */

$viewMode = $viewMode ?? 'prize';
$prizeKey = $prizeKey ?? engagement_default_stage_prize_tab();
$stagePrizesOverview = $stagePrizesOverview ?? engagement_stage_prizes_overview();
$tab = $tab ?? 'day';
$day = $day ?? engagement_latest_match_day();
$days = $days ?? engagement_match_days_with_results();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Рейтинг по этапам</p>
        <h1><?= h($tabLabel ?? 'Промежуточные призы') ?></h1>
        <p class="lead muted">
            Отдельные таблицы за каждый из <?= stage_prize_pool_count() ?> промежуточных призов по
            <?= number_format((int) ($stagePrizesOverview[0]['amount_rub'] ?? 0), 0, ',', ' ') ?> ₽.
            Очки считаются <strong>только за матчи выбранного этапа</strong>.
            Общий рейтинг — <a class="table-link" href="/rating">на главной странице рейтинга</a>.
        </p>
    </div>
    <a class="button small secondary" href="/compare">Сравнить с другом</a>
</section>

<div class="filter-tabs filter-tabs--stage-prizes">
    <?php foreach ($stagePrizesOverview as $prizeRow): ?>
        <?php
            $key = (string) $prizeRow['key'];
            $status = (string) ($prizeRow['status'] ?? 'upcoming');
            $isActive = $viewMode === 'prize' && $prizeKey === $key;
        ?>
        <a
            class="filter-tab filter-tab--stage-prize filter-tab--<?= h($status) ?><?= $isActive ? ' active' : '' ?>"
            href="/rating/stages?prize=<?= h(urlencode($key)) ?>"
            title="<?= h((string) $prizeRow['title']) ?>"
        >
            <?= h(engagement_stage_prize_tab_short_label($key)) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($viewMode === 'prize' && $prizeOverview): ?>
    <section class="card stage-prize-detail-card">
        <div class="stage-prize-detail-head">
            <div>
                <p class="eyebrow"><?= h((string) $prizeOverview['title']) ?></p>
                <p class="stage-prize-detail-desc muted"><?= h((string) $prizeOverview['description']) ?></p>
            </div>
            <div class="stage-prize-detail-meta">
                <span class="stage-prize-status stage-prize-status--<?= h((string) $prizeOverview['status']) ?>">
                    <?= h((string) $prizeOverview['status_label']) ?>
                </span>
                <span class="muted stage-prize-detail-matches">
                    Матчей: <?= (int) $prizeOverview['matches_finished'] ?> / <?= (int) $prizeOverview['matches_total'] ?>
                </span>
                <span class="stage-prize-detail-amount">
                    <?= number_format((int) $prizeOverview['amount_rub'], 0, ',', ' ') ?> ₽
                </span>
            </div>
        </div>

        <?php if (!empty($prizeOverview['leader'])): ?>
            <?php $leader = $prizeOverview['leader']; ?>
            <p class="stage-prize-detail-leader">
                <span class="stage-prize-holder-label"><?= h((string) $prizeOverview['holder_label']) ?>:</span>
                <a class="table-link" href="<?= h(participant_url((int) $leader['user_id'], 'rating')) ?>">
                    <strong><?= h((string) $leader['name']) ?></strong>
                </a>
                — <?= (int) $leader['match_points'] ?> очков
                <?php if ((int) ($leader['exact_scores_count'] ?? 0) > 0): ?>
                    <span class="muted">(<?= (int) $leader['exact_scores_count'] ?> точных)</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2 class="stage-prize-leaderboard-title">Таблица этапа</h2>
        <?php
            $emptyMessage = match ($prizeOverview['status'] ?? '') {
                'upcoming' => 'Этап ещё не начался — таблица появится после первых результатов.',
                'in_progress' => 'Пока нет завершённых матчей этого этапа.',
                default => 'Пока нет результатов для выбранного этапа.',
            };
            require __DIR__ . '/partials/stage_leaders_table.php';
        ?>
    </section>
<?php endif; ?>

<section class="card stage-prizes-section stage-prizes-section--compact">
    <h2>Все промежуточные призы</h2>
    <?php
        $stagePrizesContext = 'rating';
        require __DIR__ . '/partials/stage_prizes_table.php';
    ?>
</section>

<details class="card rating-stages-extra-slices"<?= $viewMode === 'slice' ? ' open' : '' ?>>
    <summary>Другие турнирные срезы</summary>
    <p class="muted rating-stages-extra-lead">
        Рейтинг за игровой день, весь групповой этап или весь плей-офф — без привязки к промежуточным призам.
    </p>

    <div class="filter-tabs">
        <a class="filter-tab <?= $viewMode === 'slice' && $tab === 'day' ? 'active' : '' ?>" href="/rating/stages?tab=day">Игровой день</a>
        <a class="filter-tab <?= $viewMode === 'slice' && $tab === 'group' ? 'active' : '' ?>" href="/rating/stages?tab=group">Групповой этап</a>
        <a class="filter-tab <?= $viewMode === 'slice' && $tab === 'playoff' ? 'active' : '' ?>" href="/rating/stages?tab=playoff">Плей-офф</a>
    </div>

    <?php if ($viewMode === 'slice'): ?>
        <?php if ($tab === 'day' && $days !== []): ?>
            <form method="get" action="/rating/stages" class="predictions-stage-filter">
                <input type="hidden" name="tab" value="day">
                <label>
                    <span class="muted">День</span>
                    <select name="day" onchange="this.form.submit()">
                        <?php foreach ($days as $dayOption): ?>
                            <option value="<?= h($dayOption) ?>" <?= $day === $dayOption ? 'selected' : '' ?>><?= h(engagement_match_day_label($dayOption)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        <?php endif; ?>

        <?php if ($expert): ?>
            <section class="expert-tour-card">
                <p class="eyebrow">Эксперт тура</p>
                <p>
                    <a class="table-link" href="<?= h(participant_url((int) $expert['user_id'], 'rating')) ?>"><strong><?= h((string) $expert['name']) ?></strong></a>
                    — <?= (int) $expert['match_points'] ?> очков
                    <?php if ($tab === 'day'): ?>
                        за <?= h((string) $expert['date_label']) ?>
                    <?php endif; ?>
                    <?php if ((int) ($expert['exact_scores_count'] ?? 0) > 0): ?>
                        <span class="muted">(<?= (int) $expert['exact_scores_count'] ?> точных)</span>
                    <?php endif; ?>
                </p>
            </section>
        <?php endif; ?>

        <?php require __DIR__ . '/partials/stage_leaders_table.php'; ?>
    <?php endif; ?>
</details>
