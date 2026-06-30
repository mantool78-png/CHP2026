<?php
/** @var list<array<string,mixed>> $participants */
/** @var array<string,mixed>|null $comparison */
/** @var int $userA */
/** @var int $userB */

$userA = (int) ($userA ?? 0);
$userB = (int) ($userB ?? 0);
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Сравнение</p>
        <h1>Сравнить с другом</h1>
        <p class="lead muted">Кто на каких матчах обошёл соперника по очкам — удобно для офисных лиг и чатов.</p>
    </div>
    <a class="button small secondary" href="/rating/stages">Рейтинг по этапам</a>
</section>

<section class="card">
    <form method="get" action="/compare" class="compare-form">
        <label>
            <span class="muted">Участник A</span>
            <select name="a" required>
                <option value="">Выберите…</option>
                <?php foreach ($participants as $row): ?>
                    <option value="<?= (int) $row['id'] ?>" <?= $userA === (int) $row['id'] ? 'selected' : '' ?>><?= h((string) $row['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span class="muted">Участник B</span>
            <select name="b" required>
                <option value="">Выберите…</option>
                <?php foreach ($participants as $row): ?>
                    <option value="<?= (int) $row['id'] ?>" <?= $userB === (int) $row['id'] ? 'selected' : '' ?>><?= h((string) $row['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button small">Сравнить</button>
    </form>
</section>

<?php if ($comparison): ?>
    <?php
    $userAName = (string) $comparison['user_a']['name'];
    $userBName = (string) $comparison['user_b']['name'];
    $leadName = (int) $comparison['points_a'] === (int) $comparison['points_b']
        ? null
        : ((int) $comparison['points_a'] > (int) $comparison['points_b'] ? $userAName : $userBName);
    ?>
    <section class="card">
        <h2>Итог</h2>
        <div class="grid four">
            <div class="card stat">
                <span><?= h($userAName) ?></span>
                <strong><?= (int) $comparison['points_a'] ?> очк.</strong>
                <p class="muted small-print">Побед в матчах: <?= (int) $comparison['a_wins'] ?></p>
            </div>
            <div class="card stat">
                <span><?= h($userBName) ?></span>
                <strong><?= (int) $comparison['points_b'] ?> очк.</strong>
                <p class="muted small-print">Побед в матчах: <?= (int) $comparison['b_wins'] ?></p>
            </div>
            <div class="card stat">
                <span>Ничьи</span>
                <strong><?= (int) $comparison['draws'] ?></strong>
            </div>
            <div class="card stat">
                <span>Матчей</span>
                <strong><?= (int) $comparison['matches_count'] ?></strong>
            </div>
        </div>
        <?php if ($leadName): ?>
            <p class="muted">По сумме очков впереди: <strong><?= h($leadName) ?></strong>.</p>
        <?php else: ?>
            <p class="muted">По сумме очков — ничья.</p>
        <?php endif; ?>
    </section>

    <?php if ($comparison['details'] !== []): ?>
        <section class="card">
            <h2>По матчам</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Матч</th>
                            <th>Результат</th>
                            <th><?= h($userAName) ?></th>
                            <th><?= h($userBName) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison['details'] as $row): ?>
                            <tr>
                                <td><?= h((string) $row['label']) ?></td>
                                <td><?= h((string) $row['result']) ?></td>
                                <td class="<?= ($row['winner'] ?? '') === 'a' ? 'compare-win' : '' ?>">
                                    <?= h((string) $row['pred_a']) ?> · <strong><?= (int) $row['points_a'] ?></strong>
                                </td>
                                <td class="<?= ($row['winner'] ?? '') === 'b' ? 'compare-win' : '' ?>">
                                    <?= h((string) $row['pred_b']) ?> · <strong><?= (int) $row['points_b'] ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php elseif ($userA > 0 && $userB > 0): ?>
    <section class="card">
        <p class="muted">Нет общих завершённых матчей с прогнозами у обоих участников.</p>
    </section>
<?php endif; ?>
