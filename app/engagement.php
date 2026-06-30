<?php

declare(strict_types=1);

/** @return list<array{key: string, title: string, amount_rub: int, description: string}> */
function stage_prize_definitions(): array
{
    $configured = config('app.stage_prizes', null);
    if (is_array($configured) && $configured !== []) {
        return $configured;
    }

    return [
        [
            'key' => 'group_round_1',
            'title' => 'Лучший 1-й тур',
            'amount_rub' => 1000,
            'description' => 'Больше всех очков за матчи 1-го тура группового этапа (матчи 1–24).',
        ],
        [
            'key' => 'group_round_2',
            'title' => 'Лучший 2-й тур',
            'amount_rub' => 1000,
            'description' => 'Больше всех очков за матчи 2-го тура группового этапа (матчи 25–48).',
        ],
        [
            'key' => 'group_round_3',
            'title' => 'Лучший 3-й тур',
            'amount_rub' => 1000,
            'description' => 'Больше всех очков за матчи 3-го тура группового этапа (матчи 49–72).',
        ],
        [
            'key' => 'round32',
            'title' => 'Лучший 1/16 финала',
            'amount_rub' => 1000,
            'description' => 'Больше всех очков за матчи 1/16 финала.',
        ],
        [
            'key' => 'round16',
            'title' => 'Лучший 1/8 финала',
            'amount_rub' => 1000,
            'description' => 'Больше всех очков за матчи 1/8 финала.',
        ],
        [
            'key' => 'knockout_late',
            'title' => 'Лучший финальный отрезок',
            'amount_rub' => 1000,
            'description' => 'Максимум очков за четвертьфиналы, полуфиналы, матч за 3-е место и финал.',
        ],
    ];
}

function stage_prize_pool_total(): int
{
    return array_sum(array_column(stage_prize_definitions(), 'amount_rub'));
}

function stage_prize_pool_count(): int
{
    return count(stage_prize_definitions());
}

/** @return list<string> */
function engagement_stage_prize_keys(): array
{
    return array_column(stage_prize_definitions(), 'key');
}

function engagement_stage_prize_tab_short_label(string $key): string
{
    return match ($key) {
        'group_round_1' => '1-й тур',
        'group_round_2' => '2-й тур',
        'group_round_3' => '3-й тур',
        'round32' => '1/16',
        'round16' => '1/8',
        'knockout_late' => 'Финал',
        default => $key,
    };
}

function engagement_default_stage_prize_tab(): string
{
    foreach (engagement_stage_prizes_overview() as $row) {
        if (($row['status'] ?? '') === 'in_progress') {
            return (string) $row['key'];
        }
    }

    $overview = engagement_stage_prizes_overview();
    for ($i = count($overview) - 1; $i >= 0; $i--) {
        if (($overview[$i]['status'] ?? '') === 'completed') {
            return (string) $overview[$i]['key'];
        }
    }

    $keys = engagement_stage_prize_keys();

    return $keys[0] ?? 'group_round_1';
}

/**
 * @return list<array<string,mixed>>
 */
function engagement_stage_prize_leaderboard(string $key, int $limit = 50): array
{
    $matchIds = engagement_stage_prize_matches($key, true);
    if ($matchIds === []) {
        return [];
    }

    return array_slice(engagement_leaderboard_for_matches($matchIds), 0, max(1, $limit));
}

/** @return array<string,mixed>|null */
function engagement_current_stage_prize_snapshot(): ?array
{
    foreach (engagement_stage_prizes_overview() as $row) {
        if (($row['status'] ?? '') !== 'in_progress') {
            continue;
        }

        $key = (string) $row['key'];
        $out = [
            'key' => $key,
            'title' => (string) $row['title'],
            'short_label' => engagement_stage_prize_tab_short_label($key),
            'matches_finished' => (int) $row['matches_finished'],
            'matches_total' => (int) $row['matches_total'],
            'leader' => null,
        ];

        if (!empty($row['leader'])) {
            $leader = $row['leader'];
            $out['leader'] = [
                'name' => (string) $leader['name'],
                'user_id' => (int) $leader['user_id'],
                'match_points' => (int) $leader['match_points'],
            ];
        }

        return $out;
    }

    return null;
}

