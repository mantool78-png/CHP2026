<?php
/** @var list<array<string,mixed>> $leaders */
/** @var string|null $emptyMessage */

$emptyMessage = $emptyMessage ?? 'Пока нет результатов для выбранного периода.';
?>
<?php if (!$leaders): ?>
    <p class="muted"><?= h($emptyMessage) ?></p>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaders as $index => $leader): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <a class="table-link" href="<?= h(participant_url((int) $leader['id'], 'rating')) ?>"><?= h((string) $leader['name']) ?></a>
                        </td>
                        <td><strong><?= (int) $leader['match_points'] ?></strong></td>
                        <td><?= (int) $leader['exact_scores_count'] ?></td>
                        <td><?= (int) $leader['outcomes_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
