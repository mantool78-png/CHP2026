<?php

declare(strict_types=1);

function mail_settings(): array
{
    $m = config('mail', []);

    return [
        'enabled' => !empty($m['enabled']),
        'transport' => strtolower(trim((string) ($m['transport'] ?? 'smtp'))),
        'from_email' => trim((string) ($m['from_email'] ?? '')),
        'from_name' => trim((string) ($m['from_name'] ?? (string) config('app.name', 'ЧМ-2026'))),
        'smtp_host' => trim((string) ($m['smtp_host'] ?? '')),
        'smtp_port' => (int) ($m['smtp_port'] ?? 587),
        'smtp_user' => trim((string) ($m['smtp_user'] ?? '')),
        'smtp_password' => (string) ($m['smtp_password'] ?? ''),
        'smtp_encryption' => strtolower(trim((string) ($m['smtp_encryption'] ?? 'tls'))),
        'reminder_cron_token' => trim((string) ($m['reminder_cron_token'] ?? '')),
    ];
}

function mail_is_configured(): bool
{
    $c = mail_settings();

    return $c['enabled']
        && $c['from_email'] !== ''
        && filter_var($c['from_email'], FILTER_VALIDATE_EMAIL)
        && ($c['transport'] === 'mail' || ($c['smtp_host'] !== '' && $c['smtp_port'] > 0));
}

/** Базовый URL сайта для ссылок в письмах (включая cron без HTTP_HOST). */
function mail_public_base_url(): string
{
    $custom = trim((string) config('app.public_site_url', ''));
    if ($custom !== '') {
        return rtrim($custom, '/');
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
        return preg_replace('#/+$#', '', absolute_url('/'));
    }

    return 'http://localhost';
}

function mail_mime_header_subject(string $subject): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }

    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function mail_send_message(string $to, string $subject, string $plain, string $html): bool
{
    $c = mail_settings();
    if (($c['transport'] ?? 'smtp') === 'mail') {
        return mail_send_native($to, $subject, $plain, $html);
    }

    return mail_send_smtp($to, $subject, $plain, $html);
}

/** Отправка через PHP mail() на хостинге, без SMTP-пароля. */
function mail_send_native(string $to, string $subject, string $plain, string $html): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $c = mail_settings();
    $boundary = 'bnd_' . bin2hex(random_bytes(8));
    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$plain}\r\n";
    $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$html}\r\n";
    $body .= "--{$boundary}--\r\n";

    $fromHeader = $c['from_name'] !== ''
        ? mail_mime_header_subject($c['from_name']) . ' <' . $c['from_email'] . '>'
        : $c['from_email'];
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $fromHeader,
        'Reply-To: ' . $c['from_email'],
    ];

    return mail($to, mail_mime_header_subject($subject), $body, implode("\r\n", $headers));
}

/**
 * SMTP-отправка (без внешних зависимостей).
 *
 * @return bool true при успешном завершении сессии
 */
function mail_send_smtp(string $to, string $subject, string $plain, string $html): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $c = mail_settings();

    $boundary = 'bnd_' . bin2hex(random_bytes(8));
    $plain = str_replace("\r\n", "\n", $plain);
    $plain = str_replace("\n", "\r\n", $plain);
    $htmlBody = str_replace("\r\n", "\n", $html);
    $htmlBody = str_replace("\n", "\r\n", $htmlBody);

    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$plain}\r\n";
    $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$htmlBody}\r\n";
    $body .= "--{$boundary}--\r\n";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $subjectHeader = mail_mime_header_subject($subject);
    $fromHeader = $c['from_name'] !== ''
        ? sprintf('"%s" <%s>', addcslashes($c['from_name'], '"\\'), $c['from_email'])
        : $c['from_email'];

    try {
        $fp = mail_smtp_connect($c);
        mail_smtp_expect($fp, [220]);

        $ehloHost = 'localhost';
        fwrite($fp, 'EHLO ' . $ehloHost . "\r\n");
        mail_smtp_expect($fp, [250]);

        if ($c['smtp_encryption'] === 'tls') {
            fwrite($fp, "STARTTLS\r\n");
            mail_smtp_expect($fp, [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            fwrite($fp, 'EHLO ' . $ehloHost . "\r\n");
            mail_smtp_expect($fp, [250]);
        }

        if ($c['smtp_user'] !== '') {
            fwrite($fp, "AUTH LOGIN\r\n");
            mail_smtp_expect($fp, [334]);
            fwrite($fp, base64_encode($c['smtp_user']) . "\r\n");
            mail_smtp_expect($fp, [334]);
            fwrite($fp, base64_encode($c['smtp_password']) . "\r\n");
            mail_smtp_expect($fp, [235]);
        }

        fwrite($fp, 'MAIL FROM:<' . $c['from_email'] . ">\r\n");
        mail_smtp_expect($fp, [250]);
        fwrite($fp, 'RCPT TO:<' . $to . ">\r\n");
        mail_smtp_expect($fp, [250, 251]);
        fwrite($fp, "DATA\r\n");
        mail_smtp_expect($fp, [354]);

        $dataLines = 'Subject: ' . $subjectHeader . "\r\n"
            . 'From: ' . $fromHeader . "\r\n"
            . 'To: <' . $to . ">\r\n"
            . implode("\r\n", $headers) . "\r\n\r\n"
            . preg_replace("/^\./m", '..', $body) . "\r\n.\r\n";
        fwrite($fp, $dataLines);
        mail_smtp_expect($fp, [250]);

        fwrite($fp, "QUIT\r\n");
        mail_smtp_expect($fp, [221]);

        fclose($fp);

        return true;
    } catch (Throwable $e) {
        if (isset($fp) && is_resource($fp)) {
            @fclose($fp);
        }
        error_log('mail_send_smtp: ' . $e->getMessage());

        return false;
    }
}

/**
 * @param array{enabled:bool,from_email:string,from_name:string,smtp_host:string,smtp_port:int,smtp_user:string,smtp_password:string,smtp_encryption:string,reminder_cron_token:string} $c
 * @return resource
 */
function mail_smtp_connect(array $c)
{
    $enc = $c['smtp_encryption'];
    $host = $c['smtp_host'];
    $port = $c['smtp_port'];

    if ($enc === 'ssl') {
        $remote = 'ssl://' . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 25, STREAM_CLIENT_CONNECT);
    } else {
        $remote = $host . ':' . $port;
        $fp = @stream_socket_client('tcp://' . $remote, $errno, $errstr, 25, STREAM_CLIENT_CONNECT);
    }

    if ($fp === false) {
        throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
    }

    stream_set_timeout($fp, 25);

    return $fp;
}