function engagement_fifa_match_number_from_stage(string $stage): ?int
{
    if (!preg_match('/матч\s+(\d+)/u', $stage, $matches)) {
        return null;
    }

    $num = (int) $matches[1];

    return $num > 0 ? $num : null;
}

/**
 * Тур группового этапа ЧМ-2026 по официальному номеру матча в стадии.
 * 1-й тур: матчи 1–24, 2-й: 25–48, 3-й: 49–72.
 */
function engagement_group_round_from_stage(string $stage): ?int
{
    $num = engagement_fifa_match_number_from_stage($stage);
    if ($num === null) {
        return null;
    }
    if ($num >= 1 && $num <= 24) {
        return 1;
    }
    if ($num >= 25 && $num <= 48) {
        return 2;
    }
    if ($num >= 49 && $num <= 72) {
        return 3;
    }

    return null;
}

/** @return list<int> */
function engagement_finished_match_ids_for_group_round(int $round): array
{
    if ($round < 1 || $round > 3) {
        return [];
    }

    return engagement_stage_prize_matches('group_round_' . $round, true);
}

function engagement_match_is_group_stage(array $match): bool
{
    $stage = (string) ($match['stage'] ?? '');

    return str_starts_with($stage, 'Групповой')
        || str_contains($stage, 'Групповой этап');
}

function engagement_stage_prize_match_in_phase(array $match, string $key): bool
{
    $stage = (string) ($match['stage'] ?? '');

    return match ($key) {
        'group_round_1' => engagement_match_is_group_stage($match)
            && engagement_group_round_from_stage($stage) === 1,
        'group_round_2' => engagement_match_is_group_stage($match)
            && engagement_group_round_from_stage($stage) === 2,
        'group_round_3' => engagement_match_is_group_stage($match)
            && engagement_group_round_from_stage($stage) === 3,
        'round32' => str_contains($stage, '1/16'),
        'round16' => str_contains($stage, '1/8'),
        'knockout_late' => preg_match('/(Четверть|Полуфинал|Финал|3 место)/u', $stage) === 1,
        default => false,
    };
}

function engagement_stage_prize_matches(string $key, bool $finishedOnly = false): array
{
    $ids = [];
    foreach (matches_schedule_rows() as $match) {
        if (!match_slot_has_teams($match)) {
            continue;
        }
        if (!engagement_stage_prize_match_in_phase($match, $key)) {
            continue;
        }
        if ($finishedOnly && !match_is_finished_for_schedule($match)) {
            continue;
        }
        $ids[] = (int) $match['id'];
    }

    return $ids;
}

/** @return 'upcoming'|'in_progress'|'completed' */
function engagement_stage_prize_status(string $key): string
{
    $total = count(engagement_stage_prize_matches($key, false));
    $finished = count(engagement_stage_prize_matches($key, true));

    if ($total === 0 || $finished === 0) {
        return 'upcoming';
    }
    if ($finished < $total) {
        return 'in_progress';
    }

    return 'completed';
}

function stage_prize_status_label(string $status): string
{
    return match ($status) {
        'completed' => 'Приз определён',
        'in_progress' => 'Идёт этап',
        default => 'Ожидается',
    };
}

function stage_prize_holder_label(string $status): string
{
    return $status === 'completed' ? 'Победитель этапа' : 'Сейчас лидирует';
}

/**
 * @return list<array{
 *     key: string,
 *     title: string,
 *     amount_rub: int,
 *     description: string,
 *     status: string,
 *     status_label: string,
 *     holder_label: string,
 *     matches_total: int,
 *     matches_finished: int,
 *     leader: ?array{user_id:int,name:string,match_points:int,exact_scores_count:int,outcomes_count:int}
 * }>
 */
function engagement_stage_prizes_overview(): array
{
    $out = [];
    foreach (stage_prize_definitions() as $prize) {
        $key = (string) $prize['key'];
        $status = engagement_stage_prize_status($key);
        $leader = engagement_stage_prize_leader($key);

        $out[] = [
            'key' => $key,
            'title' => (string) $prize['title'],
            'amount_rub' => (int) $prize['amount_rub'],
            'description' => (string) $prize['description'],
            'status' => $status,
            'status_label' => stage_prize_status_label($status),
            'holder_label' => stage_prize_holder_label($status),
            'matches_total' => count(engagement_stage_prize_matches($key, false)),
            'matches_finished' => count(engagement_stage_prize_matches($key, true)),
            'leader' => $leader,
        ];
    }

    return $out;
}

