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

$audit = audit_free_trial_predictions();

echo "free_trial_limit=" . free_prediction_limit() . "\n";
echo "off_trial_predictions=" . (int) $audit['off_trial_prediction_count'] . "\n";
echo "unpaid_users_with_off_trial=" . (int) $audit['unpaid_with_off_trial'] . "\n\n";

echo "trial_matches_by_schedule:\n";
foreach ($audit['trial_matches'] as $i => $match) {
    echo ($i + 1) . '. '
        . (string) ($match['stage'] ?? '')
        . ' | '
        . (string) ($match['home_team'] ?? '')
        . ' — '
        . (string) ($match['away_team'] ?? '')
        . ' | '
        . (string) ($match['starts_at'] ?? '')
        . "\n";
}

if ($audit['users_off_trial'] === []) {
    echo "\nno_off_trial_users\n";
    echo 'ok';
    exit;
}

echo "\nusers_with_off_trial_predictions:\n";
foreach ($audit['users_off_trial'] as $row) {
    echo (int) $row['id']
        . ' | '
        . (string) $row['name']
        . ' | '
        . (string) $row['email']
        . ' | count='
        . (int) $row['off_trial_count']
        . ' | '
        . (string) ($row['off_trial_matches'] ?? '')
        . "\n";
}

echo "\nok";
