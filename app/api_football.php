<?php

declare(strict_types=1);

/** Коды FIFA на сайте → код в ответе API-Football (поле team.code / name). */
const API_FOOTBALL_TEAM_CODE_ALIASES = [
    'ENG' => ['ENG', 'GB-ENG'],
    'USA' => ['USA'],
    'RSA' => ['RSA'],
    'KOR' => ['KOR'],
    'CIV' => ['CIV', 'IVO'],
    'BIH' => ['BIH'],
    'CUW' => ['CUW', 'CUR'],
    'CPV' => ['CPV'],
    'COD' => ['COD', 'DRC'],
];

/** Точные английские названия в API, когда код отличается или отсутствует. */
const API_FOOTBALL_TEAM_NAME_ALIASES = [
    'COD' => ['dr congo', 'congo dr', 'democratic republic of the congo'],
    'CUW' => ['curacao', 'curaçao'],
    'RSA' => ['south africa'],
    'KOR' => ['south korea', 'korea republic'],
    'CIV' => ['ivory coast', 'cote d ivoire', "cote d'ivoire"],
    'CPV' => ['cape verde'],
];

function api_football_settings(): array
{
    $cfg = config('api_football', []);
    if (!is_array($cfg)) {
        return [];
    }

    return $cfg;
}

function api_football_configured(): bool
{
    $cfg = api_football_settings();

    return !empty($cfg['enabled'])
        && trim((string) ($cfg['api_key'] ?? '')) !== '';
}

/** Миграция 009 применена (колонки api_team_id / api_fixture_id). */
function api_football_schema_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $teams = db()->query("SHOW COLUMNS FROM teams LIKE 'api_team_id'")->fetch();
        $matches = db()->query("SHOW COLUMNS FROM matches LIKE 'api_fixture_id'")->fetch();
        $ready = $teams !== false && $matches !== false;
    } catch (Throwable) {
        $ready = false;
    }

    return $ready;
}

function api_football_cron_token(): string
{
    return trim((string) (api_football_settings()['cron_token'] ?? ''));
}

/** @return array{ok:bool,status:int,body:array<string,mixed>,headers:array<string,string>,error:?string} */
function api_football_get(string $endpoint, array $query = []): array
{
    $cfg = api_football_settings();
    $apiKey = trim((string) ($cfg['api_key'] ?? ''));
    if ($apiKey === '') {
        return ['ok' => false, 'status' => 0, 'body' => [], 'headers' => [], 'error' => 'API key not configured'];
    }

    $base = rtrim((string) ($cfg['base_url'] ?? 'https://v3.football.api-sports.io'), '/');
    $path = '/' . ltrim($endpoint, '/');
    $url = $base . $path;
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => [], 'headers' => [], 'error' => 'curl_init failed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'x-apisports-key: ' . $apiKey,
            'Accept: application/json',
        ],
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'body' => [], 'headers' => [], 'error' => $curlError ?: 'request failed'];
    }

    /** @var array<string,mixed> $decoded */
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'body' => [], 'headers' => [], 'error' => 'invalid JSON'];
    }

    if ($status === 204) {
        return ['ok' => true, 'status' => 204, 'body' => ['response' => []], 'headers' => [], 'error' => null];
    }

    if ($status < 200 || $status >= 300) {
        $errMsg = 'HTTP ' . $status;
        if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
            $errMsg .= ' ' . json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE);
        }

        return ['ok' => false, 'status' => $status, 'body' => $decoded, 'headers' => [], 'error' => $errMsg];
    }

    if (!empty($decoded['errors']) && is_array($decoded['errors']) && count($decoded['errors']) > 0) {
        return ['ok' => false, 'status' => $status, 'body' => $decoded, 'headers' => [], 'error' => json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE)];
    }

    return ['ok' => true, 'status' => $status, 'body' => $decoded, 'headers' => [], 'error' => null];
}

/** @return list<array<string,mixed>> */
function api_football_response_list(array $body): array
{
    $list = $body['response'] ?? [];
    if (!is_array($list)) {
        return [];
    }

    return $list;
}

function api_football_league_query(): array
{
    $cfg = api_football_settings();

    return [
        'league' => (int) ($cfg['league_id'] ?? 1),
        'season' => (int) ($cfg['season'] ?? 2026),
    ];
}

