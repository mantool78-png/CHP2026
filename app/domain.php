<?php

declare(strict_types=1);

require_once __DIR__ . '/worldcup2026_groups.php';

/** Имя, фамилия или ник для таблицы — без лишних пробелов. */
function normalize_participant_display_name(string $raw): string
{
    $name = trim($raw);
    if ($name === '') {
        return '';
    }
    $collapsed = preg_replace('/\s+/u', ' ', $name);

    return is_string($collapsed) ? trim($collapsed) : $name;
}

/** Участник с таким отображаемым именем уже есть (без учёта регистра). */
function participant_display_name_taken(string $name, ?int $exceptUserId = null): bool
{
    $normalized = normalize_participant_display_name($name);
    if ($normalized === '') {
        return false;
    }
    $needle = mb_strtolower($normalized, 'UTF-8');

    $sql = 'SELECT id, name FROM users WHERE role = ?';
    $params = ['participant'];
    if ($exceptUserId !== null && $exceptUserId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $exceptUserId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $existing = mb_strtolower(normalize_participant_display_name((string) $row['name']), 'UTF-8');
        if ($existing === $needle) {
            return true;
        }
    }

    return false;
}

function upcoming_matches(): array
{
    $now = time();
    $rows = [];

    foreach (matches_schedule_rows() as $match) {
        if (match_is_finished_for_schedule($match)) {
            continue;
        }

        $status = (string) ($match['status'] ?? 'scheduled');
        if ($status === 'live') {
            $rows[] = $match;
            continue;
        }

        $startsAt = (string) ($match['starts_at'] ?? '');
        if ($startsAt !== '' && strtotime($startsAt) > $now) {
            $rows[] = $match;
        }
    }

    return $rows;
}

/** @return list<array<string,mixed>> */
function matches_schedule_rows(): array
{
    $stmt = db()->query(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team, ht.code AS home_code, at.code AS away_code
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.home_team_id IS NOT NULL AND m.away_team_id IS NOT NULL
         ORDER BY m.starts_at ASC"
    );

    return $stmt->fetchAll();
}

function matches_schedule_filter_key(?string $raw): string
{
    $key = strtolower(trim((string) $raw));

    return in_array($key, ['today', 'soon', 'results'], true) ? $key : 'soon';
}

