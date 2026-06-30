<?php
/** @var array{live: list<array<string,mixed>>, today: list<array<string,mixed>>, upcoming: list<array<string,mixed>>} $scheduleHighlights */
$scheduleHighlights = $scheduleHighlights ?? ['live' => [], 'today' => [], 'upcoming' => []];
$live = $scheduleHighlights['live'] ?? [];
$today = $scheduleHighlights['today'] ?? [];
$upcoming = $scheduleHighlights['upcoming'] ?? [];

if ($live === [] && $today === [] && $upcoming === []) {
    return;
}
?>
<section class="card home-schedule-card">
    <div class="home-schedule-head">
        <div>
            <p class="eyebrow">Календарь</p>
            <h2>ЧМ-2026: live и ближайшие матчи</h2>
        </div>
        <a class="button small secondary" href="/matches">Все матчи</a>
    </div>

    <?php if ($live !== []): ?>
        <h3 class="home-schedule-subtitle"><span class="pill live-pill"><?= h(match_live_pill_label()) ?></span> сейчас</h3>
        <div class="match-list match-list--compact">
            <?php foreach ($live as $match): ?>
                <a class="match-row match-row--page" href="<?= h(match_url((int) $match['id'], 'home')) ?>">
                    <div class="match-row-main">
                        <span class="match-row-teams">
                            <?php render_match_teams_with_flags($match['home_code'] ?? null, (string) $match['home_team'], $match['away_code'] ?? null, (string) $match['away_team']); ?>
                        </span>
                        <span class="muted match-row-stage"><?= h((string) $match['stage']) ?></span>
                    </div>
                    <div class="match-row-meta">
                        <?php render_match_status_pills($match, true); ?>
                        <?php if ($match['home_score'] !== null): ?>
                            <span class="match-row-score"><?= (int) $match['home_score'] ?>:<?= (int) $match['away_score'] ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($today !== []): ?>
        <h3 class="home-schedule-subtitle">Сегодня</h3>
        <div class="match-list match-list--compact">
            <?php foreach ($today as $match): ?>
                <a class="match-row match-row--page" href="<?= h(match_url((int) $match['id'], 'home')) ?>">
                    <div class="match-row-main">
                        <span class="match-row-teams">
                            <?php render_match_teams_with_flags($match['home_code'] ?? null, (string) $match['home_team'], $match['away_code'] ?? null, (string) $match['away_team']); ?>
                        </span>
                        <span class="muted match-row-stage"><?= h((string) $match['stage']) ?></span>
                    </div>
                    <div class="match-row-meta">
                        <time datetime="<?= h(date('c', strtotime((string) $match['starts_at']))) ?>">
                            <?= h(date('H:i', strtotime((string) $match['starts_at']))) ?> МСК
                        </time>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($upcoming !== []): ?>
        <h3 class="home-schedule-subtitle">Ближайшие</h3>
        <div class="match-list match-list--compact">
            <?php foreach ($upcoming as $match): ?>
                <a class="match-row match-row--page" href="<?= h(match_url((int) $match['id'], 'home')) ?>">
                    <div class="match-row-main">
                        <span class="match-row-teams">
                            <?php render_match_teams_with_flags($match['home_code'] ?? null, (string) $match['home_team'], $match['away_code'] ?? null, (string) $match['away_team']); ?>
                        </span>
                        <span class="muted match-row-stage"><?= h((string) $match['stage']) ?></span>
                    </div>
                    <div class="match-row-meta">
                        <time datetime="<?= h(date('c', strtotime((string) $match['starts_at']))) ?>">
                            <?= h(date('d.m.Y H:i', strtotime((string) $match['starts_at']))) ?> МСК
                        </time>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
