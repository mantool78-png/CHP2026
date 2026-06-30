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

function participant_total_count(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status <> 'blocked'"
    )->fetchColumn();
}

function active_participant_total_count(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM users WHERE ' . ranked_participant_where('')
    )->fetchColumn();
}

/**
 * Участники без прогноза на матч.
 *
 * @return list<array{
 *     id:int,
 *     name:string,
 *     email:string,
 *     payment_status:string,
 *     reminder_sent:bool,
 *     reminder_sent_at:?string
 * }>
 */
function match_missing_prediction_recipients(int $matchId, bool $activeOnly = false): array
{
    $participantWhere = $activeOnly
        ? ranked_participant_where('u')
        : "u.role = 'participant' AND u.payment_status <> 'blocked'";

    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.payment_status,
                prl.sent_at AS reminder_sent_at
         FROM users u
         LEFT JOIN prediction_reminder_log prl
           ON prl.user_id = u.id AND prl.match_id = ?
         WHERE {$participantWhere}
           AND NOT EXISTS (
               SELECT 1 FROM predictions p WHERE p.user_id = u.id AND p.match_id = ?
           )
         ORDER BY u.name ASC, u.id ASC"
    );
    $stmt->execute([$matchId, $matchId]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $sentAt = $row['reminder_sent_at'] ?? null;
        $out[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'payment_status' => (string) $row['payment_status'],
            'reminder_sent' => $sentAt !== null && $sentAt !== '',
            'reminder_sent_at' => is_string($sentAt) && $sentAt !== '' ? $sentAt : null,
        ];
    }

    return $out;
}

/**
 * @param list<int> $matchIds
 * @return array<int, list<array{id:int,name:string,email:string,payment_status:string,reminder_sent:bool,reminder_sent_at:?string}>>
 */
function admin_matches_missing_predictions_map(array $matchIds): array
{
    $matchIds = array_values(array_unique(array_filter(array_map('intval', $matchIds), static fn (int $id): bool => $id > 0)));
    if ($matchIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $participantWhere = ranked_participant_where('u');
    $stmt = db()->prepare(
        "SELECT m.id AS match_id, u.id, u.name, u.email, u.payment_status,
                prl.sent_at AS reminder_sent_at
         FROM matches m
         JOIN users u ON {$participantWhere}
         LEFT JOIN predictions p ON p.user_id = u.id AND p.match_id = m.id
         LEFT JOIN prediction_reminder_log prl ON prl.user_id = u.id AND prl.match_id = m.id
         WHERE m.id IN ($placeholders)
           AND p.id IS NULL
         ORDER BY m.id ASC, u.name ASC, u.id ASC"
    );
    $stmt->execute($matchIds);

    $out = [];
    foreach ($matchIds as $matchId) {
        $out[$matchId] = [];
    }
    foreach ($stmt->fetchAll() as $row) {
        $matchId = (int) $row['match_id'];
        $sentAt = $row['reminder_sent_at'] ?? null;
        $out[$matchId][] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'payment_status' => (string) $row['payment_status'],
            'reminder_sent' => $sentAt !== null && $sentAt !== '',
            'reminder_sent_at' => is_string($sentAt) && $sentAt !== '' ? $sentAt : null,
        ];
    }

    return $out;
}

/**
 * Участники без прогноза, которым ещё не отправляли напоминание по этому матчу.
 *
 * @return list<array{id:int,name:string,email:string}>
 */
function match_opening_reminder_recipients(int $matchId): array
{
    $out = [];
    foreach (match_missing_prediction_recipients($matchId) as $recipient) {
        if (!empty($recipient['reminder_sent'])) {
            continue;
        }
        $out[] = [
            'id' => (int) $recipient['id'],
            'name' => (string) $recipient['name'],
            'email' => (string) $recipient['email'],
        ];
    }

    return $out;
}

/**
 * Разовая рассылка «матч открытия» — всем без прогноза, независимо от оплаты.
 *
 * @return array{match_id:int,recipients:int,sent:int,skipped:int,failed:int,error?:string}
 */
function run_opening_match_reminder_mailout(int $matchId): array
{
    $out = [
        'match_id' => $matchId,
        'recipients' => 0,
        'sent' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    if (!mail_is_configured()) {
        $out['error'] = 'mail_not_configured';

        return $out;
    }

    $stmt = db()->prepare(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.id = ?"
    );
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        $out['error'] = 'match_not_found';

        return $out;
    }

    if (prediction_locked($match)) {
        $out['error'] = 'prediction_locked';

        return $out;
    }

    $recipients = match_opening_reminder_recipients($matchId);
    $out['recipients'] = count($recipients);

    $claimStmt = db()->prepare(
        'INSERT IGNORE INTO prediction_reminder_log (user_id, match_id, sent_at) VALUES (?, ?, NOW())'
    );
    $releaseStmt = db()->prepare('DELETE FROM prediction_reminder_log WHERE user_id = ? AND match_id = ?');

    foreach ($recipients as $recipient) {
        $userId = (int) $recipient['id'];
        $claimStmt->execute([$userId, $matchId]);
        if ($claimStmt->rowCount() < 1) {
            $out['skipped']++;
            continue;
        }

        $ok = mail_send_opening_match_reminder(
            (string) $recipient['email'],
            (string) $recipient['name'],
            $match
        );

        if ($ok) {
            $out['sent']++;
        } else {
            $releaseStmt->execute([$userId, $matchId]);
            $out['failed']++;
        }
    }

    return $out;
}

/**
 * Рассылка из админки: активным участникам без прогноза; $resend — повтор тем, кому уже уходило письмо.
 *
 * @return array{match_id:int,recipients:int,sent:int,skipped:int,failed:int,error?:string}
 */
function run_admin_match_reminder_mailout(int $matchId, bool $resend = false): array
{
    $out = [
        'match_id' => $matchId,
        'recipients' => 0,
        'sent' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    if (!mail_is_configured()) {
        $out['error'] = 'mail_not_configured';

        return $out;
    }

    $stmt = db()->prepare(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         WHERE m.id = ?"
    );
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        $out['error'] = 'match_not_found';

        return $out;
    }

    if (!match_slot_has_teams($match)) {
        $out['error'] = 'teams_missing';

        return $out;
    }

    if (prediction_locked($match)) {
        $out['error'] = 'prediction_locked';

        return $out;
    }

    $recipients = match_missing_prediction_recipients($matchId, true);
    if (!$resend) {
        $recipients = array_values(array_filter(
            $recipients,
            static fn (array $recipient): bool => empty($recipient['reminder_sent'])
        ));
    }
    $out['recipients'] = count($recipients);

    $touchStmt = db()->prepare(
        'INSERT INTO prediction_reminder_log (user_id, match_id, sent_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE sent_at = VALUES(sent_at)'
    );
    $releaseStmt = db()->prepare('DELETE FROM prediction_reminder_log WHERE user_id = ? AND match_id = ?');

    foreach ($recipients as $recipient) {
        $userId = (int) $recipient['id'];
        if (!$resend && !empty($recipient['reminder_sent'])) {
            $out['skipped']++;
            continue;
        }

        $touchStmt->execute([$userId, $matchId]);

        $ok = mail_send_match_prediction_nudge(
            (string) $recipient['email'],
            (string) $recipient['name'],
            $match
        );

        if ($ok) {
            $out['sent']++;
        } else {
            if (empty($recipient['reminder_sent'])) {
                $releaseStmt->execute([$userId, $matchId]);
            }
            $out['failed']++;
        }
    }

    return $out;
}
