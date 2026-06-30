<?php
/** @var string $tab */
/** @var string $stageKey */
/** @var string $nameQuery */
/** @var list<array<string,mixed>> $participants */
/** @var array{participants: list<array<string,mixed>>, matches: list<array<string,mixed>>, cells: array<int, array<int, array<string,mixed>>>} $matrix */
/** @var list<string> $matchStages */
/** @var list<array<string,mixed>> $championPredictions */
/** @var list<array<string,mixed>> $championDistribution */
/** @var bool $championLocked */
/** @var string|null $championDeadline */

$tab = $tab ?? 'participants';
$stageKey = $stageKey ?? 'all';
$nameQuery = $nameQuery ?? '';
$hasResult = static function (array $match): bool {
    return $match['home_score'] !== null && $match['away_score'] !== null;
};

$matrixQuery = static function (array $extra = []) use ($tab, $stageKey, $nameQuery): string {
    $query = array_merge(['tab' => $tab], $extra);
    if ($stageKey !== 'all') {
        $query['stage'] = $stageKey;
    }
    if ($nameQuery !== '') {
        $query['q'] = $nameQuery;
    }

    return '/predictions?' . http_build_query($query);
};
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Прозрачность конкурса</p>
        <h1>Открытые прогнозы</h1>
        <p class="lead muted">
            Прогнозы на матчи открываются после стартового свистка. Очки считает сайт по
            <a class="table-link" href="/rules">правилам</a> (основное время). Проверяйте любого участника без входа в аккаунт.
        </p>
    </div>
</section>

<div class="filter-tabs predictions-filter-tabs">
    <a class="filter-tab <?= $tab === 'participants' ? 'active' : '' ?>" href="<?= h($matrixQuery(['tab' => 'participants'])) ?>">Участники</a>
    <a class="filter-tab <?= $tab === 'matrix' ? 'active' : '' ?>" href="<?= h($matrixQuery(['tab' => 'matrix'])) ?>">Матрица</a>
    <a class="filter-tab <?= $tab === 'champions' ? 'active' : '' ?>" href="<?= h($matrixQuery(['tab' => 'champions'])) ?>">Чемпион мира</a>
</div>

<form method="get" action="/predictions" class="predictions-search-form card">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <?php if ($tab === 'matrix' && $stageKey !== 'all'): ?>
        <input type="hidden" name="stage" value="<?= h($stageKey) ?>">
    <?php endif; ?>
    <label>
        <span class="muted">Поиск по имени</span>
        <input type="search" name="q" value="<?= h($nameQuery) ?>" placeholder="Фамилия, инициалы или ник">
    </label>
    <button type="submit" class="button small">Найти</button>
    <?php if ($nameQuery !== ''): ?>
        <a class="button small secondary" href="<?= h($matrixQuery(['tab' => $tab])) ?>">Сбросить</a>
    <?php endif; ?>
</form>