/** @return list<int> */
function engagement_finished_match_ids_for_msk_date(string $dateYmd): array
{
    if ($dateYmd === '') {
        return [];
    }
    $ids = [];
    foreach (matches_schedule_rows() as $match) {
        if (!match_is_finished_for_schedule($match)) {
            continue;
        }
        if (match_starts_at_msk_date($match) === $dateYmd) {
            $ids[] = (int) $match['id'];
        }
    }

    return $ids;
}

/** @return list<int> */
function engagement_finished_match_ids_for_phase(string $phase): array
{
    $ids = [];
    foreach (matches_schedule_rows() as $match) {
        if (!match_is_finished_for_schedule($match)) {
            continue;
        }
        $stage = (string) ($match['stage'] ?? '');
        $isGroup = engagement_match_is_group_stage($match);
        if ($phase === 'group' && $isGroup) {
            $ids[] = (int) $match['id'];
        } elseif ($phase === 'playoff' && !$isGroup) {
            $ids[] = (int) $match['id'];
        } elseif ($phase === 'round32' && str_contains($stage, '1/16')) {
            $ids[] = (int) $match['id'];
        } elseif ($phase === 'round16' && str_contains($stage, '1/8')) {
            $ids[] = (int) $match['id'];
        } elseif ($phase === 'knockout_late' && preg_match('/(Четверть|Полуфинал|Финал|3 место)/u', $stage) === 1) {
            $ids[] = (int) $match['id'];
        }
    }

    return $ids;
}

/** @return list<string> */
function engagement_match_days_with_results(): array
{
    $days = [];
    foreach (matches_schedule_rows() as $match) {
        if (!match_is_finished_for_schedule($match)) {
            continue;
        }
        $day = match_starts_at_msk_date($match);
        if ($day !== '') {
            $days[$day] = true;
        }
    }
    $list = array_keys($days);
    sort($list);

    return $list;
}

function engagement_latest_match_day(): ?string
{
    $days = engagement_match_days_with_results();

    return $days === [] ? null : $days[array_key_last($days)];
}

function engagement_match_day_label(string $dateYmd): string
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
    $yesterday = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->modify('-1 day')->format('Y-m-d');
    $formatted = (new DateTimeImmutable($dateYmd, new DateTimeZone('Europe/Moscow')))->format('d.m.Y');
    if ($dateYmd === $today) {
        return 'Сегодня, ' . $formatted;
    }
    if ($dateYmd === $yesterday) {
        return 'Вчера, ' . $formatted;
    }

    return $formatted;
}

/**
 * @param list<int> $matchIds
 * @return list<array<string,mixed>>
 */
function engagement_leaderboard_for_matches(array $matchIds): array
{
    if ($matchIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.payment_status,
                COALESCE(SUM(s.points), 0) AS match_points,
                SUM(CASE WHEN s.reason = 'Точный счет' THEN 1 ELSE 0 END) AS exact_scores_count,
                SUM(CASE WHEN s.reason = 'Угадан исход' THEN 1 ELSE 0 END) AS outcomes_count
         FROM users u
         INNER JOIN scores s ON s.user_id = u.id AND s.match_id IN ($placeholders)
         WHERE u.role = 'participant' AND u.payment_status = 'active'
         GROUP BY u.id, u.name, u.payment_status, u.created_at
         ORDER BY match_points DESC, exact_scores_count DESC, outcomes_count DESC, u.created_at ASC"
    );
    $stmt->execute($matchIds);

    return $stmt->fetchAll();
}

/** @return list<array<string,mixed>> */
function engagement_leaderboard_for_msk_date(string $dateYmd): array
{
    return engagement_leaderboard_for_matches(engagement_finished_match_ids_for_msk_date($dateYmd));
}

