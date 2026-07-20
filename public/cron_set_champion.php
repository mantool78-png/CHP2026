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

$code = strtoupper(trim((string) ($_GET['code'] ?? 'ESP')));
if ($code === '') {
    echo "missing_code\n";
    exit;
}

$teamStmt = db()->prepare('SELECT id, name, code FROM teams WHERE UPPER(code) = ? LIMIT 1');
$teamStmt->execute([$code]);
$team = $teamStmt->fetch();
if (!$team || !team_is_champion_pick_candidate($team)) {
    echo "team_not_found_or_invalid code={$code}\n";
    exit;
}

$teamId = (int) $team['id'];

$setting = db()->prepare(
    "INSERT INTO settings (setting_key, setting_value, updated_at)
     VALUES ('champion_team_id', ?, NOW())
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
);
$setting->execute([(string) $teamId]);

$upd = db()->prepare(
    'UPDATE champion_predictions SET points = CASE WHEN team_id = ? THEN 10 ELSE 0 END, updated_at = NOW()'
);
$upd->execute([$teamId]);

$winners = (int) db()->query(
    'SELECT COUNT(*) FROM champion_predictions WHERE points = 10'
)->fetchColumn();
$losers = (int) db()->query(
    'SELECT COUNT(*) FROM champion_predictions WHERE points = 0'
)->fetchColumn();
$total = (int) db()->query('SELECT COUNT(*) FROM champion_predictions')->fetchColumn();

echo "champion_set=1\n";
echo 'team_id=' . $teamId . "\n";
echo 'team_name=' . $team['name'] . "\n";
echo 'team_code=' . $team['code'] . "\n";
echo "predictions_total={$total}\n";
echo "winners_plus10={$winners}\n";
echo "others_zero={$losers}\n";

$top = db()->query(
    "SELECT u.name, COALESCE(ms.match_points, 0) AS match_points,
            COALESCE(cp.points, 0) AS champion_points,
            COALESCE(ms.match_points, 0) + COALESCE(cp.points, 0) AS total_points,
            COALESCE(ms.exact_scores_count, 0) AS exact_scores_count
     FROM users u
     LEFT JOIN champion_predictions cp ON cp.user_id = u.id
     LEFT JOIN (
        SELECT user_id,
               SUM(points) AS match_points,
               SUM(CASE WHEN reason = 'Точный счет' THEN 1 ELSE 0 END) AS exact_scores_count
        FROM scores
        GROUP BY user_id
     ) ms ON ms.user_id = u.id
     WHERE u.role = 'participant' AND u.payment_status = 'active'
     ORDER BY total_points DESC, exact_scores_count DESC, u.created_at ASC
     LIMIT 10"
)->fetchAll();

echo "\n=== TOP 10 AFTER CHAMPION ===\n";
$i = 1;
foreach ($top as $row) {
    echo $i . ') ' . $row['name']
        . ' | total=' . (int) $row['total_points']
        . ' | match=' . (int) $row['match_points']
        . ' | champ=' . (int) $row['champion_points']
        . ' | exact=' . (int) $row['exact_scores_count']
        . "\n";
    $i++;
}

echo "\nok\n";