/** @return list<array<string,mixed>> */
function api_football_teams_for_league(): array
{
    $q = api_football_league_query();
    $res = api_football_get('/teams', $q);
    if (!$res['ok']) {
        return [];
    }

    return api_football_response_list($res['body']);
}

/** @return list<array<string,mixed>> */
function api_football_fixtures_for_league(): array
{
    $cfg = api_football_settings();
    $q = api_football_league_query();
    $q['timezone'] = (string) ($cfg['timezone'] ?? 'Europe/Moscow');
    $res = api_football_get('/fixtures', $q);
    if (!$res['ok']) {
        return [];
    }

    return api_football_response_list($res['body']);
}

/** @param list<int> $fixtureIds @return list<array<string,mixed>> */
function api_football_fixtures_by_ids(array $fixtureIds): array
{
    $fixtureIds = array_values(array_unique(array_filter(array_map('intval', $fixtureIds), static fn (int $id): bool => $id > 0)));
    if ($fixtureIds === []) {
        return [];
    }

    $all = [];
    foreach (array_chunk($fixtureIds, 20) as $chunk) {
        $idsParam = implode('-', array_map('strval', $chunk));
        $res = api_football_get('/fixtures', ['ids' => $idsParam]);
        if (!$res['ok']) {
            continue;
        }
        foreach (api_football_response_list($res['body']) as $row) {
            $all[] = $row;
        }
    }

    return $all;
}

function api_football_normalize_api_team_code(?string $code): string
{
    return strtoupper(trim((string) $code));
}

function api_football_team_codes_match(string $ourCode, array $apiTeam): bool
{
    $our = api_football_normalize_api_team_code($ourCode);
    if ($our === '') {
        return false;
    }

    $aliases = API_FOOTBALL_TEAM_CODE_ALIASES[$our] ?? [$our];
    $apiCode = api_football_normalize_api_team_code($apiTeam['code'] ?? ($apiTeam['team']['code'] ?? ''));
    if ($apiCode !== '' && in_array($apiCode, $aliases, true)) {
        return true;
    }

    $apiName = api_football_normalize_team_name((string) ($apiTeam['name'] ?? ($apiTeam['team']['name'] ?? '')));
    $nameAliases = API_FOOTBALL_TEAM_NAME_ALIASES[$our] ?? [];

    return $apiName !== '' && in_array($apiName, $nameAliases, true);
}

function api_football_normalize_team_name(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = str_replace(['’', '`', '´'], "'", $name);
    $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? $name;

    return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
}

/** @param array<string,mixed> $apiTeamRow */
function api_football_extract_team_id(array $apiTeamRow): int
{
    if (isset($apiTeamRow['team']['id'])) {
        return (int) $apiTeamRow['team']['id'];
    }

    return (int) ($apiTeamRow['id'] ?? 0);
}

function api_football_log(?int $matchId, string $action, string $message): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO api_football_sync_log (match_id, action, message, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$matchId, $action, mb_substr($message, 0, 500)]);
    } catch (Throwable) {
        // Таблица может отсутствовать до миграции 009.
    }
}

/** @return array{total:int,mapped:int} */
function api_football_worldcup_team_stats(): array
{
    ensure_worldcup2026_teams_in_db();

    $rows = db()->query('SELECT code, name, api_team_id FROM teams')->fetchAll();
    $totalByCode = [];
    $mappedByCode = [];
    foreach ($rows as $row) {
        $code = worldcup2026_team_code(
            $row['code'] !== null && (string) $row['code'] !== '' ? (string) $row['code'] : null,
            (string) $row['name']
        );
        if ($code === null) {
            continue;
        }
        $totalByCode[$code] = true;
        if ($row['api_team_id'] !== null && (int) $row['api_team_id'] > 0) {
            $mappedByCode[$code] = true;
        }
    }

    return [
        'total' => count(WORLD_CUP_2026_TEAMS),
        'mapped' => count($mappedByCode),
    ];
}

