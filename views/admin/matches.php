<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Матчи и результаты</h1>
    </div>
    <div class="actions">
        <a class="button small" href="/admin/matches/import">Импорт матчей</a>
        <a class="button small secondary" href="/admin/api-football">API-Football</a>
        <a class="button small secondary" href="/admin">Назад</a>
    </div>
</section>

<section class="card">
    <h2>Добавить матч</h2>
    <p class="muted small-print">Для слотов плей-офф можно оставить команды пустыми и задать подписи (например &laquo;1A&raquo; / &laquo;2B&raquo;). Когда команды известны — выберите их в списке или отредактируйте матч ниже.</p>
    <form method="post" action="/admin/matches/create" class="admin-form admin-match-create-form">
        <?= csrf_field() ?>
        <input name="stage" placeholder="Стадия" value="Групповой этап" required>
        <input name="bracket_code" placeholder="Код слота (QF-1, M73…)" maxlength="20">
        <select name="home_team_id">
            <option value="">— хозяева TBD —</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>"><?= h($team['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="away_team_id">
            <option value="">— гости TBD —</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>"><?= h($team['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input name="placeholder_home" placeholder="Подпись хозяев" maxlength="50">
        <input name="placeholder_away" placeholder="Подпись гостей" maxlength="50">
        <input type="datetime-local" name="starts_at" required>
        <button class="button" type="submit">Добавить</button>
    </form>
</section>

<?php
    $participantTotal = (int) ($participantTotal ?? 0);
    $predictionCounts = $predictionCounts ?? [];
    $missingByMatch = $missingByMatch ?? [];
    $mailConfigured = (bool) ($mailConfigured ?? false);
?>

