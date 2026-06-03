<?php
$matchBack = match_back_navigation();

$homeCode = $match['home_code'] ?? null;
$awayCode = $match['away_code'] ?? null;
$homeTeam = (string) $match['home_team'];
$awayTeam = (string) $match['away_team'];

$homeFlag = worldcup2026_flag_path($homeCode, $homeTeam);
$awayFlag = worldcup2026_flag_path($awayCode, $awayTeam);

$homeRank = $match['home_fifa_rank'] !== null && $match['home_fifa_rank'] !== '' ? (int) $match['home_fifa_rank'] : null;
$awayRank = $match['away_fifa_rank'] !== null && $match['away_fifa_rank'] !== '' ? (int) $match['away_fifa_rank'] : null;
$homeNote = trim((string) ($match['home_brief_note'] ?? ''));
$awayNote = trim((string) ($match['away_brief_note'] ?? ''));
$homeForm = trim((string) ($match['home_form_last5'] ?? ''));
$awayForm = trim((string) ($match['away_form_last5'] ?? ''));

$hasTeamInsights = $homeRank !== null || $awayRank !== null || $homeNote !== '' || $awayNote !== '' || $homeForm !== '' || $awayForm !== '';

$distTotal = (int) ($predictionStats['total'] ?? 0);
$showDistribution = $distTotal > 0;

if ($showDistribution) {
    $total = max(1, $distTotal);
    $homeP = (int) round(((int) ($predictionStats['home'] ?? 0) / $total) * 100);
    $drawP = (int) round(((int) ($predictionStats['draw'] ?? 0) / $total) * 100);
    $awayP = (int) round(((int) ($predictionStats['away'] ?? 0) / $total) * 100);
    $fix = 100 - $homeP - $drawP - $awayP;
    if ($fix !== 0) {
        $keys = ['home' => (int) ($predictionStats['home'] ?? 0), 'draw' => (int) ($predictionStats['draw'] ?? 0), 'away' => (int) ($predictionStats['away'] ?? 0)];
        arsort($keys);
        $maxKey = array_key_first($keys);
        if ($maxKey === 'home') {
            $homeP += $fix;
        } elseif ($maxKey === 'draw') {
            $drawP += $fix;
        } else {
            $awayP += $fix;
        }
    }
}

