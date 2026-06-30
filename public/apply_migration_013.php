<?php

declare(strict_types=1);

/**
 * Миграция 013: компактный набор из 5 опросов на главной.
 * GET /public/apply_migration_013.php?token=
 */

require dirname(__DIR__) . '/app/bootstrap.php';

verify_migration_web_request();

header('Content-Type: text/plain; charset=UTF-8');

$sqlFile = dirname(__DIR__) . '/database/migrations/013_site_polls_compact.sql';
if (!is_file($sqlFile)) {
    http_response_code(500);
    echo "Migration file missing\n";
    exit;
}

$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    http_response_code(500);
    echo "Cannot read migration\n";
    exit;
}

try {
    db()->exec($sql);
    echo "Migration 013 done: site polls refreshed (5 active)\nOK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
}