/** @return array{mapped:int,skipped:int,errors:list<string>} */
function api_football_map_teams(): array
{
    $mapped = 0;
    $skipped = 0;
    $errors = [];

    if (!api_football_configured()) {
        return ['mapped' => 0, 'skipped' => 0, 'errors' => ['API-Football не включён или нет ключа']];
    }

    $apiTeams = api_football_teams_for_league();
    if ($apiTeams === []) {
        return ['mapped' => 0, 'skipped' => 0, 'errors' => ['Пустой ответ /teams (проверьте league_id и season)']];
    }

    ensure_worldcup2026_teams_in_db();
    $stmt = db()->query('SELECT id, code, name, api_team_id FROM teams ORDER BY name');
    $ourTeams = [];
    $seenCodes = [];
    foreach ($stmt->fetchAll() as $row) {
        $resolvedCode = worldcup2026_team_code(
            $row['code'] !== null && (string) $row['code'] !== '' ? (string) $row['code'] : null,
            (string) $row['name']
        );
        if ($resolvedCode === null || isset($seenCodes[$resolvedCode])) {
            continue;
        }
        $row['resolved_code'] = $resolvedCode;
        $ourTeams[] = $row;
        $seenCodes[$resolvedCode] = true;
    }

    db()->exec('UPDATE teams SET api_team_id = NULL WHERE api_team_id IS NOT NULL');
    $update = db()->prepare('UPDATE teams SET api_team_id = ?, updated_at = NOW() WHERE id = ?');
    $usedApiIds = [];

    foreach ($ourTeams as $team) {
        $code = (string) $team['resolved_code'];

        $foundId = null;
        foreach ($apiTeams as $apiRow) {
            $apiTeam = $apiRow['team'] ?? $apiRow;
            if (!is_array($apiTeam)) {
                continue;
            }
            if (api_football_team_codes_match($code, $apiTeam)) {
                $foundId = api_football_extract_team_id($apiRow);
                break;
            }
        }

        if ($foundId === null || $foundId <= 0) {
            $errors[] = 'Не найден API ID для ' . $code . ' (' . $team['name'] . ')';
            $skipped++;
            continue;
        }
        if (isset($usedApiIds[$foundId])) {
            $errors[] = 'API ID ' . $foundId . ' уже сопоставлен с другой командой; пропущено: ' . $code;
            $skipped++;
            continue;
        }

        $update->execute([$foundId, (int) $team['id']]);
        $usedApiIds[$foundId] = true;
        $mapped++;
    }

    api_football_log(null, 'map_teams', "mapped={$mapped} skipped={$skipped}");

    return ['mapped' => $mapped, 'skipped' => $skipped, 'errors' => $errors];
}

/** @param array<string,mixed> $fixture */
function api_football_fixture_api_team_id(array $fixture, string $side): int
{
    $teams = $fixture['teams'] ?? [];
    if (!is_array($teams)) {
        return 0;
    }
    $node = $teams[$side] ?? [];
    if (!is_array($node)) {
        return 0;
    }

    return (int) ($node['id'] ?? 0);
}