/** @return list<array<string,mixed>> */
function engagement_leaderboard_group_stage(): array
{
    return engagement_leaderboard_for_matches(engagement_finished_match_ids_for_phase('group'));
}

/** @return list<array<string,mixed>> */
function engagement_leaderboard_playoff(): array
{
    return engagement_leaderboard_for_matches(engagement_finished_match_ids_for_phase('playoff'));
}

/** @return array<string,mixed>|null */
function engagement_expert_of_match_day(?string $dateYmd = null): ?array
{
    $dateYmd = $dateYmd ?? engagement_latest_match_day();
    if ($dateYmd === null) {
        return null;
    }

    $leaders = engagement_leaderboard_for_msk_date($dateYmd);
    if ($leaders === []) {
        return null;
    }

    $top = $leaders[0];
    $matchIds = engagement_finished_match_ids_for_msk_date($dateYmd);

    return [
        'date' => $dateYmd,
        'date_label' => engagement_match_day_label($dateYmd),
        'user_id' => (int) $top['id'],
        'name' => (string) $top['name'],
        'match_points' => (int) $top['match_points'],
        'exact_scores_count' => (int) $top['exact_scores_count'],
        'matches_count' => count($matchIds),
    ];
}

/** @return array<string,mixed> */
function engagement_participant_stats(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT p.match_id, p.home_score, p.away_score,
                s.points, s.reason,
                m.starts_at, m.home_score AS result_home, m.away_score AS result_away
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
         WHERE p.user_id = ?
           AND m.home_score IS NOT NULL
           AND m.away_score IS NOT NULL
         ORDER BY m.starts_at ASC"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $scored = 0;
    $outcomes = 0;
    $exact = 0;
    $streak = 0;
    $runningStreak = 0;
    $crowdDiffs = [];

    foreach ($rows as $row) {
        $points = (int) ($row['points'] ?? 0);
        if ($points > 0) {
            $scored++;
            if (($row['reason'] ?? '') === 'Точный счет') {
                $exact++;
            } elseif (($row['reason'] ?? '') === 'Угадан исход') {
                $outcomes++;
            }
            $runningStreak++;
            $streak = max($streak, $runningStreak);
        } else {
            $runningStreak = 0;
        }

        $predTotal = (int) $row['home_score'] + (int) $row['away_score'];
        $dist = match_prediction_distribution((int) $row['match_id']);
        if ($dist['top_score'] !== null) {
            [$modeHome, $modeAway] = array_map('intval', explode(':', (string) $dist['top_score']));
            $crowdDiffs[] = $predTotal - ($modeHome + $modeAway);
        }
    }

    $totalFinished = count($rows);
    $avgCrowdDiff = $crowdDiffs !== [] ? array_sum($crowdDiffs) / count($crowdDiffs) : 0.0;
    $boldnessLabel = 'Как все';
    $boldnessCaption = 'Счета близки к самым популярным';
    if ($avgCrowdDiff >= 0.35) {
        $boldnessLabel = 'Смелее';
        $boldnessCaption = 'Чаще ставите более результативные счета, чем большинство';
    } elseif ($avgCrowdDiff <= -0.35) {
        $boldnessLabel = 'Скромнее';
        $boldnessCaption = 'Чаще ставите более скромные счета, чем большинство';
    }

    return [
        'finished_predictions' => $totalFinished,
        'scored_matches' => $scored,
        'outcomes_count' => $outcomes,
        'exact_scores_count' => $exact,
        'outcome_rate' => $totalFinished > 0 ? (int) round(($outcomes + $exact) / $totalFinished * 100) : 0,
        'exact_rate' => $totalFinished > 0 ? (int) round($exact / $totalFinished * 100) : 0,
        'points_streak' => $streak,
        'avg_goals_vs_crowd' => round($avgCrowdDiff, 2),
        'boldness_label' => $boldnessLabel,
        'boldness_caption' => $boldnessCaption,
        'rank_history' => engagement_participant_rank_history($userId),
    ];
}

