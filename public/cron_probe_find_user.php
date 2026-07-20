<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$token = mail_settings()['reminder_cron_token'];
if (!cron_token_valid($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden\n";
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

$q = trim((string) ($_GET['q'] ?? 'Mike Ivy'));
if ($q === '') {
    echo "empty_query\n";
    exit;
}

$like = '%' . $q . '%';
$stmt = db()->prepare(
    "SELECT id, name, email, payment_status, role, created_at
     FROM users
     WHERE name LIKE ? OR email LIKE ?
     ORDER BY id ASC
     LIMIT 50"
);
$stmt->execute([$like, $like]);
$rows = $stmt->fetchAll();

echo "query={$q}\n";
echo 'count=' . count($rows) . "\n";
foreach ($rows as $row) {
    echo 'id=' . (int) $row['id']
        . ' | name=' . (string) $row['name']
        . ' | email=' . (string) $row['email']
        . ' | payment=' . (string) $row['payment_status']
        . ' | role=' . (string) $row['role']
        . ' | created=' . (string) $row['created_at']
        . "\n";
}
echo "ok\n";