<?php if ($tab === 'participants'): ?>
    <section class="card">
        <h2>Все участники</h2>
        <?php if (!$participants): ?>
            <p class="muted">Пока нет участников с прогнозами на начавшиеся матчи.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Участник</th>
                            <?php if ($championLocked): ?>
                                <th>Прогноз</th>
                            <?php endif; ?>
                            <th>Очки</th>
                            <th>Точные</th>
                            <th>Исходы</th>
                            <th>Очки чемп.</th>
                            <th>Итого</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $row): ?>
                            <tr>
                                <td><?= (int) $row['rank'] ?></td>
                                <td>
                                    <a class="table-link" href="<?= h(participant_url((int) $row['id'], 'predictions')) ?>"><?= h((string) $row['name']) ?></a>
                                </td>
                                <?php if ($championLocked): ?>
                                    <td><?= !empty($row['champion_team']) ? h((string) $row['champion_team']) : '<span class="muted">—</span>' ?></td>
                                <?php endif; ?>
                                <td><?= (int) $row['match_points'] ?></td>
                                <td><?= (int) $row['exact_scores_count'] ?></td>
                                <td><?= (int) $row['outcomes_count'] ?></td>
                                <td><?= (int) $row['champion_points'] ?></td>
                                <td><strong><?= (int) $row['total_points'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php elseif ($tab === 'matrix'): ?>
    <section class="card">
        <div class="predictions-matrix-head">
            <h2>Матрица прогнозов</h2>
            <div class="predictions-matrix-actions">
            <?php if ($matchStages !== []): ?>
                <form method="get" action="/predictions" class="predictions-stage-filter">
                    <input type="hidden" name="tab" value="matrix">
                    <?php if ($nameQuery !== ''): ?>
                        <input type="hidden" name="q" value="<?= h($nameQuery) ?>">
                    <?php endif; ?>
                    <label>
                        <span class="muted">Стадия</span>
                        <select name="stage" onchange="this.form.submit()">
                            <option value="all" <?= $stageKey === 'all' ? 'selected' : '' ?>>Все начавшиеся</option>
                            <?php foreach ($matchStages as $stage): ?>
                                <option value="<?= h((string) $stage) ?>" <?= $stageKey === $stage ? 'selected' : '' ?>><?= h((string) $stage) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            <?php endif; ?>
            <?php if ($matrix['matches'] !== [] && $matrix['participants'] !== []): ?>
                <?php
                $pdfQuery = ['stage' => $stageKey !== 'all' ? $stageKey : null, 'q' => $nameQuery !== '' ? $nameQuery : null];
                $pdfQuery = array_filter($pdfQuery, static fn ($value) => $value !== null && $value !== '');
                ?>
                <a class="button small secondary" href="/predictions/pdf<?= $pdfQuery !== [] ? '?' . h(http_build_query($pdfQuery)) : '' ?>">Скачать PDF</a>
            <?php endif; ?>
            </div>
        </div>
        <?php if ($matrix['matches'] === []): ?>
            <p class="muted">Пока нет начавшихся матчей — матрица появится после первого стартового свистка.</p>
        <?php elseif ($matrix['participants'] === []): ?>
            <p class="muted">Никого не найдено по вашему запросу.</p>
        <?php else: ?>
            <p class="muted small-print">Строки — участники, столбцы — матчи после старта. В ячейке: прогноз и начисленные очки после результата.</p>
            <div class="table-scroll predictions-matrix-wrap">
                <table class="predictions-matrix">
                    <thead>
                        <tr>
                            <th class="predictions-matrix-sticky">Участник</th>
                            <?php foreach ($matrix['matches'] as $match): ?>
                                <th class="predictions-matrix-match-col" title="<?= h((string) $match['home_team'] . ' — ' . $match['away_team']) ?>">
                                    <a class="table-link" href="<?= h(match_url((int) $match['id'], 'predictions')) ?>">
                                        <?= h((string) $match['home_team']) ?><br>—<br><?= h((string) $match['away_team']) ?>
                                    </a>
                                    <span class="muted small-print"><?= h(date('d.m', strtotime((string) $match['starts_at']))) ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matrix['participants'] as $participant): ?>
                            <?php $userId = (int) $participant['id']; ?>
                            <tr>
                                <th class="predictions-matrix-sticky" scope="row">
                                    <a class="table-link" href="<?= h(participant_url($userId, 'matrix')) ?>"><?= h((string) $participant['name']) ?></a>
                                </th>
                                <?php foreach ($matrix['matches'] as $match): ?>
                                    <?php
                                    $matchId = (int) $match['id'];
                                    $cell = $matrix['cells'][$userId][$matchId] ?? null;
                                    ?>
                                    <td class="predictions-matrix-cell">
                                        <?php if ($cell === null): ?>
                                            <span class="muted">—</span>
                                        <?php else: ?>
                                            <span class="predictions-matrix-score"><?= (int) $cell['home_score'] ?> : <?= (int) $cell['away_score'] ?></span>
                                            <?php if ($hasResult($match)): ?>
                                                <?php $pts = (int) $cell['points']; ?>
                                                <span class="predictions-matrix-points predictions-matrix-points--<?= $pts === 3 ? 'exact' : ($pts === 1 ? 'outcome' : 'zero') ?>">+<?= $pts ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card">
        <h2>Прогнозы на чемпиона мира</h2>
        <?php if (!$championLocked): ?>
            <p class="muted">
                Список откроется после закрытия приёма прогнозов на чемпиона
                <?php if ($championDeadline): ?>
                    (<?= h(date('d.m.Y H:i', strtotime($championDeadline))) ?> МСК).
                <?php else: ?>
                    .
                <?php endif; ?>
            </p>
        <?php elseif ($championPredictions === []): ?>
            <p class="muted">Пока никто не выбрал чемпиона мира.</p>
        <?php else: ?>
            <?php if ($championDistribution !== []): ?>
                <div class="champion-distribution-grid">
                    <?php foreach ($championDistribution as $row): ?>
                        <div class="champion-distribution-item">
                            <strong><?= h((string) $row['team_name']) ?></strong>
                            <span class="muted"><?= (int) $row['cnt'] ?> <?= ru_times_suffix((int) $row['cnt']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Участник</th>
                            <th>Прогноз</th>
                            <th>Очки</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($championPredictions as $row): ?>
                            <tr>
                                <td>
                                    <a class="table-link" href="<?= h(participant_url((int) $row['user_id'], 'predictions')) ?>"><?= h((string) $row['name']) ?></a>
                                </td>
                                <td><?= h((string) $row['team_name']) ?></td>
                                <td><strong><?= (int) $row['points'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
