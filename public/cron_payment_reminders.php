<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$token = mail_settings()['reminder_cron_token'];
$provided = (string) ($_GET['token'] ?? '');
if ($token === '' || !hash_equals($token, $provided)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

if (!mail_is_configured()) {
    http_response_code(503);
    echo "mail_not_configured\n";
    exit;
}

$r = run_pending_payment_reminder_mailout();
echo 'total=' . (int) $r['total'] . "\n";
echo 'sent=' . (int) $r['sent'] . "\n";
echo 'failed=' . (int) $r['failed'] . "\n";
