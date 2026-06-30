<?php $distribution = $distribution ?? prize_distribution(); ?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Призы</p>
        <h1>Топ-<?= (int) prize_places_count() ?> конкурса</h1>
        <p class="lead">
            Главный приз&nbsp;&mdash; <strong><?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?></strong> для победителя общего рейтинга.
            Места 2–<?= (int) prize_places_count() ?> получают фиксированные денежные призы (всего <?= number_format(prize_pool(), 0, ',', ' ') ?> ₽).
        </p>
    </div>
    <div class="pill accent-pill"><?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?></div>
</section>

<section class="card prize-hero-page">
    <div class="prize-hero-page-text">
        <h2>Главный приз</h2>
        <p class="lead"><?= h(config('app.prize_main_subtitle', 'Новейший iPhone для самого удачливого прогнозиста.')) ?></p>
        <p class="muted">Вручается участнику на 1-м месте в общей таблице после подсчёта всех матчей и чемпиона по правилам конкурса.</p>
    </div>
    <div class="prize-hero-page-img">
        <img src="<?= h((string) config('app.prize_main_image', '/assets/prize-iphone.png')) ?>" alt="<?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?>" width="560" height="560" decoding="async" loading="lazy">
    </div>
</section>

<section class="card">
    <h2>Полная таблица призов</h2>
    <p class="muted">Суммы денежных призов фиксированы и не зависят от числа участников.</p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Место</th>
                    <th>Приз</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($distribution as $row): ?>
                    <tr>
                        <td><?= (int) $row['place'] ?></td>
                        <td>
                            <?php if (!empty($row['is_main_prize'])): ?>
                                <strong><?= h($row['label']) ?></strong>
                            <?php else: ?>
                                <?= number_format((int) $row['amount_rub'], 0, ',', ' ') ?> ₽
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card stage-prizes-section">
    <h2>Промежуточные призы по этапам</h2>
    <?php require __DIR__ . '/partials/stage_prizes_table.php'; ?>
    <p class="muted small-print stage-prizes-table-foot">
        Подробные таблицы очков за каждый период — на странице
        <a class="table-link" href="/rating/stages">«Рейтинг по этапам»</a>.
    </p>
</section>

<section class="card">
    <h2>Участие и взнос</h2>
    <?php $transparencyMode = 'compact'; require __DIR__ . '/partials/organizer_transparency.php'; ?>
    <ul class="rules">
        <li>Стартовый взнос: <strong><?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽</strong> (после подтверждения администратором).</li>
        <li>По акции «Приведи друга» два участника оплачивают вместе одним переводом <?= number_format(referral_pair_entry_fee_rub(), 0, ',', ' ') ?> ₽ вместо <?= number_format(entry_fee_rub() * 2, 0, ',', ' ') ?> ₽; в комментарии к платежу указываются оба аккаунта (или оба email). Других вариантов парной скидки нет.</li>
        <li>Призы начисляются по итогам общей таблицы очков после завершения турнира и обработки результатов.</li>
        <li>Подробности&nbsp;&mdash; в <a class="table-link" href="/terms#organizer-transparency">условиях участия</a> и <a class="table-link" href="/rules">правилах</a>.</li>
    </ul>
</section>
