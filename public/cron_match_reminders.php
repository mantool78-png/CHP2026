<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$token = mail_settings()['reminder_cron_token'];
if (!cron_token_valid($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
$r = run_match_prediction_reminders();
echo 'matches=' . (int) $r['matches'] . "\n";
echo 'sent=' . (int) $r['sent'] . "\n";
echo 'skipped_users=' . (int) $r['skipped_users'] . "\n";
echo 'failed=' . (int) $r['failed'] . "\n";