/** @param array<string,mixed> $fixture */
function api_football_fixture_starts_at(array $fixture): ?string
{
    $date = $fixture['fixture']['date'] ?? null;
    if (!is_string($date) || trim($date) === '') {
        return null;
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

/** @return array{mapped:int,ambiguous:int,unmatched:int,errors:list<string>} */
function api_football_map_fixtures(int $windowHours = 3): array
{
    $mapped = 0;
    $ambiguous = 0;
    $unmatched = 0;
    $errors = [];

    if (!api_football_configured()) {
        return ['mapped' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'errors' => ['API-Football не включён']];
    }

    $teamRows = db()->query('SELECT id, api_team_id FROM teams WHERE api_team_id IS NOT NULL')->fetchAll();
    $apiTeamToLocal = [];
    foreach ($teamRows as $row) {
        $apiTeamToLocal[(int) $row['api_team_id']] = (int) $row['id'];
    }

    $fixtures = api_football_fixtures_for_league();
    if ($fixtures === []) {
        return ['mapped' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'errors' => ['Нет fixtures от API']];
    }

    $stmt = db()->query(
        "SELECT m.id, m.starts_at, m.home_team_id, m.away_team_id, m.api_fixture_id,
                ht.api_team_id AS home_api, at.api_team_id AS away_api
         FROM matches m
         LEFT JOIN teams ht ON ht.id = m.home_team_id
         LEFT JOIN teams at ON at.id = m.away_team_id
         WHERE m.home_team_id IS NOT NULL AND m.away_team_id IS NOT NULL
         ORDER BY m.starts_at ASC"
    );
    $ourMatches = $stmt->fetchAll();

    $update = db()->prepare(
        "UPDATE matches
         SET api_fixture_id = ?, result_source = 'api', updated_at = NOW()
         WHERE id = ? AND (api_fixture_id IS NULL OR api_fixture_id = ?)"
    );

    $windowSec = max(1, $windowHours) * 3600;

    foreach ($ourMatches as $match) {
        if ($match['api_fixture_id'] !== null && (int) $match['api_fixture_id'] > 0) {
            continue;
        }

        $homeApi = (int) ($match['home_api'] ?? 0);
        $awayApi = (int) ($match['away_api'] ?? 0);
        if ($homeApi <= 0 || $awayApi <= 0) {
            $unmatched++;
            continue;
        }

        $ourStart = strtotime((string) $match['starts_at']);
        if ($ourStart === false) {
            $unmatched++;
            continue;
        }

        $candidates = [];
        foreach ($fixtures as $fixture) {
            $fHome = api_football_fixture_api_team_id($fixture, 'home');
            $fAway = api_football_fixture_api_team_id($fixture, 'away');
            if ($fHome !== $homeApi || $fAway !== $awayApi) {
                if ($fHome !== $awayApi || $fAway !== $homeApi) {
                    continue;
                }
            }

            $apiStartRaw = api_football_fixture_starts_at($fixture);
            if ($apiStartRaw === null) {
                continue;
            }
            $apiStart = strtotime($apiStartRaw);
            if ($apiStart === false) {
                continue;
            }
            if (abs($apiStart - $ourStart) > $windowSec) {
                continue;
            }

            $fixtureId = (int) ($fixture['fixture']['id'] ?? 0);
            if ($fixtureId > 0) {
                $candidates[$fixtureId] = $fixture;
            }
        }

        if (count($candidates) === 0) {
            $unmatched++;
            continue;
        }
        if (count($candidates) > 1) {
            $ambiguous++;
            $errors[] = 'Матч #' . (int) $match['id'] . ': несколько fixture (' . implode(',', array_keys($candidates)) . ')';
            continue;
        }

        $fixtureId = (int) array_key_first($candidates);
        $update->execute([$fixtureId, (int) $match['id'], $fixtureId]);
        $mapped++;
    }

    api_football_log(null, 'map_fixtures', "mapped={$mapped} ambiguous={$ambiguous} unmatched={$unmatched}");

    return ['mapped' => $mapped, 'ambiguous' => $ambiguous, 'unmatched' => $unmatched, 'errors' => $errors];
}

/** Статусы API, при которых матч считается завершённым для лиги (счёт fulltime). */
function api_football_finished_statuses(): array
{
    return ['FT', 'AET', 'PEN'];
}

/** Статусы «матч идёт». */
function api_football_live_statuses(): array
{
    return ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE', 'INT', 'SUSP'];
}

/**
 * @param array<string,mixed> $fixture
 * @return array{home:int,away:int,status:string}|null
 */
function api_football_fixture_fulltime_score(array $fixture): ?array
{
    $statusShort = strtoupper((string) ($fixture['fixture']['status']['short'] ?? ''));
    if (!in_array($statusShort, api_football_finished_statuses(), true)) {
        return null;
    }

    $goals = $fixture['goals'] ?? [];
    $score = $fixture['score'] ?? [];
    $home = null;
    $away = null;

    if (is_array($goals)) {
        $home = $goals['home'] ?? null;
        $away = $goals['away'] ?? null;
    }
    if (($home === null || $away === null) && is_array($score) && isset($score['fulltime']) && is_array($score['fulltime'])) {
        $home = $score['fulltime']['home'] ?? $home;
        $away = $score['fulltime']['away'] ?? $away;
    }

    if (!is_numeric($home) || !is_numeric($away)) {
        return null;
    }

    return [
        'home' => max(0, (int) $home),
        'away' => max(0, (int) $away),
        'status' => $statusShort,
    ];
}

function api_football_set_match_live(int $matchId): void
{
    db()->prepare(
        "UPDATE matches SET status = 'live', api_synced_at = NOW(), updated_at = NOW()
         WHERE id = ? AND status = 'scheduled'"
    )->execute([$matchId]);
}

/**
 * @return array{checked:int,finished:int,live:int,errors:int,messages:list<string>}
 */
function run_api_football_sync(): array
{
    $result = [
        'checked' => 0,
        'finished' => 0,
        'live' => 0,
        'errors' => 0,
        'messages' => [],
    ];

    if (!api_football_configured()) {
        $result['messages'][] = 'disabled';

        return $result;
    }

    $stmt = db()->query(
        "SELECT id, api_fixture_id, result_source, home_score, status
         FROM matches
         WHERE api_fixture_id IS NOT NULL
           AND result_source = 'api'
           AND (
                status IN ('scheduled', 'live')
                OR (home_score IS NULL AND status = 'finished')
           )
         ORDER BY starts_at ASC"
    );
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        return $result;
    }

    $ids = array_map(static fn (array $r): int => (int) $r['api_fixture_id'], $rows);
    $fixtures = api_football_fixtures_by_ids($ids);
    $byId = [];
    foreach ($fixtures as $fixture) {
        $fid = (int) ($fixture['fixture']['id'] ?? 0);
        if ($fid > 0) {
            $byId[$fid] = $fixture;
        }
    }

    foreach ($rows as $row) {
        $matchId = (int) $row['id'];
        $fixtureId = (int) $row['api_fixture_id'];
        $result['checked']++;

        if (!isset($byId[$fixtureId])) {
            $result['errors']++;
            api_football_log($matchId, 'sync_miss', 'fixture ' . $fixtureId . ' not in response');
            continue;
        }

        $fixture = $byId[$fixtureId];
        $statusShort = strtoupper((string) ($fixture['fixture']['status']['short'] ?? ''));

        if (in_array($statusShort, api_football_live_statuses(), true)) {
            api_football_set_match_live($matchId);
            $result['live']++;
            continue;
        }

        $score = api_football_fixture_fulltime_score($fixture);
        if ($score === null) {
            continue;
        }

        try {
            apply_match_result($matchId, $score['home'], $score['away'], 'api');
            db()->prepare('UPDATE matches SET api_synced_at = NOW() WHERE id = ?')->execute([$matchId]);
            $result['finished']++;
            api_football_log($matchId, 'sync_ft', $score['home'] . ':' . $score['away'] . ' (' . $statusShort . ')');
        } catch (Throwable $e) {
            $result['errors']++;
            api_football_log($matchId, 'sync_error', $e->getMessage());
        }
    }

    api_football_warm_widgets_cache();

    return $result;
}

function api_football_widgets_cache_path(): string
{
    return dirname(__DIR__) . '/storage/cache/api_football_today.json';
}

/** Кэш ответа fixtures на сегодня (снижает лишние запросы при отладке виджетов). */
function api_football_warm_widgets_cache(): void
{
    if (!api_football_configured()) {
        return;
    }

    $cacheFile = api_football_widgets_cache_path();
    $ttl = api_football_widgets_cache_seconds();
    if (is_file($cacheFile)) {
        $age = time() - (int) filemtime($cacheFile);
        if ($age < $ttl) {
            return;
        }
    }

    $cfg = api_football_settings();
    $q = api_football_league_query();
    $q['date'] = date('Y-m-d');
    $q['timezone'] = (string) ($cfg['timezone'] ?? 'Europe/Moscow');
    $res = api_football_get('/fixtures', $q);
    if (!$res['ok']) {
        return;
    }

    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($cacheFile, json_encode([
        'cached_at' => date('c'),
        'body' => $res['body'],
    ], JSON_UNESCAPED_UNICODE));
}

function api_football_last_sync_at(): ?string
{
    try {
        $row = db()->query('SELECT MAX(api_synced_at) AS t FROM matches WHERE api_synced_at IS NOT NULL')->fetch();
        $t = $row['t'] ?? null;

        return is_string($t) && $t !== '' ? $t : null;
    } catch (Throwable) {
        return null;
    }
}

/** @return list<array<string,mixed>> */
function api_football_recent_sync_log(int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    try {
        $stmt = db()->prepare(
            'SELECT * FROM api_football_sync_log ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function api_football_widgets_enabled(): bool
{
    $cfg = api_football_settings();

    return api_football_configured() && !empty($cfg['widgets_enabled']);
}

function api_football_widget_api_key(): string
{
    return trim((string) (api_football_settings()['api_key'] ?? ''));
}

function api_football_widgets_cache_seconds(): int
{
    return max(60, (int) (api_football_settings()['widgets_cache_seconds'] ?? 120));
}
