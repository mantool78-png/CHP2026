<section class="page-heading">
    <div>
        <p class="eyebrow">Личный кабинет</p>
        <h1><?= h($user['name']) ?></h1>
    </div>
    <span class="status <?= h($user['payment_status']) ?>">
        <?= h($user['payment_status'] === 'active' ? 'Участник активен' : 'Пробный режим') ?>
    </span>
</section>

<?php if (!is_active_participant($user)): ?>
    <section class="card payment-card">
        <div>
            <p class="eyebrow">Пробный режим</p>
            <h2>Можно сделать <?= (int) $freePredictionLimit ?> прогнозов без оплаты</h2>
            <p class="muted">
                Осталось бесплатных прогнозов:
                <strong><?= (int) $freePredictionsRemaining ?></strong>.
                Если конкурс понравится, оплатите взнос <?= (int) config('app.entry_fee_rub') ?> ₽,
                чтобы продолжить игру без лимита и выбрать чемпиона мира.
            </p>
        </div>
        <div class="payment-steps">
            <div>
                <span>1</span>
                <strong>Попробуйте конкурс</strong>
                <p>Оставьте прогнозы на любые открытые матчи. Уже сыгранные и закрытые матчи прогнозировать нельзя.</p>
            </div>
            <div>
                <span>2</span>
                <strong>Переведите взнос</strong>
                <p><?= h(config('app.payment_instructions', 'Реквизиты для оплаты организатор сообщит отдельно.')) ?></p>
            </div>
            <div>
                <span>3</span>
                <strong>Укажите комментарий</strong>
                <p><?= h(config('app.payment_comment_hint', 'ЧМ-2026, ваш email или имя на сайте.')) ?></p>
            </div>
            <div>
                <span>4</span>
                <strong>Дождитесь активации</strong>
                <p>Админ проверит оплату вручную, после этого лимит будет снят.</p>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($participantSummary)): ?>
    <section class="card participant-summary">
        <div class="participant-summary-head">
            <h2>Ваш результат</h2>
            <a class="button small secondary" href="/leaderboard">Общая таблица</a>
        </div>
        <div class="participant-summary-grid">
            <div>
                <p class="eyebrow">Место</p>
                <p class="stat-big">
                    <?= (int) $participantSummary['rank'] ?>
                    <span class="muted">из <?= (int) $participantSummary['total_participants'] ?></span>
                </p>
            </div>
            <div>
                <p class="eyebrow">Всего очков</p>
                <p class="stat-big"><?= (int) $participantSummary['total_points'] ?></p>
            </div>
            <div>
                <p class="eyebrow">За матчи / чемпиона</p>
                <p class="stat-big">
                    <?= (int) $participantSummary['match_points'] ?>
                    <span class="muted">+ <?= (int) $participantSummary['champion_points'] ?></span>
                </p>
            </div>
            <div>
                <p class="eyebrow">Точные / исходы / прогнозов</p>
                <p class="stat-big stat-composite">
                    <?= (int) $participantSummary['exact_scores_count'] ?>
                    <span class="muted">/</span>
                    <?= (int) $participantSummary['outcomes_count'] ?>
                    <span class="muted">/</span>
                    <?= (int) $participantSummary['predictions_count'] ?>
                </p>
            </div>
        </div>
        <p class="muted small-print">
            При равенстве очков выше тот, у кого больше точных счетов, затем угаданных исходов,
            меньше оставленных прогнозов и ранняя регистрация.
        </p>
    </section>
<?php endif; ?>

