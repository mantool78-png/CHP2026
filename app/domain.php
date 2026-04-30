<?php

declare(strict_types=1);

function upcoming_matches(): array
{
    $stmt = db()->query(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         ORDER BY m.starts_at ASC"
    );

    return $stmt->fetchAll();
}

function find_match(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.id = ?
         LIMIT 1"
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

function prediction_locked(array $match): bool
{
    $lockAt = strtotime($match['starts_at']) - ((int) config('app.prediction_lock_minutes') * 60);
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
                   SUM(CASE WHEN reason IN ('Точный счет', 'Угадан исход') THEN 1 ELSE 0 END) AS outcomes_count
            FROM scores
            GROUP BY user_id
         ) ms ON ms.user_id = u.id
         LEFT JOIN champion_predictions cp ON cp.user_id = u.id
         WHERE u.role = 'participant' AND u.payment_status = 'active'
         ORDER BY total_points DESC,
                  exact_scores_count DESC,
                  outcomes_count DESC,
                  predictions_count ASC,
                  u.created_at ASC"
    );

    return $stmt->fetchAll();
}

function participant_badges(int $userId): array
{
    $predictionCount = user_predictions_count($userId);
    $championPrediction = user_champion_prediction($userId);
    $summary = participant_summary($userId);

    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN reason = 'Точный счет' THEN 1 ELSE 0 END), 0) AS exact_scores_count,
            COALESCE(SUM(CASE WHEN reason IN ('Точный счет', 'Угадан исход') THEN 1 ELSE 0 END), 0) AS outcomes_count
         FROM scores
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $scoreStats = $stmt->fetch() ?: ['exact_scores_count' => 0, 'outcomes_count' => 0];

    $exactScores = (int) $scoreStats['exact_scores_count'];
    $outcomes = (int) $scoreStats['outcomes_count'];

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
            'description' => 'Попасть в топ-10 таблицы.',
            'earned' => $summary !== null && (int) $summary['rank'] <= 10,
        ],
    ];
}

function prize_pool(): int
{
    $stmt = db()->query("SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status = 'active'");
    $participants = (int) $stmt->fetchColumn();

    return (int) floor($participants * (int) config('app.entry_fee_rub') * ((int) config('app.prize_pool_percent') / 100));
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
    $leaders = [];
    $memberIds = [];
    foreach (leaderboard() as $leader) {
        $leaders[(int) $leader['id']] = $leader;
    }

    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.created_at
         FROM mini_league_members mlm
         JOIN users u ON u.id = mlm.user_id
         WHERE mlm.league_id = ?
         ORDER BY u.created_at ASC"
    );
    $stmt->execute([$leagueId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $member) {
        $memberId = (int) $member['id'];
        $memberIds[] = $memberId;
        $rows[] = $leaders[$memberId] ?? [
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
            ?: ((int) $a['predictions_count'] <=> (int) $b['predictions_count'])
            ?: strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
    });

    return $rows;
}