function match_starts_at_msk_date(array $match): string
{
    $startsAt = (string) ($match['starts_at'] ?? '');
    if ($startsAt === '') {
        return '';
    }

    return (new DateTimeImmutable($startsAt, new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
}

function match_is_finished_for_schedule(array $match): bool
{
    $status = (string) ($match['status'] ?? 'scheduled');
    if ($status === 'finished') {
        return true;
    }
    if ($status === 'live') {
        return false;
    }

    return $match['home_score'] !== null && $match['away_score'] !== null;
}

/**
 * @return array{today: list<array<string,mixed>>, soon: list<array<string,mixed>>, results: list<array<string,mixed>>}
 */
function matches_schedule_grouped(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $todayMsk = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
    $today = [];
    $soon = [];
    $results = [];

    foreach (matches_schedule_rows() as $match) {
        $status = (string) ($match['status'] ?? 'scheduled');
        $matchDay = match_starts_at_msk_date($match);
        $finished = match_is_finished_for_schedule($match);

        if ($finished) {
            $results[] = $match;
            continue;
        }

        if ($status === 'live' || $matchDay === $todayMsk) {
            $today[] = $match;
        }

        if ($status !== 'live' && $matchDay !== '' && $matchDay > $todayMsk) {
            $soon[] = $match;
        } elseif ($status !== 'live' && $matchDay === '') {
            $soon[] = $match;
        }
    }

    usort(
        $results,
        static fn(array $a, array $b): int => strtotime((string) $b['starts_at']) <=> strtotime((string) $a['starts_at'])
    );

    $cache = ['today' => $today, 'soon' => $soon, 'results' => $results];

    return $cache;
}

/** @return list<array<string,mixed>> */
function matches_for_schedule(string $filter): array
{
    $filter = matches_schedule_filter_key($filter);
    $grouped = matches_schedule_grouped();

    return $grouped[$filter] ?? [];
}

/**
 * @return list<array{key: string, label: string, count: int}>
 */
function matches_schedule_filter_tabs(): array
{
    $grouped = matches_schedule_grouped();

    return [
        ['key' => 'today', 'label' => 'Сегодня', 'count' => count($grouped['today'])],
        ['key' => 'soon', 'label' => 'Скоро', 'count' => count($grouped['soon'])],
        ['key' => 'results', 'label' => 'Результаты', 'count' => count($grouped['results'])],
    ];
}

function matches_schedule_empty_message(string $filter): string
{
    return match (matches_schedule_filter_key($filter)) {
        'today' => 'Сегодня по расписанию матчей нет. Посмотрите вкладку «Скоро».',
        'results' => 'Завершённых матчей пока нет — результаты появятся после игр.',
        default => 'Все матчи турнира уже сыграны или расписание ещё не заполнено.',
    };
}

/**
 * Блок «LIVE / сегодня / ближайшие» для главной (данные сайта, без виджетов).
 *
 * @return array{live: list<array<string,mixed>>, today: list<array<string,mixed>>, upcoming: list<array<string,mixed>>}
 */
function home_schedule_highlights(int $liveLimit = 6, int $todayLimit = 8, int $upcomingLimit = 8): array
{
    $stmt = db()->query(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team, ht.code AS home_code, at.code AS away_code
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.home_team_id IS NOT NULL AND m.away_team_id IS NOT NULL
         ORDER BY m.starts_at ASC"
    );

    $todayMsk = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
    $now = time();
    $live = [];
    $today = [];
    $upcoming = [];

    foreach ($stmt->fetchAll() as $match) {
        if (match_is_finished_for_schedule($match)) {
            continue;
        }

        $status = (string) ($match['status'] ?? 'scheduled');
        $startsAt = (string) ($match['starts_at'] ?? '');
        $matchDay = $startsAt !== '' ? date('Y-m-d', strtotime($startsAt)) : '';

        if ($status === 'live') {
            if (count($live) < $liveLimit) {
                $live[] = $match;
            }
            continue;
        }

        if ($matchDay === $todayMsk && count($today) < $todayLimit) {
            $today[] = $match;
            continue;
        }

        if ($startsAt !== '' && strtotime($startsAt) > $now && count($upcoming) < $upcomingLimit) {
            $upcoming[] = $match;
        }
    }

    return ['live' => $live, 'today' => $today, 'upcoming' => $upcoming];
}

function find_match(int $id): ?array
{
    ensure_worldcup2026_teams_in_db();

    $stmt = db()->prepare(
        "SELECT m.*,
                ht.name AS home_team,
                at.name AS away_team,
                ht.code AS home_code,
                at.code AS away_code,
                ht.fifa_rank AS home_fifa_rank,
                ht.brief_note AS home_brief_note,
                ht.form_last5 AS home_form_last5,
                at.fifa_rank AS away_fifa_rank,
                at.brief_note AS away_brief_note,
                at.form_last5 AS away_form_last5,
                ht.api_team_id AS home_api_team_id,
                at.api_team_id AS away_api_team_id
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.id = ?
           AND m.home_team_id IS NOT NULL
           AND m.away_team_id IS NOT NULL
         LIMIT 1"
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** Шаблон стадии для матчей группового раунда (как в фильтрах кабинета). */
function group_stage_like_pattern(): string
{
    return 'Групповой этап%';
}

/**
 * Турнирные таблицы по группам A–L на основе завершённых матчей группового этапа.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function get_group_standings(): array
{
    ensure_worldcup2026_teams_in_db();

    $stmt = db()->prepare(
        "SELECT
            t.id,
            t.name,
            t.code,
            COALESCE(COUNT(m.id), 0) AS played,
            COALESCE(SUM(CASE
                WHEN m.home_team_id = t.id AND m.home_score > m.away_score THEN 1
                WHEN m.away_team_id = t.id AND m.away_score > m.home_score THEN 1
                ELSE 0 END), 0) AS won,
            COALESCE(SUM(CASE WHEN m.home_score = m.away_score AND m.home_score IS NOT NULL THEN 1 ELSE 0 END), 0) AS drawn,
            COALESCE(SUM(CASE
                WHEN m.home_team_id = t.id AND m.home_score < m.away_score THEN 1
                WHEN m.away_team_id = t.id AND m.away_score < m.home_score THEN 1
                ELSE 0 END), 0) AS lost,
            COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score WHEN m.away_team_id = t.id THEN m.away_score END), 0) AS goals_for,
            COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score WHEN m.away_team_id = t.id THEN m.home_score END), 0) AS goals_against
         FROM teams t
         LEFT JOIN matches m
            ON (m.home_team_id = t.id OR m.away_team_id = t.id)
            AND m.status = 'finished'
            AND m.home_team_id IS NOT NULL
            AND m.away_team_id IS NOT NULL
            AND m.stage LIKE ?
         GROUP BY t.id, t.name, t.code"
    );
    $stmt->execute([group_stage_like_pattern()]);
    $rows = $stmt->fetchAll();

    $statsByCode = [];
    foreach ($rows as $row) {
        $code = $row['code'] !== null && (string) $row['code'] !== '' ? strtoupper((string) $row['code']) : null;
        $resolved = worldcup2026_team_code($code, (string) $row['name']);
        if ($resolved === null) {
            continue;
        }

        $gf = (int) $row['goals_for'];
        $ga = (int) $row['goals_against'];
        $won = (int) $row['won'];
        $drawn = (int) $row['drawn'];
        $lost = (int) $row['lost'];
        $played = (int) $row['played'];
        $statsByCode[$resolved] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'played' => $played,
            'won' => $won,
            'drawn' => $drawn,
            'lost' => $lost,
            'goals_for' => $gf,
            'goals_against' => $ga,
            'goal_diff' => $gf - $ga,
            'points' => $won * 3 + $drawn,
        ];
    }

    $byGroup = [];
    foreach (WORLD_CUP_2026_TEAMS as $teamCode => $meta) {
        $stat = $statsByCode[$teamCode] ?? null;
        $byGroup[$meta['group']][] = [
            'id' => $stat['id'] ?? 0,
            'name' => $stat['name'] ?? $meta['name_ru'],
            'code' => $teamCode,
            'group' => $meta['group'],
            'played' => $stat['played'] ?? 0,
            'won' => $stat['won'] ?? 0,
            'drawn' => $stat['drawn'] ?? 0,
            'lost' => $stat['lost'] ?? 0,
            'goals_for' => $stat['goals_for'] ?? 0,
            'goals_against' => $stat['goals_against'] ?? 0,
            'goal_diff' => $stat['goal_diff'] ?? 0,
            'points' => $stat['points'] ?? 0,
        ];
    }

    $cmp = static function (array $a, array $b): int {
        if ($a['points'] !== $b['points']) {
            return $b['points'] <=> $a['points'];
        }
        if ($a['goal_diff'] !== $b['goal_diff']) {
            return $b['goal_diff'] <=> $a['goal_diff'];
        }
        if ($a['goals_for'] !== $b['goals_for']) {
            return $b['goals_for'] <=> $a['goals_for'];
        }

        return strcasecmp($a['name'], $b['name']);
    };

    foreach ($byGroup as $g => $list) {
        usort($byGroup[$g], $cmp);
        $rank = 1;
        foreach ($byGroup[$g] as $i => $_) {
            $byGroup[$g][$i]['rank'] = $rank;
            $rank++;
        }
    }

    ksort($byGroup);

    return $byGroup;
}

/** Матчи не ниже плей-офф (всё, что не групповой этап). */
function tournament_playoff_matches(): array
{
    $stmt = db()->query(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team, ht.code AS home_code, at.code AS away_code
         FROM matches m
         LEFT JOIN teams ht ON ht.id = m.home_team_id
         LEFT JOIN teams at ON at.id = m.away_team_id
         WHERE m.stage NOT LIKE 'Групповой этап%'
         ORDER BY m.starts_at ASC, m.bracket_code ASC, m.id ASC"
    );

    return $stmt->fetchAll();
}

function match_slot_home_label(array $match): string
{
    if (!empty($match['home_team'])) {
        return (string) $match['home_team'];
    }
    $ph = trim((string) ($match['placeholder_home'] ?? ''));

    return $ph !== '' ? $ph : '—';
}

function match_slot_away_label(array $match): string
{
    if (!empty($match['away_team'])) {
        return (string) $match['away_team'];
    }
    $ph = trim((string) ($match['placeholder_away'] ?? ''));

    return $ph !== '' ? $ph : '—';
}

function match_slot_has_teams(array $match): bool
{
    return !empty($match['home_team_id']) && !empty($match['away_team_id']);
}

/** Числовой порядок стадий плей-офф для вкладки «Сетка». */
function playoff_stage_order_key(string $stage): int
{
    $stage = trim($stage);
    $rules = [
        '1/16 финала' => 10,
        '1/8 финала' => 20,
        'Четвертьфинал' => 30,
        'Полуфинал' => 40,
        'Матч за 3 место' => 45,
        'Финал' => 50,
    ];
    foreach ($rules as $prefix => $key) {
        if (str_starts_with($stage, $prefix)) {
            return $key;
        }
    }

    return 100;
}

/**
 * Матчи плей-офф, сгруппированные по стадии (в логическом порядке раундов).
 *
 * @param list<array<string, mixed>> $matches
 * @return list<array{stage: string, matches: list}>
 */
function tournament_playoff_by_stage(array $matches): array
{
    $buckets = [];
    foreach ($matches as $m) {
        $st = (string) ($m['stage'] ?? '');
        if (!isset($buckets[$st])) {
            $buckets[$st] = [
                'stage' => $st,
                'order' => playoff_stage_order_key($st),
                'matches' => [],
            ];
        }
        $buckets[$st]['matches'][] = $m;
    }

    uasort($buckets, static function (array $a, array $b): int {
        if ($a['order'] !== $b['order']) {
            return $a['order'] <=> $b['order'];
        }

        return strcasecmp($a['stage'], $b['stage']);
    });

    return array_values($buckets);
}

/**
 * Агрегат прогнозов по матчу: исходы (П1/ничья/П2), число прогнозов, самый частый счёт.
 *
 * @return array{total:int,home:int,draw:int,away:int,top_score:?string,top_score_count:int}
 */
function match_prediction_distribution(int $matchId): array
{
    $stmt = db()->prepare(
        'SELECT home_score, away_score, COUNT(*) AS cnt
         FROM predictions
         WHERE match_id = ?
         GROUP BY home_score, away_score'
    );
    $stmt->execute([$matchId]);
    $rows = $stmt->fetchAll();

    $stats = [
        'total' => 0,
        'home' => 0,
        'draw' => 0,
        'away' => 0,
        'top_score' => null,
        'top_score_count' => 0,
    ];

    $bestKey = null;
    $bestCnt = 0;
    foreach ($rows as $row) {
        $cnt = (int) $row['cnt'];
        $stats['total'] += $cnt;
        $outcome = match_outcome((int) $row['home_score'], (int) $row['away_score']);
        $stats[$outcome] += $cnt;
        if ($cnt > $bestCnt) {
            $bestCnt = $cnt;
            $bestKey = (int) $row['home_score'] . ':' . (int) $row['away_score'];
        }
    }

    if ($bestKey !== null) {
        $stats['top_score'] = $bestKey;
        $stats['top_score_count'] = $bestCnt;
    }

    return $stats;
}

function match_status_label(string $status): string
{
    return match ($status) {
        'live' => 'Идёт матч',
        'finished' => 'Завершён',
        default => 'Запланирован',
    };
}

function match_live_pill_label(): string
{
    return 'Идёт';
}

function match_reference_competition_ru(string $name): string
{
    $trimmed = trim($name);
    if ($trimmed === '') {
        return '';
    }

    $map = [
        'World Cup' => 'Чемпионат мира',
        'Friendlies' => 'Товарищеский матч',
        'Friendly' => 'Товарищеский матч',
        'UEFA Euro' => 'Чемпионат Европы',
        'UEFA Nations League' => 'Лига наций УЕФА',
        'Copa America' => 'Кубок Америки',
        'AFC Asian Cup' => 'Кубок Азии',
        'Africa Cup of Nations' => 'Кубок африканских наций',
    ];

    return $map[$trimmed] ?? $trimmed;
}

function team_name_by_api_team_id(int $apiTeamId): ?string
{
    if ($apiTeamId < 1) {
        return null;
    }

    static $cache = null;
    if (!is_array($cache)) {
        $cache = [];
        $stmt = db()->query('SELECT api_team_id, name FROM teams WHERE api_team_id IS NOT NULL');
        foreach ($stmt->fetchAll() as $row) {
            $cache[(int) $row['api_team_id']] = (string) $row['name'];
        }
    }

    return $cache[$apiTeamId] ?? null;
}

function match_form_badges_html(string $formString): string
{
    if ($formString === '') {
        return '<span class="form-badge form-badge--empty">Нет данных</span>';
    }

    $html = '<div class="form-badges-row">';
    $chars = preg_split('//u', $formString, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char) {
        $char = mb_strtoupper($char, 'UTF-8');
        if ($char === 'В' || $char === 'W') {
            $html .= '<span class="form-badge form-badge--win" title="Победа">В</span>';
        } elseif ($char === 'Н' || $char === 'D') {
            $html .= '<span class="form-badge form-badge--draw" title="Ничья">Н</span>';
        } elseif ($char === 'П' || $char === 'L') {
            $html .= '<span class="form-badge form-badge--loss" title="Поражение">П</span>';
        } else {
            $html .= '<span class="form-badge form-badge--unknown">' . h($char) . '</span>';
        }
    }
    $html .= '</div>';

    return $html;
}

/**
 * Статистика команды на текущем турнире до указанного матча (не включая его).
 *
 * @return array{
 *     played: int,
 *     won: int,
 *     drawn: int,
 *     lost: int,
 *     goals_for: int,
 *     goals_against: int,
 *     goal_diff: int,
 *     points: int,
 *     matches: list<array{
 *         match_id: int,
 *         starts_at: string,
 *         stage: string,
 *         opponent: string,
 *         team_score: int,
 *         opponent_score: int,
 *         result: string,
 *         is_home: bool
 *     }>
 * }
 */
function team_tournament_progress_from_rows(int $teamId, array $rows): array
{
    $progress = [
        'played' => 0,
        'won' => 0,
        'drawn' => 0,
        'lost' => 0,
        'goals_for' => 0,
        'goals_against' => 0,
        'goal_diff' => 0,
        'points' => 0,
        'matches' => [],
    ];

    foreach ($rows as $row) {
        $homeTeamId = (int) ($row['home_team_id'] ?? 0);
        $awayTeamId = (int) ($row['away_team_id'] ?? 0);
        if ($homeTeamId !== $teamId && $awayTeamId !== $teamId) {
            continue;
        }

        $isHome = $homeTeamId === $teamId;
        $teamScore = $isHome ? (int) $row['home_score'] : (int) $row['away_score'];
        $oppScore = $isHome ? (int) $row['away_score'] : (int) $row['home_score'];
        $opponent = $isHome ? (string) $row['away_team'] : (string) $row['home_team'];

        if ($teamScore > $oppScore) {
            $result = 'W';
            $progress['won']++;
        } elseif ($teamScore < $oppScore) {
            $result = 'L';
            $progress['lost']++;
        } else {
            $result = 'D';
            $progress['drawn']++;
        }

        $progress['played']++;
        $progress['goals_for'] += $teamScore;
        $progress['goals_against'] += $oppScore;
        $progress['matches'][] = [
            'match_id' => (int) $row['id'],
            'starts_at' => (string) $row['starts_at'],
            'stage' => (string) $row['stage'],
            'opponent' => $opponent,
            'team_score' => $teamScore,
            'opponent_score' => $oppScore,
            'result' => $result,
            'is_home' => $isHome,
        ];
    }

    $progress['goal_diff'] = $progress['goals_for'] - $progress['goals_against'];
    $progress['points'] = $progress['won'] * 3 + $progress['drawn'];

    return $progress;
}

/**
 * @return array{home: array<string,mixed>, away: array<string,mixed>}
 */
function match_teams_tournament_progress(array $match): array
{
    $homeId = (int) ($match['home_team_id'] ?? 0);
    $awayId = (int) ($match['away_team_id'] ?? 0);
    $matchId = (int) ($match['id'] ?? 0);
    $startsAt = (string) ($match['starts_at'] ?? '');

    if ($homeId <= 0 || $awayId <= 0 || $matchId <= 0 || $startsAt === '') {
        return [
            'home' => team_tournament_progress_from_rows($homeId, []),
            'away' => team_tournament_progress_from_rows($awayId, []),
        ];
    }

    $stmt = db()->prepare(
        "SELECT m.id, m.starts_at, m.stage, m.home_team_id, m.away_team_id,
                m.home_score, m.away_score,
                ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.status = 'finished'
           AND m.home_score IS NOT NULL
           AND m.away_score IS NOT NULL
           AND m.id != ?
           AND (m.starts_at < ? OR (m.starts_at = ? AND m.id < ?))
           AND (m.home_team_id IN (?, ?) OR m.away_team_id IN (?, ?))
         ORDER BY m.starts_at ASC, m.id ASC"
    );
    $stmt->execute([$matchId, $startsAt, $startsAt, $matchId, $homeId, $awayId, $homeId, $awayId]);
    $rows = $stmt->fetchAll() ?: [];

    return [
        'home' => team_tournament_progress_from_rows($homeId, $rows),
        'away' => team_tournament_progress_from_rows($awayId, $rows),
    ];
}

/** Склонение «N матчей» (1 матч, 2 матча, 5 матчей). */
function ru_matches_suffix(int $n): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 14) {
        return 'матчей';
    }
    if ($n1 === 1) {
        return 'матч';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'матча';
    }

    return 'матчей';
}

function match_tournament_result_badge_html(string $result): string
{
    return match ($result) {
        'W' => '<span class="form-badge form-badge--win" title="Победа">В</span>',
        'D' => '<span class="form-badge form-badge--draw" title="Ничья">Н</span>',
        'L' => '<span class="form-badge form-badge--loss" title="Поражение">П</span>',
        default => '<span class="form-badge form-badge--unknown">' . h($result) . '</span>',
    };
}

function render_match_tournament_progress_html(array $progress): string
{
    $played = (int) ($progress['played'] ?? 0);
    if ($played === 0) {
        return '<span class="muted match-tournament-empty">Первый матч на турнире</span>';
    }

    $goalDiff = (int) ($progress['goal_diff'] ?? 0);
    $goalDiffLabel = $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff;

    $html = '<div class="match-tournament-progress">';
    $html .= '<div class="match-tournament-stat-grid">';
    $html .= '<div class="match-tournament-stat"><span class="match-tournament-stat-label">Игры</span><strong>'
        . $played . '</strong></div>';
    $html .= '<div class="match-tournament-stat"><span class="match-tournament-stat-label">В–Н–П</span><strong>'
        . (int) ($progress['won'] ?? 0) . '–' . (int) ($progress['drawn'] ?? 0) . '–' . (int) ($progress['lost'] ?? 0)
        . '</strong></div>';
    $html .= '<div class="match-tournament-stat"><span class="match-tournament-stat-label">Голы</span><strong>'
        . (int) ($progress['goals_for'] ?? 0) . ':' . (int) ($progress['goals_against'] ?? 0)
        . '</strong><small>' . h($goalDiffLabel) . '</small></div>';
    $html .= '<div class="match-tournament-stat match-tournament-stat--points"><span class="match-tournament-stat-label">Очки</span><strong>'
        . (int) ($progress['points'] ?? 0) . '</strong></div>';
    $html .= '</div>';

    $html .= '<ul class="match-tournament-games">';
    foreach ($progress['matches'] as $game) {
        $venue = !empty($game['is_home']) ? 'д' : 'г';
        $html .= '<li class="match-tournament-game">';
        $html .= '<span class="match-tournament-game-date">' . h(date('d.m', strtotime((string) $game['starts_at']))) . '</span>';
        $html .= '<span class="match-tournament-game-opponent" title="' . h((string) $game['stage']) . '">';
        $html .= h((string) $game['opponent']);
        $html .= '</span>';
        $html .= '<span class="match-tournament-game-score">';
        $html .= (int) $game['team_score'] . ':' . (int) $game['opponent_score'];
        $html .= '</span>';
        $html .= match_tournament_result_badge_html((string) $game['result']);
        $html .= '<span class="match-tournament-game-venue muted">' . h($venue) . '</span>';
        $html .= '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';

    return $html;
}

/** Бейдж LIVE или результат на карточке матча. */
function render_match_status_pills(array $match, bool $compact = false): void
{
    if (($match['status'] ?? '') === 'live') {
        echo '<span class="pill live-pill">' . h(match_live_pill_label()) . '</span>';

        return;
    }

    if ($match['home_score'] !== null && $match['away_score'] !== null) {
        $score = (int) $match['home_score'] . ':' . (int) $match['away_score'];
        echo $compact
            ? '<span class="pill">' . $score . '</span>'
            : '<span class="pill">Результат: ' . $score . '</span>';
    }
}

function prediction_locked(array $match): bool
{
    if (in_array($match['status'] ?? '', ['finished', 'live'], true)) {
        return true;
    }

    if ($match['home_score'] !== null && $match['away_score'] !== null) {
        return true;
    }

    $lockAt = strtotime((string) $match['starts_at']) - ((int) config('app.prediction_lock_minutes') * 60);

    return time() >= $lockAt;
}

function match_started(array $match): bool
{
    return time() >= strtotime($match['starts_at']);
}

function user_prediction(int $userId, int $matchId): ?array
{
    $stmt = db()->prepare('SELECT * FROM predictions WHERE user_id = ? AND match_id = ? LIMIT 1');
    $stmt->execute([$userId, $matchId]);

    return $stmt->fetch() ?: null;
}

function user_predictions_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM predictions WHERE user_id = ?');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function free_prediction_limit(): int
{
    return (int) config('app.free_prediction_limit', 5);
}

function free_predictions_remaining(int $userId): int
{
    return max(0, free_prediction_limit() - user_predictions_count($userId));
}

function champion_prediction_deadline(): ?string
{
    $configured = trim((string) config('app.champion_prediction_deadline', ''));
    if ($configured !== '') {
        return $configured;
    }

    $stmt = db()->query(
        "SELECT starts_at
         FROM matches
         WHERE stage LIKE '1/16 финала%'
         ORDER BY starts_at ASC
         LIMIT 1"
    );
    $startsAt = $stmt->fetchColumn();

    if (!$startsAt) {
        $stmt = db()->query(
            "SELECT starts_at
             FROM matches
             WHERE stage NOT LIKE 'Групповой этап%'
             ORDER BY starts_at ASC
             LIMIT 1"
        );
        $startsAt = $stmt->fetchColumn();
    }

    return $startsAt ?: null;
}

function champion_prediction_locked(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $deadline = champion_prediction_deadline();
    if ($deadline !== null) {
        $deadlineTs = strtotime($deadline);
        if ($deadlineTs !== false && time() >= $deadlineTs) {
            return $cached = true;
        }
    }

    try {
        $stmt = db()->query(
            "SELECT COUNT(*) FROM matches
             WHERE stage NOT LIKE 'Групповой этап%'
               AND (
                    status IN ('live', 'finished')
                    OR (home_score IS NOT NULL AND away_score IS NOT NULL)
                    OR starts_at <= NOW()
               )"
        );
        if ((int) $stmt->fetchColumn() > 0) {
            return $cached = true;
        }
    } catch (Throwable) {
        // ignore
    }

    return $cached = false;
}

/** Прогнозы на чемпиона мира видны публично (после закрытия приёма). */
function champion_predictions_public(): bool
{
    return champion_prediction_locked();
}

/** @return list<array<string,mixed>> Первые N матчей турнира по времени старта — пробный период. */
function free_trial_matches(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $limit = free_prediction_limit();
    $stmt = db()->query(
        "SELECT m.id, m.stage, m.starts_at, m.status,
                ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.home_team_id IS NOT NULL AND m.away_team_id IS NOT NULL
         ORDER BY m.starts_at ASC, m.id ASC
         LIMIT {$limit}"
    );
    $cache = $stmt->fetchAll() ?: [];

    return $cache;
}

/** @return list<int> */
function free_trial_match_ids(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = array_map(
        static fn(array $row): int => (int) $row['id'],
        free_trial_matches()
    );

    return $cache;
}

function is_free_trial_match(int $matchId): bool
{
    return in_array($matchId, free_trial_match_ids(), true);
}

/**
 * @return array{
 *     trial_matches: list<array<string,mixed>>,
 *     users_off_trial: list<array<string,mixed>>,
 *     off_trial_prediction_count: int,
 *     unpaid_with_off_trial: int
 * }
 */
function audit_free_trial_predictions(): array
{
    $trialMatches = free_trial_matches();
    $trialIds = free_trial_match_ids();
    if ($trialIds === []) {
        return [
            'trial_matches' => [],
            'users_off_trial' => [],
            'off_trial_prediction_count' => 0,
            'unpaid_with_off_trial' => 0,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($trialIds), '?'));
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email,
                COUNT(p.id) AS off_trial_count,
                GROUP_CONCAT(
                    CONCAT(m.stage, ': ', ht.name, ' — ', at.name)
                    ORDER BY m.starts_at SEPARATOR '; '
                ) AS off_trial_matches
         FROM users u
         JOIN predictions p ON p.user_id = u.id
         JOIN matches m ON m.id = p.match_id
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE u.role = 'participant'
           AND u.payment_status = 'pending_payment'
           AND p.match_id NOT IN ({$placeholders})
         GROUP BY u.id, u.name, u.email
         ORDER BY off_trial_count DESC, u.name ASC"
    );
    $stmt->execute($trialIds);
    $usersOffTrial = $stmt->fetchAll() ?: [];

    $countStmt = db()->prepare(
        "SELECT COUNT(*)
         FROM predictions p
         JOIN users u ON u.id = p.user_id
         WHERE u.role = 'participant'
           AND u.payment_status = 'pending_payment'
           AND p.match_id NOT IN ({$placeholders})"
    );
    $countStmt->execute($trialIds);

    return [
        'trial_matches' => $trialMatches,
        'users_off_trial' => $usersOffTrial,
        'off_trial_prediction_count' => (int) $countStmt->fetchColumn(),
        'unpaid_with_off_trial' => count($usersOffTrial),
    ];
}

function can_make_prediction(array $user, int $matchId): bool
{
    if (($user['payment_status'] ?? '') === 'blocked') {
        return false;
    }

    if (is_active_participant($user)) {
        return true;
    }

    if (user_prediction((int) $user['id'], $matchId)) {
        return true;
    }

    if (free_predictions_remaining((int) $user['id']) <= 0) {
        return false;
    }

    return is_free_trial_match($matchId);
}

function free_trial_prediction_denied_message(array $user, int $matchId): string
{
    if (!is_free_trial_match($matchId) && free_predictions_remaining((int) $user['id']) > 0) {
        return 'Бесплатные прогнозы доступны только на первые '
            . free_prediction_limit()
            . ' матчей турнира по расписанию. Чтобы прогнозировать дальше, оплатите взнос.';
    }

    return 'Бесплатный лимит прогнозов закончился. Оплатите взнос, чтобы продолжить игру.';
}

function user_score(int $userId, int $matchId): ?array
{
    $stmt = db()->prepare('SELECT * FROM scores WHERE user_id = ? AND match_id = ? LIMIT 1');
    $stmt->execute([$userId, $matchId]);

    return $stmt->fetch() ?: null;
}

function user_champion_prediction(int $userId): ?array
{
    $stmt = db()->prepare(
        "SELECT cp.*, t.name AS team_name
         FROM champion_predictions cp
         JOIN teams t ON t.id = cp.team_id
         WHERE cp.user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);

    return $stmt->fetch() ?: null;
}

function score_prediction(array $match, array $prediction): int
{
    if ($match['home_score'] === null || $match['away_score'] === null) {
        return 0;
    }

    $homeScore = (int) $match['home_score'];
    $awayScore = (int) $match['away_score'];
    $predHome = (int) $prediction['home_score'];
    $predAway = (int) $prediction['away_score'];

    if ($homeScore === $predHome && $awayScore === $predAway) {
        return 3;
    }

    return match_outcome($homeScore, $awayScore) === match_outcome($predHome, $predAway) ? 1 : 0;
}

function match_outcome(int $homeScore, int $awayScore): string
{
    if ($homeScore > $awayScore) {
        return 'home';
    }
    if ($homeScore < $awayScore) {
        return 'away';
    }

    return 'draw';
}

/**
 * @return array{match_id:int,recipients:int,sent:int,skipped:int,failed:int,error?:string}
 * @throws RuntimeException
 */
function apply_match_result(int $matchId, int $homeScore, int $awayScore, string $source = 'manual'): array
{
    if (!in_array($source, ['manual', 'api'], true)) {
        $source = 'manual';
    }

    $teamsStmt = db()->prepare('SELECT home_team_id, away_team_id FROM matches WHERE id = ?');
    $teamsStmt->execute([$matchId]);
    $teamsRow = $teamsStmt->fetch();
    if (!$teamsRow) {
        throw new RuntimeException('Матч не найден.');
    }
    if ($teamsRow['home_team_id'] === null || $teamsRow['away_team_id'] === null) {
        throw new RuntimeException('Назначьте обе команды в матче.');
    }

    $homeScore = max(0, $homeScore);
    $awayScore = max(0, $awayScore);

    $stmt = db()->prepare(
        "UPDATE matches
         SET home_score = ?, away_score = ?, status = 'finished', result_source = ?, api_synced_at = NOW(), updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$homeScore, $awayScore, $source, $matchId]);
    recalculate_scores($matchId);

    try {
        return run_match_result_notifications($matchId);
    } catch (Throwable $e) {
        error_log('run_match_result_notifications: ' . $e->getMessage());

        return [
            'match_id' => $matchId,
            'recipients' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'error' => 'notify_exception',
        ];
    }
}

function clear_match_result(int $matchId): void
{
    db()->prepare('DELETE FROM scores WHERE match_id = ?')->execute([$matchId]);
    if (db_table_exists('match_result_notification_log')) {
        db()->prepare('DELETE FROM match_result_notification_log WHERE match_id = ?')->execute([$matchId]);
    }

    $stmt = db()->prepare('SELECT api_fixture_id FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    $hasApi = $match && $match['api_fixture_id'] !== null && (int) $match['api_fixture_id'] > 0;
    $resultSource = $hasApi ? 'api' : 'manual';

    db()->prepare(
        "UPDATE matches
         SET home_score = NULL, away_score = NULL, status = 'scheduled', result_source = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([$resultSource, $matchId]);
}

function recalculate_scores(?int $matchId = null): void
{
    $params = [];
    $where = '';
    if ($matchId !== null) {
        $where = ' WHERE m.id = ?';
        $params[] = $matchId;
    }

    $stmt = db()->prepare(
        "SELECT p.*, m.home_score AS result_home_score, m.away_score AS result_away_score
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         $where"
    );
    $stmt->execute($params);

    $upsert = db()->prepare(
        "INSERT INTO scores (user_id, match_id, points, reason, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE points = VALUES(points), reason = VALUES(reason), updated_at = NOW()"
    );

    foreach ($stmt->fetchAll() as $row) {
        if ($row['result_home_score'] === null || $row['result_away_score'] === null) {
            continue;
        }

        $points = score_prediction([
            'home_score' => $row['result_home_score'],
            'away_score' => $row['result_away_score'],
        ], $row);

        $reason = $points === 3 ? 'Точный счет' : ($points === 1 ? 'Угадан исход' : 'Нет очков');
        $upsert->execute([(int) $row['user_id'], (int) $row['match_id'], $points, $reason]);
    }
}

/** SQL-фрагмент: участники, попадающие в публичные рейтинги и таблицы конкурса. */
function ranked_participant_where(string $alias = 'u'): string
{
    $prefix = $alias !== '' ? "{$alias}." : '';

    return "{$prefix}role = 'participant' AND {$prefix}payment_status = 'active'";
}

/** Статистика регистраций для блока «Уже в игре» на главной. */
function contest_registration_stats(int $recentLimit = 8): array
{
    $recentLimit = max(1, min(20, $recentLimit));

    $active = (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE " . ranked_participant_where('')
    )->fetchColumn();

    $stmt = db()->prepare(
        "SELECT name FROM users
         WHERE " . ranked_participant_where('') . "
         ORDER BY created_at DESC
         LIMIT ?"
    );
    $stmt->execute([$recentLimit]);

    return [
        'total_participants' => $active,
        'active_participants' => $active,
        'recent_participants' => $stmt->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function leaderboard(): array
{
    $stmt = db()->query(
        "SELECT u.id, u.name, u.payment_status,
                COALESCE(ms.match_points, 0) + COALESCE(cp.points, 0) AS total_points,
                COALESCE(ms.match_points, 0) AS match_points,
                COALESCE(cp.points, 0) AS champion_points,
                COALESCE(ps.predictions_count, 0) AS predictions_count,
                COALESCE(ms.exact_scores_count, 0) AS exact_scores_count,
                COALESCE(ms.outcomes_count, 0) AS outcomes_count
         FROM users u
         LEFT JOIN (
            SELECT user_id, COUNT(*) AS predictions_count
            FROM predictions
            GROUP BY user_id
         ) ps ON ps.user_id = u.id
         LEFT JOIN (
            SELECT user_id,
                   SUM(points) AS match_points,
                   SUM(CASE WHEN reason = 'Точный счет' THEN 1 ELSE 0 END) AS exact_scores_count,
                   SUM(CASE WHEN reason = 'Угадан исход' THEN 1 ELSE 0 END) AS outcomes_count
            FROM scores
            GROUP BY user_id
         ) ms ON ms.user_id = u.id
         LEFT JOIN champion_predictions cp ON cp.user_id = u.id
         WHERE " . ranked_participant_where('u') . "
         ORDER BY total_points DESC,
                  exact_scores_count DESC,
                  outcomes_count DESC,
                  u.created_at ASC"
    );

    return $stmt->fetchAll();
}

/**
 * Те же метрики, что в общей таблице лидеров, но для списка id участников.
 *
 * @param list<int> $userIds
 * @return array<int, array<string, mixed>>
 */
function participant_leaderboard_stats_by_user_ids(array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(
        array_map(static fn ($id): int => (int) $id, $userIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($userIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = db()->prepare(
        "SELECT u.id, u.name,
                COALESCE(ms.match_points, 0) + COALESCE(cp.points, 0) AS total_points,
                COALESCE(ms.match_points, 0) AS match_points,
                COALESCE(cp.points, 0) AS champion_points,
                COALESCE(ps.predictions_count, 0) AS predictions_count,
                COALESCE(ms.exact_scores_count, 0) AS exact_scores_count,
                COALESCE(ms.outcomes_count, 0) AS outcomes_count,
                u.created_at
         FROM users u
         LEFT JOIN (
            SELECT user_id, COUNT(*) AS predictions_count
            FROM predictions
            GROUP BY user_id
         ) ps ON ps.user_id = u.id
         LEFT JOIN (
            SELECT user_id,
                   SUM(points) AS match_points,
                   SUM(CASE WHEN reason = 'Точный счет' THEN 1 ELSE 0 END) AS exact_scores_count,
                   SUM(CASE WHEN reason = 'Угадан исход' THEN 1 ELSE 0 END) AS outcomes_count
            FROM scores
            GROUP BY user_id
         ) ms ON ms.user_id = u.id
         LEFT JOIN champion_predictions cp ON cp.user_id = u.id
         WHERE " . ranked_participant_where('u') . " AND u.id IN ($placeholders)"
    );
    $stmt->execute($userIds);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['id']] = $row;
    }

    return $out;
}

function participant_badges(int $userId): array
{
    $predictionCount = user_predictions_count($userId);
    $championPrediction = user_champion_prediction($userId);
    $summary = participant_summary($userId);

    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN reason = 'Точный счет' THEN 1 ELSE 0 END), 0) AS exact_scores_count,
            COALESCE(SUM(CASE WHEN reason = 'Угадан исход' THEN 1 ELSE 0 END), 0) AS outcomes_count
         FROM scores
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $scoreStats = $stmt->fetch() ?: ['exact_scores_count' => 0, 'outcomes_count' => 0];

    $exactScores = (int) $scoreStats['exact_scores_count'];
    $outcomes = (int) $scoreStats['outcomes_count'];
    $ownedMiniLeagues = user_owned_mini_leagues_count($userId);
    $largestMiniLeague = user_largest_owned_mini_league_size($userId);

    return [
        [
            'title' => 'Первый прогноз',
            'description' => 'Сделать первый прогноз на матч.',
            'earned' => $predictionCount >= 1,
        ],
        [
            'title' => 'Пробный драйв',
            'description' => 'Использовать все бесплатные прогнозы.',
            'earned' => $predictionCount >= free_prediction_limit(),
        ],
        [
            'title' => 'Выбор сделан',
            'description' => 'Выбрать будущего чемпиона мира.',
            'earned' => $championPrediction !== null,
        ],
        [
            'title' => 'Точный удар',
            'description' => 'Угадать первый точный счет.',
            'earned' => $exactScores >= 1,
        ],
        [
            'title' => 'Чувство игры',
            'description' => 'Угадать исходы 5 матчей.',
            'earned' => $outcomes >= 5,
        ],
        [
            'title' => 'В зоне призов',
            'description' => 'Попасть в топ-5 таблицы.',
            'earned' => $summary !== null && (int) $summary['rank'] <= prize_places_count(),
        ],
        [
            'title' => 'Капитан',
            'description' => 'Создать свою мини-лигу.',
            'earned' => $ownedMiniLeagues >= 1,
        ],
        [
            'title' => 'Собрал команду',
            'description' => 'Собрать 5 участников в своей мини-лиге.',
            'earned' => $largestMiniLeague >= 5,
        ],
    ];
}

function numeric_setting(string $settingKey, string $configPath, int $default): int
{
    static $cache = [];

    if (!array_key_exists($settingKey, $cache)) {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$settingKey]);
        $row = $stmt->fetch();
        $cache[$settingKey] = $row !== false && is_numeric($row['setting_value']) ? (int) $row['setting_value'] : null;
    }

    if ($cache[$settingKey] !== null) {
        return (int) $cache[$settingKey];
    }

    return (int) config($configPath, $default);
}

/**
 * Сумма гарантированных денежных призов (места 2–5). Не включает главный приз в натуральной форме.
 */
function prize_pool(): int
{
    $places = config('app.prize_cash_by_place');
    if (!is_array($places) || !$places) {
        return 21000;
    }

    return (int) array_sum(array_map('intval', $places));
}

function entry_fee_rub(): int
{
    return max(0, numeric_setting('entry_fee_rub', 'app.entry_fee_rub', 1500));
}

function referral_discount_rub(): int
{
    return max(0, numeric_setting('referral_discount_rub', 'app.referral_discount_rub', 500));
}

function referral_discount_limit_per_account(): int
{
    return max(1, numeric_setting('referral_discount_limit_per_account', 'app.referral_discount_limit_per_account', 1));
}

/** Склонение для «N раз» (1 раз, 2 раза, 5 раз). */
function ru_times_suffix(int $n): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 14) {
        return 'раз';
    }
    if ($n1 === 1) {
        return 'раз';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'раза';
    }

    return 'раз';
}