// Helper to render form badges
if (!function_exists('match_page_render_form_badges')) {
    function match_page_render_form_badges(string $formString): string {
        if ($formString === '') {
            return '<span class="form-badge form-badge--empty">Нет данных</span>';
        }
        $html = '<div class="form-badges-row">';
        $chars = preg_split('//u', $formString, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            $char = mb_strtoupper($char, 'UTF-8');
            if ($char === 'В' || $char === 'W') {
                $html .= '<span class="form-badge form-badge--win" title="Победа">W</span>';
            } elseif ($char === 'Н' || $char === 'D') {
                $html .= '<span class="form-badge form-badge--draw" title="Ничья">D</span>';
            } elseif ($char === 'П' || $char === 'L') {
                $html .= '<span class="form-badge form-badge--loss" title="Поражение">L</span>';
            } else {
                $html .= '<span class="form-badge form-badge--unknown">' . h($char) . '</span>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}
?>

<div class="match-page-container">
    <p class="page-back-row">
        <a class="button small secondary" href="<?= h($matchBack['url']) ?>">← <?= h($matchBack['label']) ?></a>
    </p>

    <!-- Главная футуристичная карточка матча -->
    <div class="card match-dashboard-card">
        <div class="match-dashboard-header">
            <span class="match-dashboard-stage"><?= h($match['stage']) ?></span>
            <span class="match-dashboard-time"><?= h(date('d.m.Y H:i', strtotime($match['starts_at']))) ?> МСК</span>
        </div>

        <!-- Дуэль команд -->
        <div class="match-duel">
            <div class="match-team-profile match-team-profile--home">
                <div class="match-flag-wrapper">
                    <?php if ($homeFlag): ?>
                        <img class="match-dashboard-flag" src="<?= h($homeFlag) ?>" alt="<?= h($homeTeam) ?>" decoding="async">
                    <?php endif; ?>
                </div>
                <h2 class="match-team-name"><?= h($homeTeam) ?></h2>
            </div>

            <div class="match-vs-score">
                <?php if ($match['home_score'] !== null): ?>
                    <div class="match-score-display">
                        <span class="score-num"><?= (int) $match['home_score'] ?></span>
                        <span class="score-colon">:</span>
                        <span class="score-num"><?= (int) $match['away_score'] ?></span>
                    </div>
                <?php else: ?>
                    <div class="match-vs-badge">VS</div>
                <?php endif; ?>
            </div>

            <div class="match-team-profile match-team-profile--away">
                <div class="match-flag-wrapper">
                    <?php if ($awayFlag): ?>
                        <img class="match-dashboard-flag" src="<?= h($awayFlag) ?>" alt="<?= h($awayTeam) ?>" decoding="async">
                    <?php endif; ?>
                </div>
                <h2 class="match-team-name"><?= h($awayTeam) ?></h2>
            </div>
        </div>

        <!-- Раздел статистики -->
        <div class="match-stats-section">
            
            <!-- FIFA RATING -->
            <div class="match-section-divider">
                <span>FIFA RATING</span>
            </div>
            <div class="match-stats-row">
                <div class="match-stat-value">
                    <?= $homeRank !== null ? '<strong>' . $homeRank . '</strong> <small>место</small>' : '<span class="muted">—</span>' ?>
                </div>
                <div class="match-stat-value">
                    <?= $awayRank !== null ? '<strong>' . $awayRank . '</strong> <small>место</small>' : '<span class="muted">—</span>' ?>
                </div>
            </div>

            <!-- TEAM FORM -->
            <div class="match-section-divider">
                <span>TEAM FORM</span>
            </div>
            <div class="match-stats-row">
                <div class="match-stat-value">
                    <?= match_page_render_form_badges($homeForm) ?>
                </div>
                <div class="match-stat-value">
                    <?= match_page_render_form_badges($awayForm) ?>
                </div>
            </div>

            <!-- PREDICTION DISTRIBUTION -->
            <?php if ($showDistribution): ?>
                <div class="match-section-divider">
                    <span>PREDICTION DISTRIBUTION</span>
                </div>
                <div class="prediction-distribution-container">
                    <!-- Сегментированный прогресс-бар -->
                    <div class="prediction-bar-wrapper">
                        <div class="prediction-bar-segment prediction-bar-segment--home" style="width: <?= $homeP ?>%;" title="Победа <?= h($homeTeam) ?>: <?= $homeP ?>%"></div>
                        <div class="prediction-bar-segment prediction-bar-segment--draw" style="width: <?= $drawP ?>%;" title="Ничья: <?= $drawP ?>%"></div>
                        <div class="prediction-bar-segment prediction-bar-segment--away" style="width: <?= $awayP ?>%;" title="Победа <?= h($awayTeam) ?>: <?= $awayP ?>%"></div>
                    </div>

                    <!-- Подписи процентов -->
                    <div class="prediction-distribution-legend">
                        <div class="legend-item legend-item--home">
                            <span class="legend-dot"></span>
                            <span class="legend-label">Победа <?= h($homeTeam) ?></span>
                            <strong class="legend-val"><?= $homeP ?>%</strong>
                        </div>
                        <div class="legend-item legend-item--draw">
                            <span class="legend-dot"></span>
                            <span class="legend-label">Ничья</span>
                            <strong class="legend-val"><?= $drawP ?>%</strong>
                        </div>
                        <div class="legend-item legend-item--away">
                            <span class="legend-dot"></span>
                            <span class="legend-label">Победа <?= h($awayTeam) ?></span>
                            <strong class="legend-val"><?= $awayP ?>%</strong>
                        </div>
                    </div>

                    <?php if (!empty($predictionStats['top_score'])): ?>
                        <div class="match-dashboard-top-score">
                            <span>Самый популярный счёт:</span>
                            <strong class="top-score-badge"><?= h((string) $predictionStats['top_score']) ?></strong>
                            <small class="muted">(<?= (int) ($predictionStats['top_score_count'] ?? 0) ?> <?= ru_times_suffix((int) ($predictionStats['top_score_count'] ?? 0)) ?>)</small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Подробная справка по сборным (Досье) -->
    <?php if ($homeNote !== '' || $awayNote !== ''): ?>
        <div class="card match-dossier-card">
            <div class="dossier-header">
                <svg class="dossier-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <h2>Подробная аналитика команд</h2>
            </div>
            <div class="dossier-grid">
                <div class="dossier-column dossier-column--home">
                    <h3>
                        <?php if ($homeFlag): ?>
                            <img class="dossier-flag" src="<?= h($homeFlag) ?>" alt="" width="20" height="14">
                        <?php endif; ?>
                        <?= h($homeTeam) ?>
                    </h3>
                    <div class="dossier-text">
                        <?= $homeNote !== '' ? nl2br(h($homeNote)) : '<p class="muted">Нет подробного описания для этой сборной.</p>' ?>
                    </div>
                </div>
                <div class="dossier-column dossier-column--away">
                    <h3>
                        <?php if ($awayFlag): ?>
                            <img class="dossier-flag" src="<?= h($awayFlag) ?>" alt="" width="20" height="14">
                        <?php endif; ?>
                        <?= h($awayTeam) ?>
                    </h3>
                    <div class="dossier-text">
                        <?= $awayNote !== '' ? nl2br(h($awayNote)) : '<p class="muted">Нет подробного описания для этой сборной.</p>' ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Прогнозы участников -->
    <div class="card match-predictions-card">
        <h2>Прогнозы участников</h2>
        <?php if (!match_started($match)): ?>
            <p class="muted">Список участников и их счётов откроется после начала матча.</p>
            <p class="muted small-print">Приём прогнозов закроется за <?= (int) config('app.prediction_lock_minutes') ?> минут до стартового свистка.</p>
        <?php elseif (!$predictions): ?>
            <p class="muted">На этот матч пока нет прогнозов.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="predictions-table">
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
                                <td class="prediction-score-cell"><?= (int) $prediction['home_score'] ?> : <?= (int) $prediction['away_score'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
