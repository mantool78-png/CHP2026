<?php

declare(strict_types=1);

/**
 * Однократное применение миграции 011 (match_result_notification_log).
 * GET /apply_migration_011.php?token= — тот же token, что у cron_match_reminders.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

verify_migration_web_request();

header('Content-Type: text/plain; charset=UTF-8');

$sqlFile = dirname(__DIR__) . '/database/migrations/011_match_result_notification_log.sql';
if (!is_file($sqlFile)) {
    http_response_code(500);
    echo "Migration file not found\n";
    exit;
}

try {
    if (db_table_exists('match_result_notification_log')) {
        echo "match_result_notification_log already exists\nOK\n";
        exit;
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Empty migration SQL');
    }

    db()->exec($sql);
    echo "Migration 011 done: match_result_notification_log\nOK\n";
} catch (Throwable $e) {
    error_log('Migration 011: ' . $e->getMessage());
    http_response_code(500);
    echo app_debug() ? ('Error: ' . $e->getMessage() . "\n") : "Error\n";
}
