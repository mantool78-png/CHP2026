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

$nameQuery = trim((string) ($_GET['name'] ?? ''));
$userId = (int) ($_GET['user_id'] ?? 0);
$nameExact = !empty($_GET['exact']);
$homeTeam = trim((string) ($_GET['home'] ?? ''));
$awayTeam = trim((string) ($_GET['away'] ?? ''));

$userStmt = db()->prepare(
    $userId > 0
        ? "SELECT id, name, email, payment_status, role, created_at
           FROM users
           WHERE role = 'participant' AND id = ?
           LIMIT 1"
        : ($nameExact
        ? "SELECT id, name, email, payment_status, role, created_at
           FROM users
           WHERE role = 'participant' AND name = ?
           ORDER BY id ASC"
        : "SELECT id, name, email, payment_status, role, created_at
           FROM users
           WHERE role = 'participant' AND (name LIKE ? OR email LIKE ?)
           ORDER BY id ASC")
);
if ($userId > 0) {
    $userStmt->execute([$userId]);
} elseif ($nameExact) {
    $userStmt->execute([$nameQuery]);
} else {
    $needle = $nameQuery !== '' ? '%' . $nameQuery . '%' : '%';
    $userStmt->execute([$needle, $needle]);
}
$users = $userStmt->fetchAll();

echo 'server_now=' . date('Y-m-d H:i:s') . "\n";
echo 'prediction_lock_minutes=' . (int) config('app.prediction_lock_minutes') . "\n";
echo 'users_found=' . count($users) . "\n\n";

if ($users === [] && $nameQuery !== '') {
    $fallbackStmt = db()->prepare(
        "SELECT id, name, email, payment_status
         FROM users
         WHERE role = 'participant'
           AND (name LIKE ? OR name LIKE ? OR email LIKE ?)
         ORDER BY name ASC
         LIMIT 15"
    );
    $fallbackStmt->execute(['%hall%', '%allback%', '%hall%']);
    $fallback = $fallbackStmt->fetchAll();
    if ($fallback === [] && $nameQuery !== '') {
        $digitsStmt = db()->prepare(
            "SELECT id, name, email, payment_status
             FROM users
             WHERE role = 'participant' AND name LIKE ?
             ORDER BY name ASC
             LIMIT 15"
        );
        $digitsStmt->execute(['%' . $nameQuery . '%']);
        $fallback = $digitsStmt->fetchAll();
    }
    if ($fallback !== []) {
        echo "similar_users:\n";
        foreach ($fallback as $row) {
            echo '  #' . (int) $row['id'] . ' ' . (string) $row['name'] . ' ' . (string) $row['email']
                . ' ' . (string) $row['payment_status'] . "\n";
        }
        echo "\n";
    }
}

$matchStmt = db()->prepare(
    "SELECT m.*, ht.name AS home_team, at.name AS away_team
     FROM matches m
     JOIN teams ht ON ht.id = m.home_team_id
     JOIN teams at ON at.id = m.away_team_id
     WHERE (? = '' OR ht.name LIKE ?)
       AND (? = '' OR at.name LIKE ?)
     ORDER BY m.starts_at ASC
     LIMIT 20"
);
$matchStmt->execute([
    $homeTeam,
    $homeTeam === '' ? '%' : '%' . $homeTeam . '%',
    $awayTeam,
    $awayTeam === '' ? '%' : '%' . $awayTeam . '%',
]);
$matches = $matchStmt->fetchAll();

echo "recent_open_or_locked_matches:\n";
$recentMatchesStmt = db()->query(
    "SELECT m.id, m.starts_at, m.status, m.home_score, m.away_score, m.updated_at, m.api_synced_at,
            ht.name AS home_team, at.name AS away_team
     FROM matches m
     JOIN teams ht ON ht.id = m.home_team_id
     JOIN teams at ON at.id = m.away_team_id
     WHERE m.starts_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
       AND m.starts_at <= DATE_ADD(NOW(), INTERVAL 2 DAY)
     ORDER BY m.starts_at ASC"
);
foreach ($recentMatchesStmt->fetchAll() as $match) {
    $lockAt = date('Y-m-d H:i:s', strtotime((string) $match['starts_at']) - ((int) config('app.prediction_lock_minutes') * 60));
    echo '  #' . (int) $match['id'] . ' '
        . (string) $match['home_team'] . ' — ' . (string) $match['away_team']
        . ' | ' . (string) $match['starts_at']
        . ' | status=' . (string) $match['status']
        . ' | score=' . ($match['home_score'] === null ? 'null' : (string) $match['home_score'])
        . ':' . ($match['away_score'] === null ? 'null' : (string) $match['away_score'])
        . ' | lock_at=' . $lockAt
        . ' | locked=' . (prediction_locked($match) ? '1' : '0')
        . ' | api_sync=' . (string) ($match['api_synced_at'] ?? 'null')
        . "\n";
}
echo "\n";

foreach ($users as $user) {
    echo '=== user #' . (int) $user['id'] . ' ' . (string) $user['name'] . ' ===' . "\n";
    echo 'email=' . (string) $user['email'] . "\n";
    echo 'payment_status=' . (string) $user['payment_status'] . "\n";
    echo 'predictions_total=' . user_predictions_count((int) $user['id']) . "\n";
    echo 'free_remaining=' . free_predictions_remaining((int) $user['id']) . "\n";

    $recentStmt = db()->prepare(
        "SELECT p.match_id, p.home_score, p.away_score, p.updated_at,
                m.starts_at, m.status, ht.name AS home_team, at.name AS away_team
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE p.user_id = ?
         ORDER BY m.starts_at DESC
         LIMIT 10"
    );
    $recentStmt->execute([(int) $user['id']]);
    echo "recent_predictions:\n";
    foreach ($recentStmt->fetchAll() as $row) {
        echo '  #' . (int) $row['match_id'] . ' '
            . (string) $row['home_team'] . ' — ' . (string) $row['away_team']
            . ' | ' . (int) $row['home_score'] . ':' . (int) $row['away_score']
            . ' | saved ' . (string) $row['updated_at']
            . ' | kickoff ' . (string) $row['starts_at']
            . ' | status=' . (string) $row['status'] . "\n";
    }

    $missingStmt = db()->prepare(
        "SELECT m.id, m.starts_at, m.status, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         LEFT JOIN predictions p ON p.match_id = m.id AND p.user_id = ?
         WHERE m.starts_at <= NOW()
           AND m.home_team_id IS NOT NULL
           AND m.away_team_id IS NOT NULL
           AND p.id IS NULL
         ORDER BY m.starts_at DESC
         LIMIT 12"
    );
    $missingStmt->execute([(int) $user['id']]);
    echo "missing_started_matches:\n";
    foreach ($missingStmt->fetchAll() as $row) {
        $lockAt = date('Y-m-d H:i:s', strtotime((string) $row['starts_at']) - ((int) config('app.prediction_lock_minutes') * 60));
        echo '  #' . (int) $row['id'] . ' '
            . (string) $row['home_team'] . ' — ' . (string) $row['away_team']
            . ' | ' . (string) $row['starts_at']
            . ' | status=' . (string) $row['status']
            . ' | lock_at=' . $lockAt
            . "\n";
    }
    echo "\n";
}

echo 'ok';
