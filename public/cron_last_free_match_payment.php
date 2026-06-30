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

set_time_limit(120);
ignore_user_abort(true);

if (!mail_is_configured()) {
    http_response_code(503);
    echo "mail_not_configured\n";
    exit;
}

$force = isset($_GET['force']) && (string) $_GET['force'] === '1';
$batchSize = max(1, min(50, (int) ($_GET['batch'] ?? 20)));

try {
    $r = run_last_free_match_payment_mailout($force, $batchSize);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'error=' . $e->getMessage() . "\n";
    exit;
}

if ($r['already_sent']) {
    echo "already_sent=1\n";
    echo "hint=Use ?force=1 to resend\n";
    exit;
}

echo 'total_recipients=' . (int) $r['total_recipients'] . "\n";
echo 'offset=' . (int) $r['offset'] . "\n";
echo 'next_offset=' . (int) $r['next_offset'] . "\n";
echo 'batch=' . $batchSize . "\n";
echo 'sent=' . (int) $r['sent'] . "\n";
echo 'failed=' . (int) $r['failed'] . "\n";
echo 'done=' . ($r['done'] ? '1' : '0') . "\n";
echo 'ok';
