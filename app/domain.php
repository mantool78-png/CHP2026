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

function find_match(int $id): ?array
{
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
                at.form_last5 AS away_form_last5
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

function prediction_locked(array $match): bool
{
    if (($match['status'] ?? '') === 'finished') {
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
        $stmt = db()->query('SELECT starts_at FROM matches ORDER BY starts_at ASC LIMIT 1');
        $startsAt = $stmt->fetchColumn();
    }

    return $startsAt ?: null;
}

function champion_prediction_locked(): bool
{
    $deadline = champion_prediction_deadline();

    return $deadline !== null && time() >= strtotime($deadline);
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

    return free_predictions_remaining((int) $user['id']) > 0;
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

/** Статистика регистраций для блока «Уже в игре» на главной. */
function contest_registration_stats(int $recentLimit = 8): array
{
    $recentLimit = max(1, min(20, $recentLimit));

    $total = (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status <> 'blocked'"
    )->fetchColumn();

    $active = (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status = 'active'"
    )->fetchColumn();

    $stmt = db()->prepare(
        "SELECT name FROM users
         WHERE role = 'participant' AND payment_status <> 'blocked'
         ORDER BY created_at DESC
         LIMIT ?"
    );
    $stmt->execute([$recentLimit]);

    return [
        'total_participants' => $total,
        'active_participants' => $active,
        'recent_participants' => $stmt->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function leaderboard(): array
{
    $stmt = db()->query(
        "SELECT u.id, u.name,
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
         WHERE u.role = 'participant' AND u.payment_status = 'active'
         ORDER BY total_points DESC,
                  exact_scores_count DESC,
                  outcomes_count DESC,
                  u.created_at ASC"
    );

    return $stmt->fetchAll();
}

/**
 * Те же метрики, что в общей таблице лидеров, но для списка id участников
 * без фильтра по оплате — для отображения мини-лиг (пробный режим, ожидание оплаты).
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
         WHERE u.role = 'participant' AND u.id IN ($placeholders)"
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

/** Реальные сборные для прогноза на чемпиона (без слотов «N-е место группы…» из расписания). */
function team_is_champion_pick_candidate(array $team): bool
{
    $name = trim((string) ($team['name'] ?? ''));
    if ($name === '') {
        return false;
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

    if (strpos($lower, 'место') !== false && strpos($lower, 'групп') !== false) {
        return false;
    }

    if (strpos($lower, 'победитель') !== false && strpos($lower, 'групп') !== false) {
        return false;
    }

    if (strpos($lower, 'участник') !== false && strpos($lower, 'стыков') !== false) {
        return false;
    }

    if (strpos($lower, 'проигравш') !== false) {
        return false;
    }

    if (preg_match('/^\d+[\s\-–—]*[еёя]\s+место/u', $name)) {
        return false;
    }

    return true;
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
    if ($extra) {
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

/** Ссылка на страницу матча с меткой источника для кнопки «Назад». */
function match_url(int $matchId, ?string $from = null): string
{
    $url = '/match?id=' . $matchId;
    $allowed = ['matches', 'dashboard', 'home', 'tournament', 'my-scores', 'rating'];
    if ($from !== null && $from !== '' && in_array($from, $allowed, true)) {
        $url .= '&from=' . rawurlencode($from);
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
    ];

    $from = trim((string) ($_GET['from'] ?? ''));
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
                return $presets['matches'];
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
    $stmt = db()->prepare('SELECT * FROM payment_receipts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);

    $row = $stmt->fetch();

    return $row ?: null;
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
        'Content-Disposition: inline; filename="' . str_replace('"', '\\"', $ascii) . '"; filename*=UTF-8\'\'' . $utf8
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
