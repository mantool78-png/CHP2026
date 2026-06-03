<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Мини-лиги</h1>
        <p class="lead muted">
            Все группы конкурса: <?= (int) $totalLeagues ?> лиг,
            <?= (int) $totalMembers ?> записей участников (один человек может быть в нескольких лигах).
        </p>
    </div>
    <a class="button small secondary" href="/admin">Назад</a>
</section>

<section class="card">
    <?php if (!$leagues): ?>
        <p class="muted">Мини-лиг пока нет.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Код</th>
                        <th>Создатель</th>
                        <th>Участников</th>
                        <th>Создана</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leagues as $league): ?>
                        <tr>
                            <td><?= (int) $league['id'] ?></td>
                            <td><strong><?= h($league['name']) ?></strong></td>
                            <td><code><?= h($league['invite_code']) ?></code></td>
                            <td>
                                <a class="table-link" href="/admin/user?id=<?= (int) $league['owner_user_id'] ?>">
                                    <?= h($league['owner_name']) ?>
                                </a>
                                <span class="muted small-print"><?= h($league['owner_email']) ?></span>
                            </td>
                            <td><?= (int) $league['members_count'] ?></td>
                            <td><?= h(date('d.m.Y H:i', strtotime((string) $league['created_at']))) ?></td>
                            <td>
                                <a class="button small secondary" href="/admin/mini-league?id=<?= (int) $league['id'] ?>">Открыть</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
