<?php
/** @var bool $configured */
/** @var array<string,mixed> $settings */
/** @var string|null $lastSyncAt */
/** @var list<array<string,mixed>> $syncLog */
/** @var array<string,mixed> $teamStats */
/** @var array<string,mixed> $matchStats */
/** @var list<array<string,mixed>> $unmappedMatches */
/** @var array{errors_24h:int,kickoff_updates_7d:int} $opsStats */
/** @var bool|null $widgetsEnabled */
/** @var bool|null $widgetsConfigDefault */
/** @var string|null $outboundIp */
/** @var array{ok:bool,error:?string,http_status:int}|null $apiPing */
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>API-Football</h1>
        <p class="muted">Привязка сборных и матчей к API-Sports, авто-результаты (счёт основного времени). Cron: <code>/cron_api_football_sync.php?token=…</code></p>
    </div>
    <div class="actions">
        <a class="button small secondary" href="/admin/matches">Матчи</a>
        <a class="button small secondary" href="/admin">Назад</a>
    </div>
</section>

<?php if (isset($schemaReady) && $schemaReady === false): ?>
    <section class="card">
        <h2>Нужно обновить базу данных</h2>
        <p class="muted">Один раз откройте ссылку ниже (под админом), дождитесь текста <strong>Migration 009 done</strong>, затем обновите эту страницу.</p>
        <?php if (!empty($migrationUrl)): ?>
            <p><a class="button" href="<?= h($migrationUrl) ?>" target="_blank" rel="noopener">Применить миграцию 009</a></p>
        <?php else: ?>
            <p class="muted">В phpMyAdmin выполните файл <code>database/migrations/009_api_football.sql</code>.</p>
        <?php endif; ?>
    </section>
<?php elseif (!$configured): ?>
    <section class="card">
        <p class="muted">В <code>config/config.php</code> задайте <code>api_football.enabled = true</code>, <code>api_key</code> и <code>cron_token</code>. Выполните миграцию <code>009_api_football.sql</code>.</p>
    </section>
