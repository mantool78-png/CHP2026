<?php
$homeUser = current_user();
$showHeroRegisterBtn = !$homeUser
    || (($homeUser['role'] ?? '') === 'participant' && ($homeUser['payment_status'] ?? '') === 'pending_payment');
$paymentBannerMessage = ($homeUser
    && ($homeUser['role'] ?? '') === 'participant'
    && ($homeUser['payment_status'] ?? '') === 'pending_payment')
    ? contest_payment_banner_message()
    : null;
?>
<?php if ($paymentBannerMessage !== null): ?>
    <section class="home-payment-banner<?= ($paymentBannerMessage['phase'] ?? '') === 'after' ? ' home-payment-banner--live' : '' ?>" role="status">
        <div class="home-payment-banner-inner">
            <p class="home-payment-banner-title">
                <?php if (($paymentBannerMessage['phase'] ?? '') === 'before'): ?>
                    До старта ЧМ&nbsp;&mdash; <strong><?= (int) $paymentBannerMessage['days'] ?> <?= h(ru_days_suffix((int) $paymentBannerMessage['days'])) ?></strong>
                <?php else: ?>
                    <strong><?= h((string) $paymentBannerMessage['title']) ?></strong>
                <?php endif; ?>
            </p>
            <p class="home-payment-banner-text muted"><?= h((string) $paymentBannerMessage['subtitle']) ?></p>
        </div>
        <a class="button small" href="/dashboard">Перейти к оплате</a>
    </section>
<?php endif; ?>

<?php
$finale = $finale ?? finale_results_hero_data();
$finaleIsDraft = false;
require __DIR__ . '/partials/finale_results_hero.php';
?>

<section class="hero-duel">
    <!-- Main content block (left column) -->
    <div class="hero-duel-content">
        <h1 class="hero-title-plain hero-site-name">Лига прогнозов на&nbsp;матчи ЧМ-2026</h1>
        <p class="eyebrow accent-strong hero-eyebrow">ЧМ-2026 уже начался — регистрация открыта на весь турнир</p>
        <p class="hero-tagline">Прогнозируй матчи и выиграй iPhone&nbsp;17e</p>
        <p class="lead hero-lead-main">
            Сделай первые <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов бесплатно, набирай очки и борись за
            <strong class="hero-prize-name"><?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?></strong>
            и денежные выплаты.
        </p>
        <ul class="hero-benefits">
            <li>Первые <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов бесплатно</li>
            <li>Главный приз&nbsp;&mdash; <?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?></li>
            <li>Денежные призы за 2–<?= (int) prize_places_count() ?> места</li>
        </ul>
        
        <div class="hero-actions">
            <div class="actions hero-actions-row">
                <?php if ($showHeroRegisterBtn): ?>
                    <a class="button hero-cta-main" href="/register">Начать бесплатно</a>
                <?php endif; ?>
                <a class="button secondary" href="/rules">Посмотреть правила</a>
            </div>
            <p class="hero-microcopy hero-microcopy--trust">
                Регистрация занимает меньше минуты. Оплата нужна только после первых <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов.
            </p>
            <p class="hero-faq-link-wrap"><a class="hero-faq-link" href="/faq">Вопросы и ответы</a></p>
        </div>
    </div>

    <!-- Right column (Image + Channels) -->
    <div class="hero-duel-right-col">
        <div class="hero-duel-image">
            <img src="/assets/hero-duel.jpg" alt="Участники конкурса прогнозов на чемпионат мира 2026" width="1280" height="720" decoding="async" loading="eager" onerror="this.style.display='none'">
        </div>
        <div class="hero-duel-aside-channels">
            <?php require __DIR__ . '/partials/hero_channels.php'; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/home_activity.php'; ?>

