<?php $user = current_user(); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(config('app.name')) ?></title>
    <link rel="icon" href="/assets/logo.png" type="image/png">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/" title="<?= h(config('app.name')) ?>">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= h(config('app.name')) ?>" decoding="async">
        </a>
        <nav>
            <a href="/rules">Правила</a>
            <a href="/leaderboard">Таблица</a>
            <a href="/prizes">Призы</a>
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
        <?php if ($message = flash('notice')): ?>
            <div class="alert notice"><?= h($message) ?></div>
        <?php endif; ?>
        <?php if ($message = flash('error')): ?>
            <div class="alert error"><?= h($message) ?></div>
        <?php endif; ?>

        <?php require $viewFile; ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <?php if (organizer_contact() !== ''): ?>
                <p class="eyebrow">Контакты</p>
                <div class="footer-contact-text"><?= render_text_with_links(organizer_contact()) ?></div>
            <?php endif; ?>
            <div class="footer-links">
                <a href="/rules">Правила</a>
                <a href="/terms">Условия участия</a>
                <a href="/privacy">Персональные данные</a>
            </div>
        </div>
    </footer>
</body>
</html>
