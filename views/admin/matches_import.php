<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Импорт матчей</h1>
    </div>
    <a class="button small secondary" href="/admin/matches">Назад к матчам</a>
</section>

<section class="card">
    <h2>Загрузить CSV</h2>
    <p class="muted">
        CSV-файл должен быть разделен точкой с запятой. Первая строка может быть заголовком.
        Если команда из файла еще не существует, сайт создаст ее автоматически.
    </p>

    <form method="post" action="/admin/matches/import" enctype="multipart/form-data" class="stack">
        <?= csrf_field() ?>
        <label>
            CSV-файл
            <input type="file" name="matches_csv" accept=".csv,text/csv" required>
        </label>
        <button class="button" type="submit">Импортировать матчи</button>
    </form>
</section>

<section class="card">
    <h2>Формат файла</h2>
    <p class="muted">Нужно 4 колонки: стадия, команда 1, команда 2, дата и время начала.</p>
    <pre class="import-example">Стадия;Команда 1;Команда 2;Дата и время
Групповой этап;Аргентина;Бразилия;2026-06-11 22:00
Групповой этап;Франция;Германия;12.06.2026 22:00</pre>
    <p class="muted">
        Дату лучше писать в формате <strong>2026-06-11 22:00</strong>.
        Время указываем московское, как и весь сайт.
    </p>
</section>
