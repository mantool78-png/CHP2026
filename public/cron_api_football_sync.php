<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$token = api_football_cron_token();
if (!cron_token_valid($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
$r = run_api_football_sync();
echo 'checked=' . (int) $r['checked'] . "\n";
echo 'finished=' . (int) $r['finished'] . "\n";
echo 'live=' . (int) $r['live'] . "\n";
echo 'teams_updated=' . (int) ($r['teams_updated'] ?? 0) . "\n";
echo 'corrected=' . (int) ($r['corrected'] ?? 0) . "\n";
echo 'schedule_updated=' . (int) $r['schedule_updated'] . "\n";
echo 'errors=' . (int) $r['errors'] . "\n";