/** Склонение «N дней» (1 день, 2 дня, 5 дней). */
function ru_days_suffix(int $n): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 14) {
        return 'дней';
    }
    if ($n1 === 1) {
        return 'день';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'дня';
    }

    return 'дней';
}

/** Склонение «N очков» (1 очко, 2 очка, 5 очков). */
function ru_points_suffix(int $n): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 14) {
        return 'очков';
    }
    if ($n1 === 1) {
        return 'очко';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'очка';
    }

    return 'очков';
}

function contest_kickoff_day_msk(): DateTimeImmutable
{
    $row = db()->query(
        "SELECT MIN(starts_at) AS kickoff
         FROM matches
         WHERE home_team_id IS NOT NULL AND away_team_id IS NOT NULL"
    )->fetch();

    $kickoffRaw = is_array($row) ? (string) ($row['kickoff'] ?? '') : '';
    if ($kickoffRaw === '') {
        $kickoffRaw = '2026-06-11 22:00:00';
    }

    return (new DateTimeImmutable($kickoffRaw, new DateTimeZone('Europe/Moscow')))->setTime(0, 0);
}

function contest_payment_banner_grace_days(): int
{
    return 4;
}

/** Календарных дней до первого матча ЧМ на сайте (по дате старта в МСК). */
function contest_days_until_kickoff(): int
{
    $kickoffDay = contest_kickoff_day_msk();
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->setTime(0, 0);
    if ($kickoffDay < $today) {
        return 0;
    }

    return (int) $today->diff($kickoffDay)->days;
}

