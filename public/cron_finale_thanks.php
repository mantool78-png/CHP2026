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

if (!mail_is_configured()) {
    http_response_code(503);
    echo "mail_not_configured\n";
    exit;
}

$confirm = (string) ($_GET['confirm'] ?? '');
$markDone = (string) ($_GET['mark_done'] ?? '');

if ($markDone === 'yes') {
    $done = db()->prepare(
        "INSERT INTO settings (setting_key, setting_value, updated_at)
         VALUES ('finale_thanks_mailout_completed_at', ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );
    $done->execute([date('c')]);
    echo "marked_done=1\n";
    echo "ok\n";
    exit;
}

if ($confirm !== 'yes') {
    $count = (int) db()->query(
        "SELECT COUNT(*) FROM users
         WHERE role = 'participant'
           AND payment_status = 'active'
           AND email IS NOT NULL
           AND email <> ''"
    )->fetchColumn();
    $doneStmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'finale_thanks_mailout_completed_at' LIMIT 1");
    $doneStmt->execute();
    $doneVal = $doneStmt->fetchColumn();
    $sentStmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'finale_thanks_mailout_sent_ids' LIMIT 1");
    $sentStmt->execute();
    $sentRaw = (string) ($sentStmt->fetchColumn() ?: '');
    $sentCount = $sentRaw === '' ? 0 : count(array_filter(explode(',', $sentRaw)));
    echo "dry_run=1\n";
    echo "recipients={$count}\n";
    echo 'completed=' . (is_string($doneVal) && $doneVal !== '' ? '1' : '0') . "\n";
    echo "progress_sent_ids={$sentCount}\n";
    echo "add_confirm=yes_to_send\n";
    exit;
}

set_time_limit(600);
ignore_user_abort(true);

$r = run_finale_thanks_mailout();
echo "mailout=finale_thanks\n";
echo 'total=' . (int) $r['total'] . "\n";
echo 'sent=' . (int) $r['sent'] . "\n";
echo 'skipped=' . (int) ($r['skipped'] ?? 0) . "\n";
echo 'failed=' . (int) $r['failed'] . "\n";
echo "ok\n";
