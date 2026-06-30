<?php

declare(strict_types=1);

/**
 * Однократное применение миграции 012 (site_polls, weekly_digest_log).
 * GET /public/apply_migration_012.php?token= — тот же token, что у cron_match_reminders.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

verify_migration_web_request();

header('Content-Type: text/plain; charset=UTF-8');

$sqlFile = dirname(__DIR__) . '/database/migrations/012_engagement.sql';
if (!is_file($sqlFile)) {
    http_response_code(500);
    echo "Migration file missing\n";
    exit;
}

if (db_table_exists('site_polls')) {
    echo "site_polls already exists\nOK\n";
    exit;
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    http_response_code(500);
    echo "Cannot read migration\n";
    exit;
}

try {
    db()->exec($sql);
    echo "Migration 012 done: site_polls, site_poll_votes, weekly_digest_log\nOK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
}