/** Показывать баннер об оплате: до старта и ещё N дней после первого матча. */
function contest_payment_banner_active(): bool
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->setTime(0, 0);
    $lastBannerDay = contest_kickoff_day_msk()->modify('+' . contest_payment_banner_grace_days() . ' days');

    return $today <= $lastBannerDay;
}

/**
 * Текст обратного отсчёта для баннера оплаты на главной.
 *
 * @return array{phase: 'before'|'after', days: int, title: string, subtitle: string}|null
 */
function contest_payment_banner_message(): ?array
{
    if (!contest_payment_banner_active()) {
        return null;
    }

    $tz = new DateTimeZone('Europe/Moscow');
    $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0);
    $kickoffDay = contest_kickoff_day_msk();
    $lastBannerDay = $kickoffDay->modify('+' . contest_payment_banner_grace_days() . ' days');
    $freeLimit = (int) config('app.free_prediction_limit', 5);

    if ($today < $kickoffDay) {
        $days = (int) $today->diff($kickoffDay)->days;

        return [
            'phase' => 'before',
            'days' => $days,
            'title' => 'До старта ЧМ — ' . $days . ' ' . ru_days_suffix($days),
            'subtitle' => 'Оплатите взнос, чтобы играть весь турнир без лимита и бороться за призы. Пробный режим — '
                . $freeLimit . ' прогнозов.',
        ];
    }

    $daysLeft = (int) $today->diff($lastBannerDay)->days;
    $graceDays = contest_payment_banner_grace_days();

    return [
        'phase' => 'after',
        'days' => $daysLeft,
        'title' => 'ЧМ уже идёт — оплатите взнос',
        'subtitle' => 'Без подтверждённой оплаты доступен только пробный режим (' . $freeLimit . ' прогнозов). '
            . 'Успейте внести взнос'
            . ($daysLeft > 0 ? ' — напоминание на сайте ещё ' . $daysLeft . ' ' . ru_days_suffix($daysLeft) : ' сегодня')
            . '.',
    ];
}

