<?php

declare(strict_types=1);

/**
 * Однократное применение миграции 008 на сервере (MySQL с localhost с точки зрения PHP).
 * GET /apply_migration_008.php?token= — тот же token, что у cron_match_reminders.php
 *
 * После успешного применения файл можно удалить с сервера или оставить (повторные вызовы безопасны).
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/migration_runner_008.php';

$token = (string) ($_GET['token'] ?? '');
$expected = (string) config('mail.reminder_cron_token', '');
if ($expected === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

$pdo = null;
try {
    $pdo = db();
    $pdo->beginTransaction();
    $steps = migration_008_apply($pdo);
    $pdo->commit();
    foreach ($steps as $line) {
        echo $line . "\n";
    }
    echo "OK\n";
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
}
