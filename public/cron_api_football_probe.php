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

$stage = trim((string) ($_GET['stage'] ?? 'Групповой этап - матч 6'));
$stmt = db()->prepare(
    "SELECT m.*, ht.name AS home_team, at.name AS away_team,
            ht.api_team_id AS home_api, at.api_team_id AS away_api
     FROM matches m
     JOIN teams ht ON ht.id = m.home_team_id
     JOIN teams at ON at.id = m.away_team_id
     WHERE m.stage = ?
     LIMIT 1"
);
$stmt->execute([$stage]);
$match = $stmt->fetch();

if (!$match) {
    echo "match_not_found stage={$stage}\n";
    exit;
}

echo 'match_id=' . (int) $match['id'] . "\n";
echo 'stage=' . (string) $match['stage'] . "\n";
echo 'teams=' . (string) $match['home_team'] . ' vs ' . (string) $match['away_team'] . "\n";
echo 'starts_at=' . (string) $match['starts_at'] . "\n";
echo 'status=' . (string) $match['status'] . "\n";
echo 'score=' . ($match['home_score'] === null ? '-' : (int) $match['home_score']) . ':'
    . ($match['away_score'] === null ? '-' : (int) $match['away_score']) . "\n";
echo 'api_fixture_id=' . (int) ($match['api_fixture_id'] ?? 0) . "\n";
echo 'result_source=' . (string) ($match['result_source'] ?? '') . "\n";
echo 'api_synced_at=' . (string) ($match['api_synced_at'] ?? '') . "\n";
echo 'home_api_team_id=' . (int) ($match['home_api'] ?? 0) . "\n";
echo 'away_api_team_id=' . (int) ($match['away_api'] ?? 0) . "\n\n";

$log = db()->prepare(
    "SELECT action, message, created_at FROM api_football_sync_log
     WHERE match_id = ? ORDER BY created_at DESC LIMIT 8"
);
$log->execute([(int) $match['id']]);
echo "recent_log:\n";
foreach ($log->fetchAll() as $row) {
    echo (string) $row['created_at'] . ' | ' . (string) $row['action'] . ' | ' . (string) $row['message'] . "\n";
}

if (!api_football_configured()) {
    echo "\napi_not_configured\n";
    exit;
}

echo "\nmap_fixtures:\n";
$map = api_football_map_fixtures(12);
echo json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$stmt->execute([$stage]);
$match = $stmt->fetch() ?: $match;
echo "\nafter_map api_fixture_id=" . (int) ($match['api_fixture_id'] ?? 0) . "\n";

$fixtureId = (int) ($match['api_fixture_id'] ?? 0);
if ($fixtureId > 0) {
    $fixtures = api_football_fixtures_by_ids([$fixtureId]);
    if ($fixtures !== []) {
        $f = $fixtures[0];
        echo 'api_status=' . (string) ($f['fixture']['status']['short'] ?? '') . "\n";
        echo 'api_date=' . (string) ($f['fixture']['date'] ?? '') . "\n";
        echo 'api_goals=' . json_encode($f['goals'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\nsync:\n";
$sync = run_api_football_sync();
echo 'checked=' . (int) $sync['checked'] . "\n";
echo 'live=' . (int) $sync['live'] . "\n";
echo 'finished=' . (int) $sync['finished'] . "\n";
echo 'errors=' . (int) $sync['errors'] . "\n";

$stmt->execute([$stage]);
$match = $stmt->fetch() ?: $match;
echo "\nfinal status=" . (string) $match['status'] . ' score='
    . ($match['home_score'] === null ? '-' : (int) $match['home_score']) . ':'
    . ($match['away_score'] === null ? '-' : (int) $match['away_score']) . "\n";
echo 'ok';