<?php else: ?>
    <section class="grid four">
        <div class="card stat">
            <span>Команды с API ID</span>
            <strong><?= (int) $teamStats['mapped'] ?> / <?= (int) $teamStats['total'] ?></strong>
        </div>
        <div class="card stat">
            <span>Матчи с fixture ID</span>
            <strong><?= (int) $matchStats['mapped'] ?> / <?= (int) $matchStats['total'] ?></strong>
        </div>
        <div class="card stat">
            <span>Последний sync</span>
            <strong class="small-print"><?= $lastSyncAt ? h(date('d.m.Y H:i', strtotime($lastSyncAt))) : '—' ?></strong>
        </div>
        <div class="card stat">
            <span>Ошибки API за 24 ч</span>
            <strong><?= (int) ($opsStats['errors_24h'] ?? 0) ?></strong>
            <p class="muted small-print">Сдвигов времени за 7 дн.: <?= (int) ($opsStats['kickoff_updates_7d'] ?? 0) ?></p>
        </div>
    </section>

    <section class="card">
        <h2>Действия</h2>
        <p class="muted small-print">Порядок: 1) привязать команды → 2) cron или «Синхронизировать сейчас» (участники плей-офф подставляются из API автоматически, затем привязка fixture и счёт). Ручной счёт в матчах помечается как manual и не перезаписывается API.</p>
        <div class="actions">
            <form method="post" action="/admin/api-football/map-teams">
                <?= csrf_field() ?>
                <button class="button" type="submit">Привязать команды</button>
            </form>
            <form method="post" action="/admin/api-football/map-fixtures">
                <?= csrf_field() ?>
                <button class="button secondary" type="submit">Привязать матчи</button>
            </form>
            <form method="post" action="/admin/api-football/sync-now">
                <?= csrf_field() ?>
                <button class="button secondary" type="submit">Синхронизировать сейчас</button>
            </form>
        </div>
        <p class="muted small-print">Лига <?= (int) ($settings['league_id'] ?? 1) ?>, сезон <?= (int) ($settings['season'] ?? 2026) ?>, TZ <?= h((string) ($settings['timezone'] ?? 'Europe/Moscow')) ?>.</p>
    </section>

    <section class="card">
        <h2>Доступ API с сервера</h2>
        <p class="muted small-print">
            Запросы с сайта (H2H, cron, синхронизация) идут с IP хостинга Beget. В
            <a class="table-link" href="https://dashboard.api-football.com/" target="_blank" rel="noopener noreferrer">API-Sports</a>
            → Account → <strong>Allowed IPs</strong> добавьте этот адрес (не путать с Allowed referrers для виджетов).
        </p>
        <p>
            Исходящий IP сервера:
            <strong><code><?= $outboundIp ? h($outboundIp) : 'не удалось определить' ?></code></strong>
        </p>
        <?php if (!empty($apiPing)): ?>
            <p>
                Проверка <code>/status</code>:
                <?php if (!empty($apiPing['ok'])): ?>
                    <strong>OK</strong>
                <?php else: ?>
                    <strong>ошибка</strong>
                    <span class="muted small-print"><?= h((string) ($apiPing['error'] ?? '')) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </section>

    <?php require __DIR__ . '/partials/api_football_today_cache.php'; ?>

    <section class="card">
        <h2>Виджеты на сайте</h2>
        <p class="muted small-print">
            Блоки на <a class="table-link" href="/matches">/matches</a> и <a class="table-link" href="/tournament">/tournament</a>.
            В HTML страницы виден API-ключ&nbsp;&mdash; в <a class="table-link" href="https://dashboard.api-football.com/" target="_blank" rel="noopener noreferrer">личном кабинете API-Sports</a> включите ограничение домена (Allowed referrers): <code>wc2026.gymacro.ru</code>.
            Если виджеты пустые: проверьте domain lock, квоту API и консоль браузера (F12).
        </p>
        <p>
            Сейчас: <strong><?= !empty($widgetsEnabled) ? 'включены' : 'выключены' ?></strong>
            <?php if (isset($widgetsConfigDefault)): ?>
                <span class="muted small-print">(в config.php: <?= $widgetsConfigDefault ? 'widgets_enabled true' : 'false' ?>; настройка в БД имеет приоритет)</span>
            <?php endif; ?>
        </p>
        <div class="actions">
            <?php if (empty($widgetsEnabled)): ?>
                <form method="post" action="/admin/api-football/toggle-widgets">
                    <?= csrf_field() ?>
                    <input type="hidden" name="enable" value="1">
                    <button class="button" type="submit">Включить виджеты</button>
                </form>
            <?php else: ?>
                <form method="post" action="/admin/api-football/toggle-widgets">
                    <?= csrf_field() ?>
                    <input type="hidden" name="enable" value="0">
                    <button class="button secondary" type="submit">Выключить виджеты</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($unmappedMatches): ?>
        <section class="card">
            <h2>Требуют внимания (до 80)</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Матч</th>
                            <th>Старт</th>
                            <th>Fixture</th>
                            <th>API team</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unmappedMatches as $m): ?>
                            <tr>
                                <td><?= (int) $m['id'] ?></td>
                                <td><?= h((string) $m['home_team']) ?> — <?= h((string) $m['away_team']) ?></td>
                                <td><?= h(date('d.m.Y H:i', strtotime((string) $m['starts_at']))) ?></td>
                                <td><?= $m['api_fixture_id'] ? (int) $m['api_fixture_id'] : '—' ?></td>
                                <td class="small-print">
                                    <?= $m['home_api'] ? '✓' : '—' ?> / <?= $m['away_api'] ? '✓' : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($syncLog): ?>
        <section class="card">
            <h2>Журнал синхронизации</h2>
            <ul class="rules small-print">
                <?php foreach ($syncLog as $entry): ?>
                    <li>
                        <?= h(date('d.m.Y H:i', strtotime((string) $entry['created_at']))) ?>
                        — <?= h((string) $entry['action']) ?>:
                        <?= h((string) $entry['message']) ?>
                        <?php if ($entry['match_id']): ?>(матч #<?= (int) $entry['match_id'] ?>)<?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php endif; ?>
