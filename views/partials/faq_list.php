<div class="faq-list">
    <details class="faq-item">
        <summary>Как оплатить участие?</summary>
        <div class="faq-answer faq-answer--payment">
            <p>
                Стартовый взнос&nbsp;&mdash; <strong><?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽</strong>
                (после <?= (int) config('app.free_prediction_limit', 5) ?> бесплатных прогнозов).
                Оплата&nbsp;&mdash; банковским переводом; организатор подтверждает вручную, обычно в короткий срок.
            </p>
            <ol class="faq-payment-steps">
                <li>Отсканируйте QR-код в приложении Сбербанка или Т‑Банка <strong>или</strong> переведите по номеру телефона (СБП).</li>
                <li>Укажите в комментарии к платежу свой email или имя на сайте (см. ниже).</li>
                <li>После входа в <a class="table-link" href="/login">личный кабинет</a> приложите чек (JPG, PNG или PDF, до 10&nbsp;МБ)&nbsp;&mdash; так быстрее найдём ваш платёж.</li>
                <li>Дождитесь активации: откроется полный доступ и прогноз на чемпиона мира.</li>
            </ol>
            <?php require __DIR__ . '/payment_details.php'; ?>
            <p class="muted small-print">
                Пара по акции «Приведи друга»&nbsp;&mdash; один перевод
                <strong><?= number_format(referral_pair_entry_fee_rub(), 0, ',', ' ') ?> ₽</strong>
                на двоих, в комментарии&nbsp;&mdash; оба email.
                <a class="table-link" href="/register">Регистрация</a>
                · вопросы&nbsp;&mdash;
                <a class="table-link" href="<?= h(contest_organizer_telegram_url()) ?>" target="_blank" rel="noopener"><?= h(contest_organizer_telegram_handle()) ?></a>
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Сколько стоит участие и когда нужно оплатить?</summary>
        <div class="faq-answer">
            <p>
                Стартовый взнос&nbsp;&mdash; <strong><?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽</strong>.
                Первые <strong><?= (int) config('app.free_prediction_limit', 5) ?></strong> прогнозов можно сделать бесплатно; полный доступ, включая прогноз на чемпиона мира, открывается после подтверждения оплаты организатором.
            </p>
            <p class="muted small-print">Реквизиты, QR-код и порядок перевода&nbsp;&mdash; в ответе <strong>«Как оплатить участие?»</strong> выше.</p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Как работает акция «Приведи друга»?</summary>
        <div class="faq-answer">
            <p>
                Два участника могут зайти по акции «Приведи друга»: один перевод на
                <strong><?= number_format(referral_pair_entry_fee_rub(), 0, ',', ' ') ?> ₽</strong>
                вместо <?= number_format(entry_fee_rub() * 2, 0, ',', ' ') ?> ₽ за двоих по отдельности.
                В комментарии к платежу обязательно укажите <strong>оба email или оба аккаунта</strong>.
                Если кто-то уже оплатил за себя полные <?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽ отдельно, парная скидка на «добавление» друга не действует — у второго полный взнос <?= number_format(entry_fee_rub(), 0, ',', ' ') ?> ₽.
                Лимит акции на аккаунт: не более <?= (int) referral_discount_limit_per_account() ?> <?= ru_times_suffix((int) referral_discount_limit_per_account()) ?>.
                Детали&nbsp;&mdash; в <a class="table-link" href="/rules">правилах</a>.
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>До какого момента можно изменить прогноз на матч?</summary>
        <div class="faq-answer">
            <p>
                Прогноз можно править до закрытия приёма&nbsp;&mdash; за <strong><?= (int) config('app.prediction_lock_minutes') ?></strong> минут до старта матча (время на сайте указано по МСК).
                После этого прогноз фиксируется, если только администратор не внёс результат раньше.
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Учитывается ли дополнительное время и серия пенальти?</summary>
        <div class="faq-answer">
            <p>
                Нет. Для конкурса везде берётся счёт <strong>по итогам основного времени</strong> (90 минут и компенсированное арбитром время). В плей-офф ничья в основное время&nbsp;&mdash; это ничья, даже если позже победитель определился в овертайме или пенальти.
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Как начисляются очки и что при равенстве в таблице?</summary>
        <div class="faq-answer">
            <p>
                <strong>1 очко</strong>&nbsp;&mdash; угаданный исход, <strong>3 очка</strong>&nbsp;&mdash; точный счёт (очко за исход при этом не добавляется),
                <strong>10 очков</strong>&nbsp;&mdash; угаданный чемпион мира.
            </p>
            <p>
                При равенстве очков выше тот, у кого больше точных счётов, затем угаданных исходов, затем кто раньше зарегистрировался и был подтверждён.
                Эти дополнительные показатели позволяют <strong>однозначно определить место</strong> каждого участника: даже при одинаковых очках кто-то всё равно будет выше в таблице.
                Жеребьёвка не проводится&nbsp;&mdash; при равенстве первых двух критериев решает более ранняя регистрация.
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>До когда можно выбрать чемпиона мира?</summary>
        <div class="faq-answer">
            <?php $faqChampionDl = champion_prediction_deadline(); ?>
            <p>
                Прогноз на чемпиона можно менять до начала первого матча стадии 1/16 финала.
                <?php if ($faqChampionDl): ?>
                    <strong>Дедлайн на сайте:</strong> <?= h(date('d.m.Y H:i', strtotime($faqChampionDl))) ?> МСК.
                <?php else: ?>
                    Точное время дедлайна на сайте будет указано дополнительно.
                <?php endif; ?>
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Как проверить прогнозы и очки других участников?</summary>
        <div class="faq-answer">
            <p>
                Все открытые прогнозы&nbsp;&mdash; в разделе
                <a class="table-link" href="/predictions">«Открытые прогнозы»</a>:
                список участников, матрица по матчам и прогнозы на чемпиона (после закрытия приёма).
                На странице каждого матча после старта видны прогнозы и начисленные очки;
                у каждого участника есть публичный профиль со всей историей.
                Правила начисления&nbsp;&mdash; в <a class="table-link" href="/rules">правилах</a>.
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Кто организатор и куда идут взносы?</summary>
        <div class="faq-answer">
            <?php $transparencyMode = 'compact'; require __DIR__ . '/organizer_transparency.php'; ?>
            <p class="muted small-print">Подробнее&nbsp;&mdash; <a class="table-link" href="/terms#organizer-transparency">условия участия, раздел 4</a>.</p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Какие призы и когда их получают?</summary>
        <div class="faq-answer">
            <p>
                Лидер общей таблицы получает главный приз (<strong><?= h(config('app.prize_main_title', 'Apple iPhone 17e 256 GB')) ?></strong>),
                места 2–<?= (int) prize_places_count() ?>&nbsp;&mdash; фиксированные денежные суммы.
                Сводная таблица призов&nbsp;&mdash; на странице <a class="table-link" href="/prizes">«Призы»</a>.
                Сроки выплат и передачи приза описаны в <a class="table-link" href="/terms">условиях участия</a>.
            </p>
        </div>
    </details>
    <details class="faq-item">
        <summary>Это лотерея или букмекерская контора?</summary>
        <div class="faq-answer">
            <p>
                Нет. Конкурс прогнозов основан на ваших прогнозах и начислении очков по правилам, а не на случайном розыгрыше.
                Подробный контекст&nbsp;&mdash; в разделе «Статус конкурса» в <a class="table-link" href="/terms">условиях участия</a>.
            </p>
        </div>
    </details>
</div>
