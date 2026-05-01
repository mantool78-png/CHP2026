<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);

$sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_name('chp2026_session');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params(0, '/', '', $sessionSecure, true);
}
session_start();

function config(?string $key = null, $default = null)
{
    global $config;

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config('db');
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['database'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    if ($scriptDir && $scriptDir !== '/' && substr($path, 0, strlen($scriptDir)) === $scriptDir) {
        $path = substr($path, strlen($scriptDir)) ?: '/';
    }

    return '/' . trim($path, '/');
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/** Абсолютный URL текущего сайта (для ссылок в приглашениях, писем и т.п.). */
function absolute_url(string $path): string
{
    $path = ($path !== '' && $path[0] === '/') ? $path : '/' . $path;
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host . $path;
}

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . '/views/' . $template . '.php';
    require dirname(__DIR__) . '/views/partials/layout.php';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Сессия устарела. Обновите страницу и попробуйте снова.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $message;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null && (int) $user['id'] === (int) $_SESSION['user_id']) {
        return $user;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    return $user;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_user();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Доступ запрещен');
    }

    return $user;
}

function is_active_participant(?array $user = null): bool
{
    $user = $user ?: current_user();
    return $user && ($user['payment_status'] ?? '') === 'active';
}

/** Текст из таблицы settings; если ключа нет — значение из config.php. */
function site_text_setting(string $dbKey, string $configPath, string $default): string
{
    static $cache = [];

    if (!array_key_exists($dbKey, $cache)) {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$dbKey]);
        $row = $stmt->fetch();
        $cache[$dbKey] = $row !== false ? (string) $row['setting_value'] : false;
    }

    if ($cache[$dbKey] !== false) {
        return (string) $cache[$dbKey];
    }

    return (string) config($configPath, $default);
}

function payment_instructions(): string
{
    return site_text_setting(
        'site_payment_instructions',
        'app.payment_instructions',
        'Реквизиты для оплаты организатор сообщит отдельно.'
    );
}

function payment_comment_hint(): string
{
    return site_text_setting(
        'site_payment_comment_hint',
        'app.payment_comment_hint',
        'ЧМ-2026, ваш email или имя на сайте.'
    );
}

/** Связь с организаторами (Telegram, email и т.д.). Пусто — блок на сайте не показывается. */
function organizer_contact(): string
{
    return site_text_setting(
        'site_organizer_contact',
        'app.organizer_contact',
        ''
    );
}

function render_text_with_links(string $value): string
{
    $escaped = h($value);
    $linked = preg_replace_callback(
        '~https?://[^\s<]+~u',
        static function (array $matches): string {
            $url = $matches[0];
            return '<a class="table-link" href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a>';
        },
        $escaped
    );

    return nl2br($linked ?? $escaped);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function login_attempt_key(string $email): string
{
    return hash('sha256', mb_strtolower(trim($email)) . '|' . client_ip());
}

function login_rate_limit_status(string $email): array
{
    $stmt = db()->prepare('SELECT * FROM login_attempts WHERE attempt_key = ? LIMIT 1');
    $stmt->execute([login_attempt_key($email)]);
    $attempt = $stmt->fetch();

    if (!$attempt || empty($attempt['locked_until'])) {
        return ['locked' => false, 'seconds' => 0];
    }

    $seconds = strtotime((string) $attempt['locked_until']) - time();
    return [
        'locked' => $seconds > 0,
        'seconds' => max(0, $seconds),
    ];
}

function record_failed_login(string $email): void
{
    $key = login_attempt_key($email);
    $stmt = db()->prepare('SELECT attempts, last_attempt_at FROM login_attempts WHERE attempt_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $attempt = $stmt->fetch();

    $now = time();
    $attempts = 1;
    if ($attempt && strtotime((string) $attempt['last_attempt_at']) > ($now - 15 * 60)) {
        $attempts = min(255, (int) $attempt['attempts'] + 1);
    }

    $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', $now + 15 * 60) : null;

    $upsert = db()->prepare(
        "INSERT INTO login_attempts (attempt_key, attempts, locked_until, last_attempt_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             attempts = VALUES(attempts),
             locked_until = VALUES(locked_until),
             last_attempt_at = VALUES(last_attempt_at)"
    );
    $upsert->execute([$key, $attempts, $lockedUntil, date('Y-m-d H:i:s', $now)]);
}

function clear_failed_logins(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE attempt_key = ?');
    $stmt->execute([login_attempt_key($email)]);
}

require __DIR__ . '/domain.php';
