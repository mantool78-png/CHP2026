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

$matchId = max(1, (int) ($_GET['match_id'] ?? 1));
$r = run_opening_match_reminder_mailout($matchId);

if (!empty($r['error'])) {
    echo $r['error'] . "\n";
}

echo 'match_id=' . (int) $r['match_id'] . "\n";
echo 'recipients=' . (int) $r['recipients'] . "\n";
echo 'sent=' . (int) $r['sent'] . "\n";
echo 'skipped=' . (int) $r['skipped'] . "\n";
echo 'failed=' . (int) $r['failed'] . "\n";
