<?php

declare(strict_types=1);

function weekly_digest_week_start(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow'));

    return $now->modify('monday this week')->format('Y-m-d');
}

function weekly_digest_should_run_today(): bool
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow'));

    return (int) $now->format('N') === 1;
}

/** @return list<array<string,mixed>> */
function weekly_digest_upcoming_without_prediction(int $userId, int $limit = 3): array
{
    $stmt = db()->prepare(
        "SELECT m.id, m.starts_at, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         LEFT JOIN predictions p ON p.match_id = m.id AND p.user_id = ?
         WHERE m.starts_at > NOW()
           AND m.home_team_id IS NOT NULL
           AND m.away_team_id IS NOT NULL
           AND p.id IS NULL
         ORDER BY m.starts_at ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function weekly_digest_points_last_days(int $userId, int $days = 7): int
{
    $since = (new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))
        ->modify('-' . max(1, $days) . ' days')
        ->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(s.points), 0)
         FROM scores s
         JOIN matches m ON m.id = s.match_id
         WHERE s.user_id = ?
           AND m.starts_at >= ?"
    );
    $stmt->execute([$userId, $since]);

    return (int) $stmt->fetchColumn();
}

/** @return array<string,mixed>|null */
function weekly_digest_mini_league_rival(int $userId, int $days = 7): ?array
{
    $stmt = db()->prepare(
        "SELECT ml.id, ml.name
         FROM mini_leagues ml
         JOIN mini_league_members mlm ON mlm.league_id = ml.id AND mlm.user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $league = $stmt->fetch();
    if (!$league) {
        return null;
    }

    $leagueId = (int) $league['id'];
    $membersStmt = db()->prepare(
        "SELECT u.id, u.name FROM mini_league_members mlm
         JOIN users u ON u.id = mlm.user_id
         WHERE mlm.league_id = ? AND u.id <> ?"
    );
    $membersStmt->execute([$leagueId, $userId]);
    $members = $membersStmt->fetchAll();
    if ($members === []) {
        return null;
    }

    $myWeekly = weekly_digest_points_last_days($userId, $days);
    $bestRival = null;
    $bestGain = 0;
    foreach ($members as $member) {
        $gain = weekly_digest_points_last_days((int) $member['id'], $days);
        if ($gain > $myWeekly && $gain > $bestGain) {
            $bestGain = $gain;
            $bestRival = $member;
        }
    }

    if ($bestRival === null) {
        return null;
    }

    return [
        'league_name' => (string) $league['name'],
        'rival_name' => (string) $bestRival['name'],
        'rival_weekly_points' => $bestGain,
        'your_weekly_points' => $myWeekly,
        'gap' => $bestGain - $myWeekly,
    ];
}

function weekly_digest_user_rank(int $userId): ?array
{
    $leaders = leaderboard();
    foreach ($leaders as $index => $row) {
        if ((int) $row['id'] === $userId) {
            return [
                'rank' => $index + 1,
                'total' => count($leaders),
                'total_points' => (int) $row['total_points'],
            ];
        }
    }

    return null;
}

function mail_send_weekly_digest(string $toEmail, string $participantName, array $payload): bool
{
    $site = mail_public_base_url();
    $rank = $payload['rank'] ?? null;
    $weeklyPoints = (int) ($payload['weekly_points'] ?? 0);
    $missing = $payload['missing_matches'] ?? [];
    $rival = $payload['rival'] ?? null;

    $plainLines = [
        'Здравствуйте, ' . $participantName . '!',
        '',
        'Краткий итог вашей недели в лиге прогнозов ЧМ-2026:',
    ];
    if (is_array($rank)) {
        $plainLines[] = 'Место в общем рейтинге: ' . (int) $rank['rank'] . ' из ' . (int) $rank['total']
            . ' (' . (int) $rank['total_points'] . ' очков).';
    }
    $plainLines[] = 'Очков за последние 7 дней: ' . $weeklyPoints . '.';
    if ($rival) {
        $plainLines[] = 'В мини-лиге «' . (string) $rival['league_name'] . '» вас обошёл '
            . (string) $rival['rival_name'] . ' (+' . (int) $rival['gap'] . ' очков за неделю).';
    }
    if ($missing !== []) {
        $plainLines[] = '';
        $plainLines[] = 'Ближайшие матчи без вашего прогноза:';
        foreach ($missing as $match) {
            $plainLines[] = '• ' . (string) $match['home_team'] . ' — ' . (string) $match['away_team']
                . ' (' . date('d.m H:i', strtotime((string) $match['starts_at'])) . ' МСК)';
        }
    }
    $plainLines[] = '';
    $plainLines[] = 'Кабинет: ' . $site . '/dashboard';
    $plainLines[] = 'Рейтинг: ' . $site . '/rating';

    $plain = implode("\n", $plainLines);

    $html = '<p>Здравствуйте, <strong>' . h($participantName) . '</strong>!</p>';
    $html .= '<p>Краткий итог вашей недели в лиге прогнозов ЧМ-2026:</p><ul>';
    if (is_array($rank)) {
        $html .= '<li>Место в общем рейтинге: <strong>' . (int) $rank['rank'] . '</strong> из '
            . (int) $rank['total'] . ' (' . (int) $rank['total_points'] . ' очков)</li>';
    }
    $html .= '<li>Очков за последние 7 дней: <strong>' . $weeklyPoints . '</strong></li>';
    if ($rival) {
        $html .= '<li>В мини-лиге «' . h((string) $rival['league_name']) . '» вас обошёл '
            . '<strong>' . h((string) $rival['rival_name']) . '</strong> (+'
            . (int) $rival['gap'] . ' очков за неделю)</li>';
    }
    $html .= '</ul>';
    if ($missing !== []) {
        $html .= '<p><strong>Ближайшие матчи без прогноза:</strong></p><ul>';
        foreach ($missing as $match) {
            $html .= '<li>' . h((string) $match['home_team']) . ' — ' . h((string) $match['away_team'])
                . ' · ' . h(date('d.m H:i', strtotime((string) $match['starts_at']))) . ' МСК</li>';
        }
        $html .= '</ul>';
    }
    $html .= '<p><a href="' . h($site . '/dashboard') . '">Открыть кабинет</a> · '
        . '<a href="' . h($site . '/rating') . '">Рейтинг</a></p>';

    return mail_send_message(
        $toEmail,
        'Ваша неделя в лиге прогнозов ЧМ-2026',
        $plain,
        $html
    );
}

/** @return array{sent: int, skipped: int, failed: int} */
function run_weekly_digest_mailout(bool $force = false): array
{
    if (!$force && !weekly_digest_should_run_today()) {
        return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
    }

    if (!mail_is_configured() || !db_table_exists('weekly_digest_log')) {
        return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
    }

    $weekStart = weekly_digest_week_start();
    $sent = 0;
    $skipped = 0;
    $failed = 0;

    $stmt = db()->query(
        "SELECT u.id, u.name, u.email
         FROM users u
         WHERE u.role = 'participant'
           AND u.payment_status = 'active'
           AND u.email <> ''"
    );

    foreach ($stmt->fetchAll() as $user) {
        $userId = (int) $user['id'];
        $check = db()->prepare('SELECT 1 FROM weekly_digest_log WHERE user_id = ? AND week_start = ? LIMIT 1');
        $check->execute([$userId, $weekStart]);
        if ($check->fetchColumn()) {
            $skipped++;
            continue;
        }

        $payload = [
            'rank' => weekly_digest_user_rank($userId),
            'weekly_points' => weekly_digest_points_last_days($userId, 7),
            'missing_matches' => weekly_digest_upcoming_without_prediction($userId, 3),
            'rival' => weekly_digest_mini_league_rival($userId, 7),
        ];

        $ok = mail_send_weekly_digest((string) $user['email'], (string) $user['name'], $payload);
        if ($ok) {
            db()->prepare(
                'INSERT INTO weekly_digest_log (user_id, week_start, sent_at) VALUES (?, ?, ?)'
            )->execute([$userId, $weekStart, date('Y-m-d H:i:s')]);
            $sent++;
        } else {
            $failed++;
        }
    }

    return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
}
