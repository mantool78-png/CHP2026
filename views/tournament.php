<?php
/** @var string $tournamentTab */
/** @var array<string, array<int, array<string, mixed>>> $groupStandings */
/** @var list<array{stage: string, matches: list<array<string, mixed>>}> $playoffRounds */
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">ЧМ-2026</p>
        <h1>Турнир</h1>
        <p class="lead">Таблицы групп по сыгранным матчам (жеребьёвка ФИФА, 12 групп по 4 команды) и сетка плей-офф. Сортировка: очки, разница мячей, забитые.</p>
        <?php $tournamentLastSync = api_football_last_sync_at(); ?>
        <?php if ($tournamentLastSync): ?>
            <p class="muted small-print">Зачёт конкурса по результатам на этом сайте; данные обновлялись <?= h(date('d.m.Y H:i', strtotime($tournamentLastSync))) ?> МСК.</p>
        <?php endif; ?>
    </div>
</section>

<section class="card tournament-facts">
    <details>
        <summary class="tournament-facts-summary">Формат ЧМ-2026</summary>
        <div class="tournament-facts-body stack">
            <p class="muted">Впервые в истории — <strong>48 сборных</strong>, <strong>104 матча</strong>, финал — <strong>19 июля 2026</strong> в Нью-Джерси (MetLife Stadium). Три страны-хозяина: Мексика открывает турнир 11 июня на «Ацтеке», Канада и США стартуют 12 июня.</p>
            <p class="muted">Из группы в плей-офф выходят <strong>двое лучших</strong> и <strong>восемь лучших третьих мест</strong> из двенадцати групп (A–L) — дальше раунд на 32 команды.</p>
        </div>
    </details>
</section>

<div class="filter-tabs tournament-tabs">
    <a
        class="filter-tab <?= $tournamentTab === 'groups' ? 'active' : '' ?>"
        href="/tournament?tab=groups"
    >Группы</a>
    <a
        class="filter-tab <?= $tournamentTab === 'playoff' ? 'active' : '' ?>"
        href="/tournament?tab=playoff"
    >Плей-офф</a>
</div>

<?php if ($tournamentTab === 'groups'): ?>
    <?php if (!$groupStandings): ?>
        <section class="card">
            <p class="muted">Здесь появятся группы, в которых есть команды из справочника ЧМ-2026. Добавьте сборные с кодом ФИФА или названием как в турнире — статистика (И, В, Н, П, очки) заполнится после того, как в админке будут завершены матчи с этапом «Групповой этап…».</p>
        </section>
    <?php else: ?>
        <div class="tournament-groups-grid">
            <?php foreach ($groupStandings as $letter => $rows): ?>
                <section class="card tournament-group-card">
                    <h2 class="tournament-group-title">Группа <?= h($letter) ?></h2>
                    <?php if (!empty(WORLD_CUP_2026_GROUP_LABEL_RU[$letter])): ?>
                        <p class="muted tournament-group-caption tournament-group-caption--flags">
                            <?php foreach (worldcup2026_codes_in_group($letter) as $teamCode): ?>
                                <?php $teamMeta = WORLD_CUP_2026_TEAMS[$teamCode]; ?>
                                <span class="tournament-group-caption-team">
                                    <img class="team-flag" src="/assets/flags/<?= h($teamCode) ?>.svg" alt="" width="24" height="16" loading="lazy" decoding="async">
                                    <span><?= h($teamMeta['name_ru']) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                    <div class="table-scroll">
                        <table class="tournament-standings-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Команда</th>
                                    <th>И</th>
                                    <th>В</th>
                                    <th>Н</th>
                                    <th>П</th>
                                    <th>Мячи</th>
                                    <th>Очки</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= (int) $row['rank'] ?></td>
                                        <td>
                                            <span class="tournament-team-cell">
                                                <?php render_team_with_flag((string) ($row['code'] ?? ''), (string) $row['name'], 'team-with-flag tournament-team-cell__name'); ?>
                                            </span>
                                        </td>
                                        <td><?= (int) $row['played'] ?></td>
                                        <td><?= (int) $row['won'] ?></td>
                                        <td><?= (int) $row['drawn'] ?></td>
                                        <td><?= (int) $row['lost'] ?></td>
                                        <td><?= (int) $row['goals_for'] ?>-<?= (int) $row['goals_against'] ?></td>
                                        <td><strong><?= (int) $row['points'] ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if (!$playoffRounds): ?>
        <section class="card">
            <p class="muted">Сетка плей-офф появится после добавления матчей со стадиями вне «Групповой этап» (1/16 финала и далее).</p>
        </section>
    <?php else: ?>
        <div class="tournament-playoff">
            <?php foreach ($playoffRounds as $round): ?>
                <section class="card tournament-playoff-round">
                    <h2><?= h($round['stage']) ?></h2>
                    <ul class="tournament-playoff-matches">
                        <?php foreach ($round['matches'] as $m): ?>
                            <?php
                                $hasTeams = match_slot_has_teams($m);
                                $homeL = match_slot_home_label($m);
                                $awayL = match_slot_away_label($m);
                                $finished = ($m['status'] ?? '') === 'finished'
                                    && $m['home_score'] !== null && $m['away_score'] !== null;
                                $scoreStr = $finished
                                    ? ((int) $m['home_score'] . ' : ' . (int) $m['away_score'])
                                    : '—';
                            ?>
                            <li class="tournament-playoff-match<?= $hasTeams ? ' tournament-playoff-match--set' : '' ?>">
                                <div class="tournament-playoff-pair">
                                    <span class="tournament-playoff-name<?= empty($m['home_team']) ? ' tournament-playoff-name--tbd' : '' ?>">
                                        <?php if (!empty($m['home_team'])): ?>
                                            <?php render_team_with_flag($m['home_code'] ?? null, (string) $m['home_team']); ?>
                                        <?php else: ?>
                                            <?= h($homeL) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="tournament-playoff-vs">—</span>
                                    <span class="tournament-playoff-name<?= empty($m['away_team']) ? ' tournament-playoff-name--tbd' : '' ?>">
                                        <?php if (!empty($m['away_team'])): ?>
                                            <?php render_team_with_flag($m['away_code'] ?? null, (string) $m['away_team']); ?>
                                        <?php else: ?>
                                            <?= h($awayL) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="tournament-playoff-meta">
                                    <?php if (!empty($m['bracket_code'])): ?>
                                        <span class="pill"><?= h((string) $m['bracket_code']) ?></span>
                                    <?php endif; ?>
                                    <time class="muted" datetime="<?= h(date('c', strtotime((string) $m['starts_at']))) ?>">
                                        <?= h(date('d.m.Y H:i', strtotime((string) $m['starts_at']))) ?> МСК
                                    </time>
                                    <span class="tournament-playoff-score"><?= h($scoreStr) ?></span>
                                    <?php if ($hasTeams): ?>
                                        <a class="button small secondary" href="<?= h(match_url((int) $m['id'], 'tournament')) ?>">Матч</a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
