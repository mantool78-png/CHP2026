<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Участники и оплаты</h1>
    </div>
    <a class="button small secondary" href="/admin">Назад</a>
</section>

<section class="card">
    <?php if (!$users): ?>
        <p class="muted">Участников пока нет.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Статус</th>
                    <th>Прогнозы</th>
                    <th>Точные</th>
                    <th>Исходы</th>
                    <th>Чемпион</th>
                    <th>Очки</th>
                    <th>Дата</th>
                    <th></th>
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
                        <td><?= h($participant['payment_status']) ?></td>
                        <td><?= (int) $participant['predictions_count'] ?></td>
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
                                <button class="button small" type="submit">Подтвердить</button>
                            </form>
                            <form method="post" action="/admin/users/block">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
                                <button class="button small danger" type="submit">Блок</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
