<?php
$user = current_user();

$seoPath = path();
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : config('app.name');
$pageDescription = isset($pageDescription) && is_string($pageDescription) ? $pageDescription
    : 'ЧМ-2026: прогнозы с главным призом Apple iPhone 17e 256 GB победителю и денежными призами топ-5.';
$ogImageRel = isset($ogImageRel) && is_string($ogImageRel) ? $ogImageRel : '/assets/hero-duel.jpg';
$ogImageUrl = preg_match('#^https?://#', $ogImageRel) === 1 ? $ogImageRel : absolute_url($ogImageRel);
$canonicalUrl = canonical_url_for_request();

$excludeFromIndexing = (
    substr($seoPath, 0, 6) === '/admin'
    || in_array($seoPath, ['/dashboard', '/my-scores', '/mini-leagues', '/mini-league'], true)
);

$pageJsonLdBase = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => config('app.name'),
    'url' => absolute_url('/'),
    'description' => $pageDescription,
    'inLanguage' => 'ru-RU',
];

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($excludeFromIndexing): ?>
        <meta name="robots" content="noindex,nofollow">
    <?php endif; ?>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    <title><?= h($pageTitle) ?></title>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:site_name" content="<?= h(config('app.name')) ?>">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <meta property="og:url" content="<?= h($canonicalUrl) ?>">
    <meta property="og:image" content="<?= h($ogImageUrl) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($pageTitle) ?>">
    <meta name="twitter:description" content="<?= h($pageDescription) ?>">
    <meta name="twitter:image" content="<?= h($ogImageUrl) ?>">
    <link rel="icon" href="/assets/favicon.ico" sizes="any">
    <link rel="icon" href="/assets/favicon-32x32.png" type="image/png" sizes="32x32">
    <link rel="icon" href="/assets/favicon-16x16.png" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/styles.css?v=20260603-hero-no-gaps">
    <script type="application/ld+json"><?= json_encode($pageJsonLdBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/" title="<?= h(config('app.name')) ?>">
            <img class="brand-logo" src="/assets/logo.png" alt="<?= h(config('app.name')) ?>" decoding="async">
        </a>
        <nav>
            <a href="/rules">Правила</a>
            <a href="/matches">Матчи</a>
            <a href="/tournament">Турнир</a>
            <a href="/rating">Рейтинг</a>
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
        <?php require __DIR__ . '/header_channels.php'; ?>
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
            <p class="eyebrow">Контакты</p>
            <?php require __DIR__ . '/official_channels.php'; ?>
            <?php if (organizer_contact() !== ''): ?>
                <div class="footer-contact-text"><?= render_text_with_links(organizer_contact()) ?></div>
            <?php endif; ?>
            <div class="footer-links">
                <a href="/rules">Правила</a>
                <a href="/matches">Матчи</a>
                <a href="/rating">Рейтинг</a>
                <a href="/faq">Вопросы</a>
                <a href="/terms">Условия участия</a>
                <a href="/terms#organizer-transparency">Организатор и взносы</a>
                <a href="/privacy">Персональные данные</a>
            </div>
        </div>
    </footer>
    <?php
    $ymCounterId = (int) config('app.yandex_metrika_id', 0);
    if ($ymCounterId > 0 && strncmp($seoPath, '/admin', 6) !== 0) {
        require __DIR__ . '/yandex_metrika.php';
    }
    require __DIR__ . '/scroll_to_top.php';
    ?>
</body>
</html>
