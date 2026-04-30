<?php $user = current_user(); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(config('app.name')) ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/"><?= h(config('app.name')) ?></a>
        <nav>
            <a href="/rules">Правила</a>
            <a href="/leaderboard">Таблица</a>
            <?php if ($user): ?>
                <?php if (($user['role'] ?? '') !== 'admin'): ?>
                    <a href="/mini-leagues">Мини-лиги</a>
                <?php endif; ?>
                <a href="<?= ($user['role'] ?? '') === 'admin' ? '/admin' : '/dashboard' ?>">Кабинет</a>
                <form action="/logout" method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Выйти</button>
                </form>
            <?php else: ?>
                <a href="/login">Вход</a>
                <a href="/register" class="button small">Участвовать</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container">
        <?php if ($message = flash('success')): ?>
            <div class="alert success"><?= h($message) ?></div>
        <?php endif; ?>
        <?php if ($message = flash('error')): ?>
            <div class="alert error"><?= h($message) ?></div>
        <?php endif; ?>

        <?php require $viewFile; ?>
    </main>
</body>
</html>
