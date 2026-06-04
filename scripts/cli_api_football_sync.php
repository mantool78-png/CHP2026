<?php

declare(strict_types=1);

/**
 * CLI: синхронизация результатов API-Football.
 * php scripts/cli_api_football_sync.php
 * php scripts/cli_api_football_sync.php map-teams
 * php scripts/cli_api_football_sync.php map-fixtures
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

$r = run_api_football_sync();
echo 'checked=' . $r['checked'] . "\n";
echo 'finished=' . $r['finished'] . "\n";
echo 'live=' . $r['live'] . "\n";
echo 'errors=' . $r['errors'] . "\n";
