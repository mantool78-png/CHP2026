<?php

declare(strict_types=1);

/**
 * CLI: синхронизация результатов API-Football.
 * php scripts/cli_api_football_sync.php
 * php scripts/cli_api_football_sync.php map-teams
 * php scripts/cli_api_football_sync.php map-fixtures
 * php scripts/cli_api_football_sync.php predictions-test <match_id>
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = $argv[1] ?? 'sync';

if ($cmd === 'map-teams') {
    $r = api_football_map_teams();
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($cmd === 'map-fixtures') {
    $r = api_football_map_fixtures();
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($cmd === 'h2h-test') {
    $matchId = (int) ($argv[2] ?? 0);
    $match = $matchId > 0 ? find_match($matchId) : null;
    if (!$match) {
        fwrite(STDERR, "Usage: php scripts/cli_api_football_sync.php h2h-test <match_id>\n");
        exit(1);
    }
    $homeApi = (int) ($match['home_api_team_id'] ?? 0);
    $awayApi = (int) ($match['away_api_team_id'] ?? 0);
    echo "match #{$matchId}: {$match['home_team']} (api {$homeApi}) vs {$match['away_team']} (api {$awayApi})\n";
    if ($homeApi < 1 || $awayApi < 1) {
        echo "Missing api_team_id — run map-teams\n";
        exit(1);
    }
    $h2h = api_football_match_h2h($homeApi, $awayApi, 8);
    echo json_encode($h2h, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($cmd === 'predictions-test') {
    $matchId = (int) ($argv[2] ?? 0);
    $match = $matchId > 0 ? find_match($matchId) : null;
    if (!$match) {
        fwrite(STDERR, "Usage: php scripts/cli_api_football_sync.php predictions-test <match_id>\n");
        exit(1);
    }
    $fixtureId = (int) ($match['api_fixture_id'] ?? 0);
    echo "match #{$matchId}: {$match['home_team']} vs {$match['away_team']}\n";
    echo "api_fixture_id={$fixtureId}\n";
    if ($fixtureId < 1) {
        echo "Missing api_fixture_id — run map-fixtures\n";
        exit(1);
    }
    $predictions = api_football_match_predictions(
        $fixtureId,
        (string) $match['home_team'],
        (string) $match['away_team']
    );
    echo json_encode($predictions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(empty($predictions['available']) ? 2 : 0);
}

$r = run_api_football_sync();
echo 'checked=' . $r['checked'] . "\n";
echo 'finished=' . $r['finished'] . "\n";
echo 'live=' . $r['live'] . "\n";
echo 'schedule_updated=' . $r['schedule_updated'] . "\n";
echo 'errors=' . $r['errors'] . "\n";
