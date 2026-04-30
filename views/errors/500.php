<section class="card">
    <h1>Ошибка приложения</h1>
    <p class="muted">Проверьте настройки базы данных и импорт SQL-схемы.</p>
    <?php if (!empty($message)): ?>
        <pre><?= h($message) ?></pre>
    <?php endif; ?>
</section>