/** @return list<array{date: string, date_label: string, rank: int, points: int, total_participants: int}> */
function engagement_participant_rank_history(int $userId): array
{
    $days = engagement_match_days_with_results();
    if ($days === []) {
        return [];
    }

    $history = [];
    $cumulativeMatchIds = [];
    foreach ($days as $day) {
        foreach (engagement_finished_match_ids_for_msk_date($day) as $mid) {
            $cumulativeMatchIds[] = $mid;
        }
        $board = engagement_leaderboard_for_matches(array_values(array_unique($cumulativeMatchIds)));
        $rank = null;
        $points = 0;
        foreach ($board as $index => $row) {
            if ((int) $row['id'] === $userId) {
                $rank = $index + 1;
                $points = (int) $row['match_points'];
                break;
            }
        }
        if ($rank === null) {
            continue;
        }
        $history[] = [
            'date' => $day,
            'date_label' => engagement_match_day_label($day),
            'rank' => $rank,
            'points' => $points,
            'total_participants' => count($board),
        ];
    }

    return $history;
}

/** @return list<array{key: string, title: string, description: string, earned_at: ?string}> */
function engagement_participant_badges(int $userId): array
{
    $badges = [];
    $stmt = db()->prepare(
        "SELECT p.match_id, p.home_score, p.away_score, s.points,
                m.starts_at, ht.name AS home_team, at.name AS away_team
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
         WHERE p.user_id = ?
           AND s.points > 0
           AND m.home_score IS NOT NULL
         ORDER BY m.starts_at ASC"
    );
    $stmt->execute([$userId]);

    foreach ($stmt->fetchAll() as $row) {
        $matchId = (int) $row['match_id'];
        $dist = match_prediction_distribution($matchId);
        if ($dist['total'] < 5) {
            continue;
        }
        $predOutcome = match_outcome((int) $row['home_score'], (int) $row['away_score']);
        $homePct = (int) round($dist['home'] / $dist['total'] * 100);
        $awayPct = (int) round($dist['away'] / $dist['total'] * 100);
        $underdog = ($predOutcome === 'home' && $homePct < 20)
            || ($predOutcome === 'away' && $awayPct < 20);
        if (!$underdog) {
            continue;
        }
        $side = $predOutcome === 'home' ? (string) $row['home_team'] : (string) $row['away_team'];
        $pct = $predOutcome === 'home' ? $homePct : $awayPct;
        $badges[] = [
            'key' => 'underdog_' . $matchId,
            'title' => 'Угадал аутсайдера',
            'description' => $side . ' (' . $pct . '% у толпы) · '
                . (string) $row['home_team'] . ' — ' . (string) $row['away_team'],
            'earned_at' => (string) $row['starts_at'],
        ];
    }

    return $badges;
}

/** @return array<string,mixed>|null */
function engagement_compare_participants(int $userAId, int $userBId): ?array
{
    if ($userAId < 1 || $userBId < 1 || $userAId === $userBId) {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT u.id, u.name FROM users u
         WHERE u.id IN (?, ?) AND u.role = 'participant' AND u.payment_status = 'active'"
    );
    $stmt->execute([$userAId, $userBId]);
    $users = [];
    foreach ($stmt->fetchAll() as $row) {
        $users[(int) $row['id']] = $row;
    }
    if (count($users) !== 2) {
        return null;
    }

    $sql = db()->prepare(
        "SELECT m.id, m.starts_at, ht.name AS home_team, at.name AS away_team,
                m.home_score, m.away_score,
                pa.home_score AS pred_a_home, pa.away_score AS pred_a_away,
                pb.home_score AS pred_b_home, pb.away_score AS pred_b_away,
                COALESCE(sa.points, 0) AS points_a,
                COALESCE(sb.points, 0) AS points_b
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         JOIN predictions pa ON pa.match_id = m.id AND pa.user_id = ?
         JOIN predictions pb ON pb.match_id = m.id AND pb.user_id = ?
         LEFT JOIN scores sa ON sa.user_id = pa.user_id AND sa.match_id = m.id
         LEFT JOIN scores sb ON sb.user_id = pb.user_id AND sb.match_id = m.id
         WHERE m.home_score IS NOT NULL AND m.away_score IS NOT NULL
         ORDER BY m.starts_at ASC"
    );
    $sql->execute([$userAId, $userBId]);
    $rows = $sql->fetchAll();

    $aWins = 0;
    $bWins = 0;
    $draws = 0;
    $pointsA = 0;
    $pointsB = 0;
    $details = [];

    foreach ($rows as $row) {
        $pa = (int) $row['points_a'];
        $pb = (int) $row['points_b'];
        $pointsA += $pa;
        $pointsB += $pb;
        if ($pa > $pb) {
            $aWins++;
            $winner = 'a';
        } elseif ($pb > $pa) {
            $bWins++;
            $winner = 'b';
        } else {
            $draws++;
            $winner = 'tie';
        }
        $details[] = [
            'match_id' => (int) $row['id'],
            'label' => (string) $row['home_team'] . ' — ' . (string) $row['away_team'],
            'starts_at' => (string) $row['starts_at'],
            'result' => (int) $row['home_score'] . ' : ' . (int) $row['away_score'],
            'pred_a' => (int) $row['pred_a_home'] . ' : ' . (int) $row['pred_a_away'],
            'pred_b' => (int) $row['pred_b_home'] . ' : ' . (int) $row['pred_b_away'],
            'points_a' => $pa,
            'points_b' => $pb,
            'winner' => $winner,
        ];
    }

    return [
        'user_a' => $users[$userAId],
        'user_b' => $users[$userBId],
        'matches_count' => count($details),
        'points_a' => $pointsA,
        'points_b' => $pointsB,
        'a_wins' => $aWins,
        'b_wins' => $bWins,
        'draws' => $draws,
        'details' => $details,
    ];
}

