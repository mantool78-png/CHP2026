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
$force = isset($_GET['force']) && (string) $_GET['force'] === '1';
$r = run_weekly_digest_mailout($force);
echo 'sent=' . (int) $r['sent'] . "\n";
echo 'skipped=' . (int) $r['skipped'] . "\n";
echo 'failed=' . (int) $r['failed'] . "\n";
