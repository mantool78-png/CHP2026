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
engagement_refresh_home_activity_cache();
$snapshot = engagement_home_activity_snapshot();
echo 'updated_at=' . (string) ($snapshot['updated_at'] ?? '') . "\n";
echo 'ok';