function db_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();
    } catch (PDOException) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

/**
 * Участники с неподтверждённой оплатой без загруженного чека (для напоминания).
 *
 * @return list<array{id: int, name: string, email: string, predictions_count: int}>
 */
function pending_payment_reminder_recipients(): array
{
    $receiptFilter = db_table_exists('payment_receipts')
        ? 'AND pr.user_id IS NULL'
        : '';
    $receiptJoin = db_table_exists('payment_receipts')
        ? 'LEFT JOIN payment_receipts pr ON pr.user_id = u.id'
        : '';

    try {
        $stmt = db()->query(
            "SELECT u.id, u.name, u.email,
                    (SELECT COUNT(*) FROM predictions p WHERE p.user_id = u.id) AS predictions_count
             FROM users u
             {$receiptJoin}
             WHERE u.role = 'participant'
               AND u.payment_status = 'pending_payment'
               {$receiptFilter}
             ORDER BY u.created_at ASC"
        );
    } catch (PDOException) {
        return [];
    }

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'predictions_count' => (int) ($row['predictions_count'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return array{sent: int, failed: int, total: int}
 */
function run_pending_payment_reminder_mailout(): array
{
    if (!mail_is_configured()) {
        return ['sent' => 0, 'failed' => 0, 'total' => 0];
    }

    $sent = 0;
    $failed = 0;
    foreach (pending_payment_reminder_recipients() as $recipient) {
        if (mail_send_payment_reminder(
            (string) $recipient['email'],
            (string) $recipient['name'],
            (int) $recipient['predictions_count']
        )) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'total' => $sent + $failed,
    ];
}

/** @return array<string,mixed>|null */
function last_free_prediction_match(): ?array
{
    $matches = free_trial_matches();
    if ($matches === []) {
        return null;
    }

    return $matches[count($matches) - 1];
}

/**
 * @return array{
 *     sent: int,
 *     failed: int,
 *     skipped: int,
 *     total: int,
 *     already_sent: bool,
 *     offset: int,
 *     next_offset: int,
 *     total_recipients: int,
 *     done: bool
 * }
 */
function run_last_free_match_payment_mailout(bool $force = false, int $batchSize = 20): array
{
    $empty = [
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
        'total' => 0,
        'already_sent' => false,
        'offset' => 0,
        'next_offset' => 0,
        'total_recipients' => 0,
        'done' => false,
    ];

    if (!mail_is_configured()) {
        return $empty;
    }

    $sentKey = 'last_free_match_payment_mailout_at';
    $offsetKey = 'last_free_match_payment_mailout_offset';
    $batchSize = max(1, min(50, $batchSize));

    if ($force) {
        db()->prepare('DELETE FROM settings WHERE setting_key IN (?, ?)')->execute([$sentKey, $offsetKey]);
    }

    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$sentKey]);
    $alreadySent = (string) ($stmt->fetchColumn() ?: '') !== '';
    if ($alreadySent && !$force) {
        return array_merge($empty, ['already_sent' => true, 'done' => true]);
    }

    $match = last_free_prediction_match();
    if (!$match) {
        return $empty;
    }

    $recipients = pending_payment_reminder_recipients();
    $totalRecipients = count($recipients);
    if ($totalRecipients === 0) {
        return array_merge($empty, ['done' => true]);
    }

    $stmt->execute([$offsetKey]);
    $offset = max(0, (int) ($stmt->fetchColumn() ?: 0));
    if ($offset >= $totalRecipients) {
        db()->prepare(
            "INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = NOW(), updated_at = NOW()"
        )->execute([$sentKey]);
        db()->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$offsetKey]);

        return array_merge($empty, [
            'total_recipients' => $totalRecipients,
            'offset' => $totalRecipients,
            'next_offset' => $totalRecipients,
            'done' => true,
        ]);
    }

    $batch = array_slice($recipients, $offset, $batchSize);
    $sent = 0;
    $failed = 0;
    foreach ($batch as $recipient) {
        if (mail_send_last_free_match_payment_notice(
            (string) $recipient['email'],
            (string) $recipient['name'],
            $match,
            (int) $recipient['predictions_count']
        )) {
            $sent++;
        } else {
            $failed++;
        }
    }

    $nextOffset = $offset;
    $done = false;
    if ($failed === 0 && $sent > 0) {
        $nextOffset = $offset + count($batch);
        db()->prepare(
            "INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
        )->execute([$offsetKey, (string) $nextOffset]);

        if ($nextOffset >= $totalRecipients) {
            db()->prepare(
                "INSERT INTO settings (setting_key, setting_value, updated_at)
                 VALUES (?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE setting_value = NOW(), updated_at = NOW()"
            )->execute([$sentKey]);
            db()->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$offsetKey]);
            $done = true;
        }
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => 0,
        'total' => $sent + $failed,
        'already_sent' => false,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'total_recipients' => $totalRecipients,
        'done' => $done,
    ];
}

/** Склонение «N участник(ов)» для блока социального доказательства. */
function ru_participant_count_label(int $n): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 14) {
        return 'участников';
    }
    if ($n1 === 1) {
        return 'участник';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'участника';
    }

    return 'участников';
}

/** Склонение «N голос(ов)» для опроса. */
function ru_vote_count_label(int $n): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 14) {
        return 'голосов';
    }
    if ($n1 === 1) {
        return 'голос';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'голоса';
    }

    return 'голосов';
}

function referral_discounted_entry_fee_rub(): int
{
    return max(0, entry_fee_rub() - referral_discount_rub());
}

function referral_pair_entry_fee_rub(): int
{
    return referral_discounted_entry_fee_rub() * 2;
}

function payment_amount_options(): array
{
    $pairTotal = number_format(referral_pair_entry_fee_rub(), 0, ',', ' ');

    return [
        entry_fee_rub() => 'Один участник — полный взнос',
        referral_discounted_entry_fee_rub() => 'Парная регистрация — доля этого участника (взнос за двоих — одним переводом ' . $pairTotal . ' ₽)',
    ];
}

/** Полное описание призовых мест (топ-5). */
function prize_distribution(): array
{
    $mainTitle = (string) config('app.prize_main_title', 'Apple iPhone 17e 256 GB');
    $cashByPlace = config('app.prize_cash_by_place');
    if (!is_array($cashByPlace)) {
        $cashByPlace = [2 => 10000, 3 => 5000, 4 => 4000, 5 => 2000];
    }

    $rows = [
        [
            'place' => 1,
            'label' => $mainTitle,
            'amount_rub' => null,
            'is_main_prize' => true,
        ],
    ];

    ksort($cashByPlace);
    foreach ($cashByPlace as $place => $amount) {
        $rows[] = [
            'place' => (int) $place,
            'label' => 'Денежный приз',
            'amount_rub' => (int) $amount,
            'is_main_prize' => false,
        ];
    }

    return $rows;
}

/** Количество призовых мест (для бейджей и текстов). */
function prize_places_count(): int
{
    return count(prize_distribution());
}

function participant_summary(int $userId): ?array
{
    $leaders = leaderboard();
    foreach ($leaders as $index => $row) {
        if ((int) $row['id'] === $userId) {
            return [
                'rank' => $index + 1,
                'total_participants' => count($leaders),
                'total_points' => (int) $row['total_points'],
                'match_points' => (int) $row['match_points'],
                'champion_points' => (int) $row['champion_points'],
                'exact_scores_count' => (int) $row['exact_scores_count'],
                'outcomes_count' => (int) $row['outcomes_count'],
                'predictions_count' => (int) $row['predictions_count'],
            ];
        }
    }

    return null;
}

function user_mini_leagues(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT ml.*,
                (SELECT COUNT(*) FROM mini_league_members mlm WHERE mlm.league_id = ml.id) AS members_count
         FROM mini_leagues ml
         JOIN mini_league_members mlm ON mlm.league_id = ml.id
         WHERE mlm.user_id = ?
         ORDER BY ml.created_at DESC"
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/** Все мини-лиги для админ-панели. */
function admin_all_mini_leagues(): array
{
    return db()->query(
        "SELECT ml.*,
                u.name AS owner_name,
                u.email AS owner_email,
                (SELECT COUNT(*) FROM mini_league_members mlm WHERE mlm.league_id = ml.id) AS members_count
         FROM mini_leagues ml
         JOIN users u ON u.id = ml.owner_user_id
         ORDER BY ml.created_at DESC"
    )->fetchAll();
}

/** Участники мини-лиги для админ-панели. */
function admin_mini_league_members(int $leagueId): array
{
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.payment_status, mlm.created_at AS joined_at
         FROM mini_league_members mlm
         JOIN users u ON u.id = mlm.user_id
         WHERE mlm.league_id = ?
         ORDER BY mlm.created_at ASC"
    );
    $stmt->execute([$leagueId]);

    return $stmt->fetchAll();
}

