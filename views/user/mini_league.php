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
    $inviteUrl = '/mini-leagues';
    $inviteText = 'Я создал мини-лигу "' . $league['name'] . '" в конкурсе прогнозов ЧМ-2026. '
        . 'Вступай по коду ' . $league['invite_code'] . ': ' . $inviteUrl;
?>

<section class="card invite-card">
    <h2>Пригласить друзей</h2>
    <p class="muted">Отправьте этот текст друзьям, чтобы они вступили в вашу мини-лигу.</p>
    <div class="invite-box" data-copy-text="<?= h($inviteText) ?>">
        <p><?= h($inviteText) ?></p>
        <button class="button small" type="button" data-copy-button>Скопировать</button>
    </div>
</section>

<section class="card">
    <h2>Таблица мини-лиги</h2>
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
                            <td><?= h($leader['name']) ?></td>
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

<script>
document.querySelectorAll('[data-copy-button]').forEach(function (button) {
    button.addEventListener('click', function () {
        var box = button.closest('[data-copy-text]');
        var text = box ? box.dataset.copyText : '';

        if (navigator.clipboard && text) {
            navigator.clipboard.writeText(text).then(function () {
                button.textContent = 'Скопировано';
                setTimeout(function () {
                    button.textContent = 'Скопировать';
                }, 1800);
            });
            return;
        }

        window.prompt('Скопируйте текст приглашения:', text);
    });
});
</script>
