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

const API_FOOTBALL_PREDICTIONS_CACHE_VERSION = 8;

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

/** Сообщение для пользователя (страница матча, админка) вместо сырого JSON API. */
function api_football_format_user_error(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return 'Не удалось загрузить справочные данные.';
    }

    $raw = trim($raw);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        if (stripos($raw, 'allowed IPs') !== false || stripos($raw, 'not allowed to call the API') !== false) {
            return api_football_ip_whitelist_hint();
        }

        return $raw;
    }

    $messages = [];
    foreach ($decoded as $key => $value) {
        $text = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        if (stripos($text, 'allowed IPs') !== false || stripos($text, 'not allowed to call the API') !== false) {
            return api_football_ip_whitelist_hint();
        }
        $messages[] = is_string($key) && $key !== '' ? $key . ': ' . $text : $text;
    }

    return $messages !== [] ? implode('; ', $messages) : $raw;
}

function api_football_ip_whitelist_hint(): string
{
    $ip = api_football_outbound_public_ip();
    $ipPart = $ip !== null ? ' Добавьте в Allowed IPs: ' . $ip . '.' : ' Узнайте исходящий IP хостинга (см. /admin/api-football) и добавьте его в Allowed IPs.';

    return 'IP сервера не в белом списке API-Sports (dashboard.api-football.com → Account → Allowed IPs).' . $ipPart;
}

/** Исходящий IP хостинга для whitelist API-Sports (кэш 24 ч). */
function api_football_outbound_public_ip(): ?string
{
    $cacheFile = dirname(__DIR__) . '/storage/cache/api_football_outbound_ip.txt';
    if (is_file($cacheFile)) {
        $age = time() - (int) filemtime($cacheFile);
        if ($age < 86400) {
            $cached = trim((string) file_get_contents($cacheFile));
            if ($cached !== '' && filter_var($cached, FILTER_VALIDATE_IP)) {
                return $cached;
            }
        }
    }

    $ch = curl_init('https://api.ipify.org?format=text');
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $raw = curl_exec($ch);
    curl_close($ch);
    if (!is_string($raw)) {
        return null;
    }

    $ip = trim($raw);
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return null;
    }

    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($cacheFile, $ip);

    return $ip;
}

/**
 * Проверка доступа к API с сервера (для админки).
 *
 * @return array{ok: bool, error: ?string, http_status: int}
 */
