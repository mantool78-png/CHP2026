<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1><?= h($participant['name']) ?></h1>
        <p class="muted"><?= h($participant['email']) ?></p>
    </div>
    <a class="button small secondary" href="/admin/users">Назад к участникам</a>
</section>

<section class="grid four">
    <div class="card stat">
        <span>Итого</span>
        <strong><?= (int) $participant['total_points'] ?></strong>
    </div>
    <div class="card stat">
        <span>Прогнозов</span>
        <strong><?= (int) $participant['predictions_count'] ?></strong>
    </div>
    <div class="card stat">
        <span>Точных счетов</span>
        <strong><?= (int) $participant['exact_scores_count'] ?></strong>
    </div>
    <div class="card stat">
        <span>Исходов</span>
        <strong><?= (int) $participant['outcomes_count'] ?></strong>
    </div>
</section>

<section class="card">
    <h2>Сводка</h2>
    <dl class="detail-list">
        <div>
            <dt>Статус оплаты</dt>
            <dd><?= h($participant['payment_status']) ?></dd>
        </div>
        <div>
            <dt>Подтверждённая сумма</dt>
            <dd><?= $participant['payment_amount_rub'] !== null ? number_format((int) $participant['payment_amount_rub'], 0, ',', ' ') . ' ₽' : '—' ?></dd>
        </div>
        <div>
            <dt>Прогноз на чемпиона</dt>
            <dd><?= h($participant['champion_team'] ?: '—') ?> · <?= (int) $participant['champion_points'] ?> очков</dd>
        </div>
        <div>
            <dt>Очки за матчи</dt>
            <dd><?= (int) $participant['match_points'] ?></dd>
        </div>
        <div>
            <dt>Дата регистрации</dt>
            <dd><?= h(date('d.m.Y H:i', strtotime($participant['created_at']))) ?></dd>
        </div>
    </dl>
</section>

<section class="card">
    <h2>Чек об оплате</h2>
    <?php if (!empty($participant['receipt_uploaded_at'])): ?>
        <p class="muted">
            Файл: <strong><?= h((string) ($participant['receipt_original_name'] ?? '')) ?></strong>
            · <?= h((string) ($participant['receipt_mime_type'] ?? '')) ?>
            · <?= (int) ($participant['receipt_size_bytes'] ?? 0) > 0 ? number_format((int) ($participant['receipt_size_bytes'] ?? 0), 0, ',', ' ') . ' байт' : '—' ?>
            · загрузка <?= h(date('d.m.Y H:i', strtotime((string) $participant['receipt_uploaded_at']))) ?>
        </p>
        <a class="button small" href="/admin/user/receipt?id=<?= (int) $participant['id'] ?>" target="_blank" rel="noopener">Открыть чек</a>
    <?php else: ?>
        <p class="muted">Участник ещё не прикрепил чек.</p>
    <?php endif; ?>
</section>

<?php if (($participant['payment_status'] ?? '') === 'pending_payment'): ?>
    <section class="card">
        <h2>Оплата</h2>
        <p class="muted">
            Подтвердите фактически полученную сумму по этому участнику. Для пары «Приведи друга» при одном банковском переводе на <?= number_format(referral_pair_entry_fee_rub(), 0, ',', ' ') ?> ₽
            укажите долю этого аккаунта — <?= number_format(referral_discounted_entry_fee_rub(), 0, ',', ' ') ?> ₽ (у второго участника такая же доля при отдельном подтверждении).
        </p>
        <div class="table-actions">
            <form method="post" action="/admin/users/activate" class="activation-form">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
                <label>
                    Сумма оплаты
                    <select name="amount_rub">
                        <?php foreach (payment_amount_options() as $amountRub => $label): ?>
                            <option value="<?= (int) $amountRub ?>">
                                <?= number_format((int) $amountRub, 0, ',', ' ') ?> ₽ — <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button" type="submit">Подтвердить оплату</button>
            </form>
            <form method="post" action="/admin/users/block">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
                <button class="button danger" type="submit">Заблокировать</button>
            </form>
        </div>
    </section>
<?php elseif (($participant['payment_status'] ?? '') === 'active'): ?>
    <section class="card">
        <h2>Оплата</h2>
        <p class="muted">
            Статус оплаты подтверждён, участник активен.
            <?php if ($participant['payment_amount_rub'] !== null): ?>
                Зафиксированная сумма: <?= number_format((int) $participant['payment_amount_rub'], 0, ',', ' ') ?> ₽.
            <?php endif; ?>
        </p>
    </section>
<?php elseif (($participant['payment_status'] ?? '') === 'blocked'): ?>
    <section class="card">
        <h2>Оплата</h2>
        <p class="muted">Участник заблокирован.</p>
    </section>
<?php endif; ?>

<section class="card auth-card">
    <h2>Сброс пароля</h2>
    <p class="muted">Рассылаем участнику только по известному каналу (Telegram и т.д.). Почта с сайта не отправляется.</p>
    <form method="post" action="/admin/user/reset-password" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
        <label>
            Новый пароль
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        </label>
        <label>
            Повторите пароль
            <input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password">
        </label>
        <button class="button secondary" type="submit">Установить новый пароль</button>
    </form>
</section>

<section class="card">
    <h2>Удалить участника</h2>
    <p class="muted">
        Безвозвратно: профиль, все прогнозы, чек в папке хранения, записи в мини-лигах и связанное.
        Платёжные записи в базе удалятся каскадом вместе с пользователем.
    </p>
    <form
        method="post"
        action="/admin/users/delete"
        class="stack"
        onsubmit="return confirm(<?= json_encode(
            'Удалить участника «' . $participant['name'] . '» навсегда?',
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        ) ?>);"
    >
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $participant['id'] ?>">
        <button class="button danger" type="submit">Удалить участника</button>
    </form>
</section>

<section class="card">
    <h2>Прогнозы участника</h2>
    <?php if (!$predictions): ?>
        <p class="muted">Участник пока не сделал прогнозов.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Матч</th>
                        <th>Дата</th>
                        <th>Прогноз</th>
                        <th>Результат</th>
                        <th>Очки</th>
                        <th>Причина</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($predictions as $prediction): ?>
                        <tr>
                            <td>
                                <strong><?= h($prediction['home_team']) ?> — <?= h($prediction['away_team']) ?></strong>
                                <div class="muted"><?= h($prediction['stage']) ?></div>
                            </td>
                            <td><?= h(date('d.m.Y H:i', strtotime($prediction['starts_at']))) ?></td>
                            <td><?= (int) $prediction['home_score'] ?> : <?= (int) $prediction['away_score'] ?></td>
                            <td>
                                <?php if ($prediction['result_home_score'] === null): ?>
                                    —
                                <?php else: ?>
                                    <?= (int) $prediction['result_home_score'] ?> : <?= (int) $prediction['result_away_score'] ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int) $prediction['points'] ?></strong></td>
                            <td><?= h($prediction['reason'] ?: 'Матч не завершен') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