<section class="card">
    <h2>Прогноз на чемпиона</h2>
    <?php if ($championPredictionDeadline): ?>
        <p class="muted">
            Дедлайн выбора чемпиона:
            <?= h(date('d.m.Y H:i', strtotime($championPredictionDeadline))) ?> МСК.
            <?php if ($championPredictionLocked): ?>Прием уже закрыт.<?php endif; ?>
        </p>
    <?php endif; ?>
    <form method="post" action="/champion" class="champion-form">
        <?= csrf_field() ?>
        <select name="team_id" required <?= !is_active_participant($user) || $championPredictionLocked ? 'disabled' : '' ?>>
            <option value="">Выберите команду</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>" <?= ($championPrediction['team_id'] ?? null) == $team['id'] ? 'selected' : '' ?>>
                    <?= h($team['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button small" type="submit" <?= !is_active_participant($user) || $championPredictionLocked ? 'disabled' : '' ?>>Сохранить</button>
        <?php if ($championPrediction): ?>
            <span class="muted">Текущий выбор: <?= h($championPrediction['team_name']) ?></span>
        <?php endif; ?>
    </form>
</section>

<section class="card">
    <h2>Прогнозы на матчи</h2>
    <div class="filter-tabs">
        <?php foreach ($stageFilters as $filterKey => $filterLabel): ?>
            <?php
                $stageQuery = ['stage' => $filterKey];
                if ($activeDate !== '') {
                    $stageQuery['date'] = $activeDate;
                }
                $stageHref = '/dashboard' . ($filterKey === 'all' && $activeDate === '' ? '' : '?' . http_build_query($stageQuery));
            ?>
            <a
                class="filter-tab <?= $activeStage === $filterKey ? 'active' : '' ?>"
                href="<?= h($stageHref) ?>"
            >
                <?= h($filterLabel) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="/dashboard" class="date-filter">
        <?php if ($activeStage !== 'all'): ?>
            <input type="hidden" name="stage" value="<?= h($activeStage) ?>">
        <?php endif; ?>
        <label>
            Дата матчей
            <select name="date" onchange="this.form.submit()">
                <option value="">Все даты</option>
                <?php foreach ($availableDates as $dateRow): ?>
                    <option value="<?= h($dateRow['match_date']) ?>" <?= $activeDate === $dateRow['match_date'] ? 'selected' : '' ?>>
                        <?= h(date('d.m.Y', strtotime($dateRow['match_date']))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($activeDate !== ''): ?>
            <a class="button small secondary" href="/dashboard<?= $activeStage !== 'all' ? '?stage=' . h($activeStage) : '' ?>">Сбросить дату</a>
        <?php endif; ?>
    </form>

    <p class="muted">Показано матчей: <?= count($matches) ?></p>
    <?php if (!$matches): ?>
        <p class="muted">По выбранным фильтрам матчей нет.</p>
    <?php else: ?>
        <div class="prediction-list">
            <?php foreach ($matches as $match): ?>
                <?php
                    $prediction = user_prediction((int) $user['id'], (int) $match['id']);
                    $score = user_score((int) $user['id'], (int) $match['id']);
                    $locked = prediction_locked($match);
                    $canSubmitPrediction = !$locked && can_make_prediction($user, (int) $match['id']);
                ?>
                <form class="prediction-row" method="post" action="/predictions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                    <div>
                        <a class="match-title" href="/match?id=<?= (int) $match['id'] ?>">
                            <?= h($match['home_team']) ?> — <?= h($match['away_team']) ?>
                        </a>
                        <p class="muted">
                            <?= h($match['stage']) ?> · <?= h(date('d.m.Y H:i', strtotime($match['starts_at']))) ?>
                            <?php if ($locked): ?> · прием закрыт<?php endif; ?>
                        </p>
                        <div class="prediction-meta">
                            <?php if ($prediction): ?>
                                <span class="pill success">Ваш прогноз: <?= (int) $prediction['home_score'] ?>:<?= (int) $prediction['away_score'] ?></span>
                            <?php else: ?>
                                <span class="pill">Прогноз еще не сохранен</span>
                            <?php endif; ?>
                            <?php if (!$locked && !is_active_participant($user) && !$prediction): ?>
                                <span class="pill accent">
                                    <?= (int) $freePredictionsRemaining > 0 ? 'Можно бесплатно' : 'Нужна оплата' ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($match['home_score'] !== null && $match['away_score'] !== null): ?>
                                <span class="pill">Результат: <?= (int) $match['home_score'] ?>:<?= (int) $match['away_score'] ?></span>
                            <?php endif; ?>
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
