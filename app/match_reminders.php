<?php

declare(strict_types=1);

/**
 * Напоминания о прогнозе за ~1 час до starts_at матча.
 * Окно выбора по времени приложения (Europe/Moscow в config): [+50 мин, +75 мин) от «сейчас»,
 * чтобы при cron раз в 10–15 минут не пропустить момент.
 *
 * @return array{matches:int,sent:int,skipped_users:int,failed:int}
 */
function run_match_prediction_reminders(): array
{
    $out = ['matches' => 0, 'sent' => 0, 'skipped_users' => 0, 'failed' => 0];

    if (!mail_is_configured()) {
        return $out;
    }

    $tzName = (string) config('app.timezone', 'Europe/Moscow');
    $tz = new DateTimeZone($tzName);
    $now = new DateTimeImmutable('now', $tz);
    $winStart = $now->modify('+50 minutes');
    $winEnd = $now->modify('+75 minutes');

    $stmt = db()->prepare(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.status <> 'finished'
           AND m.home_score IS NULL AND m.away_score IS NULL
           AND m.home_team_id IS NOT NULL AND m.away_team_id IS NOT NULL
           AND m.starts_at >= ? AND m.starts_at < ?"
    );
    $stmt->execute([
        $winStart->format('Y-m-d H:i:s'),
        $winEnd->format('Y-m-d H:i:s'),
    ]);
    $matches = $stmt->fetchAll();

    $claimStmt = db()->prepare(
        'INSERT IGNORE INTO prediction_reminder_log (user_id, match_id, sent_at) VALUES (?, ?, NOW())'
    );
    $releaseStmt = db()->prepare('DELETE FROM prediction_reminder_log WHERE user_id = ? AND match_id = ?');
    $usersStmt = db()->prepare(
        "SELECT u.*
         FROM users u
         WHERE u.role = 'participant'
           AND u.payment_status <> 'blocked'
           AND NOT EXISTS (
               SELECT 1 FROM predictions p WHERE p.user_id = u.id AND p.match_id = ?
           )"
    );

    foreach ($matches as $match) {
        $out['matches']++;
        $matchId = (int) $match['id'];
        if (prediction_locked($match)) {
            continue;
        }

        $usersStmt->execute([$matchId]);
        $users = $usersStmt->fetchAll();

        foreach ($users as $user) {
            if (!can_make_prediction($user, $matchId)) {
                $out['skipped_users']++;
                continue;
            }

            $claimStmt->execute([(int) $user['id'], $matchId]);
            if ($claimStmt->rowCount() < 1) {
                $out['skipped_users']++;
                continue;
            }

            $ok = mail_send_match_reminder(
                (string) $user['email'],
                (string) $user['name'],
                $match
            );

            if (!$ok) {
                $releaseStmt->execute([(int) $user['id'], $matchId]);
                $out['failed']++;
            } else {
                $out['sent']++;
            }
        }
    }

    return $out;
}