function engagement_home_activity_cache_path(): string
{
    return dirname(__DIR__) . '/storage/cache/home_activity.json';
}

/** @return array<string,mixed> */
function engagement_build_home_activity_snapshot(): array
{
    $snapshot = [
        'updated_at' => date('c'),
        'last_match' => null,
        'leader' => null,
        'stage_prize' => null,
        'next_deadline' => null,
        'expert' => null,
    ];

    $stagePrize = engagement_current_stage_prize_snapshot();
    if ($stagePrize !== null) {
        $snapshot['stage_prize'] = $stagePrize;
    }

    $expert = engagement_expert_of_match_day();
    if ($expert !== null) {
        $snapshot['expert'] = $expert;
    }

    $leaders = leaderboard();
    if ($leaders !== []) {
        $top = $leaders[0];
        $snapshot['leader'] = [
            'name' => (string) $top['name'],
            'user_id' => (int) $top['id'],
            'total_points' => (int) $top['total_points'],
        ];
    }

    foreach (array_reverse(matches_schedule_rows()) as $match) {
        if (!match_is_finished_for_schedule($match)) {
            continue;
        }
        $matchId = (int) $match['id'];
        $stmtExact = db()->prepare('SELECT COUNT(*) FROM scores WHERE match_id = ? AND points = 3');
        $stmtExact->execute([$matchId]);
        $exact = (int) $stmtExact->fetchColumn();
        $stmtScored = db()->prepare('SELECT COUNT(*) FROM scores WHERE match_id = ? AND points > 0');
        $stmtScored->execute([$matchId]);
        $scored = (int) $stmtScored->fetchColumn();
        $snapshot['last_match'] = [
            'label' => (string) $match['home_team'] . ' — ' . (string) $match['away_team'],
            'exact_count' => $exact,
            'scored_count' => $scored,
        ];
        break;
    }

    foreach (upcoming_matches() as $match) {
        $lockAt = strtotime((string) $match['starts_at']) - (int) config('app.prediction_lock_minutes', 5) * 60;
        if ($lockAt <= time()) {
            continue;
        }
        $snapshot['next_deadline'] = [
            'label' => (string) $match['home_team'] . ' — ' . (string) $match['away_team'],
            'match_id' => (int) $match['id'],
            'lock_at' => date('c', $lockAt),
            'seconds_left' => max(0, $lockAt - time()),
        ];
        break;
    }

    return $snapshot;
}

function engagement_refresh_home_activity_cache(): void
{
    $path = engagement_home_activity_cache_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, json_encode(engagement_build_home_activity_snapshot(), JSON_UNESCAPED_UNICODE));
}

