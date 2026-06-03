<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Участники и оплаты</h1>
    </div>
    <a class="button small secondary" href="/admin">Назад</a>
</section>

<section class="card">
    <p class="muted">
        Быстрое «Подтвердить» в таблице — по полному взносу (<?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽).
        Если участник из пары по акции «Приведи друга», откройте карточку и укажите сумму доли (<?= number_format(referral_discounted_entry_fee_rub(), 0, ',', ' ') ?> ₽).
    </p>
    <div class="filter-tabs">
        <?php foreach ($statusFilters as $filterKey => $filterLabel): ?>
            <a
                class="filter-tab <?= $activeStatus === $filterKey ? 'active' : '' ?>"
                href="/admin/users<?= $filterKey === 'all' ? '' : '?status=' . h($filterKey) ?>"
            >
                <?= h($filterLabel) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$users): ?>
        <p class="muted">По выбранному фильтру участников нет.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Участник</th>
                    <th>Email</th>
                    <th>Статус</th>
                    <th>Оплата</th>
                    <th>Прогнозы</th>
                    <th>Бесплатно</th>
                    <th>Точные</th>
                    <th>Исходы</th>
                    <th>Чемпион</th>
                    <th>Очки</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $participant): ?>
                    <tr>
                        <td>
                            <a class="table-link" href="/admin/user?id=<?= (int) $participant['id'] ?>">
                                <?= h($participant['name']) ?>
                            </a>
                        </td>
                        <td><?= h($participant['email']) ?></td>
                        <td>
                            <span class="status <?= h($participant['payment_status']) ?>">
                                <?= h($participant['payment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $participant['payment_amount_rub'] !== null ? number_format((int) $participant['payment_amount_rub'], 0, ',', ' ') . ' ₽' : '—' ?>
                        </td>
                        <td><?= (int) $participant['predictions_count'] ?></td>
                        <td>
                            <?php if ($participant['payment_status'] === 'active'): ?>
                                <span class="muted">лимит снят</span>
                            <?php else: ?>
                                <?= min((int) $participant['predictions_count'], (int) $freePredictionLimit) ?> / <?= (int) $freePredictionLimit ?>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $participant['exact_scores_count'] ?></td>
                        <td><?= (int) $participant['outcomes_count'] ?></td>
                        <td><?= h($participant['champion_team'] ?: '—') ?></td>
                        <td>
                            <strong><?= (int) $participant['total_points'] ?></strong>
                            <span class="muted">
                                (матчи: <?= (int) $participant['match_points'] ?>,
                                чемпион: <?= (int) $participant['champion_points'] ?>)
                            </span>
                        </td>
                        <td><?= h(date('d.m.Y', strtotime($participant['created_at']))) ?></td>
                        <td class="table-actions">
                            <a class="button small secondary" href="/admin/user?id=<?= (int) $participant['id'] ?>">Детали</a>
                            <form method="post" action="/admin/users/activate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
                                <input type="hidden" name="amount_rub" value="<?= (int) entry_fee_rub() ?>">
                                <button class="button small" type="submit">Подтвердить</button>
                            </form>
                            <form method="post" action="/admin/users/block">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
                                <button class="button small danger" type="submit">Блок</button>
                            </form>
                            <form
                                method="post"
                                action="/admin/users/delete"
                                onsubmit="return confirm(<?= json_encode(
                                    'Удалить участника «' . $participant['name'] . '» и все связанные данные (прогнозы, чек, мини-лиги)? Действие необратимо.',
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                                ) ?>);"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
                                <button class="button small danger" type="submit">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
