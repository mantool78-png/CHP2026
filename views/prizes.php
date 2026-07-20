<?php
$prizeWinners = $prizeWinners ?? prize_winners();
$stagePrizesOverview = $stagePrizesOverview ?? engagement_stage_prizes_overview();
$stagePrizesContext = 'prizes';
$completedStages = count(array_filter(
    $stagePrizesOverview,
    static fn (array $row): bool => ($row['status'] ?? '') === 'completed'
));
$prizeImage = (string) config('app.prize_main_image', '/assets/prize-iphone-hero.jpg');
$prizeTitle = (string) config('app.prize_main_title', 'Apple iPhone 17e 256 GB');
$prizeSubtitle = (string) config('app.prize_main_subtitle', 'Новейший iPhone для самого удачливого прогнозиста.');
$mainWinner = $prizeWinners[0] ?? null;
?>
<section class="page-heading prizes-page-heading">
    <div>
        <p class="eyebrow">Призы</p>
        <h1>Топ-<?= (int) prize_places_count() ?> конкурса</h1>
        <p class="lead">
            Главный приз&nbsp;&mdash; <strong><?= h($prizeTitle) ?></strong>.
            Места 2–<?= (int) prize_places_count() ?>: фиксированные выплаты
            (<?= number_format(prize_pool(), 0, ',', ' ') ?> ₽).
            Победители уже определены по итогам Лиги прогнозов.
        </p>
    </div>
</section>

<section class="prize-hero-page">
    <div class="prize-hero-page-text">
        <p class="eyebrow accent-strong">Главный приз</p>
        <h2 class="prize-hero-page-title"><?= h($prizeTitle) ?></h2>
        <p class="prize-hero-page-lead"><?= h($prizeSubtitle) ?></p>
        <?php if (is_array($mainWinner) && !empty($mainWinner['name'])): ?>
            <p class="prize-hero-page-winner">
                Победитель:&nbsp;
                <a class="table-link" href="<?= h(participant_url((int) $mainWinner['user_id'], 'prizes')) ?>">
                    <?= h((string) $mainWinner['name']) ?>
                </a>
                <span class="muted">· <?= (int) ($mainWinner['total_points'] ?? 0) ?> оч.</span>
            </p>
        <?php else: ?>
            <p class="muted prize-hero-page-note">
                Вручается участнику на&nbsp;1-м месте общей таблицы после всех матчей и начисления очков за чемпиона.
            </p>
        <?php endif; ?>
        <a class="button small secondary" href="/rating">Смотреть рейтинг</a>
    </div>
    <div class="prize-hero-page-visual">
        <div class="prize-hero-page-glow" aria-hidden="true"></div>
        <img
            src="<?= h($prizeImage) ?>?v=2"
            alt="<?= h($prizeTitle) ?>"
            width="320"
            height="320"
            decoding="async"
            loading="eager"
        >
    </div>
</section>

<section class="card prizes-top-card">
    <h2>Победители проекта</h2>
    <p class="muted">Итоги общей таблицы после всех матчей и очков за чемпиона.</p>
    <ol class="prizes-top-list">
        <?php foreach ($prizeWinners as $row): ?>
            <?php
            $place = (int) $row['place'];
            $isMain = !empty($row['is_main_prize']);
            $label = $isMain
                ? (string) $row['label']
                : number_format((int) ($row['amount_rub'] ?? 0), 0, ',', ' ') . ' ₽';
            $winnerName = trim((string) ($row['name'] ?? ''));
            $winnerId = (int) ($row['user_id'] ?? 0);
            ?>
            <li class="prizes-top-item<?= $isMain ? ' prizes-top-item--main' : '' ?>">
                <span class="prizes-top-place"><?= $place ?></span>
                <div class="prizes-top-body">
                    <?php if ($winnerName !== '' && $winnerId > 0): ?>
                        <a class="prizes-top-name table-link" href="<?= h(participant_url($winnerId, 'prizes')) ?>">
                            <?= h($winnerName) ?>
                        </a>
                    <?php else: ?>
                        <span class="prizes-top-name muted">Ожидается</span>
                    <?php endif; ?>
                    <span class="prizes-top-label"><?= h($label) ?></span>
                    <?php if (isset($row['total_points'])): ?>
                        <span class="prizes-top-points muted">
                            <?= (int) $row['total_points'] ?> оч.
                            · <?= (int) ($row['exact_scores_count'] ?? 0) ?> т.
                            · <?= (int) ($row['outcomes_count'] ?? 0) ?> исх.
                        </span>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<section class="card stage-prizes-section">
    <h2>Лучшие по этапам</h2>
    <p class="stage-prizes-table-lead muted">
        <?= stage_prize_pool_count() ?> независимых призов по
        <?= number_format((int) ($stagePrizesOverview[0]['amount_rub'] ?? 0), 0, ',', ' ') ?> ₽
        (всего <?= number_format(stage_prize_pool_total(), 0, ',', ' ') ?> ₽).
        Считаются только очки за матчи этапа.
        <?php if ($completedStages > 0): ?>
            Определено: <?= $completedStages ?> из <?= stage_prize_pool_count() ?>.
        <?php endif; ?>
    </p>

    <div class="stage-prize-cards">
        <?php foreach ($stagePrizesOverview as $prize): ?>
            <?php
            $status = (string) ($prize['status'] ?? 'upcoming');
            $leader = $prize['leader'] ?? null;
            $short = engagement_stage_prize_tab_short_label((string) $prize['key']);
            ?>
            <article class="stage-prize-card stage-prize-card--<?= h($status) ?>">
                <header class="stage-prize-card-head">
                    <div>
                        <p class="stage-prize-card-kicker"><?= h($short) ?></p>
                        <h3 class="stage-prize-card-title"><?= h((string) $prize['title']) ?></h3>
                    </div>
                    <strong class="stage-prize-card-amount"><?= number_format((int) $prize['amount_rub'], 0, ',', ' ') ?> ₽</strong>
                </header>

                <p class="stage-prize-card-desc muted"><?= h((string) $prize['description']) ?></p>

                <div class="stage-prize-card-meta">
                    <span class="pill stage-prize-status stage-prize-status--<?= h($status) ?>">
                        <?= h((string) ($prize['status_label'] ?? '')) ?>
                    </span>
                    <span class="stage-prize-card-matches muted">
                        <?php if ((int) ($prize['matches_total'] ?? 0) > 0): ?>
                            <?= (int) $prize['matches_finished'] ?>/<?= (int) $prize['matches_total'] ?> матчей
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>

                <div class="stage-prize-card-leader">
                    <?php if (is_array($leader)): ?>
                        <span class="muted small-print"><?= h((string) ($prize['holder_label'] ?? '')) ?></span>
                        <a class="table-link stage-prize-card-name" href="<?= h(participant_url((int) $leader['user_id'], 'prizes')) ?>">
                            <?= h((string) $leader['name']) ?>
                        </a>
                        <span class="stage-prize-card-points">
                            <strong><?= (int) $leader['match_points'] ?></strong> оч.
                            <span class="muted">· <?= (int) ($leader['exact_scores_count'] ?? 0) ?> т. · <?= (int) ($leader['outcomes_count'] ?? 0) ?> исх.</span>
                        </span>
                    <?php elseif ($status === 'upcoming'): ?>
                        <span class="muted">Этап ещё не начался</span>
                    <?php else: ?>
                        <span class="muted">Пока нет очков</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="muted small-print stage-prizes-table-foot">
        Подробные таблицы&nbsp;&mdash;
        <a class="table-link" href="/rating/stages">рейтинг по этапам</a>.
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
