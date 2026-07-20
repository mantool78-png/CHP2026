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

$matchId = (int) ($_GET['match_id'] ?? 84);
$prevHome = (int) ($_GET['prev_home'] ?? 3);
$prevAway = (int) ($_GET['prev_away'] ?? 2);
$dryRun = !empty($_GET['dry_run']);

if ($matchId <= 0) {
    http_response_code(400);
    echo "invalid_match_id\n";
    exit;
}

if ($dryRun) {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM predictions p
         JOIN users u ON u.id = p.user_id
         WHERE p.match_id = ?
           AND u.role = 'participant'
           AND u.payment_status <> 'blocked'"
    );
    $stmt->execute([$matchId]);
    echo 'dry_run=1' . "\n";
    echo 'match_id=' . $matchId . "\n";
    echo 'prev_score=' . $prevHome . ':' . $prevAway . "\n";
    echo 'recipients=' . (int) $stmt->fetchColumn() . "\n";
    echo 'ok';
    exit;
}

$r = run_match_result_correction_notifications($matchId, $prevHome, $prevAway);
echo 'match_id=' . (int) $r['match_id'] . "\n";
echo 'recipients=' . (int) $r['recipients'] . "\n";
echo 'sent=' . (int) $r['sent'] . "\n";
echo 'failed=' . (int) $r['failed'] . "\n";
if (!empty($r['error'])) {
    echo 'error=' . (string) $r['error'] . "\n";
}
echo 'ok';
