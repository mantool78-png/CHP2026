<?php

declare(strict_types=1);

function match_result_notifications_enabled(): bool
{
    if (!mail_is_configured()) {
        return false;
    }

    $m = config('mail', []);
    if (array_key_exists('match_result_notifications_enabled', $m)) {
        return !empty($m['match_result_notifications_enabled']);
    }

    return true;
}

/**
 * @return array{match_id:int,recipients:int,sent:int,skipped:int,failed:int,error?:string}
 */
function run_match_result_notifications(int $matchId): array
{
    $out = [
        'match_id' => $matchId,
        'recipients' => 0,
        'sent' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    if (!match_result_notifications_enabled()) {
        $out['error'] = 'mail_disabled';

        return $out;
    }

    if (!db_table_exists('match_result_notification_log')) {
        $out['error'] = 'log_table_missing';

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

    if ($match['home_score'] === null || $match['away_score'] === null) {
        $out['error'] = 'result_missing';

        return $out;
    }

    $resultHome = (int) $match['home_score'];
    $resultAway = (int) $match['away_score'];

    $usersStmt = db()->prepare(
        "SELECT u.id, u.name, u.email,
                p.home_score AS pred_home, p.away_score AS pred_away,
                COALESCE(s.points, 0) AS points,
                s.reason,
                l.result_home AS notified_home,
                l.result_away AS notified_away
         FROM predictions p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
         LEFT JOIN match_result_notification_log l
           ON l.user_id = u.id AND l.match_id = p.match_id
         WHERE p.match_id = ?
           AND u.role = 'participant'
           AND u.payment_status <> 'blocked'
         ORDER BY u.id ASC"
    );
    $usersStmt->execute([$matchId]);
    $recipients = $usersStmt->fetchAll();
    $out['recipients'] = count($recipients);

    $touchStmt = db()->prepare(
        'INSERT INTO match_result_notification_log (user_id, match_id, result_home, result_away, sent_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE result_home = VALUES(result_home), result_away = VALUES(result_away), sent_at = VALUES(sent_at)'
    );
    $releaseStmt = db()->prepare('DELETE FROM match_result_notification_log WHERE user_id = ? AND match_id = ?');

    foreach ($recipients as $recipient) {
        $userId = (int) $recipient['id'];
        $notifiedHome = $recipient['notified_home'];
        $notifiedAway = $recipient['notified_away'];
        if ($notifiedHome !== null && $notifiedAway !== null
            && (int) $notifiedHome === $resultHome && (int) $notifiedAway === $resultAway) {
            $out['skipped']++;
            continue;
        }

        $touchStmt->execute([$userId, $matchId, $resultHome, $resultAway]);

        $ok = mail_send_match_result(
            (string) $recipient['email'],
            (string) $recipient['name'],
            $match,
            [
                'pred_home' => (int) $recipient['pred_home'],
                'pred_away' => (int) $recipient['pred_away'],
                'points' => (int) $recipient['points'],
                'reason' => (string) ($recipient['reason'] ?? ''),
            ]
        );

        if ($ok) {
            $out['sent']++;
        } else {
            if ($notifiedHome === null && $notifiedAway === null) {
                $releaseStmt->execute([$userId, $matchId]);
            }
            $out['failed']++;
        }
    }

    return $out;
}