<?php if ($nextMatch): ?>
    <section class="card countdown-card">
        <div>
            <p class="eyebrow">До ближайшего матча чемпионата мира осталось</p>
            <h2><?php render_match_teams_with_flags($nextMatch['home_code'] ?? null, (string) $nextMatch['home_team'], $nextMatch['away_code'] ?? null, (string) $nextMatch['away_team']); ?></h2>
            <p class="muted">
                <?= h($nextMatch['stage']) ?> · <?= h(date('d.m.Y H:i', strtotime($nextMatch['starts_at']))) ?> МСК
            </p>
        </div>
        <div
            class="countdown"
            data-countdown-target="<?= h(date('c', strtotime($nextMatch['starts_at']))) ?>"
        >
            <span data-days>0</span><small>дней</small>
            <span data-hours>00</span><small>часов</small>
            <span data-minutes>00</span><small>минут</small>
            <span data-seconds>00</span><small>секунд</small>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/partials/home_schedule.php'; ?>

<?php
$showResults = user_has_voted_champion_poll() || isset($_GET['show_results']);
$pollResults = champion_poll_results();
?>
<section id="champion-poll" class="card champion-poll-card">
    <div class="champion-poll-header">
        <h2 class="champion-poll-title">Кто выиграет ЧМ-2026?</h2>
        <span class="champion-poll-votes-count"><?= number_format($pollResults['total'], 0, ',', ' ') ?> <?= ru_vote_count_label($pollResults['total']) ?></span>
    </div>

    <?php if ($showResults): ?>
        <!-- RESULTS VIEW -->
        <div class="champion-poll-results">
            <?php foreach ($pollResults['options'] as $res): ?>
                <?php
                $flagPath = $res['code'] ? worldcup2026_flag_path($res['code']) : null;
                ?>
                <div class="champion-poll-result-row">
                    <div class="champion-poll-progress-bar" style="width: <?= $res['percent'] ?>%;"></div>
                    <div class="champion-poll-row-content">
                        <span class="champion-poll-team-name"><?= h($res['name']) ?></span>
                        <div class="champion-poll-right">
                            <span class="champion-poll-percent"><?= $res['percent'] ?>%</span>
                            <?php if ($flagPath): ?>
                                <img class="poll-flag" src="<?= h($flagPath) ?>" alt="<?= h($res['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="poll-flag poll-flag--generic">🌍</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="champion-poll-footer">
                <p class="muted small-print">Спасибо за ваше участие! Голосование анонимное.</p>
                <p class="muted small-print" style="margin-top: 0.5rem; line-height: 1.4;">
                    * Данное голосование является общим опросом гостей сайта и не влияет на ваш выбор Чемпиона мира в Лиге прогнозов 2026 (который делается в личном кабинете участника).
                </p>
            </div>
        </div>
    <?php else: ?>
        <!-- VOTING VIEW -->
        <form method="post" action="/champion-poll/vote" class="champion-poll-form">
            <?= csrf_field() ?>
            <div class="champion-poll-options-grid">
                <?php foreach ($pollResults['options'] as $res): ?>
                    <?php
                    $flagPath = $res['code'] ? worldcup2026_flag_path($res['code']) : null;
                    ?>
                    <button type="submit" name="option_key" value="<?= h($res['key']) ?>" class="champion-poll-option-btn">
                        <span class="champion-poll-team-name">
                            <?php if ($flagPath): ?>
                                <img class="poll-flag poll-flag--btn" src="<?= h($flagPath) ?>" alt="<?= h($res['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="poll-flag poll-flag--generic poll-flag--btn">🌍</span>
                            <?php endif; ?>
                            <?= h($res['name']) ?>
                        </span>
                        <span class="champion-poll-vote-arrow">→</span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="champion-poll-footer">
                <a class="table-link small-print" href="/?show_results=1#champion-poll">Посмотреть результаты без голосования</a>
                <p class="muted small-print" style="margin-top: 0.75rem; line-height: 1.4;">
                    * Данное голосование является общим опросом гостей сайта и не влияет на ваш выбор Чемпиона мира в Лиге прогнозов 2026 (который делается в личном кабинете участника).
                </p>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/site_polls.php'; ?>