/** @param resource $fp */
function mail_smtp_expect($fp, array $codes): void
{
    $all = '';
    while (true) {
        $line = fgets($fp, 8192);
        if ($line === false) {
            throw new RuntimeException('SMTP read failed: ' . trim($all));
        }
        $all .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($line, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($all));
    }
}

/** Приветствие после регистрации (ошибки в error_log, не бросаем исключение наружу). */
function mail_send_registration_welcome(string $toEmail, string $participantName): void
{
    if (!mail_is_configured()) {
        return;
    }

    $site = mail_public_base_url();
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';

    $subject = 'Вы зарегистрировались в конкурсе прогнозов';
    $plain = "Здравствуйте, {$name}!\n\n"
        . "Спасибо за регистрацию в конкурсе прогнозов. Желаем удачи в игре и точных прогнозов.\n\n"
        . "Личный кабинет: {$site}/dashboard\n\n"
        . "С уважением,\n"
        . (string) config('app.name', 'ЧМ-2026');

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p>Спасибо за регистрацию в конкурсе прогнозов. Желаем удачи в игре и точных прогнозов.</p>'
        . '<p><a href="' . h($site . '/dashboard') . '">Перейти в личный кабинет</a></p>';

    if (!mail_send_message($toEmail, $subject, $plain, $html)) {
        error_log('mail_send_registration_welcome failed for ' . $toEmail);
    }
}

/** Напоминание сделать прогноз (~за час до начала матча). */
function mail_send_match_reminder(string $toEmail, string $participantName, array $match): bool
{
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $mid = (int) ($match['id'] ?? 0);
    $url = $site . '/dashboard';
    if ($mid > 0) {
        $url = $site . '/match?id=' . $mid;
    }
    $home = (string) ($match['home_team'] ?? '');
    $away = (string) ($match['away_team'] ?? '');
    $when = '';
    if (!empty($match['starts_at'])) {
        $when = date('d.m.Y H:i', strtotime((string) $match['starts_at'])) . ' МСК';
    }
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';

    $subject = 'Через час матч — не забудьте прогноз';
    $plain = "Здравствуйте, {$name}!\n\n"
        . "До начала матча {$home} — {$away}"
        . ($when !== '' ? " ({$when})" : '')
        . " остался примерно час. Прием прогнозов закроется за "
        . (int) config('app.prediction_lock_minutes', 5)
        . " минут до стартового свистка.\n\n"
        . "Сделайте прогноз: {$url}\n\n"
        . 'С уважением, ' . (string) config('app.name', 'ЧМ-2026');

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p>До начала матча <strong>' . h($home) . ' — ' . h($away) . '</strong>'
        . ($when !== '' ? ' (' . h($when) . ')' : '')
        . ' остался примерно час. Приём прогнозов закроется за '
        . (int) config('app.prediction_lock_minutes', 5)
        . ' минут до стартового свистка.</p>'
        . '<p><a href="' . h($url) . '">Сделать прогноз</a></p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}
