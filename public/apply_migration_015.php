<?php

declare(strict_types=1);

/**
 * Миграция 015: исправление времени начала матчей (МСК).
 * GET /public/apply_migration_015.php?token=
 */

require dirname(__DIR__) . '/app/bootstrap.php';

verify_migration_web_request();

header('Content-Type: text/plain; charset=UTF-8');

$sqlFile = dirname(__DIR__) . '/database/migrations/015_fix_match_schedule_msk.sql';
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
    echo "Migration 015 done: match kickoff times updated (MSK)\nOK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
}
