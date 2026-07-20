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

if (!db_table_exists('mini_leagues') || !db_table_exists('mini_league_members')) {
    echo "mini_leagues_missing\n";
    exit;
}

$totalLeagues = (int) db()->query('SELECT COUNT(*) FROM mini_leagues')->fetchColumn();
$totalMemberships = (int) db()->query('SELECT COUNT(*) FROM mini_league_members')->fetchColumn();
$uniqueMembers = (int) db()->query('SELECT COUNT(DISTINCT user_id) FROM mini_league_members')->fetchColumn();
$soloLeagues = (int) db()->query(
    "SELECT COUNT(*) FROM (
        SELECT ml.id
        FROM mini_leagues ml
        LEFT JOIN mini_league_members mlm ON mlm.league_id = ml.id
        GROUP BY ml.id
        HAVING COUNT(mlm.user_id) <= 1
     ) t"
)->fetchColumn();
$multiLeagues = (int) db()->query(
    "SELECT COUNT(*) FROM (
        SELECT ml.id
        FROM mini_leagues ml
        JOIN mini_league_members mlm ON mlm.league_id = ml.id
        GROUP BY ml.id
        HAVING COUNT(mlm.user_id) >= 2
     ) t"
)->fetchColumn();
$leagues5plus = (int) db()->query(
    "SELECT COUNT(*) FROM (
        SELECT ml.id
        FROM mini_leagues ml
        JOIN mini_league_members mlm ON mlm.league_id = ml.id
        GROUP BY ml.id
        HAVING COUNT(mlm.user_id) >= 5
     ) t"
)->fetchColumn();

echo "total_leagues={$totalLeagues}\n";
echo "total_memberships={$totalMemberships}\n";
echo "unique_members={$uniqueMembers}\n";
echo "solo_leagues={$soloLeagues}\n";
echo "multi_leagues={$multiLeagues}\n";
echo "leagues_5plus={$leagues5plus}\n\n";

$leagues = db()->query(
    "SELECT ml.id, ml.name, ml.invite_code, ml.created_at,
            u.name AS owner_name,
            COUNT(mlm.user_id) AS members_count
     FROM mini_leagues ml
     JOIN users u ON u.id = ml.owner_user_id
     LEFT JOIN mini_league_members mlm ON mlm.league_id = ml.id
     GROUP BY ml.id, ml.name, ml.invite_code, ml.created_at, u.name
     ORDER BY members_count DESC, ml.created_at ASC"
)->fetchAll();

echo "=== LEAGUES BY SIZE ===\n";
foreach ($leagues as $league) {
    $leagueId = (int) $league['id'];
    $membersCount = (int) $league['members_count'];
    echo '#' . $leagueId
        . ' | ' . (string) $league['name']
        . ' | members=' . $membersCount
        . ' | owner=' . (string) $league['owner_name']
        . ' | created=' . (string) $league['created_at']
        . "\n";

    $leaders = mini_league_leaderboard($leagueId);
    $top = array_slice($leaders, 0, min(5, count($leaders)));
    $place = 1;
    foreach ($top as $row) {
        echo '  ' . $place . ') '
            . (string) $row['name']
            . ' | pts=' . (int) ($row['total_points'] ?? $row['match_points'] ?? 0)
            . ' | exact=' . (int) ($row['exact_scores_count'] ?? 0)
            . ' | outcomes=' . (int) ($row['outcomes_count'] ?? 0)
            . "\n";
        $place++;
    }
    echo "\n";
}

echo 'ok';
