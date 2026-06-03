<section class="page-heading">
    <div>
        <p class="eyebrow">Расписание</p>
        <h1>Матчи ЧМ-2026</h1>
        <p class="lead">
            Прогнозы на матчи чемпионата мира по футболу 2026. Время указано по Москве.
            Групповой этап и плей-офф&nbsp;&mdash; на странице <a class="table-link" href="/tournament">«Турнир»</a>.
        </p>
    </div>
</section>

<?php if (!$matches): ?>
    <section class="card">
        <p class="muted">Расписание появится после загрузки матчей. Следите за обновлениями в официальном канале.</p>
    </section>
<?php else: ?>
    <section class="card">
        <div class="match-list match-list--page">
            <?php foreach ($matches as $match): ?>
                <a class="match-row match-row--page" href="<?= h(match_url((int) $match['id'], 'matches')) ?>">
                    <div class="match-row-main">
                        <span class="match-row-teams">
                            <?php render_match_teams_with_flags($match['home_code'] ?? null, (string) $match['home_team'], $match['away_code'] ?? null, (string) $match['away_team']); ?>
                        </span>
                        <span class="muted match-row-stage"><?= h((string) $match['stage']) ?></span>
                    </div>
                    <time datetime="<?= h(date('c', strtotime((string) $match['starts_at']))) ?>">
                        <?= h(date('d.m.Y H:i', strtotime((string) $match['starts_at']))) ?> МСК
                    </time>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="card">
    <p class="muted">Хотите сделать прогноз? Зарегистрируйтесь&nbsp;&mdash; первые <?= (int) config('app.free_prediction_limit', 5) ?> прогнозов бесплатно.</p>
    <div class="actions">
        <a class="button" href="/register">Начать бесплатно</a>
        <a class="button secondary" href="/tournament">Турнирные таблицы</a>
    </div>
</section>