function user_owned_mini_leagues_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM mini_leagues WHERE owner_user_id = ?');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function user_largest_owned_mini_league_size(int $userId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(mlm.user_id) AS members_count
         FROM mini_leagues ml
         LEFT JOIN mini_league_members mlm ON mlm.league_id = ml.id
         WHERE ml.owner_user_id = ?
         GROUP BY ml.id
         ORDER BY members_count DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function find_mini_league(int $leagueId): ?array
{
    $stmt = db()->prepare(
        "SELECT ml.*,
                u.name AS owner_name,
                (SELECT COUNT(*) FROM mini_league_members mlm WHERE mlm.league_id = ml.id) AS members_count
         FROM mini_leagues ml
         JOIN users u ON u.id = ml.owner_user_id
         WHERE ml.id = ?
         LIMIT 1"
    );
    $stmt->execute([$leagueId]);

    return $stmt->fetch() ?: null;
}

function find_mini_league_by_code(string $inviteCode): ?array
{
    $stmt = db()->prepare('SELECT * FROM mini_leagues WHERE invite_code = ? LIMIT 1');
    $stmt->execute([strtoupper(trim($inviteCode))]);

    return $stmt->fetch() ?: null;
}

function user_in_mini_league(int $leagueId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM mini_league_members WHERE league_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$leagueId, $userId]);

    return (bool) $stmt->fetchColumn();
}

function generate_mini_league_code(): string
{
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = db()->prepare('SELECT 1 FROM mini_leagues WHERE invite_code = ? LIMIT 1');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());

    return $code;
}

function mini_league_leaderboard(int $leagueId): array
{
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.created_at
         FROM mini_league_members mlm
         JOIN users u ON u.id = mlm.user_id
         WHERE mlm.league_id = ?
           AND u.payment_status = 'active'
         ORDER BY u.created_at ASC"
    );
    $stmt->execute([$leagueId]);
    $members = $stmt->fetchAll();
    if ($members === []) {
        return [];
    }

    $ids = array_map(static fn (array $m): int => (int) $m['id'], $members);
    $statsById = participant_leaderboard_stats_by_user_ids($ids);

    $rows = [];
    foreach ($members as $member) {
        $memberId = (int) $member['id'];
        $rows[] = $statsById[$memberId] ?? [
            'id' => $memberId,
            'name' => $member['name'],
            'total_points' => 0,
            'match_points' => 0,
            'champion_points' => 0,
            'predictions_count' => 0,
            'exact_scores_count' => 0,
            'outcomes_count' => 0,
            'created_at' => $member['created_at'],
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return ((int) $b['total_points'] <=> (int) $a['total_points'])
            ?: ((int) $b['exact_scores_count'] <=> (int) $a['exact_scores_count'])
            ?: ((int) $b['outcomes_count'] <=> (int) $a['outcomes_count'])
            ?: strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
    });

    return $rows;
}

function mini_league_tab_key(?string $raw): string
{
    $key = strtolower(trim((string) $raw));

    return in_array($key, ['rating', 'matrix', 'champions'], true) ? $key : 'rating';
}

function mini_league_url(int $leagueId, string $tab = 'rating', ?string $stageKey = null): string
{
    $tab = mini_league_tab_key($tab);
    $query = ['id' => $leagueId];
    if ($tab !== 'rating') {
        $query['tab'] = $tab;
    }
    if ($tab === 'matrix' && $stageKey !== null && $stageKey !== '' && $stageKey !== 'all') {
        $query['stage'] = $stageKey;
    }

    return '/mini-league?' . http_build_query($query);
}

/** @return list<array{id:int,name:string,payment_status:string,created_at:string}> */
function mini_league_member_users(int $leagueId): array
{
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.payment_status, u.created_at
         FROM mini_league_members mlm
         JOIN users u ON u.id = mlm.user_id
         WHERE mlm.league_id = ?
         ORDER BY u.name ASC"
    );
    $stmt->execute([$leagueId]);

    return $stmt->fetchAll();
}

/** @return list<array<string, mixed>> */
function mini_league_participants_overview(int $leagueId): array
{
    $users = array_values(array_filter(
        mini_league_member_users($leagueId),
        static fn (array $user): bool => ($user['payment_status'] ?? '') === 'active'
    ));
    if ($users === []) {
        return [];
    }

    $statsById = participant_leaderboard_stats_by_user_ids(array_map(static fn (array $row): int => (int) $row['id'], $users));
    $championTeams = champion_prediction_locked()
        ? transparent_champion_teams_by_user_ids(array_keys($statsById))
        : [];

    $overview = [];
    foreach ($users as $user) {
        $id = (int) $user['id'];
        $stat = $statsById[$id] ?? [
            'id' => $id,
            'name' => (string) $user['name'],
            'total_points' => 0,
            'match_points' => 0,
            'champion_points' => 0,
            'predictions_count' => 0,
            'exact_scores_count' => 0,
            'outcomes_count' => 0,
            'created_at' => (string) $user['created_at'],
        ];
        $overview[] = array_merge($stat, [
            'payment_status' => (string) $user['payment_status'],
            'champion_team' => $championTeams[$id] ?? null,
        ]);
    }

    usort($overview, static function (array $a, array $b): int {
        $cmp = (int) $b['total_points'] <=> (int) $a['total_points'];
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = (int) $b['exact_scores_count'] <=> (int) $a['exact_scores_count'];
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = (int) $b['outcomes_count'] <=> (int) $a['outcomes_count'];
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
    });

    foreach ($overview as $index => &$row) {
        $row['rank'] = $index + 1;
    }
    unset($row);

    return $overview;
}

/**
 * @return array{
 *   participants: list<array<string, mixed>>,
 *   matches: list<array<string, mixed>>,
 *   cells: array<int, array<int, array<string, mixed>>>
 * }
 */
function mini_league_predictions_matrix(int $leagueId, ?string $stageKey = null): array
{
    $participants = mini_league_participants_overview($leagueId);
    $userIds = array_map(static fn (array $row): int => (int) $row['id'], $participants);
    $matches = transparent_started_matches($stageKey);
    $matchIds = array_map(static fn (array $row): int => (int) $row['id'], $matches);

    if ($userIds === [] || $matchIds === []) {
        return ['participants' => $participants, 'matches' => $matches, 'cells' => []];
    }

    $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
    $matchPlaceholders = implode(',', array_fill(0, count($matchIds), '?'));
    $stmt = db()->prepare(
        "SELECT p.user_id, p.match_id, p.home_score, p.away_score,
                COALESCE(s.points, 0) AS points, s.reason,
                m.home_score AS result_home_score, m.away_score AS result_away_score
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
         WHERE p.user_id IN ($userPlaceholders)
           AND p.match_id IN ($matchPlaceholders)"
    );
    $stmt->execute(array_merge($userIds, $matchIds));

    $cells = [];
    foreach ($stmt->fetchAll() as $row) {
        $cells[(int) $row['user_id']][(int) $row['match_id']] = $row;
    }

    return ['participants' => $participants, 'matches' => $matches, 'cells' => $cells];
}

