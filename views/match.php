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

$tournamentProgress = $tournamentProgress ?? ['home' => ['played' => 0], 'away' => ['played' => 0]];
$showTournamentProgress = ((int) ($tournamentProgress['home']['played'] ?? 0) > 0)
    || ((int) ($tournamentProgress['away']['played'] ?? 0) > 0);

$distTotal = (int) ($predictionStats['total'] ?? 0);
$showDistribution = $distTotal > 0;
$matchHasResult = $match['home_score'] !== null && $match['away_score'] !== null;
$matchIsLive = match_started($match);

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

?>

<div class="match-page-container">
    <p class="page-back-row">
        <a class="button small secondary" href="<?= h($matchBack['url']) ?>">← <?= h($matchBack['label']) ?></a>
    </p>

    <!-- Главная футуристичная карточка матча -->
    <div class="card match-dashboard-card">
        <div class="match-dashboard-header">
            <span class="match-dashboard-stage"><?= h($match['stage']) ?></span>
            <span class="match-dashboard-time">
                <?php if (($match['status'] ?? '') === 'live'): ?>
                    <span class="pill live-pill"><?= h(match_live_pill_label()) ?></span>
                <?php endif; ?>
                <?= h(date('d.m.Y H:i', strtotime($match['starts_at']))) ?> МСК
            </span>
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
                <?php elseif (($match['status'] ?? '') === 'live'): ?>
                    <div class="match-vs-badge live-pill-text"><?= h(match_live_pill_label()) ?></div>
                <?php else: ?>
                    <div class="match-vs-badge" aria-label="против">—</div>
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
            
            <div class="match-section-divider">
                <span>Рейтинг ФИФА</span>
            </div>
            <div class="match-stats-row">
                <div class="match-stat-value">
                    <?= $homeRank !== null ? '<strong>' . $homeRank . '</strong> <small>место</small>' : '<span class="muted">—</span>' ?>
                </div>
                <div class="match-stat-value">
                    <?= $awayRank !== null ? '<strong>' . $awayRank . '</strong> <small>место</small>' : '<span class="muted">—</span>' ?>
                </div>
            </div>

            <?php if ($showTournamentProgress): ?>
                <div class="match-tournament-panel">
                    <div class="match-tournament-panel-head">
                        <span class="match-tournament-panel-badge">На турнире</span>
                        <p class="match-tournament-panel-hint">
                            Завершённые матчи этих сборных на ЧМ-2026 до текущей встречи
                        </p>
                    </div>
                    <div class="match-tournament-panel-columns">
                        <div class="match-tournament-team-col">
                            <h3 class="match-tournament-team-name"><?= h($homeTeam) ?></h3>
                            <?= render_match_tournament_progress_html($tournamentProgress['home']) ?>
                        </div>
                        <div class="match-tournament-team-col">
                            <h3 class="match-tournament-team-name"><?= h($awayTeam) ?></h3>
                            <?= render_match_tournament_progress_html($tournamentProgress['away']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="match-section-divider">
                <span>Форма сборных</span>
            </div>
            <div class="match-stats-row">
                <div class="match-stat-value">
                    <?= match_form_badges_html($homeForm) ?>
                </div>
                <div class="match-stat-value">
                    <?= match_form_badges_html($awayForm) ?>
                </div>
            </div>

            <?php if ($showDistribution): ?>
                <div class="match-section-divider">
                    <span><?= $matchIsLive ? 'Распределение прогнозов' : 'Как ставят участники' ?></span>
                </div>
                <?php if (!$matchIsLive): ?>
                    <p class="muted small-print match-participant-hint-note">
                        Анонимная сводка до старта матча. Список прогнозов по именам откроется после свистка.
                    </p>
                <?php endif; ?>
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
                        <?php
                            $topScoreRaw = (string) $predictionStats['top_score'];
                            $topScoreDisplay = preg_match('/^\d+:\d+$/', $topScoreRaw) === 1
                                ? preg_replace('/^(\d+):(\d+)$/', '$1 : $2', $topScoreRaw)
                                : $topScoreRaw;
                        ?>
                        <div class="match-dashboard-top-score">
                            <span class="match-top-score-label">Самый популярный счёт:</span>
                            <span class="match-top-score-result">
                                <strong class="top-score-badge"><?= h($topScoreDisplay) ?></strong>
                                <small class="muted">(<?= (int) ($predictionStats['top_score_count'] ?? 0) ?> <?= ru_times_suffix((int) ($predictionStats['top_score_count'] ?? 0)) ?>)</small>
                            </span>
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

    <?php
    $h2h = $h2h ?? ['matches' => [], 'summary' => ['home_wins' => 0, 'away_wins' => 0, 'draws' => 0, 'total' => 0], 'cached_at' => null, 'error' => null];
    $h2hEnabled = !empty($h2hEnabled);
    $h2hMatches = $h2h['matches'] ?? [];
    $h2hSummary = $h2h['summary'] ?? [];
    ?>
    <?php if ($h2hEnabled): ?>
        <section class="card match-h2h-card">
            <h2>Очные встречи</h2>
            <p class="muted small-print">
                Прошлые матчи между <?= h($homeTeam) ?> и <?= h($awayTeam) ?>. На зачёт прогнозов не влияет.
            </p>
            <?php if ($h2hMatches !== []): ?>
                <?php if ((int) ($h2hSummary['total'] ?? 0) > 0): ?>
                    <div class="match-h2h-summary">
                        <span class="h2h-stat"><strong><?= (int) ($h2hSummary['home_wins'] ?? 0) ?></strong> побед <?= h($homeTeam) ?></span>
                        <span class="h2h-stat"><strong><?= (int) ($h2hSummary['draws'] ?? 0) ?></strong> ничьих</span>
                        <span class="h2h-stat"><strong><?= (int) ($h2hSummary['away_wins'] ?? 0) ?></strong> побед <?= h($awayTeam) ?></span>
                    </div>
                <?php endif; ?>
                <ul class="match-h2h-list">
                    <?php foreach ($h2hMatches as $h2hRow): ?>
                        <li>
                            <span class="match-h2h-date muted"><?= h((string) $h2hRow['date']) ?></span>
                            <span class="match-h2h-teams">
                                <?php
                                $h2hHome = team_name_by_api_team_id((int) ($h2hRow['home_api_id'] ?? 0)) ?? (string) $h2hRow['home'];
                                $h2hAway = team_name_by_api_team_id((int) ($h2hRow['away_api_id'] ?? 0)) ?? (string) $h2hRow['away'];
                                ?>
                                <?= h($h2hHome) ?>
                                <span class="match-h2h-score"><?= h((string) $h2hRow['score']) ?></span>
                                <?= h($h2hAway) ?>
                            </span>
                            <?php if (!empty($h2hRow['competition'])): ?>
                                <span class="muted small-print"><?= h(match_reference_competition_ru((string) $h2hRow['competition'])) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif (!empty($h2h['error'])): ?>
                <p class="muted">Не удалось загрузить историю очных встреч. Попробуйте позже.</p>
            <?php else: ?>
                <p class="muted">Ранее эти сборные не встречались на крупных турнирах или данных об очных матчах нет.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Прогнозы участников -->
    <div class="card match-predictions-card">
        <div class="match-predictions-head">
            <h2>Прогнозы участников</h2>
            <?php if (match_started($match) && $predictions): ?>
                <a class="button small secondary" href="/match/pdf?id=<?= (int) $match['id'] ?>">Скачать PDF</a>
            <?php endif; ?>
        </div>
        <?php if (!match_started($match)): ?>
            <p class="muted">Список участников и их счётов откроется после начала матча.</p>
            <p class="muted small-print">Приём прогнозов закроется за <?= (int) config('app.prediction_lock_minutes') ?> минут до стартового свистка.</p>
        <?php elseif (!$predictions): ?>
            <p class="muted">На этот матч пока нет прогнозов.</p>
        <?php else: ?>
            <p class="muted small-print">
                Полная матрица всех прогнозов&nbsp;&mdash; в разделе
                <a class="table-link" href="/predictions">«Открытые прогнозы»</a>.
            </p>
            <div class="table-scroll">
                <table class="predictions-table">
                    <thead>
                        <tr>
                            <th>Участник</th>
                            <th>Прогноз</th>
                            <?php if ($matchHasResult): ?>
                                <th>Очки</th>
                                <th>Статус</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($predictions as $prediction): ?>
                            <tr>
                                <td>
                                    <a class="table-link" href="<?= h(participant_url((int) $prediction['user_id'], 'match')) ?>"><?= h($prediction['name']) ?></a>
                                </td>
                                <td class="prediction-score-cell"><?= (int) $prediction['home_score'] ?> : <?= (int) $prediction['away_score'] ?></td>
                                <?php if ($matchHasResult): ?>
                                    <td><strong><?= (int) $prediction['points'] ?></strong></td>
                                    <td><?= h((string) ($prediction['reason'] ?: 'Нет очков')) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