/** @return array<string,mixed> */
function engagement_home_activity_snapshot(): array
{
    $path = engagement_home_activity_cache_path();
    if (is_file($path)) {
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data)) {
            $stagePrize = engagement_current_stage_prize_snapshot();
            if ($stagePrize !== null) {
                $data['stage_prize'] = $stagePrize;
            } else {
                unset($data['stage_prize']);
            }

            return $data;
        }
    }

    return engagement_build_home_activity_snapshot();
}

/** @return list<array<string,mixed>> */
function site_polls_active(): array
{
    if (!db_table_exists('site_polls')) {
        return [];
    }

    $stmt = db()->query(
        'SELECT id, slug, title, options_json, sort_order
         FROM site_polls WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    );

    $polls = [];
    foreach ($stmt->fetchAll() as $row) {
        $options = json_decode((string) ($row['options_json'] ?? '[]'), true);
        if (!is_array($options)) {
            $options = [];
        }
        $polls[] = [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'options' => $options,
        ];
    }

    return $polls;
}

/** @return array<string,mixed> */
function site_poll_results(int $pollId): array
{
    $stmt = db()->prepare('SELECT id, slug, title, options_json FROM site_polls WHERE id = ? LIMIT 1');
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();
    if (!$poll) {
        return ['total' => 0, 'options' => [], 'poll' => null];
    }

    $options = json_decode((string) ($poll['options_json'] ?? '[]'), true);
    if (!is_array($options)) {
        $options = [];
    }

    $votes = [];
    if (db_table_exists('site_poll_votes')) {
        $vstmt = db()->prepare('SELECT option_key, COUNT(*) AS cnt FROM site_poll_votes WHERE poll_id = ? GROUP BY option_key');
        $vstmt->execute([$pollId]);
        foreach ($vstmt->fetchAll() as $vrow) {
            $votes[(string) $vrow['option_key']] = (int) $vrow['cnt'];
        }
    }

    $total = array_sum($votes);
    $results = [];
    foreach ($options as $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $key = (string) ($opt['key'] ?? '');
        $label = (string) ($opt['label'] ?? $key);
        $cnt = $votes[$key] ?? 0;
        $results[] = [
            'key' => $key,
            'label' => $label,
            'votes' => $cnt,
            'percent' => $total > 0 ? (int) round($cnt / $total * 100) : 0,
        ];
    }

    usort($results, static fn (array $a, array $b): int => $b['votes'] <=> $a['votes']);

    return ['total' => $total, 'options' => $results, 'poll' => $poll];
}

function user_has_voted_site_poll(int $pollId): bool
{
    if (isset($_COOKIE['voted_poll_' . $pollId])) {
        return true;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip && db_table_exists('site_poll_votes')) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM site_poll_votes WHERE poll_id = ? AND ip_address = ?');
        $stmt->execute([$pollId, $ip]);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    $user = current_user();
    if ($user && db_table_exists('site_poll_votes')) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM site_poll_votes WHERE poll_id = ? AND user_id = ?');
        $stmt->execute([$pollId, (int) $user['id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    }

    return false;
}

/** @return list<array<string,mixed>> */
function engagement_compare_participant_options(): array
{
    $stmt = db()->query(
        "SELECT u.id, u.name FROM users u
         WHERE u.role = 'participant' AND u.payment_status = 'active'
         ORDER BY u.name ASC"
    );

    return $stmt->fetchAll();
}

/** @return array<string,mixed>|null */
function engagement_stage_prize_leader(string $key): ?array
{
    $matchIds = engagement_stage_prize_matches($key, true);
    if ($matchIds === []) {
        return null;
    }

    $board = engagement_leaderboard_for_matches($matchIds);
    if ($board === []) {
        return null;
    }

    $top = $board[0];

    return [
        'user_id' => (int) $top['id'],
        'name' => (string) $top['name'],
        'match_points' => (int) $top['match_points'],
        'exact_scores_count' => (int) ($top['exact_scores_count'] ?? 0),
        'outcomes_count' => (int) ($top['outcomes_count'] ?? 0),
    ];
}

function engagement_format_duration_short(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' сек';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($hours > 0) {
        return $hours . ' ч ' . $minutes . ' мин';
    }

    return $minutes . ' мин';
}