/** @return list<array<string, mixed>> */
function mini_league_champion_predictions(int $leagueId): array
{
    if (!champion_prediction_locked()) {
        return [];
    }

    $userIds = array_map(
        static fn (array $row): int => (int) $row['id'],
        array_values(array_filter(
            mini_league_member_users($leagueId),
            static fn (array $user): bool => ($user['payment_status'] ?? '') === 'active'
        ))
    );
    if ($userIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = db()->prepare(
        "SELECT u.id AS user_id, u.name, t.name AS team_name, cp.points
         FROM champion_predictions cp
         JOIN users u ON u.id = cp.user_id
         JOIN teams t ON t.id = cp.team_id
         WHERE cp.user_id IN ($placeholders)
         ORDER BY u.name ASC"
    );
    $stmt->execute($userIds);

    return $stmt->fetchAll();
}

/** Только сборные из официального списка 48 участников ЧМ-2026 (без слотов расписания и плей-офф). */
function team_is_champion_pick_candidate(array $team): bool
{
    $name = trim((string) ($team['name'] ?? ''));
    if ($name === '') {
        return false;
    }

    $code = isset($team['code']) && (string) $team['code'] !== '' ? (string) $team['code'] : null;

    return worldcup2026_is_participant_team($code, $name);
}

function teams_for_champion_select(): array
{
    $stmt = db()->query('SELECT * FROM teams ORDER BY name');

    return array_values(array_filter(
        $stmt->fetchAll(),
        static function (array $row): bool {
            return team_is_champion_pick_candidate($row);
        }
    ));
}

/** Список для селекта + текущий выбор, даже если это старый «слот» (чтобы можно было сменить). */
function teams_for_champion_select_with_current(?array $championPrediction): array
{
    $list = teams_for_champion_select();
    if (!$championPrediction || empty($championPrediction['team_id'])) {
        return $list;
    }

    $tid = (int) $championPrediction['team_id'];
    foreach ($list as $row) {
        if ((int) $row['id'] === $tid) {
            return $list;
        }
    }

    $stmt = db()->prepare('SELECT * FROM teams WHERE id = ? LIMIT 1');
    $stmt->execute([$tid]);
    $extra = $stmt->fetch();
    if ($extra && team_is_champion_pick_candidate($extra)) {
        $list[] = $extra;
    }

    usort($list, static function (array $a, array $b): int {
        return strcmp((string) $a['name'], (string) $b['name']);
    });

    return $list;
}

/**
 * Редирект после сохранения прогноза: главная (карточки на лендинге) или кабинет с фильтрами.
 */
function prediction_save_return_url(string $returnTo, ?string $stageKey, string $dateFilter, int $matchId): string
{
    if (trim($returnTo) === 'home') {
        $hash = $matchId > 0 ? ('home-match-' . $matchId) : 'home-predictions';

        return '/' . '#' . $hash;
    }

    return dashboard_return_url($stageKey, $dateFilter, $matchId > 0 ? 'match-' . $matchId : '');
}

/** Редирект после действий в кабинете: сохранить фильтры и прокрутку к якорю. */
function dashboard_return_url(?string $stageKey, string $dateFilter, string $hashFragment): string
{
    $allowed = ['all', 'group', 'round32', 'round16', 'quarter', 'semi', 'third', 'final'];
    $stageKey = (string) ($stageKey ?? 'all');
    if (!in_array($stageKey, $allowed, true)) {
        $stageKey = 'all';
    }
    $dateFilter = trim($dateFilter);
    if ($dateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
        $dateFilter = '';
    }
    $query = [];
    if ($stageKey !== 'all') {
        $query['stage'] = $stageKey;
    }
    if ($dateFilter !== '') {
        $query['date'] = $dateFilter;
    }
    $qs = $query === [] ? '' : ('?' . http_build_query($query));
    $hash = $hashFragment === '' ? '' : ('#' . preg_replace('/^#/', '', $hashFragment));

    return '/dashboard' . $qs . $hash;
}

function predictions_transparency_tab_key(?string $raw): string
{
    $key = strtolower(trim((string) $raw));

    return in_array($key, ['participants', 'matrix', 'champions'], true) ? $key : 'participants';
}

/** Ссылка на публичный профиль участника. */
function participant_url(int $userId, ?string $from = null): string
{
    $url = '/participant?id=' . $userId;
    $allowed = ['predictions', 'rating', 'match', 'matrix', 'my-scores'];
    if ($from !== null && $from !== '' && in_array($from, $allowed, true)) {
        $url .= '&from=' . rawurlencode($from);
    }

    return $url;
}

/** Участники с хотя бы одним прогнозом — только с подтверждённой оплатой. */
function transparent_participant_users(): array
{
    $stmt = db()->query(
        "SELECT DISTINCT u.id, u.name, u.payment_status, u.created_at
         FROM users u
         INNER JOIN predictions p ON p.user_id = u.id
         WHERE " . ranked_participant_where('u') . "
         ORDER BY u.name ASC"
    );

    return $stmt->fetchAll();
}

/**
 * @param list<int> $userIds
 * @return array<int, string>
 */
function transparent_champion_teams_by_user_ids(array $userIds): array
{
    if (!champion_prediction_locked() || $userIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = db()->prepare(
        "SELECT cp.user_id, t.name AS team_name
         FROM champion_predictions cp
         JOIN teams t ON t.id = cp.team_id
         WHERE cp.user_id IN ($placeholders)"
    );
    $stmt->execute($userIds);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['user_id']] = (string) $row['team_name'];
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function transparent_participants_overview(): array
{
    $users = transparent_participant_users();
    if ($users === []) {
        return [];
    }

    $statsById = participant_leaderboard_stats_by_user_ids(array_map(static fn (array $row): int => (int) $row['id'], $users));
    $championTeams = transparent_champion_teams_by_user_ids(array_keys($statsById));

    $overview = [];
    foreach ($users as $user) {
        $id = (int) $user['id'];
        $stat = $statsById[$id] ?? [
            'id' => $id,
            'name' => (string) $user['name'],
            'total_points' => 0,
            'match_points' => 0,
            'champion_points' => 0,
            'predictions_count' => 0,
            'exact_scores_count' => 0,
            'outcomes_count' => 0,
            'created_at' => (string) $user['created_at'],
        ];
        $overview[] = array_merge($stat, [
            'payment_status' => (string) $user['payment_status'],
            'champion_team' => $championTeams[$id] ?? null,
        ]);
    }

    usort($overview, static function (array $a, array $b): int {
        $cmp = (int) $b['total_points'] <=> (int) $a['total_points'];
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = (int) $b['exact_scores_count'] <=> (int) $a['exact_scores_count'];
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = (int) $b['outcomes_count'] <=> (int) $a['outcomes_count'];
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
    });

    foreach ($overview as $index => &$row) {
        $row['rank'] = $index + 1;
    }
    unset($row);

    return $overview;
}

function transparent_participant_summary(int $userId): ?array
{
    $overview = transparent_participants_overview();
    foreach ($overview as $row) {
        if ((int) $row['id'] === $userId) {
            return [
                'rank' => (int) $row['rank'],
                'total_participants' => count($overview),
                'total_points' => (int) $row['total_points'],
                'match_points' => (int) $row['match_points'],
                'champion_points' => (int) $row['champion_points'],
                'exact_scores_count' => (int) $row['exact_scores_count'],
                'outcomes_count' => (int) $row['outcomes_count'],
                'predictions_count' => (int) $row['predictions_count'],
                'champion_team' => $row['champion_team'] ?? null,
                'payment_status' => (string) ($row['payment_status'] ?? ''),
            ];
        }
    }

    return null;
}

function public_participant(int $userId): ?array
{
    $stmt = db()->prepare(
        "SELECT id, name, payment_status, role, created_at
         FROM users
         WHERE id = ? AND " . ranked_participant_where('') . "
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function public_participant_future_predictions_count(int $userId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         WHERE p.user_id = ? AND m.starts_at > NOW()"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

/** @return list<array<string, mixed>> */
function public_participant_predictions(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT p.*, m.stage, m.starts_at, m.home_score AS result_home_score, m.away_score AS result_away_score,
                m.status AS match_status,
                ht.name AS home_team, at.name AS away_team,
                COALESCE(s.points, 0) AS points,
                s.reason
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
         WHERE p.user_id = ? AND m.starts_at <= NOW()
         ORDER BY m.starts_at ASC"
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function transparent_started_match_stage_key(?string $raw): string
{
    $key = trim((string) $raw);

    return $key === '' ? 'all' : $key;
}

/** @return list<array<string, mixed>> */
function transparent_started_match_stages(): array
{
    $stmt = db()->query(
        "SELECT DISTINCT m.stage
         FROM matches m
         WHERE m.starts_at <= NOW()
           AND m.home_team_id IS NOT NULL
           AND m.away_team_id IS NOT NULL
         ORDER BY m.stage ASC"
    );

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** @return list<array<string, mixed>> */
function transparent_started_matches(?string $stageKey = null): array
{
    $stageKey = transparent_started_match_stage_key($stageKey);
    $sql = "SELECT m.id, m.stage, m.starts_at, m.home_score, m.away_score, m.status,
                   ht.name AS home_team, at.name AS away_team,
                   ht.code AS home_code, at.code AS away_code
            FROM matches m
            JOIN teams ht ON ht.id = m.home_team_id
            JOIN teams at ON at.id = m.away_team_id
            WHERE m.starts_at <= NOW()
              AND m.home_team_id IS NOT NULL
              AND m.away_team_id IS NOT NULL";
    $params = [];
    if ($stageKey !== 'all') {
        $sql .= ' AND m.stage = ?';
        $params[] = $stageKey;
    }
    $sql .= ' ORDER BY m.starts_at ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * @return array{
 *   participants: list<array<string, mixed>>,
 *   matches: list<array<string, mixed>>,
 *   cells: array<int, array<int, array<string, mixed>>>
 * }
 */
function transparent_predictions_matrix(?string $stageKey = null): array
{
    $participants = transparent_participants_overview();
    $matches = transparent_started_matches($stageKey);
    $matchIds = array_map(static fn (array $row): int => (int) $row['id'], $matches);
    if ($matchIds === []) {
        return ['participants' => $participants, 'matches' => [], 'cells' => []];
    }

    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $stmt = db()->prepare(
        "SELECT p.user_id, p.match_id, p.home_score, p.away_score,
                COALESCE(s.points, 0) AS points, s.reason,
                m.home_score AS result_home_score, m.away_score AS result_away_score
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
         WHERE p.match_id IN ($placeholders)"
    );
    $stmt->execute($matchIds);

    $cells = [];
    foreach ($stmt->fetchAll() as $row) {
        $cells[(int) $row['user_id']][(int) $row['match_id']] = $row;
    }

    return ['participants' => $participants, 'matches' => $matches, 'cells' => $cells];
}

/** @return list<array<string, mixed>> */
function transparent_champion_predictions(): array
{
    if (!champion_prediction_locked()) {
        return [];
    }

    $stmt = db()->query(
        "SELECT u.id AS user_id, u.name, u.payment_status, t.name AS team_name, cp.points
         FROM champion_predictions cp
         JOIN users u ON u.id = cp.user_id
         JOIN teams t ON t.id = cp.team_id
         WHERE " . ranked_participant_where('u') . "
         ORDER BY u.name ASC"
    );

    return $stmt->fetchAll();
}

/** @return list<array<string, mixed>> */
function transparent_champion_team_distribution(): array
{
    if (!champion_prediction_locked()) {
        return [];
    }

    $stmt = db()->query(
        "SELECT t.name AS team_name, COUNT(*) AS cnt
         FROM champion_predictions cp
         JOIN teams t ON t.id = cp.team_id
         JOIN users u ON u.id = cp.user_id
         WHERE " . ranked_participant_where('u') . "
         GROUP BY t.id, t.name
         ORDER BY cnt DESC, t.name ASC"
    );

    return $stmt->fetchAll();
}

function participant_back_navigation(): array
{
    $presets = [
        'predictions' => ['url' => '/predictions', 'label' => 'К прогнозам'],
        'rating' => ['url' => '/rating', 'label' => 'К рейтингу'],
        'match' => ['url' => '/matches?filter=results', 'label' => 'К матчам'],
        'matrix' => ['url' => '/predictions?tab=matrix', 'label' => 'К матрице'],
        'my-scores' => ['url' => '/my-scores', 'label' => 'К моим очкам'],
    ];

    $from = trim((string) ($_GET['from'] ?? ''));
    if (isset($presets[$from])) {
        return $presets[$from];
    }

    return $presets['predictions'];
}

/** Ссылка на страницу матча с меткой источника для кнопки «Назад». */
function match_url(int $matchId, ?string $from = null, ?string $scheduleFilter = null, ?int $leagueId = null): string
{
    $url = '/match?id=' . $matchId;
    $allowed = ['matches', 'dashboard', 'home', 'tournament', 'my-scores', 'rating', 'predictions', 'participant', 'mini-league'];
    if ($from !== null && $from !== '' && in_array($from, $allowed, true)) {
        $url .= '&from=' . rawurlencode($from);
        if ($from === 'matches') {
            $filter = matches_schedule_filter_key($scheduleFilter);
            $url .= '&filter=' . rawurlencode($filter);
        }
        if ($from === 'mini-league' && $leagueId !== null && $leagueId > 0) {
            $url .= '&league_id=' . $leagueId;
        }
    }

    return $url;
}

/** Куда вести кнопку «Назад» со страницы матча. */
function match_back_navigation(): array
{
    $presets = [
        'matches' => ['url' => '/matches', 'label' => 'К расписанию'],
        'dashboard' => ['url' => '/dashboard#dashboard-predictions', 'label' => 'К прогнозам'],
        'home' => ['url' => '/#home-predictions', 'label' => 'На главную'],
        'tournament' => ['url' => '/tournament', 'label' => 'К турниру'],
        'my-scores' => ['url' => '/my-scores', 'label' => 'К моим очкам'],
        'rating' => ['url' => '/rating', 'label' => 'К рейтингу'],
        'predictions' => ['url' => '/predictions', 'label' => 'К прогнозам'],
        'participant' => ['url' => '/predictions', 'label' => 'К прогнозам'],
        'mini-league' => ['url' => '/mini-leagues', 'label' => 'К мини-лиге'],
    ];

    $from = trim((string) ($_GET['from'] ?? ''));
    if ($from === 'mini-league') {
        $leagueId = (int) ($_GET['league_id'] ?? 0);
        $tab = mini_league_tab_key($_GET['tab'] ?? 'matrix');
        $stageKey = transparent_started_match_stage_key($_GET['stage'] ?? null);
        if ($leagueId > 0) {
            return [
                'url' => mini_league_url($leagueId, $tab, $stageKey !== 'all' ? $stageKey : null),
                'label' => 'К мини-лиге',
            ];
        }

        return $presets['mini-league'];
    }
    if ($from === 'matches') {
        $filter = matches_schedule_filter_key($_GET['filter'] ?? null);

        return ['url' => '/matches?filter=' . rawurlencode($filter), 'label' => 'К расписанию'];
    }
    if (isset($presets[$from])) {
        return $presets[$from];
    }

    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($ref !== '' && $host !== '') {
        $refHost = parse_url($ref, PHP_URL_HOST);
        if ($refHost === null || strcasecmp((string) $refHost, $host) === 0) {
            $refPath = parse_url($ref, PHP_URL_PATH) ?: '';
            $refQuery = parse_url($ref, PHP_URL_QUERY);
            if ($refPath === '/matches') {
                $filter = 'soon';
                if (is_string($refQuery) && $refQuery !== '') {
                    parse_str($refQuery, $refParams);
                    if (is_array($refParams)) {
                        $filter = matches_schedule_filter_key($refParams['filter'] ?? null);
                    }
                }

                return ['url' => '/matches?filter=' . rawurlencode($filter), 'label' => 'К расписанию'];
            }
            if ($refPath === '/dashboard') {
                $url = '/dashboard';
                if (is_string($refQuery) && $refQuery !== '') {
                    $url .= '?' . $refQuery;
                }

                return ['url' => $url . '#dashboard-predictions', 'label' => 'К прогнозам'];
            }
            if ($refPath === '/' || $refPath === '') {
                return $presets['home'];
            }
            if ($refPath === '/tournament') {
                return $presets['tournament'];
            }
            if ($refPath === '/my-scores') {
                return $presets['my-scores'];
            }
            if ($refPath === '/rating' || $refPath === '/leaderboard') {
                return $presets['rating'];
            }
            if ($refPath === '/predictions') {
                return $presets['predictions'];
            }
            if ($refPath === '/participant') {
                return $presets['participant'];
            }
            if ($refPath === '/mini-league') {
                $url = '/mini-league';
                if (is_string($refQuery) && $refQuery !== '') {
                    $url .= '?' . $refQuery;
                }

                return ['url' => $url, 'label' => 'К мини-лиге'];
            }
        }
    }

    return $presets['matches'];
}

/**
 * После входа или регистрации по ссылке-приглашению: вступить в мини-лигу и вернуть URL редиректа.
 * Сама ссылка задаётся через $_SESSION['pending_mini_league_invite'] (код приглашения).
 */
function complete_pending_mini_league_join_for_user(int $userId): ?string
{
    $raw = $_SESSION['pending_mini_league_invite'] ?? null;
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $code = strtoupper(trim($raw));
    if ($code === '' || mb_strlen($code) > 16) {
        unset($_SESSION['pending_mini_league_invite']);

        return null;
    }

    $league = find_mini_league_by_code($code);
    if (!$league) {
        unset($_SESSION['pending_mini_league_invite']);
        flash('error', 'Приглашение в мини-лигу недействительно или срок истёк.');

        return null;
    }

    unset($_SESSION['pending_mini_league_invite']);

    $stmt = db()->prepare(
        'INSERT IGNORE INTO mini_league_members (league_id, user_id, created_at) VALUES (?, ?, NOW())'
    );
    $stmt->execute([(int) $league['id'], $userId]);

    flash('success', 'Вы в мини-лиге «' . $league['name'] . '».');

    return '/mini-league?id=' . (int) $league['id'];
}

function payment_receipt_max_bytes(): int
{
    return 10 * 1024 * 1024;
}

function payment_receipt_storage_root(): string
{
    return dirname(__DIR__) . '/storage';
}

function payment_receipt_storage_subdir(): string
{
    return 'payment_receipts';
}

/** @return array<string, string> mime => extension */
function payment_receipt_allowed_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
}

function user_can_upload_payment_receipt(array $user): bool
{
    return ($user['role'] ?? '') === 'participant'
        && ($user['payment_status'] ?? '') === 'pending_payment';
}

function payment_receipt_for_user(int $userId): ?array
{
    if (!db_table_exists('payment_receipts')) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT * FROM payment_receipts WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function payment_receipt_absolute_path(string $relativePath): string
{
    $norm = str_replace(["\0", '\\'], ['', '/'], $relativePath);
    if (str_contains($norm, '..')) {
        throw new InvalidArgumentException('Некорректный путь к файлу.');
    }

    return payment_receipt_storage_root() . '/' . ltrim($norm, '/');
}

function delete_payment_receipt_file(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    try {
        $full = payment_receipt_absolute_path($relativePath);
    } catch (InvalidArgumentException) {
        return;
    }

    $root = realpath(payment_receipt_storage_root());
    $real = realpath($full);
    if ($root !== false && $real !== false && str_starts_with($real, $root) && is_file($real)) {
        @unlink($real);
    }
}

/** Отправить файл чека клиенту (только после проверки прав администратора). */
function output_payment_receipt_http(array $receipt): void
{
    $full = payment_receipt_absolute_path((string) ($receipt['file_path'] ?? ''));
    $root = realpath(payment_receipt_storage_root());
    $real = realpath($full);
    if ($real === false || $root === false || !str_starts_with($real, $root) || !is_readable($real)) {
        http_response_code(404);
        exit('Файл не найден');
    }

    $mime = (string) (($receipt['mime_type'] ?? '') ?: 'application/octet-stream');
    $orig = (string) (($receipt['original_name'] ?? '') ?: 'receipt');

    $length = filesize($real);
    if ($length !== false) {
        header('Content-Length: ' . $length);
    }

    header('Content-Type: ' . $mime);

    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $orig) ?: 'receipt';
    $utf8 = rawurlencode($orig);
    header(
        'Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $ascii) . '"; filename*=UTF-8\'\'' . $utf8
    );

    readfile($real);
    exit;
}

/**
 * Одна запись на пользователя; повторная загрузка заменяет файл на диске и в БД.
 *
 * @param array|null $file элемент {@see $_FILES} (например, receipt)
 */
function save_payment_receipt_from_upload(int $userId, ?array $file): void
{
    if ($file === null || !isset($file['tmp_name'])) {
        throw new InvalidArgumentException('Выберите файл чека.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('Файл слишком большой для сервера. Уменьшите размер или обратитесь к организаторам.');
        }

        throw new InvalidArgumentException('Не удалось загрузить файл. Попробуйте ещё раз.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Ошибка загрузки файла.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > payment_receipt_max_bytes()) {
        throw new InvalidArgumentException('Размер файла не должен превышать 10 МБ.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $allowed = payment_receipt_allowed_mimes();
    if (!isset($allowed[$mime])) {
        throw new InvalidArgumentException('Допустимые форматы: JPG, PNG или PDF.');
    }

    $ext = $allowed[$mime];
    $origName = (string) ($file['name'] ?? 'receipt');
    if (mb_strlen($origName) > 220) {
        $origName = mb_substr($origName, 0, 220);
    }

    $subdir = payment_receipt_storage_subdir();
    $dir = payment_receipt_storage_root() . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать каталог для файлов.');
    }

    $relative = $subdir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = payment_receipt_absolute_path($relative);

    $existing = payment_receipt_for_user($userId);

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new InvalidArgumentException('Не удалось сохранить файл.');
    }

    try {
        $stmt = db()->prepare(
            "INSERT INTO payment_receipts (user_id, file_path, original_name, mime_type, size_bytes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), original_name = VALUES(original_name),
                mime_type = VALUES(mime_type), size_bytes = VALUES(size_bytes), updated_at = NOW()"
        );
        $stmt->execute([$userId, $relative, $origName, $mime, $size]);
    } catch (Throwable $e) {
        @unlink($dest);
        throw $e;
    }

    if ($existing && ($existing['file_path'] ?? '') !== '' && ($existing['file_path'] ?? '') !== $relative) {
        delete_payment_receipt_file((string) $existing['file_path']);
    }
}

function champion_poll_options(): array
{
    return [
        'esp' => ['name' => 'Испания', 'code' => 'ESP', 'base_votes' => 0],
        'fra' => ['name' => 'Франция', 'code' => 'FRA', 'base_votes' => 0],
        'arg' => ['name' => 'Аргентина', 'code' => 'ARG', 'base_votes' => 0],
        'eng' => ['name' => 'Англия', 'code' => 'ENG', 'base_votes' => 0],
        'por' => ['name' => 'Португалия', 'code' => 'POR', 'base_votes' => 0],
        'ned' => ['name' => 'Нидерланды', 'code' => 'NED', 'base_votes' => 0],
        'bra' => ['name' => 'Бразилия', 'code' => 'BRA', 'base_votes' => 0],
        'ger' => ['name' => 'Германия', 'code' => 'GER', 'base_votes' => 0],
        'other' => ['name' => 'Кто-то другой', 'code' => null, 'base_votes' => 0],
    ];
}

function champion_poll_results(): array
{
    $options = champion_poll_options();
    
    $dbVotes = [];
    if (db_table_exists('champion_poll_votes')) {
        $stmt = db()->query('SELECT option_key, COUNT(*) AS cnt FROM champion_poll_votes GROUP BY option_key');
        foreach ($stmt->fetchAll() as $row) {
            $dbVotes[$row['option_key']] = (int) $row['cnt'];
        }
    }

    $totalVotes = 0;
    $results = [];
    foreach ($options as $key => $opt) {
        $actualVotes = $dbVotes[$key] ?? 0;
        $total = $opt['base_votes'] + $actualVotes;
        $totalVotes += $total;
        $results[$key] = [
            'key' => $key,
            'name' => $opt['name'],
            'code' => $opt['code'],
            'votes' => $total,
        ];
    }

    $totalVotesReal = $totalVotes;
    $totalVotes = max(1, $totalVotes);
    foreach ($results as $key => &$res) {
        $res['percent'] = $totalVotesReal === 0 ? 0 : (int) round(($res['votes'] / $totalVotes) * 100);
    }
    unset($res);

    $sum = 0;
    foreach ($results as $res) {
        $sum += $res['percent'];
    }
    $diff = 100 - $sum;
    if ($totalVotesReal > 0 && $diff !== 0 && count($results) > 0) {
        $maxKey = null;
        $maxVotes = -1;
        foreach ($results as $key => $res) {
            if ($res['votes'] > $maxVotes) {
                $maxVotes = $res['votes'];
                $maxKey = $key;
            }
        }
        if ($maxKey !== null) {
            $results[$maxKey]['percent'] += $diff;
        }
    }

    uasort($results, static function (array $a, array $b): int {
        return $b['votes'] <=> $a['votes'];
    });

    return [
        'total' => $totalVotesReal,
        'options' => $results,
    ];
}

function user_has_voted_champion_poll(): bool
{
    $cookieName = 'voted_champion_poll';
    if (isset($_COOKIE[$cookieName])) {
        return true;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip && db_table_exists('champion_poll_votes')) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM champion_poll_votes WHERE ip_address = ?');
        $stmt->execute([$ip]);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    return false;
}