function api_football_ping_api(): array
{
    if (!api_football_configured()) {
        return ['ok' => false, 'error' => 'API не настроен', 'http_status' => 0];
    }

    $res = api_football_get('/status');
    if ($res['ok']) {
        return ['ok' => true, 'error' => null, 'http_status' => (int) $res['status']];
    }

    return [
        'ok' => false,
        'error' => api_football_format_user_error($res['error'] ?? null),
        'http_status' => (int) $res['status'],
    ];
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
function api_football_map_fixtures(int $windowHours = 12): array
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

function api_football_team_is_placeholder(?string $code, string $name): bool
{
    return worldcup2026_team_code($code, $name) === null;
}

function api_football_match_is_playoff_stage(string $stage): bool
{
    return !str_starts_with(trim($stage), 'Групповой этап');
}

/** @return array<int,int> */
function api_football_api_team_to_local_map(): array
{
    ensure_worldcup2026_teams_in_db();
    $map = [];
    foreach (db()->query('SELECT id, api_team_id FROM teams WHERE api_team_id IS NOT NULL')->fetchAll() as $row) {
        $apiId = (int) ($row['api_team_id'] ?? 0);
        if ($apiId > 0) {
            $map[$apiId] = (int) $row['id'];
        }
    }

    return $map;
}

/**
 * @param array<string,mixed> $apiTeamSide
 */
function api_football_ensure_local_team_for_api(int $apiTeamId, array $apiTeamSide, array &$apiToLocal): ?int
{
    if ($apiTeamId <= 0) {
        return null;
    }

    if (isset($apiToLocal[$apiTeamId])) {
        return $apiToLocal[$apiTeamId];
    }

    $stmt = db()->prepare('SELECT id, code, name FROM teams WHERE api_team_id = ? LIMIT 1');
    $stmt->execute([$apiTeamId]);
    $row = $stmt->fetch();
    if ($row) {
        $localId = (int) $row['id'];
        if (!api_football_team_is_placeholder(
            $row['code'] !== null ? (string) $row['code'] : null,
            (string) $row['name']
        )) {
            $apiToLocal[$apiTeamId] = $localId;

            return $localId;
        }
    }

    foreach (WORLD_CUP_2026_TEAMS as $code => $meta) {
        if (!api_football_team_codes_match($code, $apiTeamSide)) {
            continue;
        }

        $find = db()->prepare('SELECT id FROM teams WHERE UPPER(code) = ? LIMIT 1');
        $find->execute([$code]);
        $localId = (int) ($find->fetchColumn() ?: 0);
        if ($localId <= 0) {
            continue;
        }

        db()->prepare('UPDATE teams SET api_team_id = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$apiTeamId, $localId]);
        $apiToLocal[$apiTeamId] = $localId;

        return $localId;
    }

    return null;
}

function api_football_match_needs_api_team_sync(array $match): bool
{
    if (!api_football_match_is_playoff_stage((string) ($match['stage'] ?? ''))) {
        return false;
    }
    if (($match['status'] ?? '') === 'finished' || $match['home_score'] !== null) {
        return false;
    }

    $homePlaceholder = api_football_team_is_placeholder(
        isset($match['home_code']) && (string) $match['home_code'] !== '' ? (string) $match['home_code'] : null,
        (string) ($match['home_team'] ?? '')
    );
    $awayPlaceholder = api_football_team_is_placeholder(
        isset($match['away_code']) && (string) $match['away_code'] !== '' ? (string) $match['away_code'] : null,
        (string) ($match['away_team'] ?? '')
    );

    return $homePlaceholder || $awayPlaceholder;
}

/**
 * @param list<array<string,mixed>> $fixtures
 * @param array<int,array<string,mixed>> $fixturesById
 * @return array<string,mixed>|null
 */
function api_football_find_fixture_for_match(array $match, array $fixtures, array $fixturesById, int $windowSec): ?array
{
    $fixtureId = (int) ($match['api_fixture_id'] ?? 0);
    if ($fixtureId > 0 && isset($fixturesById[$fixtureId])) {
        return $fixturesById[$fixtureId];
    }

    $ourStart = strtotime((string) ($match['starts_at'] ?? ''));
    if ($ourStart === false) {
        return null;
    }

    $homeApi = (int) ($match['home_api'] ?? 0);
    $awayApi = (int) ($match['away_api'] ?? 0);
    $candidates = [];

    foreach ($fixtures as $fixture) {
        $apiStartRaw = api_football_fixture_starts_at($fixture);
        if ($apiStartRaw === null) {
            continue;
        }
        $apiStart = strtotime($apiStartRaw);
        if ($apiStart === false || abs($apiStart - $ourStart) > $windowSec) {
            continue;
        }

        $fHome = api_football_fixture_api_team_id($fixture, 'home');
        $fAway = api_football_fixture_api_team_id($fixture, 'away');

        if ($homeApi > 0 && $fHome !== $homeApi && $fAway !== $homeApi) {
            continue;
        }
        if ($awayApi > 0 && $fHome !== $awayApi && $fAway !== $awayApi) {
            continue;
        }

        $candidates[] = $fixture;
    }

    if (count($candidates) === 1) {
        return $candidates[0];
    }

    return null;
}

/**
 * Подставляет реальные сборные в слоты плей-офф по данным API-Football.
 *
 * @return array{teams_updated:int,skipped_tbd:int,ambiguous:int,errors:list<string>}
 */
function api_football_sync_match_teams_from_api(int $windowHours = 3): array
{
    $result = [
        'teams_updated' => 0,
        'skipped_tbd' => 0,
        'ambiguous' => 0,
        'errors' => [],
    ];

    if (!api_football_configured()) {
        return $result;
    }

    $fixtures = api_football_fixtures_for_league();
    if ($fixtures === []) {
        $result['errors'][] = 'Нет fixtures от API';

        return $result;
    }

    $fixturesById = [];
    foreach ($fixtures as $fixture) {
        $fid = (int) ($fixture['fixture']['id'] ?? 0);
        if ($fid > 0) {
            $fixturesById[$fid] = $fixture;
        }
    }

    $apiToLocal = api_football_api_team_to_local_map();
    $windowSec = max(1, $windowHours) * 3600;

    $stmt = db()->query(
        "SELECT m.id, m.stage, m.starts_at, m.api_fixture_id, m.home_team_id, m.away_team_id,
                m.status, m.home_score, m.result_source,
                ht.name AS home_team, at.name AS away_team, ht.code AS home_code, at.code AS away_code,
                ht.api_team_id AS home_api, at.api_team_id AS away_api
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.stage NOT LIKE 'Групповой этап%'
           AND m.home_score IS NULL
           AND m.status IN ('scheduled', 'live')
         ORDER BY m.starts_at ASC"
    );

    $update = db()->prepare(
        "UPDATE matches
         SET home_team_id = ?, away_team_id = ?, api_fixture_id = ?, result_source = 'api',
             placeholder_home = NULL, placeholder_away = NULL, updated_at = NOW()
         WHERE id = ?"
    );

    foreach ($stmt->fetchAll() as $match) {
        if (!api_football_match_needs_api_team_sync($match)) {
            continue;
        }

        $matchId = (int) $match['id'];
        $fixture = api_football_find_fixture_for_match($match, $fixtures, $fixturesById, $windowSec);
        if ($fixture === null) {
            if ((int) ($match['api_fixture_id'] ?? 0) <= 0) {
                $ourStart = strtotime((string) $match['starts_at']);
                $nearCount = 0;
                if ($ourStart !== false) {
                    foreach ($fixtures as $candidate) {
                        $apiStartRaw = api_football_fixture_starts_at($candidate);
                        if ($apiStartRaw === null) {
                            continue;
                        }
                        $apiStart = strtotime($apiStartRaw);
                        if ($apiStart !== false && abs($apiStart - $ourStart) <= $windowSec) {
                            $nearCount++;
                        }
                    }
                }
                if ($nearCount > 1) {
                    $result['ambiguous']++;
                    $result['errors'][] = 'Матч #' . $matchId . ': несколько fixture в окне времени (' . $nearCount . ')';
                }
            }
            continue;
        }

        $homeApiId = api_football_fixture_api_team_id($fixture, 'home');
        $awayApiId = api_football_fixture_api_team_id($fixture, 'away');
        if ($homeApiId <= 0 || $awayApiId <= 0) {
            $result['skipped_tbd']++;
            continue;
        }

        $teams = $fixture['teams'] ?? [];
        $homeMeta = is_array($teams['home'] ?? null) ? $teams['home'] : [];
        $awayMeta = is_array($teams['away'] ?? null) ? $teams['away'] : [];

        $homeLocalId = api_football_ensure_local_team_for_api($homeApiId, $homeMeta, $apiToLocal);
        $awayLocalId = api_football_ensure_local_team_for_api($awayApiId, $awayMeta, $apiToLocal);
        if ($homeLocalId === null || $awayLocalId === null) {
            $result['errors'][] = 'Матч #' . $matchId . ': API team не сопоставлен (' . $homeApiId . '/' . $awayApiId . ')';
            continue;
        }

        $fixtureId = (int) ($fixture['fixture']['id'] ?? 0);
        $currentHome = (int) ($match['home_team_id'] ?? 0);
        $currentAway = (int) ($match['away_team_id'] ?? 0);
        $currentFixture = (int) ($match['api_fixture_id'] ?? 0);

        if ($currentHome === $homeLocalId && $currentAway === $awayLocalId && $currentFixture === $fixtureId) {
            continue;
        }

        $update->execute([$homeLocalId, $awayLocalId, $fixtureId > 0 ? $fixtureId : null, $matchId]);
        $result['teams_updated']++;

        $nameStmt = db()->prepare('SELECT name FROM teams WHERE id = ?');
        $nameStmt->execute([$homeLocalId]);
        $homeName = (string) ($nameStmt->fetchColumn() ?: '');
        $nameStmt->execute([$awayLocalId]);
        $awayName = (string) ($nameStmt->fetchColumn() ?: '');
        api_football_log(
            $matchId,
            'teams_from_api',
            $homeName . ' — ' . $awayName . ' (fixture ' . $fixtureId . ')'
        );
    }

    if ($result['teams_updated'] > 0) {
        api_football_log(
            null,
            'sync_teams',
            'updated=' . $result['teams_updated'] . ' tbd=' . $result['skipped_tbd'] . ' ambiguous=' . $result['ambiguous']
        );
    }

    return $result;
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
 * Счёт для зачёта в конкурсе: только основное время (90+).
 * В API-Football goals при AET/PEN — итог с овертаймом; нужен score.fulltime.
 *
 * @param array<string,mixed> $fixture
 * @return array{home:int,away:int,status:string}|null
 */
function api_football_fixture_fulltime_score(array $fixture): ?array
{
    $statusShort = strtoupper((string) ($fixture['fixture']['status']['short'] ?? ''));
    if (!in_array($statusShort, api_football_finished_statuses(), true)) {
        return null;
    }

    $home = null;
    $away = null;
    $score = $fixture['score'] ?? [];
    if (is_array($score) && isset($score['fulltime']) && is_array($score['fulltime'])) {
        $ft = $score['fulltime'];
        if (is_numeric($ft['home'] ?? null) && is_numeric($ft['away'] ?? null)) {
            $home = (int) $ft['home'];
            $away = (int) $ft['away'];
        }
    }

    // goals совпадает с fulltime только при обычном FT; при AET/PEN там счёт после овертайма.
    if (($home === null || $away === null) && $statusShort === 'FT') {
        $goals = $fixture['goals'] ?? [];
        if (is_array($goals)) {
            if (is_numeric($goals['home'] ?? null)) {
                $home = (int) $goals['home'];
            }
            if (is_numeric($goals['away'] ?? null)) {
                $away = (int) $goals['away'];
            }
        }
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

/**
 * Текущий счёт для отображения live: в овертайме/пенальти — только основное время, если API его уже отдал.
 *
 * @return array{home:int,away:int}|null
 */
function api_football_fixture_contest_score(array $fixture): ?array
{
    $statusShort = strtoupper((string) ($fixture['fixture']['status']['short'] ?? ''));

    if (in_array($statusShort, api_football_finished_statuses(), true)) {
        $ft = api_football_fixture_fulltime_score($fixture);

        return $ft !== null ? ['home' => $ft['home'], 'away' => $ft['away']] : null;
    }

    if (in_array($statusShort, ['ET', 'BT', 'P'], true)) {
        $score = $fixture['score'] ?? [];
        if (is_array($score) && isset($score['fulltime']) && is_array($score['fulltime'])) {
            $ft = $score['fulltime'];
            if (is_numeric($ft['home'] ?? null) && is_numeric($ft['away'] ?? null)) {
                return ['home' => max(0, (int) $ft['home']), 'away' => max(0, (int) $ft['away'])];
            }
        }
    }

    $goals = $fixture['goals'] ?? [];
    if (!is_array($goals) || !is_numeric($goals['home'] ?? null) || !is_numeric($goals['away'] ?? null)) {
        return null;
    }

    return ['home' => max(0, (int) $goals['home']), 'away' => max(0, (int) $goals['away'])];
}

function api_football_set_match_live(int $matchId, array $fixture): void
{
    $contestScore = api_football_fixture_contest_score($fixture);
    if ($contestScore === null) {
        db()->prepare(
            "UPDATE matches SET status = 'live', api_synced_at = NOW(), updated_at = NOW()
             WHERE id = ? AND status IN ('scheduled', 'live')"
        )->execute([$matchId]);

        return;
    }

    db()->prepare(
        "UPDATE matches SET status = 'live', home_score = ?, away_score = ?, api_synced_at = NOW(), updated_at = NOW()
         WHERE id = ? AND status IN ('scheduled', 'live')"
    )->execute([$contestScore['home'], $contestScore['away'], $matchId]);
}

/** Обновить starts_at из API, если FIFA сдвинула время (порог 2 мин). */
function api_football_sync_match_starts_at(int $matchId, array $fixture): bool
{
    $apiStart = api_football_fixture_starts_at($fixture);
    if ($apiStart === null) {
        return false;
    }

    $stmt = db()->prepare('SELECT starts_at FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $currentTs = strtotime((string) $row['starts_at']);
    $apiTs = strtotime($apiStart);
    if ($currentTs === false || $apiTs === false || abs($apiTs - $currentTs) < 120) {
        return false;
    }

    db()->prepare('UPDATE matches SET starts_at = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$apiStart, $matchId]);
    api_football_log(
        $matchId,
        'kickoff_change',
        date('d.m.Y H:i', $currentTs) . ' → ' . date('d.m.Y H:i', $apiTs) . ' МСК'
    );

    return true;
}

/** @return array{errors_24h:int,kickoff_updates_7d:int} */
function api_football_admin_dashboard_stats(): array
{
    try {
        $errors = (int) db()->query(
            "SELECT COUNT(*) FROM api_football_sync_log
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND action IN ('sync_error', 'sync_miss')"
        )->fetchColumn();
        $kickoff = (int) db()->query(
            "SELECT COUNT(*) FROM api_football_sync_log
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND action = 'kickoff_change'"
        )->fetchColumn();

        return ['errors_24h' => $errors, 'kickoff_updates_7d' => $kickoff];
    } catch (Throwable) {
        return ['errors_24h' => 0, 'kickoff_updates_7d' => 0];
    }
}

/**
 * @return array{checked:int,finished:int,live:int,schedule_updated:int,teams_updated:int,corrected:int,errors:int,messages:list<string>}
 */
function run_api_football_sync(): array
{
    $result = [
        'checked' => 0,
        'finished' => 0,
        'live' => 0,
        'schedule_updated' => 0,
        'teams_updated' => 0,
        'corrected' => 0,
        'errors' => 0,
        'messages' => [],
    ];

    if (!api_football_configured()) {
        $result['messages'][] = 'disabled';

        return $result;
    }

    $teamSync = api_football_sync_match_teams_from_api();
    $result['teams_updated'] = (int) $teamSync['teams_updated'];
    if ($teamSync['errors'] !== []) {
        $result['errors'] += count($teamSync['errors']);
        $result['messages'] = array_merge($result['messages'], array_slice($teamSync['errors'], 0, 3));
    }

    api_football_map_fixtures();

    $stmt = db()->query(
        "SELECT id, api_fixture_id, result_source, home_score, away_score, status
         FROM matches
         WHERE api_fixture_id IS NOT NULL
           AND result_source = 'api'
           AND (
                status = 'live'
                OR (home_score IS NULL AND status = 'finished')
                OR (status = 'scheduled' AND starts_at <= DATE_ADD(NOW(), INTERVAL 7 DAY))
                OR (
                    status = 'finished'
                    AND home_score IS NOT NULL
                    AND starts_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                )
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

        if (api_football_sync_match_starts_at($matchId, $fixture)) {
            $result['schedule_updated']++;
        }

        if (in_array($statusShort, api_football_live_statuses(), true)) {
            api_football_set_match_live($matchId, $fixture);
            $result['live']++;
            $displayScore = api_football_fixture_contest_score($fixture);
            $scoreLabel = $displayScore !== null
                ? $displayScore['home'] . ':' . $displayScore['away']
                : (($fixture['goals']['home'] ?? '?') . ':' . ($fixture['goals']['away'] ?? '?'));
            api_football_log(
                $matchId,
                'sync_live',
                $scoreLabel . ' (' . $statusShort . ')'
            );
            continue;
        }

        $score = api_football_fixture_fulltime_score($fixture);
        if ($score === null) {
            continue;
        }

        $currentHome = $row['home_score'] !== null ? (int) $row['home_score'] : null;
        $currentAway = $row['away_score'] !== null ? (int) $row['away_score'] : null;
        $alreadyFinished = $currentHome !== null && $currentAway !== null && ($row['status'] ?? '') === 'finished';

        if ($alreadyFinished && $currentHome === $score['home'] && $currentAway === $score['away']) {
            continue;
        }

        try {
            apply_match_result($matchId, $score['home'], $score['away'], 'api');
            db()->prepare('UPDATE matches SET api_synced_at = NOW() WHERE id = ?')->execute([$matchId]);
            if ($alreadyFinished) {
                $result['corrected']++;
                api_football_log(
                    $matchId,
                    'sync_ft_correct',
                    $currentHome . ':' . $currentAway . ' → ' . $score['home'] . ':' . $score['away'] . ' (' . $statusShort . ')'
                );
            } else {
                $result['finished']++;
                api_football_log($matchId, 'sync_ft', $score['home'] . ':' . $score['away'] . ' (' . $statusShort . ')');
            }
        } catch (Throwable $e) {
            $result['errors']++;
            api_football_log($matchId, 'sync_error', $e->getMessage());
        }
    }

    api_football_warm_predictions_cache();
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

function api_football_db_setting(string $key): ?string
{
    try {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        return $row !== false ? (string) $row['setting_value'] : null;
    } catch (Throwable) {
        return null;
    }
}

function api_football_save_db_setting(string $key, string $value): void
{
    db()->prepare(
        "INSERT INTO settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    )->execute([$key, $value]);
}

function api_football_widgets_enabled(): bool
{
    if (!api_football_configured()) {
        return false;
    }

    $override = api_football_db_setting('site_api_football_widgets');
    if ($override !== null) {
        return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
    }

    return !empty(api_football_settings()['widgets_enabled']);
}

function api_football_set_widgets_enabled(bool $enabled): void
{
    api_football_save_db_setting('site_api_football_widgets', $enabled ? '1' : '0');
}

function api_football_widget_api_key(): string
{
    $widgetKey = trim((string) (api_football_settings()['widget_api_key'] ?? ''));
    if ($widgetKey !== '') {
        return $widgetKey;
    }

    return trim((string) (api_football_settings()['api_key'] ?? ''));
}

function api_football_widgets_cache_seconds(): int
{
    return max(60, (int) (api_football_settings()['widgets_cache_seconds'] ?? 120));
}

/** @return list<array{home: string, away: string, time: string, status: string, score: string}> */
function api_football_cached_today_rows(): array
{
    $path = api_football_widgets_cache_path();
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    /** @var array<string,mixed>|null $payload */
    $payload = json_decode($raw, true);
    if (!is_array($payload) || !is_array($payload['body'] ?? null)) {
        return [];
    }

    $rows = [];
    foreach (api_football_response_list($payload['body']) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $teams = $item['teams'] ?? [];
        $fixture = $item['fixture'] ?? [];
        $status = is_array($fixture['status'] ?? null) ? $fixture['status'] : [];
        $home = (string) (($teams['home']['name'] ?? '') ?: '—');
        $away = (string) (($teams['away']['name'] ?? '') ?: '—');
        $date = (string) ($fixture['date'] ?? '');
        $time = $date !== '' ? date('H:i', strtotime($date)) : '—';
        $short = (string) ($status['short'] ?? '');
        $score = '—';
        $ft = api_football_fixture_fulltime_score($item);
        if ($ft !== null) {
            $score = $ft['home'] . ':' . $ft['away'];
        } elseif (is_array($item['goals'] ?? null)) {
            $gh = $item['goals']['home'];
            $ga = $item['goals']['away'];
            if ($gh !== null && $ga !== null) {
                $score = $gh . ':' . $ga;
            }
        }

        $rows[] = [
            'home' => $home,
            'away' => $away,
            'time' => $time,
            'status' => $short,
            'score' => $score,
        ];
    }

    usort($rows, static fn (array $a, array $b): int => strcmp($a['time'], $b['time']));

    return $rows;
}

function api_football_cached_today_at(): ?string
{
    $path = api_football_widgets_cache_path();
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    /** @var array<string,mixed>|null $payload */
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        return null;
    }

    $cachedAt = $payload['cached_at'] ?? null;

    return is_string($cachedAt) && $cachedAt !== '' ? $cachedAt : null;
}

function api_football_h2h_cache_seconds(): int
{
    return max(3600, (int) (api_football_settings()['h2h_cache_seconds'] ?? 21600));
}

function api_football_h2h_cache_path(int $teamA, int $teamB): string
{
    $lo = min($teamA, $teamB);
    $hi = max($teamA, $teamB);

    return dirname(__DIR__) . '/storage/cache/api_football_h2h_' . $lo . '_' . $hi . '.json';
}

/**
 * История очных встреч двух сборных (кэш на сервере).
 *
 * @return array{
 *   matches: list<array{date: string, competition: string, home: string, away: string, score: string, home_api_id: int, away_api_id: int}>,
 *   summary: array{home_wins: int, away_wins: int, draws: int, total: int},
 *   cached_at: ?string,
 *   error: ?string
 * }
 */
function api_football_match_h2h(int $homeApiTeamId, int $awayApiTeamId, int $last = 8): array
{
    $empty = [
        'matches' => [],
        'summary' => ['home_wins' => 0, 'away_wins' => 0, 'draws' => 0, 'total' => 0],
        'cached_at' => null,
        'error' => null,
    ];

    if (!api_football_configured() || $homeApiTeamId < 1 || $awayApiTeamId < 1) {
        return $empty;
    }

    $last = max(1, min(20, $last));
    $cacheFile = api_football_h2h_cache_path($homeApiTeamId, $awayApiTeamId);
    $ttl = api_football_h2h_cache_seconds();

    if (is_file($cacheFile)) {
        $age = time() - (int) filemtime($cacheFile);
        if ($age < $ttl) {
            $cached = api_football_read_h2h_cache($cacheFile, $homeApiTeamId, $awayApiTeamId);
            if ($cached !== null) {
                return $cached;
            }
        }
    }

    $fetch = api_football_fetch_h2h_body($homeApiTeamId, $awayApiTeamId, $last);
    $body = $fetch['body'] ?? null;
    if ($body === null) {
        $empty['error'] = 'load_failed';

        return $empty;
    }

    $parsed = api_football_parse_h2h_response($body, $homeApiTeamId, $awayApiTeamId);

    if ($parsed['matches'] !== []) {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode([
            'cached_at' => date('c'),
            'home_api_team_id' => $homeApiTeamId,
            'away_api_team_id' => $awayApiTeamId,
            'payload' => $parsed,
        ], JSON_UNESCAPED_UNICODE));
    }

    $parsed['cached_at'] = date('c');
    $parsed['error'] = null;

    return $parsed;
}

/**
 * @return array{body: ?array<string,mixed>, error: ?string}
 */
function api_football_fetch_h2h_body(int $homeApiTeamId, int $awayApiTeamId, int $last): array
{
    $keys = array_values(array_unique([
        $homeApiTeamId . '-' . $awayApiTeamId,
        $awayApiTeamId . '-' . $homeApiTeamId,
        min($homeApiTeamId, $awayApiTeamId) . '-' . max($homeApiTeamId, $awayApiTeamId),
    ]));

    $lastBody = null;
    $bestBody = null;
    $bestCount = 0;
    $lastError = null;

    foreach ($keys as $h2hKey) {
        $queryVariants = [
            ['h2h' => $h2hKey, 'last' => (string) $last],
            ['h2h' => $h2hKey],
        ];

        foreach ($queryVariants as $query) {
            $res = api_football_get('/fixtures/headtohead', $query);
            if (!$res['ok']) {
                $lastError = $res['error'] ?? ('HTTP ' . ($res['status'] ?? 0));
                continue;
            }

            $lastBody = $res['body'];
            $count = count(api_football_response_list($lastBody));
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestBody = $lastBody;
            }
            if ($bestCount > 0) {
                break 2;
            }
        }
    }

    $body = $bestBody ?? $lastBody;

    return [
        'body' => is_array($body) ? $body : null,
        'error' => $body === null ? ($lastError ?? 'Пустой ответ API') : null,
    ];
}

/**
 * @return array{matches: list<array<string,mixed>>, summary: array{home_wins: int, away_wins: int, draws: int, total: int}, cached_at: ?string, error: ?string}|null
 */
function api_football_read_h2h_cache(string $path, int $homeApiTeamId, int $awayApiTeamId): ?array
{
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    /** @var array<string,mixed>|null $data */
    $data = json_decode($raw, true);
    if (!is_array($data) || !is_array($data['payload'] ?? null)) {
        return null;
    }

    /** @var array<string,mixed> $payload */
    $payload = $data['payload'];
    $payload['cached_at'] = is_string($data['cached_at'] ?? null) ? $data['cached_at'] : null;
    $payload['error'] = null;

    return $payload;
}

/**
 * @param array<string,mixed> $body
 * @return array{matches: list<array<string,mixed>>, summary: array{home_wins: int, away_wins: int, draws: int, total: int}}
 */
function api_football_parse_h2h_response(array $body, int $perspectiveHomeApiId, int $perspectiveAwayApiId): array
{
    $matches = [];
    $homeWins = 0;
    $awayWins = 0;
    $draws = 0;

    foreach (api_football_response_list($body) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $row = api_football_h2h_row_from_fixture($item);
        if ($row === null) {
            continue;
        }

        $matches[] = $row;

        $winnerApi = api_football_h2h_winner_api_id($row);
        if ($winnerApi === null) {
            $draws++;
        } elseif ($winnerApi === $perspectiveHomeApiId) {
            $homeWins++;
        } elseif ($winnerApi === $perspectiveAwayApiId) {
            $awayWins++;
        }
    }

    return [
        'matches' => $matches,
        'summary' => [
            'home_wins' => $homeWins,
            'away_wins' => $awayWins,
            'draws' => $draws,
            'total' => count($matches),
        ],
    ];
}

/**
 * @param array<string,mixed> $item
 * @return array{date: string, competition: string, home: string, away: string, score: string, home_api_id: int, away_api_id: int, home_goals: int, away_goals: int}|null
 */
function api_football_h2h_row_from_fixture(array $item): ?array
{
    $statusShort = strtoupper((string) ($item['fixture']['status']['short'] ?? ''));
    if (in_array($statusShort, ['NS', 'TBD', 'PST', 'CANC', 'ABD', 'WO', 'AWD'], true)) {
        return null;
    }

    $homeGoals = null;
    $awayGoals = null;
    $scoreBlock = $item['score'] ?? [];
    if (is_array($scoreBlock) && isset($scoreBlock['fulltime']) && is_array($scoreBlock['fulltime'])) {
        $ft = $scoreBlock['fulltime'];
        if (is_numeric($ft['home'] ?? null) && is_numeric($ft['away'] ?? null)) {
            $homeGoals = (int) $ft['home'];
            $awayGoals = (int) $ft['away'];
        }
    }

    if ($homeGoals === null || $awayGoals === null) {
        $goals = $item['goals'] ?? [];
        if (is_array($goals) && is_numeric($goals['home'] ?? null) && is_numeric($goals['away'] ?? null)) {
            $homeGoals = (int) $goals['home'];
            $awayGoals = (int) $goals['away'];
        }
    }

    if ($homeGoals === null || $awayGoals === null) {
        $ftScore = api_football_fixture_fulltime_score($item);
        if ($ftScore === null) {
            return null;
        }
        $homeGoals = (int) $ftScore['home'];
        $awayGoals = (int) $ftScore['away'];
    }

    $teams = $item['teams'] ?? [];
    $fixture = $item['fixture'] ?? [];
    $league = $item['league'] ?? [];
    if (!is_array($teams) || !is_array($fixture)) {
        return null;
    }

    $homeApiId = (int) (($teams['home']['id'] ?? 0) ?: 0);
    $awayApiId = (int) (($teams['away']['id'] ?? 0) ?: 0);
    $dateRaw = (string) ($fixture['date'] ?? '');

    return [
        'date' => $dateRaw !== '' ? date('d.m.Y', strtotime($dateRaw)) : '—',
        'competition' => trim((string) (($league['name'] ?? '') ?: '')),
        'home' => (string) (($teams['home']['name'] ?? '') ?: '—'),
        'away' => (string) (($teams['away']['name'] ?? '') ?: '—'),
        'score' => $homeGoals . ':' . $awayGoals,
        'home_api_id' => $homeApiId,
        'away_api_id' => $awayApiId,
        'home_goals' => $homeGoals,
        'away_goals' => $awayGoals,
    ];
}

/**
 * @param array{home_api_id: int, away_api_id: int, home_goals: int, away_goals: int} $row
 */
function api_football_h2h_winner_api_id(array $row): ?int
{
    if ($row['home_goals'] > $row['away_goals']) {
        return (int) $row['home_api_id'];
    }
    if ($row['away_goals'] > $row['home_goals']) {
        return (int) $row['away_api_id'];
    }

    return null;
}

function api_football_predictions_enabled(): bool
{
    if (!api_football_configured()) {
        return false;
    }

    $cfg = api_football_settings();

    return !array_key_exists('predictions_enabled', $cfg) || !empty($cfg['predictions_enabled']);
}

function api_football_predictions_cache_seconds(): int
{
    return max(3600, (int) (api_football_settings()['predictions_cache_seconds'] ?? 86400));
}

/** Сколько держать последний удачный индекс, если API временно отдаёт пустой ответ. */
function api_football_predictions_stale_cache_seconds(): int
{
    return 7 * 86400;
}

/** Не дергать /predictions повторно после пустого ответа API. */
function api_football_predictions_negative_cache_seconds(): int
{
    return 6 * 3600;
}

/** Короткий negative cache для «мягких» отказов парсера (данные могут появиться позже). */
function api_football_predictions_soft_negative_cache_seconds(): int
{
    return 30 * 60;
}

function api_football_predictions_is_soft_negative_error(?string $error): bool
{
    return in_array(
        (string) $error,
        ['neutral_placeholder', 'double_chance_placeholder', 'empty_predictions', 'no_content', 'empty_response', 'no_predictions_block'],
        true
    );
}

function api_football_predictions_negative_cache_seconds_for_error(?string $error): int
{
    return api_football_predictions_is_soft_negative_error($error)
        ? api_football_predictions_soft_negative_cache_seconds()
        : api_football_predictions_negative_cache_seconds();
}

/** Минимальный интервал прогрева /predictions из cron. */
function api_football_predictions_warm_interval_seconds(): int
{
    return 3600;
}

function api_football_predictions_advice_is_meaningful(string $advice): bool
{
    $advice = mb_strtolower(trim($advice), 'UTF-8');
    if ($advice === '') {
        return false;
    }

    return !in_array($advice, ['no predictions available', 'no prediction available'], true);
}

/**
 * Реалистичное распределение исходов (Poisson): три ненулевые доли, не 33/33/33 и не 50/50/0.
 *
 * @param array{home: int, draw: int, away: int} $percent
 */
function api_football_predictions_percent_is_realistic(array $percent): bool
{
    $home = (int) $percent['home'];
    $draw = (int) $percent['draw'];
    $away = (int) $percent['away'];
    $sum = $home + $draw + $away;

    if ($sum < 95 || $sum > 105) {
        return false;
    }

    // Любой нулевой исход — double chance, не реальное распределение 1/X/2.
    if ($home === 0 || $draw === 0 || $away === 0) {
        return false;
    }

    $spread = max($home, $draw, $away) - min($home, $draw, $away);

    return $spread > 2;
}

/**
 * @param array{home: int, draw: int, away: int} $percent
 * @param array{home: int, away: int} $comparisonTotal
 */
function api_football_predictions_has_signal(
    array $percent,
    array $comparisonTotal,
    bool $hasExplicitWinner,
    string $advice,
    ?string $underOverLabel
): bool {
    unset($comparisonTotal, $hasExplicitWinner, $advice, $underOverLabel);

    return api_football_predictions_percent_is_realistic($percent);
}

/**
 * @param array<string,mixed> $payload
 */
function api_football_predictions_cached_payload_is_usable(array $payload): bool
{
    if (empty($payload['available'])) {
        return false;
    }

    $percent = $payload['percent'] ?? [];
    if (!is_array($percent)) {
        return false;
    }

    $comparison = $payload['comparison_total'] ?? [];
    if (!is_array($comparison)) {
        $comparison = [];
    }

    $winnerName = trim((string) ($payload['winner_name'] ?? ''));
    $winnerSide = $payload['winner_side'] ?? null;

    return api_football_predictions_has_signal(
        [
            'home' => (int) ($percent['home'] ?? 0),
            'draw' => (int) ($percent['draw'] ?? 0),
            'away' => (int) ($percent['away'] ?? 0),
        ],
        [
            'home' => (int) ($comparison['home'] ?? 0),
            'away' => (int) ($comparison['away'] ?? 0),
        ],
        $winnerName !== '' || ($winnerSide !== null && $winnerSide !== ''),
        (string) ($payload['advice'] ?? ''),
        isset($payload['under_over_label']) && $payload['under_over_label'] !== null && $payload['under_over_label'] !== ''
            ? (string) $payload['under_over_label']
            : null
    );
}

function api_football_predictions_cache_path(int $apiFixtureId): string
{
    return dirname(__DIR__) . '/storage/cache/api_football_predictions_' . $apiFixtureId . '.json';
}

function api_football_predictions_percent_int($raw): int
{
    if (is_numeric($raw)) {
        return max(0, min(100, (int) round((float) $raw)));
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return 0;
    }

    if (preg_match('/-?\d+(?:\.\d+)?/', $text, $m) !== 1) {
        return 0;
    }

    return max(0, min(100, (int) round((float) $m[0])));
}

function api_football_predictions_under_over_label_ru(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^([+-]?)(\d+(?:\.\d+)?)$/', $raw, $m) !== 1) {
        return null;
    }

    $sign = $m[1];
    $value = (float) $m[2];
    if ($sign === '-') {
        $cap = (int) floor($value);

        return $cap <= 1
            ? 'ожидается не более 1 гола в матче'
            : 'ожидается не более ' . $cap . ' голов в матче';
    }

    if ($sign === '+') {
        return 'ожидается больше ' . rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . ' голов в матче';
    }

    return null;
}

/**
 * @param array<string,mixed> $item
 * @return array<string,mixed>
 */
function api_football_parse_predictions_item(array $item, string $homeTeamName, string $awayTeamName): array
{
    $empty = [
        'available' => false,
        'percent' => ['home' => 0, 'draw' => 0, 'away' => 0],
        'winner_name' => '',
        'winner_side' => null,
        'win_or_draw' => false,
        'under_over' => null,
        'under_over_label' => null,
        'comparison_total' => ['home' => 0, 'away' => 0],
        'summary' => '',
        'advice' => '',
        'cached_at' => null,
        'error' => null,
    ];

    $predictions = $item['predictions'] ?? [];
    if (!is_array($predictions)) {
        $empty['error'] = 'no_predictions_block';

        return $empty;
    }

    $teams = $item['teams'] ?? [];
    $homeApiId = is_array($teams) ? (int) (($teams['home']['id'] ?? 0) ?: 0) : 0;
    $awayApiId = is_array($teams) ? (int) (($teams['away']['id'] ?? 0) ?: 0) : 0;

    $percentRaw = $predictions['percent'] ?? [];
    $percent = [
        'home' => api_football_predictions_percent_int(is_array($percentRaw) ? ($percentRaw['home'] ?? 0) : 0),
        'draw' => api_football_predictions_percent_int(is_array($percentRaw) ? ($percentRaw['draw'] ?? 0) : 0),
        'away' => api_football_predictions_percent_int(is_array($percentRaw) ? ($percentRaw['away'] ?? 0) : 0),
    ];
    $percentSum = $percent['home'] + $percent['draw'] + $percent['away'];
    $percentSpread = max($percent) - min($percent);
    if ($percentSum > 0 && $percentSum !== 100) {
        $fix = 100 - $percentSum;
        $keys = ['home' => $percent['home'], 'draw' => $percent['draw'], 'away' => $percent['away']];
        arsort($keys);
        $maxKey = array_key_first($keys);
        if (is_string($maxKey) && array_key_exists($maxKey, $percent)) {
            $percent[$maxKey] += $fix;
        }
    }

    $winner = $predictions['winner'] ?? [];
    $winnerName = is_array($winner) ? trim((string) ($winner['name'] ?? '')) : '';
    $winnerId = is_array($winner) ? (int) (($winner['id'] ?? 0) ?: 0) : 0;
    $hasExplicitWinner = $winnerName !== '' || $winnerId > 0;
    $winnerSide = null;
    if ($winnerId > 0 && $winnerId === $homeApiId) {
        $winnerSide = 'home';
    } elseif ($winnerId > 0 && $winnerId === $awayApiId) {
        $winnerSide = 'away';
    } elseif ($percentSpread > 2 && $percent['draw'] >= $percent['home'] && $percent['draw'] >= $percent['away'] && $percent['draw'] > 0) {
        $winnerSide = 'draw';
    } elseif ($percentSpread > 2 && $percent['home'] >= $percent['away'] && $percent['home'] > 0) {
        $winnerSide = 'home';
    } elseif ($percentSpread > 2 && $percent['away'] > 0) {
        $winnerSide = 'away';
    }

    if ($winnerName === '' && $winnerSide === 'home') {
        $winnerName = $homeTeamName;
    } elseif ($winnerName === '' && $winnerSide === 'away') {
        $winnerName = $awayTeamName;
    } elseif ($winnerName === '' && $winnerSide === 'draw') {
        $winnerName = 'ничья';
    }

    $comparison = $item['comparison'] ?? [];
    $totalRaw = is_array($comparison) ? ($comparison['total'] ?? []) : [];
    $comparisonTotal = [
        'home' => api_football_predictions_percent_int(is_array($totalRaw) ? ($totalRaw['home'] ?? 0) : 0),
        'away' => api_football_predictions_percent_int(is_array($totalRaw) ? ($totalRaw['away'] ?? 0) : 0),
    ];

    $underOver = trim((string) ($predictions['under_over'] ?? ''));
    $underOverLabel = api_football_predictions_under_over_label_ru($underOver !== '' ? $underOver : null);
    $advice = trim((string) ($predictions['advice'] ?? ''));
    $winOrDraw = !empty($predictions['win_or_draw']);

    $hasSignal = api_football_predictions_has_signal(
        $percent,
        $comparisonTotal,
        $hasExplicitWinner,
        $advice,
        $underOverLabel
    );
    if (!$hasSignal) {
        $home = (int) $percent['home'];
        $draw = (int) $percent['draw'];
        $away = (int) $percent['away'];
        $hasZeroOutcome = $home === 0 || $draw === 0 || $away === 0;
        $empty['error'] = $hasZeroOutcome ? 'double_chance_placeholder' : 'neutral_placeholder';

        return $empty;
    }

    $summary = api_football_predictions_summary_ru(
        $homeTeamName,
        $awayTeamName,
        $percent,
        $winnerName,
        $winnerSide,
        $winOrDraw,
        $comparisonTotal,
        $underOverLabel
    );

    return [
        'available' => true,
        'percent' => $percent,
        'winner_name' => $winnerName,
        'winner_side' => $winnerSide,
        'win_or_draw' => $winOrDraw,
        'under_over' => $underOver !== '' ? $underOver : null,
        'under_over_label' => $underOverLabel,
        'comparison_total' => $comparisonTotal,
        'summary' => $summary,
        'advice' => $advice,
        'cached_at' => null,
        'error' => null,
    ];
}

/**
 * @param array{home: int, draw: int, away: int} $percent
 * @param array{home: int, away: int} $comparisonTotal
 */
function api_football_predictions_summary_ru(
    string $homeTeamName,
    string $awayTeamName,
    array $percent,
    string $winnerName,
    ?string $winnerSide,
    bool $winOrDraw,
    array $comparisonTotal,
    ?string $underOverLabel
): string {
    $parts = [];

    if ($winnerName !== '' && $winnerSide !== 'draw') {
        $favLine = 'По статистической модели фаворитом выглядит ' . $winnerName;
        if ($winOrDraw) {
            $favLine .= ' (модель допускает победу или ничью)';
        }
        $parts[] = $favLine . '.';
    } elseif ($winnerSide === 'draw' || ($percent['draw'] >= $percent['home'] && $percent['draw'] >= $percent['away'] && $percent['draw'] > 0)) {
        $parts[] = 'По статистической модели наиболее вероятен исход «ничья».';
    }

    if (($percent['home'] + $percent['draw'] + $percent['away']) > 0) {
        $parts[] = 'Вероятности исхода: ' . $homeTeamName . ' — ' . $percent['home'] . '%, ничья — ' . $percent['draw'] . '%, ' . $awayTeamName . ' — ' . $percent['away'] . '%.';
    }

    if ($comparisonTotal['home'] > 0 || $comparisonTotal['away'] > 0) {
        $parts[] = 'Сводное сравнение сил: ' . $homeTeamName . ' ' . $comparisonTotal['home'] . '% — ' . $awayTeamName . ' ' . $comparisonTotal['away'] . '%.';
    }

    if ($underOverLabel !== null) {
        $parts[] = ucfirst($underOverLabel) . '.';
    }

    return trim(implode(' ', $parts));
}

/**
 * @return array<string,mixed>
 */
function api_football_read_predictions_cache(string $path, bool $allowStale = false): ?array
{
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    /** @var array<string,mixed>|null $data */
    $data = json_decode($raw, true);
    if (!is_array($data) || !is_array($data['payload'] ?? null)) {
        return null;
    }

    $version = (int) ($data['version'] ?? 1);
    if ($version !== API_FOOTBALL_PREDICTIONS_CACHE_VERSION && !$allowStale) {
        return null;
    }

    /** @var array<string,mixed> $payload */
    $payload = $data['payload'];
    if (!api_football_predictions_cached_payload_is_usable($payload)) {
        return null;
    }

    $payload['cached_at'] = is_string($data['cached_at'] ?? null) ? $data['cached_at'] : null;
    $payload['error'] = null;

    return $payload;
}

function api_football_predictions_cache_fallback(string $cacheFile, int $maxAge): ?array
{
    if (!is_file($cacheFile)) {
        return null;
    }

    $age = time() - (int) filemtime($cacheFile);
    if ($age > $maxAge) {
        return null;
    }

    return api_football_read_predictions_cache($cacheFile, true);
}

/** @return array<string,mixed>|null */
function api_football_read_predictions_cache_raw(string $path): ?array
{
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    /** @var array<string,mixed>|null $data */
    $data = json_decode($raw, true);
    if (!is_array($data) || !is_array($data['payload'] ?? null)) {
        return null;
    }

    /** @var array<string,mixed> $payload */
    $payload = $data['payload'];
    $payload['cached_at'] = is_string($data['cached_at'] ?? null) ? $data['cached_at'] : null;

    return $payload;
}

function api_football_save_predictions_cache(int $apiFixtureId, array $payload): void
{
    $cacheFile = api_football_predictions_cache_path($apiFixtureId);
    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $payload['cached_at'] = date('c');
    file_put_contents($cacheFile, json_encode([
        'version' => API_FOOTBALL_PREDICTIONS_CACHE_VERSION,
        'cached_at' => $payload['cached_at'],
        'api_fixture_id' => $apiFixtureId,
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE));
}

/**
 * Статистический прогноз API-Football для матча (кэш на сервере).
 *
 * @return array{
 *   available: bool,
 *   percent: array{home: int, draw: int, away: int},
 *   winner_name: string,
 *   winner_side: ?string,
 *   win_or_draw: bool,
 *   under_over: ?string,
 *   under_over_label: ?string,
 *   comparison_total: array{home: int, away: int},
 *   summary: string,
 *   advice: string,
 *   cached_at: ?string,
 *   error: ?string
 * }
 */
function api_football_match_predictions(int $apiFixtureId, string $homeTeamName, string $awayTeamName): array
{
    $empty = [
        'available' => false,
        'percent' => ['home' => 0, 'draw' => 0, 'away' => 0],
        'winner_name' => '',
        'winner_side' => null,
        'win_or_draw' => false,
        'under_over' => null,
        'under_over_label' => null,
        'comparison_total' => ['home' => 0, 'away' => 0],
        'summary' => '',
        'advice' => '',
        'cached_at' => null,
        'error' => null,
    ];

    if (!api_football_predictions_enabled() || $apiFixtureId < 1) {
        return $empty;
    }

    $cacheFile = api_football_predictions_cache_path($apiFixtureId);
    $ttl = api_football_predictions_cache_seconds();
    $staleTtl = api_football_predictions_stale_cache_seconds();

    if (is_file($cacheFile)) {
        $age = time() - (int) filemtime($cacheFile);
        $cacheRawJson = file_get_contents($cacheFile);
        $cacheEnvelope = is_string($cacheRawJson) ? json_decode($cacheRawJson, true) : null;
        if (is_array($cacheEnvelope)
            && (int) ($cacheEnvelope['version'] ?? 0) < API_FOOTBALL_PREDICTIONS_CACHE_VERSION) {
            $payload = is_array($cacheEnvelope['payload'] ?? null) ? $cacheEnvelope['payload'] : [];
            if (empty($payload['available'])
                && api_football_predictions_is_soft_negative_error((string) ($payload['error'] ?? ''))) {
                @unlink($cacheFile);
            }
        }

        $rawCached = api_football_read_predictions_cache_raw($cacheFile);
        if ($rawCached !== null) {
            if (!empty($rawCached['available'])) {
                if ($age < $ttl) {
                    $cached = api_football_read_predictions_cache($cacheFile, true);
                    if ($cached !== null) {
                        return $cached;
                    }
                }
            } else {
                $negativeTtl = api_football_predictions_negative_cache_seconds_for_error(
                    isset($rawCached['error']) ? (string) $rawCached['error'] : null
                );
                if ($age < $negativeTtl) {
                    return $rawCached;
                }
            }
        }
    }

    $staleCached = api_football_predictions_cache_fallback($cacheFile, $staleTtl);

    $res = api_football_get('/predictions', ['fixture' => (string) $apiFixtureId]);
    if (!$res['ok']) {
        $empty['error'] = $res['error'] ?? ('HTTP ' . ($res['status'] ?? 0));
        $result = $staleCached ?? $empty;
        if ($staleCached === null) {
            api_football_save_predictions_cache($apiFixtureId, $result);
        }

        return $result;
    }

    if ((int) ($res['status'] ?? 0) === 204) {
        $empty['error'] = 'no_content';
        $result = $staleCached ?? $empty;
        if ($staleCached === null) {
            api_football_save_predictions_cache($apiFixtureId, $result);
        }

        return $result;
    }

    $items = api_football_response_list($res['body']);
    if ($items === [] || !is_array($items[0] ?? null)) {
        $empty['error'] = 'empty_response';
        $result = $staleCached ?? $empty;
        if ($staleCached === null) {
            api_football_save_predictions_cache($apiFixtureId, $result);
        }

        return $result;
    }

    /** @var array<string,mixed> $item */
    $item = $items[0];
    $parsed = api_football_parse_predictions_item($item, $homeTeamName, $awayTeamName);
    if (empty($parsed['available'])) {
        $result = $staleCached ?? $parsed;
        if ($staleCached === null) {
            api_football_save_predictions_cache($apiFixtureId, $result);
        }

        return $result;
    }

    api_football_save_predictions_cache($apiFixtureId, $parsed);

    return $parsed;
}

/** Удалить устаревшие и нереалистичные кэши predictions. */
function api_football_purge_soft_prediction_negative_caches(): int
{
    $dir = dirname(__DIR__) . '/storage/cache';
    $removed = 0;
    foreach (glob($dir . '/api_football_predictions_*.json') ?: [] as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = api_football_read_predictions_cache_raw($file);
        if ($raw === null) {
            continue;
        }

        $shouldRemove = false;
        if (!empty($raw['available'])) {
            $percent = is_array($raw['percent'] ?? null) ? $raw['percent'] : [];
            $shouldRemove = !api_football_predictions_percent_is_realistic([
                'home' => (int) ($percent['home'] ?? 0),
                'draw' => (int) ($percent['draw'] ?? 0),
                'away' => (int) ($percent['away'] ?? 0),
            ]);
        } elseif (api_football_predictions_is_soft_negative_error((string) ($raw['error'] ?? ''))) {
            $shouldRemove = true;
        }

        if ($shouldRemove && @unlink($file)) {
            $removed++;
        }
    }

    return $removed;
}

/** Прогрев кэша предматчевого индекса для ближайших матчей с api_fixture_id. */
function api_football_warm_predictions_cache(int $daysAhead = 21): void
{
    if (!api_football_predictions_enabled()) {
        return;
    }

    api_football_purge_soft_prediction_negative_caches();

    $warmStamp = dirname(__DIR__) . '/storage/cache/api_football_predictions_warm_at.txt';
    $interval = api_football_predictions_warm_interval_seconds();
    if (is_file($warmStamp) && time() - (int) filemtime($warmStamp) < $interval) {
        return;
    }
    @touch($warmStamp);

    $stmt = db()->prepare(
        "SELECT m.id, m.api_fixture_id, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.api_fixture_id IS NOT NULL
           AND m.home_score IS NULL
           AND m.status IN ('scheduled', 'live')
           AND m.starts_at > NOW()
           AND m.starts_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
         ORDER BY m.starts_at ASC
         LIMIT 15"
    );
    $stmt->execute([max(1, $daysAhead)]);

    foreach ($stmt->fetchAll() as $row) {
        $fixtureId = (int) ($row['api_fixture_id'] ?? 0);
        if ($fixtureId < 1) {
            continue;
        }

        api_football_match_predictions(
            $fixtureId,
            (string) $row['home_team'],
            (string) $row['away_team']
        );
    }
}