<section class="main-prize-showcase">
    <div class="main-prize-showcase-text">
        <p class="eyebrow">Главный приз</p>
        <h2 class="main-prize-title"><?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?></h2>
        <p class="lead main-prize-lead"><?= h(config('app.prize_main_subtitle', 'Новейший iPhone для самого удачливого прогнозиста.')) ?></p>
        <p class="muted">Победитель общего рейтинга по итогам турнира забирает смартфон. Модель и комплектация соответствуют заявленным организаторами.</p>
        <a class="button secondary small" href="/prizes">Все призы топ-<?= (int) prize_places_count() ?></a>
    </div>
    <div class="main-prize-showcase-photo">
        <img src="<?= h((string) config('app.prize_main_image', '/assets/prize-iphone.png')) ?>" alt="<?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?>" width="640" height="640" decoding="async" loading="lazy">
    </div>
</section>

<?php require __DIR__ . '/partials/trust_block.php'; ?>

<section class="card prize-pool-card">
    <div class="prize-pool-info">
        <h2><?= number_format($prizePool, 0, ',', ' ') ?> ₽</h2>
        <p>Гарантированные денежные призы за 2–<?= (int) prize_places_count() ?> места</p>
    </div>
    <ul class="prize-pool-details">
        <li>Стартовый взнос: <?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽</li>
        <?php foreach (array_slice(prize_distribution(), 1) as $row): ?>
            <li><?= (int) $row['place'] ?> место — <?= number_format((int) $row['amount_rub'], 0, ',', ' ') ?> ₽</li>
        <?php endforeach; ?>
    </ul>
</section>

<?php
$registrationStats = $registrationStats ?? contest_registration_stats();
$referralSavings = max(0, entry_fee_rub() * 2 - referral_pair_entry_fee_rub());
?>
<section class="card social-proof">
    <p class="eyebrow accent-strong">Уже в игре</p>
    <h2><?= (int) $registrationStats['total_participants'] ?> <?= ru_participant_count_label((int) $registrationStats['total_participants']) ?> зарегистрировались</h2>
    <p class="lead social-proof-lead">
        Регистрация открыта на протяжении всего чемпионата&nbsp;&mdash; можно присоединиться до, во время и после старта турнира.
        Прогнозы принимаются только на матчи, которые ещё не начались.
        Собери друзей и устройте свою мини-битву прогнозов.
    </p>
    <?php if (!empty($registrationStats['recent_participants'])): ?>
        <div class="social-proof-recent">
            <p class="eyebrow">Последние участники</p>
            <ul class="social-proof-names">
                <?php foreach ($registrationStats['recent_participants'] as $participantName): ?>
                    <li><?= h((string) $participantName) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="actions social-proof-actions">
        <a class="button secondary small" href="#referral">Позвать друга</a>
        <a class="button secondary small" href="/rating">Смотреть рейтинг</a>
    </div>
</section>

<section id="referral" class="card referral-card">
    <div>
        <p class="eyebrow accent-strong">Приведи друга</p>
        <h2>Позови друга и участвуйте вдвоём за <?= number_format(referral_pair_entry_fee_rub(), 0, ',', ' ') ?> ₽</h2>
        <p class="lead referral-card-tagline">Один перевод, два аккаунта<?php if ($referralSavings > 0): ?>, экономия <?= number_format($referralSavings, 0, ',', ' ') ?> ₽<?php endif; ?>.</p>
        <p class="muted">
            Регистрируйтесь парой и оплатите <strong><?= number_format(referral_pair_entry_fee_rub(), 0, ',', ' ') ?> ₽ одним банковским переводом</strong>
            вместо <?= number_format(entry_fee_rub() * 2, 0, ',', ' ') ?> ₽ за двух участников по отдельности.
            В комментарии укажите <strong>оба аккаунта или оба email</strong>.
        </p>
    </div>
    <a class="button secondary small" href="/rules">Условия акции</a>
