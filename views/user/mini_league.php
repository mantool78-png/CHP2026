<?php
/** @var array<string,mixed> $league */
/** @var string $tab */
/** @var string $stageKey */
/** @var list<array<string,mixed>> $leaders */
/** @var array{participants:list<array<string,mixed>>,matches:list<array<string,mixed>>,cells:array<int,array<int,array<string,mixed>>>} $matrix */
/** @var list<string> $matchStages */
/** @var list<array<string,mixed>> $championPredictions */
/** @var bool $championLocked */
/** @var string|null $championDeadline */

$tab = $tab ?? 'rating';
$stageKey = $stageKey ?? 'all';
$leagueId = (int) $league['id'];
$hasResult = static function (array $match): bool {
    return $match['home_score'] !== null && $match['away_score'] !== null;
};
$tabUrl = static function (string $targetTab, array $extra = []) use ($leagueId, $stageKey): string {
    $query = ['id' => $leagueId];
    if ($targetTab !== 'rating') {
        $query['tab'] = $targetTab;
    }
    if ($targetTab === 'matrix' && $stageKey !== 'all') {
        $query['stage'] = $stageKey;
    }

    return '/mini-league?' . http_build_query(array_merge($query, $extra));
};
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Мини-лига</p>
        <h1><?= h($league['name']) ?></h1>
        <p class="lead">
            Код приглашения:
            <strong><?= h($league['invite_code']) ?></strong>
            · участников: <?= (int) $league['members_count'] ?>
        </p>
    </div>
    <a class="button small secondary" href="/mini-leagues">Все мини-лиги</a>
</section>

<?php
    $inviteLink = absolute_url('/mini-leagues/join?code=' . rawurlencode((string) $league['invite_code']));
    $inviteText = 'Мини-лига «' . $league['name'] . '», прогнозы ЧМ-2026.' . "\n"
        . 'Откройте ссылку (войдите при необходимости) и подтвердите кнопкой «Вступить»:' . "\n"
        . $inviteLink;
?>

<section class="card invite-card">
    <h2>Пригласить друзей</h2>
    <p class="muted">Отправьте текст ниже: в WhatsApp/Telegram ссылка обычно становится кликабельной. Код приглашения по-прежнему в шапке страницы — если удобнее, можно ввести его вручную на странице «Мини-лиги».</p>
    <div class="invite-box">
        <p class="invite-copy-source"><?= h($inviteText) ?></p>
        <button class="button small" type="button" data-copy-button>Скопировать</button>
    </div>
</section>

<div class="filter-tabs predictions-filter-tabs">
    <a class="filter-tab <?= $tab === 'rating' ? 'active' : '' ?>" href="<?= h($tabUrl('rating')) ?>">Рейтинг</a>
    <a class="filter-tab <?= $tab === 'matrix' ? 'active' : '' ?>" href="<?= h($tabUrl('matrix')) ?>">Матрица прогнозов</a>
    <a class="filter-tab <?= $tab === 'champions' ? 'active' : '' ?>" href="<?= h($tabUrl('champions')) ?>">Чемпион мира</a>
</div>

<?php if ($tab === 'rating'): ?>
    <section class="card">
        <h2>Таблица мини-лиги</h2>
        <p class="muted small-print">Очки считаются по тем же правилам, что и в общем конкурсе, но места — только среди участников этой группы.</p>
        <?php if (!$leaders): ?>
            <p class="muted">В мини-лиге пока нет участников.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Участник</th>
                            <th>Очки</th>
                            <th>Точные</th>
                            <th>Исходы</th>
                            <th>Прогнозы</th>
                            <th>Очки чемп.</th>
                            <th>Итого</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaders as $index => $leader): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <a class="table-link" href="<?= h(participant_url((int) $leader['id'], 'rating')) ?>"><?= h($leader['name']) ?></a>
                                </td>
                                <td><?= (int) $leader['match_points'] ?></td>
                                <td><?= (int) $leader['exact_scores_count'] ?></td>
                                <td><?= (int) $leader['outcomes_count'] ?></td>
                                <td><?= (int) $leader['predictions_count'] ?></td>
                                <td><?= (int) $leader['champion_points'] ?></td>
                                <td><strong><?= (int) $leader['total_points'] ?></strong></td>
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
            <h2>Прогнозы участников лиги</h2>
            <?php if ($matchStages !== []): ?>
                <form method="get" action="/mini-league" class="predictions-stage-filter">
                    <input type="hidden" name="id" value="<?= $leagueId ?>">
                    <input type="hidden" name="tab" value="matrix">
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
        </div>
        <?php if ($matrix['matches'] === []): ?>
            <p class="muted">Пока нет начавшихся матчей — матрица появится после первого стартового свистка.</p>
        <?php elseif ($matrix['participants'] === []): ?>
            <p class="muted">В мини-лиге нет участников.</p>
        <?php else: ?>
            <p class="muted small-print">
                Только члены вашей группы. Прогнозы видны после старта матча — как в разделе
                <a class="table-link" href="/predictions?tab=matrix">«Открытые прогнозы»</a>.
            </p>
            <div class="table-scroll predictions-matrix-wrap">
                <table class="predictions-matrix">
                    <thead>
                        <tr>
                            <th class="predictions-matrix-sticky">Участник</th>
                            <?php foreach ($matrix['matches'] as $match): ?>
                                <th class="predictions-matrix-match-col" title="<?= h((string) $match['home_team'] . ' — ' . $match['away_team']) ?>">
                                    <a class="table-link" href="<?= h(match_url((int) $match['id'], 'mini-league', null, $leagueId)) ?>">
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
        <h2>Прогнозы на чемпиона (участники лиги)</h2>
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
            <p class="muted">Никто из участников лиги пока не выбрал чемпиона мира.</p>
        <?php else: ?>
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
                                    <a class="table-link" href="<?= h(participant_url((int) $row['user_id'], 'matrix')) ?>"><?= h((string) $row['name']) ?></a>
                                </td>
                                <td><?= h((string) $row['team_name']) ?></td>
                                <td><?= (int) $row['points'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<script>
document.querySelectorAll('[data-copy-button]').forEach(function (button) {
    button.addEventListener('click', function () {
        var box = button.closest('.invite-box');
        var source = box ? box.querySelector('.invite-copy-source') : null;
        var text = source ? source.textContent.trim() : '';

        function markCopied() {
            var label = 'Скопировать';
            button.textContent = 'Скопировано';
            setTimeout(function () {
                button.textContent = label;
            }, 1800);
        }

        function legacyCopy() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
            } catch (e) {}
            document.body.removeChild(ta);
        }

        if (!text) {
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(markCopied).catch(function () {
                legacyCopy();
                markCopied();
            });
            return;
        }

        legacyCopy();
        markCopied();
    });
});
</script>
