<?php

declare(strict_types=1);

/**
 * CLI: миграция 009 (API-Football). php scripts/apply_migration_009.php
 */

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

$sqlFile = dirname(__DIR__) . '/database/migrations/009_api_football.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Не найден {$sqlFile}\n");
    exit(1);
}

try {
    $pdo = new PDO($dsn, (string) $dbConf['username'], (string) $dbConf['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Подключение к MySQL не удалось: ' . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Не удалось прочитать SQL.\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: []));
foreach ($statements as $statement) {
    if ($statement === '') {
        continue;
    }
    try {
        $pdo->exec($statement);
        echo "OK: " . substr(str_replace("\n", ' ', $statement), 0, 80) . "…\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
            echo "SKIP (already applied): " . substr($statement, 0, 60) . "…\n";
            continue;
        }
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Migration 009 done.\n";
