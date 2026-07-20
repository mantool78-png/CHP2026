<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$token = mail_settings()['reminder_cron_token'];
if (!cron_token_valid($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

/**
 * Активные платящие участники: точные счета по завершённым матчам.
 * 1) Матчи с минимумом точных
 * 2) Реальные счёты (X:Y) по числу точных угадываний
 * 3) Матчи, которые никто не угадал точно
 */

$matchSql = "
SELECT
    m.id,
    m.stage,
    m.starts_at,
    ht.name AS home_team,
    at.name AS away_team,
    m.home_score,
    m.away_score,
    CONCAT(m.home_score, ':', m.away_score) AS result_score,
    COUNT(DISTINCT p.user_id) AS predictions_count,
    COUNT(DISTINCT CASE WHEN s.reason = 'Точный счет' THEN s.user_id END) AS exact_count,
    COUNT(DISTINCT CASE WHEN s.reason = 'Угадан исход' THEN s.user_id END) AS outcome_count
FROM matches m
JOIN teams ht ON ht.id = m.home_team_id
JOIN teams at ON at.id = m.away_team_id
LEFT JOIN predictions p
    ON p.match_id = m.id
   AND p.user_id IN (SELECT id FROM users WHERE role = 'participant' AND payment_status = 'active')
LEFT JOIN scores s
    ON s.match_id = m.id
   AND s.user_id IN (SELECT id FROM users WHERE role = 'participant' AND payment_status = 'active')
WHERE m.home_score IS NOT NULL
  AND m.away_score IS NOT NULL
GROUP BY m.id, m.stage, m.starts_at, ht.name, at.name, m.home_score, m.away_score
ORDER BY exact_count ASC, predictions_count DESC, m.starts_at ASC
";

$matches = db()->query($matchSql)->fetchAll();
$finished = count($matches);
$zeroExact = 0;
foreach ($matches as $row) {
    if ((int) $row['exact_count'] === 0) {
        $zeroExact++;
    }
}

echo "finished_matches={$finished}\n";
echo "matches_with_zero_exact={$zeroExact}\n\n";

echo "=== MATCHES BY FEWEST EXACT (top 20) ===\n";
foreach (array_slice($matches, 0, 20) as $row) {
    echo '#' . (int) $row['id']
        . ' | ' . $row['home_team'] . ' — ' . $row['away_team']
        . ' | result=' . $row['result_score']
        . ' | exact=' . (int) $row['exact_count']
        . ' | outcome_only=' . (int) $row['outcome_count']
        . ' | preds=' . (int) $row['predictions_count']
        . ' | stage=' . $row['stage']
        . "\n";
}

echo "\n=== ZERO EXACT MATCHES ===\n";
$shown = 0;
foreach ($matches as $row) {
    if ((int) $row['exact_count'] !== 0) {
        continue;
    }
    echo '#' . (int) $row['id']
        . ' | ' . $row['home_team'] . ' — ' . $row['away_team']
        . ' | result=' . $row['result_score']
        . ' | preds=' . (int) $row['predictions_count']
        . "\n";
    $shown++;
}
if ($shown === 0) {
    echo "(none)\n";
}

$scorelineSql = "
SELECT
    CONCAT(m.home_score, ':', m.away_score) AS result_score,
    COUNT(DISTINCT m.id) AS matches_count,
    COUNT(DISTINCT CASE WHEN s.reason = 'Точный счет' THEN s.id END) AS exact_hits,
    COUNT(DISTINCT CASE WHEN s.reason = 'Точный счет' THEN s.user_id END) AS unique_users
FROM matches m
LEFT JOIN scores s
    ON s.match_id = m.id
   AND s.reason = 'Точный счет'
   AND s.user_id IN (SELECT id FROM users WHERE role = 'participant' AND payment_status = 'active')
WHERE m.home_score IS NOT NULL
  AND m.away_score IS NOT NULL
GROUP BY m.home_score, m.away_score
ORDER BY exact_hits ASC, matches_count DESC, result_score ASC
";

echo "\n=== REAL SCORELINES BY EXACT HITS (rarest first) ===\n";
foreach (db()->query($scorelineSql)->fetchAll() as $row) {
    echo $row['result_score']
        . ' | matches=' . (int) $row['matches_count']
        . ' | exact_hits=' . (int) $row['exact_hits']
        . ' | unique_users=' . (int) $row['unique_users']
        . "\n";
}

$whoSql = "
SELECT
    m.id AS match_id,
    ht.name AS home_team,
    at.name AS away_team,
    CONCAT(m.home_score, ':', m.away_score) AS result_score,
    u.name AS user_name
FROM matches m
JOIN teams ht ON ht.id = m.home_team_id
JOIN teams at ON at.id = m.away_team_id
JOIN scores s ON s.match_id = m.id AND s.reason = 'Точный счет'
JOIN users u ON u.id = s.user_id AND u.role = 'participant' AND u.payment_status = 'active'
WHERE m.home_score IS NOT NULL
  AND m.away_score IS NOT NULL
  AND m.id IN (
      SELECT s2.match_id
      FROM scores s2
      JOIN users u2 ON u2.id = s2.user_id AND u2.role = 'participant' AND u2.payment_status = 'active'
      WHERE s2.reason = 'Точный счет'
      GROUP BY s2.match_id
      HAVING COUNT(*) = 1
  )
ORDER BY m.starts_at ASC
";

echo "\n=== SOLO EXACT (only 1 person nailed the score) ===\n";
$solo = db()->query($whoSql)->fetchAll();
echo 'count=' . count($solo) . "\n";
foreach ($solo as $row) {
    echo '#' . (int) $row['match_id']
        . ' | ' . $row['home_team'] . ' — ' . $row['away_team']
        . ' | ' . $row['result_score']
        . ' | ' . $row['user_name']
        . "\n";
}

echo "\nok\n";
