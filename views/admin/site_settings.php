<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Тексты для участников</h1>
    </div>
    <a class="button small secondary" href="/admin">Назад</a>
</section>

<section class="card">
    <p class="muted">
        Участники видят реквизиты и подсказку в личном кабинете (до подтверждения оплаты). Контакты — в кабинете, в правилах и в подвале сайта (если поле не пустое).
        Если поле очистить и сохранить, для него снова подтянется значение из <code>config/config.php</code>, если оно там задано.
        Кнопка сброса удаляет все три значения, сохранённые в базе.
    </p>

    <form method="post" action="/admin/settings" class="stack" style="margin-top:1.25rem">
        <?= csrf_field() ?>
        <label>
            Реквизиты и способ оплаты
            <textarea name="payment_instructions" rows="8" maxlength="8000"><?= h($paymentInstructions) ?></textarea>
        </label>
        <label>
            Подсказка для комментария к переводу
            <textarea name="payment_comment_hint" rows="3" maxlength="500"><?= h($paymentCommentHint) ?></textarea>
        </label>
        <label>
            Контакты для вопросов (Telegram, email, время ответа)
            <textarea name="organizer_contact" rows="4" maxlength="1500" placeholder="Например: Telegram @username или почта organizer@example.com"><?= h($organizerContact) ?></textarea>
        </label>
        <button class="button" type="submit">Сохранить</button>
    </form>

    <form method="post" action="/admin/settings/reset" class="admin-settings-actions" onsubmit="return confirm('Вернуть всё из config.php и удалить сохранённые в базе тексты?');">
        <?= csrf_field() ?>
        <button class="button small secondary" type="submit">Сбросить к config.php</button>
    </form>
</section>
