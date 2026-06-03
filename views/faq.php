<section class="page-heading">
    <div>
        <p class="eyebrow">Помощь</p>
        <h1>Частые вопросы</h1>
        <p class="lead">Ответы о взносах, прогнозах, призах и организаторе конкурса прогнозов на ЧМ-2026.</p>
    </div>
</section>

<section class="card home-faq">
    <p class="muted home-faq-intro">
        Коротко о главном. Подробные <a class="table-link" href="/rules">правила конкурса прогнозов</a> и
        <a class="table-link" href="/terms">условия участия</a>.
    </p>
    <?php require __DIR__ . '/partials/faq_list.php'; ?>
</section>

<section class="card">
    <h2>Не нашли ответ?</h2>
    <p class="muted">Напишите организатору в Telegram&nbsp;&mdash; ответим по оплате, активации и призам.</p>
    <a class="button secondary small" href="<?= h(contest_organizer_telegram_url()) ?>" target="_blank" rel="noopener">Задать вопрос <?= h(contest_organizer_telegram_handle()) ?></a>
</section>
