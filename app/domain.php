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
