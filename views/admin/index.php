<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Управление конкурсом</h1>
    </div>
    <div class="actions">
        <a class="button small" href="/admin/users">Участники</a>
        <a class="button small secondary" href="/admin/settings">Тексты и контакты</a>
        <a class="button small secondary" href="/admin/matches">Матчи</a>
        <a class="button small secondary" href="/admin/teams">Команды</a>
        <a class="button small secondary" href="/admin/password">Сменить пароль</a>
    </div>
</section>

<section class="card">
    <h2>Перед открытым набором</h2>
    <ul class="rules">
        <li>Смените пароль администратора (кнопка выше).</li>
        <li>При потере пароля участником: карточка участника «Сброс пароля».</li>
        <li>Заполните раздел «Тексты и контакты»: реквизиты, подсказку к переводу, контакт для вопросов участников.</li>
        <li>Миграции БД на сервере: <code>002_settings_value_text.sql</code> и <code>003_login_attempts.sql</code> (можно проверить в phpMyAdmin или спросить — мы уже прогоняли на хостинге).</li>
        <li>Сделайте копию базы: панель Beget или «Экспорт» в phpMyAdmin, файл храните отдельно от сайта.</li>
        <li>При необходимости включите HTTPS для домена/поддомена в панели Beget и откройте сайт по https:// — так надёжнее для участников.</li>
        <li>Перед массовой рекламой покажите юристу страницы «Правила», «Условия участия», «Персональные данные».</li>
        <li>Проверьте полный сценарий: регистрация → перевод → подтверждение оплаты → прогнозы → ввод результата матча и очки в таблице.</li>
    </ul>
</section>

<section class="grid four">
    <div class="card stat">
        <span>Участников</span>
        <strong><?= (int) $stats['participants'] ?></strong>
    </div>
    <div class="card stat">
        <span>Оплачено</span>
        <strong><?= (int) $stats['active'] ?></strong>
    </div>
    <div class="card stat">
        <span>Ожидают</span>
        <strong><?= (int) $stats['pending'] ?></strong>
    </div>
    <div class="card stat">
        <span>Призовой фонд</span>
        <strong><?= number_format($prizePool, 0, ',', ' ') ?> ₽</strong>
    </div>
</section>

<section class="card">
    <h2>Чемпион мира</h2>
    <p class="muted">После финала выберите победителя турнира, чтобы начислить 10 бонусных очков.</p>
    <form method="post" action="/admin/champion" class="champion-form">
        <?= csrf_field() ?>
        <select name="team_id" required>
            <option value="">Выберите команду</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>" <?= (string) $championTeamId === (string) $team['id'] ? 'selected' : '' ?>>
                    <?= h($team['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button" type="submit">Начислить бонус</button>
    </form>
</section>
