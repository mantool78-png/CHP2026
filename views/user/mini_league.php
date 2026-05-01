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
        . 'По ссылке друг войдёт или зарегистрируется — вступление в группу выполнится само:' . "\n"
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
