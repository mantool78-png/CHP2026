<section class="page-heading">
    <div>
        <p class="eyebrow">Призовой фонд</p>
        <h1>Распределение призов</h1>
        <p class="lead">
            <?= (int) config('app.prize_pool_percent') ?>% стартовых взносов идут в призовой фонд.
            Победителями становятся 10 лучших участников общего рейтинга.
        </p>
    </div>
    <div class="pill"><?= number_format($prizePool, 0, ',', ' ') ?> ₽</div>
</section>

<section class="card">
    <h2>Текущие выплаты топ-10</h2>
    <?php if ($prizePool <= 0): ?>
        <p class="muted">Призовой фонд появится после подтверждения первых оплат.</p>
    <?php endif; ?>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Место</th>
                    <th>Доля фонда</th>
                    <th>Сумма сейчас</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($distribution as $row): ?>
                    <tr>
                        <td><?= (int) $row['place'] ?></td>
                        <td><?= (int) $row['percent'] ?>%</td>
                        <td><strong><?= number_format((int) $row['amount'], 0, ',', ' ') ?> ₽</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Как формируется фонд</h2>
    <ul class="rules">
        <li>Стартовый взнос участника: <?= (int) config('app.entry_fee_rub') ?> ₽.</li>
        <li><?= (int) config('app.prize_pool_percent') ?>% взноса попадает в призовой фонд.</li>
        <li>10% остаются организаторам на поддержку проекта.</li>
        <li>Фактические суммы растут после подтверждения новых оплат админом.</li>
    </ul>
</section>
