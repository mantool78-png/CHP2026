<section class="hero">
    <div>
        <p class="eyebrow">Чемпионат мира по футболу 2026</p>
        <h1>Конкурс прогнозов для тех, кто хочет смотреть турнир азартнее.</h1>
        <p class="lead">
            Угадывайте исходы матчей, точные счета и будущего чемпиона мира.
            Первые <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов можно сделать без оплаты,
            а присоединиться к конкурсу можно даже после старта турнира.
        </p>
        <div class="actions">
            <a class="button" href="/register">Принять участие</a>
            <a class="button secondary" href="/leaderboard">Смотреть таблицу</a>
        </div>
    </div>
    <div class="card hero-card">
        <h2><?= number_format($prizePool, 0, ',', ' ') ?> ₽</h2>
        <p>Текущий призовой фонд</p>
        <ul>
            <li>Взнос: <?= (int) config('app.entry_fee_rub') ?> ₽</li>
            <li><?= (int) config('app.prize_pool_percent') ?>% идет в призовой фонд</li>
            <li>10% — организаторам проекта</li>
        </ul>
    </div>
</section>

<?php if ($nextMatch): ?>
    <section class="card countdown-card">
        <div>
            <p class="eyebrow">До ближайшего матча чемпионата мира осталось</p>
            <h2><?= h($nextMatch['home_team']) ?> — <?= h($nextMatch['away_team']) ?></h2>
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
                    <a class="match-row" href="/match?id=<?= (int) $match['id'] ?>">
                        <span><?= h($match['home_team']) ?> — <?= h($match['away_team']) ?></span>
                        <time><?= h(date('d.m H:i', strtotime($match['starts_at']))) ?></time>
                    </a>
                <?php endforeach; ?>
            </div>
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
    <h2>Топ участников</h2>
    <?php if (!$leaders): ?>
        <p class="muted">Пока нет активных участников.</p>
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