<section class="card">
    <h2>Расписание</h2>
    <p class="muted small-print">
        Для открытых матчей: колонка «Прогнозы» и блок «Без прогноза» — активные участники (оплачен взнос), у которых ещё нет прогноза.
        Email-напоминание уходит только им.
    </p>
    <div class="filter-tabs">
        <?php foreach ($stageFilters as $filterKey => $filterLabel): ?>
            <a
                class="filter-tab <?= $activeStage === $filterKey ? 'active' : '' ?>"
                href="/admin/matches<?= $filterKey === 'all' ? '' : '?stage=' . h($filterKey) ?>"
            >
                <?= h($filterLabel) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="muted">Показано матчей: <?= count($matches) ?></p>
    <?php if (!$matches): ?>
        <p class="muted">Матчи еще не добавлены.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="admin-matches-wide">
                <thead>
                    <tr>
                        <th>Матч</th>
                        <th>Слот</th>
                        <th>Старт</th>
                        <th>Счет</th>
                        <th>Прогнозы</th>
                        <th>API</th>
                        <th>Результат</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): ?>
                        <?php
                            $hasTeams = match_slot_has_teams($match);
                            $homeL = match_slot_home_label($match);
                            $awayL = match_slot_away_label($match);
                            $startsField = date('Y-m-d\TH:i', strtotime((string) $match['starts_at']));
                            $matchId = (int) $match['id'];
                            $reminderOpen = $hasTeams && !prediction_locked($match);
                            $withPredictions = (int) ($predictionCounts[$matchId] ?? 0);
                            $missingRecipients = $missingByMatch[$matchId] ?? [];
                            $missingCount = count($missingRecipients);
                            $remindedCount = 0;
                            foreach ($missingRecipients as $recipient) {
                                if (!empty($recipient['reminder_sent'])) {
                                    $remindedCount++;
                                }
                            }
                        ?>
                        <tr id="match-<?= $matchId ?>" class="admin-match-row">
                            <td>
                                <strong><?= h($homeL) ?> — <?= h($awayL) ?></strong>
                                <div class="muted small-print"><?= h((string) $match['stage']) ?></div>
                            </td>
                            <td><?= h((string) ($match['bracket_code'] ?? '')) ?: '—' ?></td>
                            <td><?= h(date('d.m.Y H:i', strtotime((string) $match['starts_at']))) ?></td>
                            <td>
                                <?= $match['home_score'] === null ? '—' : (int) $match['home_score'] . ' : ' . (int) $match['away_score'] ?>
                                <span class="muted small-print"><?= h(match_status_label((string) ($match['status'] ?? 'scheduled'))) ?></span>
                                <?php if (($match['status'] ?? '') === 'live'): ?>
                                    <span class="pill live-pill">LIVE</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-match-predictions-cell">
                                <?php if ($reminderOpen): ?>
                                    <strong><?= $withPredictions ?></strong>
                                    <span class="muted">/ <?= $participantTotal ?></span>
                                    <?php if ($missingCount > 0): ?>
                                        <div class="muted small-print">нет: <?= $missingCount ?></div>
                                    <?php else: ?>
                                        <div class="muted small-print">все поставили</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small-print">
                                <?php if (!empty($match['api_fixture_id'])): ?>
                                    #<?= (int) $match['api_fixture_id'] ?>
                                    <span class="muted">(<?= h((string) ($match['result_source'] ?? 'manual')) ?>)</span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasTeams): ?>
                                    <div class="admin-result-actions">
                                        <form method="post" action="/admin/results" class="result-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                                            <input type="hidden" name="return_stage" value="<?= h($activeStage) ?>">
                                            <input type="number" min="0" name="home_score" value="<?= h($match['home_score'] ?? '') ?>" required>
                                            <span>:</span>
                                            <input type="number" min="0" name="away_score" value="<?= h($match['away_score'] ?? '') ?>" required>
                                            <button class="button small" type="submit">Сохранить</button>
                                        </form>
                                        <?php if ($match['home_score'] !== null && $match['away_score'] !== null): ?>
                                            <form method="post" action="/admin/results" class="result-form result-form-reset" onsubmit="return confirm('Сбросить результат и удалить начисленные за этот матч очки?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                                                <input type="hidden" name="return_stage" value="<?= h($activeStage) ?>">
                                                <input type="hidden" name="clear_result" value="1">
                                                <button class="button small secondary" type="submit">Сбросить</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="muted small-print">Назначьте команды для ввода счёта.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($reminderOpen && $missingCount > 0): ?>
                            <tr class="admin-match-reminder-row">
                                <td colspan="7">
                                    <details class="admin-match-reminder-details">
                                        <summary>
                                            Без прогноза (активные): <strong><?= $missingCount ?></strong>
                                            <?php if ($remindedCount > 0): ?>
                                                <span class="muted">(письмо уже: <?= $remindedCount ?>)</span>
                                            <?php endif; ?>
                                        </summary>
                                        <div class="admin-match-reminder-panel">
                                            <div class="table-scroll">
                                                <table class="admin-match-reminder-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Участник</th>
                                                            <th>Email</th>
                                                            <th>Оплата</th>
                                                            <th>Письмо</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($missingRecipients as $recipient): ?>
                                                            <tr>
                                                                <td>
                                                                    <a class="table-link" href="/admin/user?id=<?= (int) $recipient['id'] ?>">
                                                                        <?= h($recipient['name']) ?>
                                                                    </a>
                                                                </td>
                                                                <td><?= h($recipient['email']) ?></td>
                                                                <td><?= h($recipient['payment_status']) ?></td>
                                                                <td>
                                                                    <?php if (!empty($recipient['reminder_sent']) && !empty($recipient['reminder_sent_at'])): ?>
                                                                        <?= h(date('d.m.Y H:i', strtotime((string) $recipient['reminder_sent_at']))) ?>
                                                                    <?php else: ?>
                                                                        <span class="muted">—</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php if ($mailConfigured): ?>
                                                <?php
                                                    $pendingMailCount = $missingCount - $remindedCount;
                                                    $mailLabel = $homeL . ' — ' . $awayL;
                                                ?>
                                                <div class="admin-match-reminder-actions actions">
                                                    <?php if ($pendingMailCount > 0): ?>
                                                        <form
                                                            method="post"
                                                            action="/admin/matches/send-reminders"
                                                            onsubmit="return confirm('Отправить напоминание активным участникам без прогноза: <?= (int) $pendingMailCount ?> чел. по матчу «<?= h($mailLabel) ?>»?');"
                                                        >
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="match_id" value="<?= $matchId ?>">
                                                            <input type="hidden" name="return_stage" value="<?= h($activeStage) ?>">
                                                            <button class="button small" type="submit">
                                                                Разослать напоминание (<?= (int) $pendingMailCount ?>)
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($remindedCount > 0): ?>
                                                        <form
                                                            method="post"
                                                            action="/admin/matches/send-reminders"
                                                            onsubmit="return confirm('Повторно отправить напоминание всем <?= (int) $missingCount ?> активным участникам без прогноза по матчу «<?= h($mailLabel) ?>»?');"
                                                        >
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="match_id" value="<?= $matchId ?>">
                                                            <input type="hidden" name="return_stage" value="<?= h($activeStage) ?>">
                                                            <input type="hidden" name="resend" value="1">
                                                            <button class="button small secondary" type="submit">
                                                                Повторить всем без прогноза (<?= (int) $missingCount ?>)
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="muted small-print">Почта не настроена — рассылка недоступна.</p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="admin-match-edit-row">
                            <td colspan="7">
                                <details class="admin-match-edit-details">
                                    <summary>Редактировать слот и команды</summary>
                                    <form method="post" action="/admin/matches/update" class="admin-form admin-match-edit-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                                        <input type="hidden" name="return_stage" value="<?= h($activeStage) ?>">
                                        <input name="stage" value="<?= h((string) $match['stage']) ?>" required>
                                        <input name="bracket_code" value="<?= h((string) ($match['bracket_code'] ?? '')) ?>" placeholder="Код слота" maxlength="20">
                                        <select name="home_team_id">
                                            <option value="" <?= empty($match['home_team_id']) ? 'selected' : '' ?>>— хозяева TBD —</option>
                                            <?php foreach ($teams as $team): ?>
                                                <option value="<?= (int) $team['id'] ?>" <?= (int) ($match['home_team_id'] ?? 0) === (int) $team['id'] ? 'selected' : '' ?>>
                                                    <?= h($team['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="away_team_id">
                                            <option value="" <?= empty($match['away_team_id']) ? 'selected' : '' ?>>— гости TBD —</option>
                                            <?php foreach ($teams as $team): ?>
                                                <option value="<?= (int) $team['id'] ?>" <?= (int) ($match['away_team_id'] ?? 0) === (int) $team['id'] ? 'selected' : '' ?>>
                                                    <?= h($team['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input name="placeholder_home" value="<?= h((string) ($match['placeholder_home'] ?? '')) ?>" placeholder="Подпись хозяев" maxlength="50">
                                        <input name="placeholder_away" value="<?= h((string) ($match['placeholder_away'] ?? '')) ?>" placeholder="Подпись гостей" maxlength="50">
                                        <input type="datetime-local" name="starts_at" value="<?= h($startsField) ?>" required>
                                        <button class="button small" type="submit">Сохранить изменения</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
