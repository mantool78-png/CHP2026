<?php

declare(strict_types=1);

/**
 * CLI: миграция 008. На Beget SSH из корня сайта: php scripts/apply_migration_008.php
 * С ПК (если в панели открыт внешний доступ к MySQL): php scripts/apply_migration_008.php ХОСТ-ИЗ-ПАНЕЛИ-BEGET
 */

require dirname(__DIR__) . '/app/migration_runner_008.php';

$configFile = dirname(__DIR__) . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Файл config/config.php не найден.\n");
    exit(1);
}

/** @var array<string, mixed> $config */
$config = require $configFile;
$dbConf = $config['db'] ?? null;
if (!is_array($dbConf)) {
    fwrite(STDERR, "В config нет секции db.\n");
    exit(1);
}

$host = $argv[1] ?? (string) ($dbConf['host'] ?? 'localhost');
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $host,
    (string) $dbConf['database'],
    (string) ($dbConf['charset'] ?? 'utf8mb4')
);

try {
    $pdo = new PDO($dsn, (string) $dbConf['username'], (string) $dbConf['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Подключение к MySQL не удалось: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "На Beget откройте в браузере (с сервера localhost к БД не нужен внешний доступ):\n");
    fwrite(STDERR, "  https://ваш-сайт/apply_migration_008.php?token=... (токен как у cron_match_reminders)\n");
    exit(1);
}

echo "DB host: {$host}\n";

try {
    $pdo->beginTransaction();
    foreach (migration_008_apply($pdo) as $line) {
        echo $line . "\n";
    }
    $pdo->commit();
    echo "OK\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
    exit(1);
}
