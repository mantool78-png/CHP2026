<section class="page-heading">
    <div>
        <p class="eyebrow">Мини-лига #<?= (int) $league['id'] ?></p>
        <h1><?= h($league['name']) ?></h1>
        <p class="lead muted">
            Код: <strong><?= h($league['invite_code']) ?></strong>
            · участников: <?= (int) $league['members_count'] ?>
            · создатель:
            <a class="table-link" href="/admin/user?id=<?= (int) $league['owner_user_id'] ?>"><?= h($league['owner_name']) ?></a>
        </p>
    </div>
    <a class="button small secondary" href="/admin/mini-leagues">Все мини-лиги</a>
</section>

<section class="card">
    <h2>Ссылка приглашения</h2>
    <p class="muted">Та же ссылка, что видят участники при копировании из личного кабинета.</p>
    <p><a class="table-link" href="<?= h($inviteLink) ?>" target="_blank" rel="noopener"><?= h($inviteLink) ?></a></p>
</section>

<section class="card">
    <h2>Таблица очков в лиге</h2>
    <?php if (!$leaders): ?>
        <p class="muted">Нет участников.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Участник</th>
                        <th>Очки</th>
                        <th>Матчи</th>
                        <th>Чемпион</th>
                        <th>Точные</th>
                        <th>Исходы</th>
                        <th>Прогнозы</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaders as $index => $leader): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <a class="table-link" href="/admin/user?id=<?= (int) $leader['id'] ?>"><?= h($leader['name']) ?></a>
                            </td>
                            <td><strong><?= (int) $leader['total_points'] ?></strong></td>
                            <td><?= (int) $leader['match_points'] ?></td>
                            <td><?= (int) $leader['champion_points'] ?></td>
                            <td><?= (int) $leader['exact_scores_count'] ?></td>
                            <td><?= (int) $leader['outcomes_count'] ?></td>
                            <td><?= (int) $leader['predictions_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Состав (вступления)</h2>
    <?php if (!$members): ?>
        <p class="muted">Нет записей в mini_league_members.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Email</th>
                        <th>Статус оплаты</th>
                        <th>Вступил</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td>
                                <a class="table-link" href="/admin/user?id=<?= (int) $member['id'] ?>"><?= h($member['name']) ?></a>
                            </td>
                            <td><?= h($member['email']) ?></td>
                            <td><?= h($member['payment_status']) ?></td>
                            <td><?= h(date('d.m.Y H:i', strtotime((string) $member['joined_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
