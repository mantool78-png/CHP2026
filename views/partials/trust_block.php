<section class="card trust-block">
    <p class="eyebrow">Прозрачность</p>
    <h2>Почему можно доверять конкурсу</h2>
    <ul class="trust-block-list">
        <li>
            <strong>Организатор:</strong> проект «GYMACRO», <?= h(contest_organizer_person_name()) ?>.
        </li>
        <li>
            Все <a class="table-link" href="/rules">правила начисления очков</a> опубликованы заранее&nbsp;&mdash; без сюрпризов после турнира.
        </li>
        <li>
            <a class="table-link" href="/prizes">Призы фиксированы</a> и не зависят от случайного розыгрыша или числа участников.
        </li>
        <li>
            Обсуждение и поддержка&nbsp;&mdash; в
            <a class="table-link" href="<?= h(contest_telegram_channel_url()) ?>" target="_blank" rel="noopener">Telegram @chpwc2026</a>
            и у организатора
            <a class="table-link" href="<?= h(contest_organizer_telegram_url()) ?>" target="_blank" rel="noopener"><?= h(contest_organizer_telegram_handle()) ?></a>.
        </li>
        <li>
            Итоги и выплаты публикуются после завершения турнира&nbsp;&mdash; см.
            <a class="table-link" href="/terms#organizer-transparency">условия участия</a>.
        </li>
    </ul>
    <a class="button secondary small" href="<?= h(contest_organizer_telegram_url()) ?>" target="_blank" rel="noopener">Задать вопрос организатору</a>
</section>