</section>

<section id="how-it-works" class="card how-it-works">
    <p class="eyebrow">Старт простой</p>
    <h2>Как это работает</h2>
    <p class="muted how-it-works-lead">Четыре шага от регистрации до борьбы за призы.</p>
    <div class="how-steps">
        <article class="how-step">
            <span class="how-step-num" aria-hidden="true">1</span>
            <div class="how-step-body">
                <h3 class="how-step-title">Прогнозируй счёт</h3>
                <p class="muted">Перед каждым матчем выбираешь исход или точный счёт.</p>
            </div>
        </article>
        <article class="how-step">
            <span class="how-step-num" aria-hidden="true">2</span>
            <div class="how-step-body">
                <h3 class="how-step-title">Лови очки за точность</h3>
                <p class="muted">Точный счёт&nbsp;&mdash; 3 очка, угаданный исход&nbsp;&mdash; 1 очко.</p>
            </div>
        </article>
        <article class="how-step">
            <span class="how-step-num" aria-hidden="true">3</span>
            <div class="how-step-body">
                <h3 class="how-step-title">Обгоняй друзей</h3>
                <p class="muted">Поднимайся в рейтинге и в мини-лигах на протяжении всего чемпионата.</p>
            </div>
        </article>
        <article class="how-step">
            <span class="how-step-num" aria-hidden="true">4</span>
            <div class="how-step-body">
                <h3 class="how-step-title">Забирай призы</h3>
                <p class="muted">Лидер рейтинга получает <?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?>; дальше&nbsp;&mdash; фиксированные денежные призы.</p>
            </div>
        </article>
    </div>
    <div class="scoring-example">
        <p class="scoring-example-title">Пример: матч закончился <strong>2:1</strong></p>
        <ul class="scoring-example-list">
            <li><span class="scoring-example-outcome scoring-example-outcome--exact">3 очка</span> Прогноз <strong>2:1</strong>&nbsp;&mdash; точный счёт</li>
            <li><span class="scoring-example-outcome scoring-example-outcome--win">1 очко</span> Прогноз <strong>1:0</strong>&nbsp;&mdash; угадан исход</li>
            <li><span class="scoring-example-outcome scoring-example-outcome--miss">0</span> Прогноз <strong>1:1</strong>&nbsp;&mdash; очков нет</li>
        </ul>
    </div>
</section>

<section class="card why-hooks">
    <h2>Почему это затягивает</h2>
    <ul class="hook-lines">
        <li>Каждый гол влияет на твоё место</li>
        <li>Интрига до последнего тура</li>
        <li>Постоянная борьба с друзьями</li>
        <li>Азарт без лишней сложности</li>
    </ul>
</section>

<section class="card final-cta-home">
    <h2>Готов проверить, кто лучше разбирается в футболе?</h2>
    <p class="lead final-cta-lead">Подключайся в любой момент&nbsp;&mdash; и ставь прогнозы на ближайшие матчи.</p>
    <div class="actions">
        <a class="button" href="<?= $homeUser ? '/dashboard' : '/register' ?>">Войти в чемпионат</a>
    </div>
</section>

<?php
$homePredictUser = $homeUser
    && ($homeUser['role'] ?? '') === 'participant'
    && ($homeUser['payment_status'] ?? '') !== 'blocked'
    ? $homeUser
    : null;
?>
<?php if ($homePredictUser): ?>
    <?php
        $homeFreeRem = free_predictions_remaining((int) $homePredictUser['id']);
        $homeFreeLim = free_prediction_limit();
    ?>
    <section class="card" id="home-predictions">
        <div class="participant-summary-head">
            <h2>Ваши прогнозы</h2>
            <a class="button small secondary" href="/dashboard">Все матчи в кабинете</a>
        </div>
        <p class="muted">
            <?php if (is_active_participant($homePredictUser)): ?>
                Введите счёт и сохраните прогноз до закрытия приёма. Полный список матчей и фильтры — в личном кабинете.
            <?php else: ?>
                Первые <?= (int) $homeFreeLim ?> прогнозов доступны до подтверждения взноса.
                Осталось бесплатных: <strong><?= (int) $homeFreeRem ?></strong>.
            <?php endif; ?>
        </p>
        <?php if (!$matches): ?>
            <p class="muted">Матчи появятся после загрузки расписания.</p>
        <?php else: ?>
            <div class="prediction-list">
                <?php foreach ($matches as $match): ?>
                    <?php
                        $prediction = user_prediction((int) $homePredictUser['id'], (int) $match['id']);
                        $score = user_score((int) $homePredictUser['id'], (int) $match['id']);
                        $locked = prediction_locked($match);
                        $canSubmitPrediction = !$locked && can_make_prediction($homePredictUser, (int) $match['id']);
                    ?>
                    <form id="home-match-<?= (int) $match['id'] ?>" class="prediction-row" method="post" action="/predictions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                        <input type="hidden" name="return_to" value="home">
                        <input type="hidden" name="return_stage" value="all">
                        <input type="hidden" name="return_date" value="">
                        <div>
                            <a class="match-title" href="<?= h(match_url((int) $match['id'], 'home')) ?>">
                                <?php render_match_teams_with_flags($match['home_code'] ?? null, (string) $match['home_team'], $match['away_code'] ?? null, (string) $match['away_team']); ?>
                            </a>
                            <p class="muted">
                                <?= h($match['stage']) ?> · <?= h(date('d.m.Y H:i', strtotime($match['starts_at']))) ?>
                                <?php if ($locked): ?> · приём закрыт<?php endif; ?>
                            </p>
                            <div class="prediction-meta">
                                <?php if ($prediction): ?>
                                    <span class="pill success">Ваш прогноз: <?= (int) $prediction['home_score'] ?>:<?= (int) $prediction['away_score'] ?></span>
                                <?php else: ?>
                                    <span class="pill">Прогноз ещё не сохранён</span>
                                <?php endif; ?>
                                <?php if (!$locked && !is_active_participant($homePredictUser) && !$prediction): ?>
                                    <span class="pill accent">
                                        <?= (int) $homeFreeRem > 0 ? 'Можно бесплатно' : 'Нужна оплата' ?>
                                    </span>
                                <?php endif; ?>
                                <?php render_match_status_pills($match); ?>
                                <?php if ($score): ?>
                                    <span class="pill accent">Очки: <?= (int) $score['points'] ?> · <?= h($score['reason']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="score-inputs">
                            <input type="number" name="home_score" min="0" value="<?= h($prediction['home_score'] ?? '') ?>" <?= !$canSubmitPrediction ? 'disabled' : '' ?>>
                            <span>:</span>
                            <input type="number" name="away_score" min="0" value="<?= h($prediction['away_score'] ?? '') ?>" <?= !$canSubmitPrediction ? 'disabled' : '' ?>>
                        </div>
                        <button class="button small" type="submit" <?= !$canSubmitPrediction ? 'disabled' : '' ?>>Сохранить</button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="grid two">
    <div class="card">
        <h2>Правила начисления</h2>
        <ul class="rules">
            <li><strong>1 очко</strong> за угаданный исход: победа команды или ничья.</li>
            <li><strong>3 очка</strong> за точный счет и исход.</li>
            <li><strong>10 очков</strong> за угаданного чемпиона мира.</li>
            <li>Первые <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов доступны без оплаты.</li>
            <li>Прогноз закрывается за <?= (int) config('app.prediction_lock_minutes') ?> минут до начала матча.</li>
        </ul>
    </div>
    <div class="card">
        <h2>Ближайшие матчи</h2>
        <?php if (!$matches): ?>
            <p class="muted">Расписание появится после загрузки матчей админом.</p>
        <?php else: ?>
            <div class="match-list">
                <?php foreach ($matches as $match): ?>
                    <a class="match-row" href="<?= h(match_url((int) $match['id'], 'home')) ?>">
                        <span><?php render_match_teams_with_flags($match['home_code'] ?? null, (string) $match['home_team'], $match['away_code'] ?? null, (string) $match['away_team']); ?></span>
                        <time><?= h(date('d.m H:i', strtotime($match['starts_at']))) ?></time>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="muted home-matches-more"><a class="table-link" href="/matches">Полное расписание матчей ЧМ-2026</a></p>
        <?php endif; ?>
    </div>
</section>

<script>
document.querySelectorAll('[data-countdown-target]').forEach(function (timer) {
    var target = new Date(timer.dataset.countdownTarget).getTime();
    var days = timer.querySelector('[data-days]');
    var hours = timer.querySelector('[data-hours]');
    var minutes = timer.querySelector('[data-minutes]');
    var seconds = timer.querySelector('[data-seconds]');

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function updateCountdown() {
        var remaining = Math.max(0, target - Date.now());
        var totalSeconds = Math.floor(remaining / 1000);

        days.textContent = Math.floor(totalSeconds / 86400);
        hours.textContent = pad(Math.floor((totalSeconds % 86400) / 3600));
        minutes.textContent = pad(Math.floor((totalSeconds % 3600) / 60));
        seconds.textContent = pad(totalSeconds % 60);
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>

<section class="card">
    <div class="participant-summary-head">
        <h2>Топ участников</h2>
        <a class="button small secondary" href="/rating">Полный рейтинг</a>
    </div>
    <?php
        $hasLeaderboardPoints = false;
        foreach ($leaders as $leaderRow) {
            if ((int) ($leaderRow['total_points'] ?? 0) > 0) {
                $hasLeaderboardPoints = true;
                break;
            }
        }
    ?>
    <?php if (!$leaders): ?>
        <p class="muted">Пока нет зарегистрированных участников. Рейтинг заполнится после первых прогнозов и матчей.</p>
    <?php elseif (!$hasLeaderboardPoints): ?>
        <p class="muted">Участники уже в игре, но очки появятся после первых матчей. Следите за <a class="table-link" href="/rating">рейтингом</a>.</p>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Участник</th>
                    <th>Очки</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaders as $index => $leader): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= h($leader['name']) ?></td>
                        <td><strong>0</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Участник</th>
                    <th>Очки</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaders as $index => $leader): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= h($leader['name']) ?></td>
                        <td><strong><?= (int) $leader['total_points'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card home-faq-teaser">
    <h2>Есть вопросы?</h2>
    <p class="muted">Ответы о взносах, прогнозах, призах и организаторе&nbsp;&mdash; на отдельной странице.</p>
    <a class="button secondary small" href="/faq">Частые вопросы</a>
</section>

<section class="card home-seo muted">
    <h2>Конкурс прогнозов на ЧМ-2026</h2>
    <p>
        Чемпионат прогнозов 2026&nbsp;&mdash; турнир для болельщиков: делайте <strong>прогнозы на матчи ЧМ-2026</strong>,
        получайте очки за угаданные исходы и точные счета, соревнуйтесь в общей таблице и боритесь за призы.
        Это <strong>футбольный конкурс с призами</strong>, а не случайный розыгрыш: правила прозрачны, главный приз&nbsp;&mdash;
        <?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?>.
        Первые <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов доступны бесплатно.
    </p>
    <p>
        <a class="table-link" href="/rules">Правила конкурса прогнозов</a> ·
        <a class="table-link" href="/prizes">Призы чемпионата прогнозов</a> ·
        <a class="table-link" href="/matches">Расписание матчей ЧМ-2026</a> ·
        <a class="table-link" href="/rating">Таблица участников</a> ·
        <a class="table-link" href="/faq">Частые вопросы</a>
    </p>
</section>
