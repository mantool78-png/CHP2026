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

/** Ручное напоминание из админки — без привязки к «за час до матча». */
function mail_send_match_prediction_nudge(string $toEmail, string $participantName, array $match): bool
{
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $mid = (int) ($match['id'] ?? 0);
    $url = $mid > 0 ? $site . '/match?id=' . $mid : $site . '/dashboard';
    $home = (string) ($match['home_team'] ?? '');
    $away = (string) ($match['away_team'] ?? '');
    $when = '';
    if (!empty($match['starts_at'])) {
        $when = date('d.m.Y H:i', strtotime((string) $match['starts_at'])) . ' МСК';
    }
    $lockMin = (int) config('app.prediction_lock_minutes', 5);
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';
    $appName = (string) config('app.name', 'ЧМ-2026');

    $subject = 'Не забудьте прогноз — ' . $home . ' — ' . $away;
    $plain = "Здравствуйте, {$name}!\n\n"
        . "Матч {$home} — {$away}"
        . ($when !== '' ? " начинается {$when}." : '.')
        . "\n\nУ вас пока нет прогноза на эту игру. Приём прогнозов закроется за {$lockMin} минут до стартового свистка.\n\n"
        . "Сделать прогноз: {$url}\n\n"
        . "С уважением,\n{$appName}";

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p>Матч <strong>' . h($home) . ' — ' . h($away) . '</strong>'
        . ($when !== '' ? ' начинается <strong>' . h($when) . '</strong>.' : '.')
        . '</p>'
        . '<p>У вас пока нет прогноза на эту игру. Приём прогнозов закроется за '
        . $lockMin . ' минут до стартового свистка.</p>'
        . '<p><a href="' . h($url) . '">Сделать прогноз</a></p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}

/** Разовое напоминание о матче открытия (или другом матче) — текст «вариант A». */
function mail_send_opening_match_reminder(string $toEmail, string $participantName, array $match): bool
{
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $mid = (int) ($match['id'] ?? 0);
    $url = $mid > 0 ? $site . '/match?id=' . $mid : $site . '/dashboard';
    $home = (string) ($match['home_team'] ?? '');
    $away = (string) ($match['away_team'] ?? '');
    $lockMin = (int) config('app.prediction_lock_minutes', 5);
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';
    $appName = (string) config('app.name', 'ЧМ-2026');

    $subject = 'Сегодня открытие ЧМ-2026 — не забудьте прогноз на ' . $home . ' — ' . $away;
    $plain = "Здравствуйте, {$name}!\n\n"
        . "Сегодня в 22:00 МСК стартует Чемпионат мира — матч открытия {$home} — {$away}.\n\n"
        . "У вас пока нет прогноза на эту игру. Приём прогнозов закроется за {$lockMin} минут до стартового свистка.\n\n"
        . "Сделать прогноз: {$url}\n\n"
        . "Удачи в конкурсе!\n\n"
        . "С уважением,\n{$appName}";

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p>Сегодня в <strong>22:00 МСК</strong> стартует Чемпионат мира — матч открытия '
        . '<strong>' . h($home) . ' — ' . h($away) . '</strong>.</p>'
        . '<p>У вас пока нет прогноза на эту игру. Приём прогнозов закроется за '
        . $lockMin . ' минут до стартового свистка.</p>'
        . '<p><a href="' . h($url) . '">Сделать прогноз</a></p>'
        . '<p>Удачи в конкурсе!</p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}

/** Результат матча и начисленные очки — участнику, который ставил прогноз. */
function mail_send_match_result(
    string $toEmail,
    string $participantName,
    array $match,
    array $predictionResult
): bool {
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $mid = (int) ($match['id'] ?? 0);
    $matchUrl = $mid > 0 ? $site . '/match?id=' . $mid : $site . '/my-scores';
    $ratingUrl = $site . '/rating';
    $home = (string) ($match['home_team'] ?? '');
    $away = (string) ($match['away_team'] ?? '');
    $resultHome = (int) ($match['home_score'] ?? 0);
    $resultAway = (int) ($match['away_score'] ?? 0);
    $predHome = (int) ($predictionResult['pred_home'] ?? 0);
    $predAway = (int) ($predictionResult['pred_away'] ?? 0);
    $points = (int) ($predictionResult['points'] ?? 0);
    $reason = trim((string) ($predictionResult['reason'] ?? ''));
    if ($reason === '') {
        $reason = $points === 3 ? 'Точный счет' : ($points === 1 ? 'Угадан исход' : 'Нет очков');
    }
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';
    $appName = (string) config('app.name', 'ЧМ-2026');

    $subject = 'Результат матча: ' . $home . ' — ' . $away . ' ' . $resultHome . ':' . $resultAway;
    $pointsLine = $points > 0
        ? "Вам начислено {$points} " . ru_points_suffix($points) . " ({$reason})."
        : "За этот матч начислено 0 очков ({$reason}).";

    $plain = "Здравствуйте, {$name}!\n\n"
        . "Матч {$home} — {$away} завершён со счётом {$resultHome}:{$resultAway} (основное время).\n\n"
        . "Ваш прогноз: {$predHome}:{$predAway}\n"
        . "{$pointsLine}\n\n"
        . "Карточка матча: {$matchUrl}\n"
        . "Общий рейтинг: {$ratingUrl}\n\n"
        . "С уважением,\n{$appName}";

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p>Матч <strong>' . h($home) . ' — ' . h($away) . '</strong> завершён со счётом '
        . '<strong>' . $resultHome . ':' . $resultAway . '</strong> (основное время).</p>'
        . '<p>Ваш прогноз: <strong>' . $predHome . ':' . $predAway . '</strong><br>'
        . h($pointsLine) . '</p>'
        . '<p><a href="' . h($matchUrl) . '">Карточка матча</a> · '
        . '<a href="' . h($ratingUrl) . '">Общий рейтинг</a></p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}

/** Уточнение результата матча после исправления счёта (основное время vs овертайм). */
function mail_send_match_result_correction(
    string $toEmail,
    string $participantName,
    array $match,
    array $predictionResult,
    int $prevHome,
    int $prevAway
): bool {
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $mid = (int) ($match['id'] ?? 0);
    $matchUrl = $mid > 0 ? $site . '/match?id=' . $mid : $site . '/my-scores';
    $ratingUrl = $site . '/rating';
    $rulesUrl = $site . '/rules';
    $home = (string) ($match['home_team'] ?? '');
    $away = (string) ($match['away_team'] ?? '');
    $resultHome = (int) ($match['home_score'] ?? 0);
    $resultAway = (int) ($match['away_score'] ?? 0);
    $predHome = (int) ($predictionResult['pred_home'] ?? 0);
    $predAway = (int) ($predictionResult['pred_away'] ?? 0);
    $points = (int) ($predictionResult['points'] ?? 0);
    $reason = trim((string) ($predictionResult['reason'] ?? ''));
    if ($reason === '') {
        $reason = $points === 3 ? 'Точный счет' : ($points === 1 ? 'Угадан исход' : 'Нет очков');
    }
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';
    $appName = (string) config('app.name', 'ЧМ-2026');
    $stage = trim((string) ($match['stage'] ?? ''));

    $subject = 'Уточнение результата: ' . $home . ' — ' . $away . ' ' . $resultHome . ':' . $resultAway;
    $pointsLine = $points > 0
        ? "Вам начислено {$points} " . ru_points_suffix($points) . " ({$reason})."
        : "За этот матч начислено 0 очков ({$reason}).";

    $plain = "Здравствуйте, {$name}!\n\n"
        . "По правилам нашего конкурса в матчах плей-офф зачитывается только результат основного времени (90 минут + компенсация), без дополнительного времени и серии пенальти.\n\n"
        . "В матче {$home} — {$away}"
        . ($stage !== '' ? " ({$stage})" : '')
        . " после окончания основного времени был зафиксирован счёт {$resultHome}:{$resultAway}. "
        . "Победа {$home} была определена только в дополнительное время.\n\n"
        . "Из-за технической ошибки при автоматической синхронизации с API в систему временно попал счёт {$prevHome}:{$prevAway} (с учётом овертайма). "
        . "Мы исправили результат на {$resultHome}:{$resultAway} и пересчитали очки всех участников.\n\n"
        . "Ваш прогноз: {$predHome}:{$predAway}\n"
        . "{$pointsLine}\n\n"
        . "Актуальный счёт и таблица прогнозов: {$matchUrl}\n"
        . "Общий рейтинг: {$ratingUrl}\n"
        . "Правила конкурса: {$rulesUrl}\n\n"
        . "Приносим извинения за путаницу. Если что-то выглядит не так — напишите организатору.\n\n"
        . "С уважением,\n{$appName}";

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p>По правилам нашего конкурса в матчах плей-офф зачитывается только результат <strong>основного времени</strong> '
        . '(90 минут + компенсация), без дополнительного времени и серии пенальти.</p>'
        . '<p>В матче <strong>' . h($home) . ' — ' . h($away) . '</strong>'
        . ($stage !== '' ? ' (' . h($stage) . ')' : '')
        . ' после основного времени был зафиксирован счёт <strong>' . $resultHome . ':' . $resultAway . '</strong>. '
        . 'Победа ' . h($home) . ' была определена только в дополнительное время.</p>'
        . '<p>Из-за технической ошибки при автоматической синхронизации с API в систему временно попал счёт '
        . '<strong>' . $prevHome . ':' . $prevAway . '</strong> (с учётом овертайма). '
        . 'Мы исправили результат на <strong>' . $resultHome . ':' . $resultAway . '</strong> '
        . 'и пересчитали очки всех участников.</p>'
        . '<p>Ваш прогноз: <strong>' . $predHome . ':' . $predAway . '</strong><br>'
        . h($pointsLine) . '</p>'
        . '<p><a href="' . h($matchUrl) . '">Карточка матча</a> · '
        . '<a href="' . h($ratingUrl) . '">Общий рейтинг</a> · '
        . '<a href="' . h($rulesUrl) . '">Правила</a></p>'
        . '<p class="muted">Приносим извинения за путаницу. Если что-то выглядит не так — напишите организатору.</p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}

/** Напоминание об оплате взноса (участники без подтверждённой оплаты и без чека). */
function mail_send_payment_reminder(string $toEmail, string $participantName, int $predictionsCount = 0): bool
{
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';
    $days = contest_days_until_kickoff();
    $daysPart = $days > 0
        ? 'До старта ЧМ-2026 осталось ' . $days . ' ' . ru_days_suffix($days) . '.'
        : 'Чемпионат мира уже на пороге.';
    $freeLimit = free_prediction_limit();
    $freeLeft = max(0, $freeLimit - max(0, $predictionsCount));
    $fee = number_format(entry_fee_rub(), 0, ',', ' ');
    $pairFee = number_format(referral_pair_entry_fee_rub(), 0, ',', ' ');
    $prize = (string) config('app.prize_main_title', 'Apple iPhone 17e 256 GB');
    $dashboardUrl = $site . '/dashboard';
    $rulesUrl = $site . '/rules';

    $trialLine = $predictionsCount > 0
        ? 'Вы уже оставили ' . $predictionsCount . ' из ' . $freeLimit . ' бесплатных прогнозов'
            . ($freeLeft > 0 ? ' — осталось ' . $freeLeft . '.' : ' — лимит пробного режима скоро закончится.')
        : 'У вас ещё ' . $freeLimit . ' бесплатных прогнозов, чтобы попробовать конкурс до оплаты.';

    $subject = $days > 0
        ? "До старта ЧМ-2026 — {$days} " . ru_days_suffix($days) . '. Успейте войти в конкурс'
        : 'Старт ЧМ-2026: оплатите взнос и продолжайте игру';

    $plain = "Здравствуйте, {$name}!\n\n"
        . "{$daysPart}\n\n"
        . "Вы зарегистрированы в лиге прогнозов, но взнос пока не подтверждён. {$trialLine}\n\n"
        . "Чтобы играть весь турнир без ограничений, бороться за {$prize} и денежные призы:\n"
        . "1) Переведите {$fee} ₽ (реквизиты в личном кабинете)\n"
        . "2) Укажите email или имя в комментарии к переводу\n"
        . "3) При желании приложите чек в кабинете — так быстрее подтвердим оплату\n\n"
        . "С другом дешевле: один перевод {$pairFee} ₽ на двоих (акция «Приведи друга»). Подробности: {$rulesUrl}\n\n"
        . "Личный кабинет: {$dashboardUrl}\n\n"
        . 'С уважением, ' . (string) config('app.name', 'ЧМ-2026');

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p><strong>' . h($daysPart) . '</strong></p>'
        . '<p>Вы зарегистрированы в лиге прогнозов, но взнос пока не подтверждён. '
        . h($trialLine) . '</p>'
        . '<p>Чтобы играть весь турнир без ограничений и бороться за <strong>' . h($prize) . '</strong> и денежные призы:</p>'
        . '<ol>'
        . '<li>Переведите <strong>' . h($fee) . ' ₽</strong> (реквизиты в личном кабинете)</li>'
        . '<li>Укажите email или имя в комментарии к переводу</li>'
        . '<li>При желании приложите чек в кабинете — так быстрее подтвердим оплату</li>'
        . '</ol>'
        . '<p>С другом дешевле: один перевод <strong>' . h($pairFee) . ' ₽</strong> на двоих '
        . '(акция «Приведи друга»). <a href="' . h($rulesUrl) . '">Условия акции</a></p>'
        . '<p><a href="' . h($dashboardUrl) . '"><strong>Перейти в личный кабинет</strong></a></p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}

/** Последний матч с бесплатным прогнозом: напоминание об оплате взноса. */
function mail_send_last_free_match_payment_notice(
    string $toEmail,
    string $participantName,
    array $match,
    int $predictionsCount = 0
): bool {
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';
    $home = (string) ($match['home_team'] ?? 'Катар');
    $away = (string) ($match['away_team'] ?? 'Швейцария');
    $label = $home . ' — ' . $away;
    $startsAt = (string) ($match['starts_at'] ?? '');
    $kickoffLabel = $startsAt !== ''
        ? date('d.m.Y H:i', strtotime($startsAt)) . ' МСК'
        : 'сегодня';
    $lockMinutes = (int) config('app.prediction_lock_minutes', 5);
    $deadlineLabel = $startsAt !== ''
        ? date('d.m.Y H:i', strtotime($startsAt) - $lockMinutes * 60) . ' МСК'
        : '';

    $freeLimit = free_prediction_limit();
    $fee = number_format(entry_fee_rub(), 0, ',', ' ');
    $pairFee = number_format(referral_pair_entry_fee_rub(), 0, ',', ' ');
    $prize = (string) config('app.prize_main_title', 'Apple iPhone 17e 256 GB');
    $dashboardUrl = $site . '/dashboard';
    $rulesUrl = $site . '/rules';
    $matchUrl = isset($match['id']) ? $site . match_url((int) $match['id'], 'mail') : $dashboardUrl;

    $usedLine = $predictionsCount > 0
        ? 'Вы уже использовали ' . min($predictionsCount, $freeLimit) . ' из ' . $freeLimit . ' бесплатных прогнозов.'
        : 'У вас ещё есть бесплатные прогнозы, но после этого матча пробный режим закончится.';

    $subject = 'Сегодня последний бесплатный прогноз — оплатите взнос, чтобы продолжить игру';

    $plain = "Здравствуйте, {$name}!\n\n"
        . "Сегодня последний матч, на который можно сделать бесплатный прогноз: {$label}.\n"
        . "Старт: {$kickoffLabel}."
        . ($deadlineLabel !== '' ? " Прогнозы принимаются до {$deadlineLabel}." : '') . "\n\n"
        . "{$usedLine}\n"
        . "Чтобы продолжить игру после этого матча, необходимо оплатить стартовый взнос {$fee} ₽.\n\n"
        . "1) Переведите {$fee} ₽ (реквизиты в личном кабинете)\n"
        . "2) Укажите email или имя в комментарии к переводу\n"
        . "3) При желании приложите чек в кабинете — так быстрее подтвердим оплату\n\n"
        . "С другом дешевле: один перевод {$pairFee} ₽ на двоих (акция «Приведи друга»). Подробности: {$rulesUrl}\n\n"
        . "Сделать прогноз: {$matchUrl}\n"
        . "Оплата и реквизиты: {$dashboardUrl}\n\n"
        . 'С уважением, ' . (string) config('app.name', 'ЧМ-2026');

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p><strong>Сегодня последний матч с бесплатным прогнозом:</strong> '
        . h($label) . '.</p>'
        . '<p>Старт: <strong>' . h($kickoffLabel) . '</strong>.'
        . ($deadlineLabel !== '' ? ' Прогнозы принимаются до <strong>' . h($deadlineLabel) . '</strong>.' : '')
        . '</p>'
        . '<p>' . h($usedLine) . '</p>'
        . '<p>Чтобы продолжить игру после этого матча, необходимо оплатить стартовый взнос '
        . '<strong>' . h($fee) . ' ₽</strong> и дождаться подтверждения организатором.</p>'
        . '<p>После оплаты вы сможете прогнозировать весь турнир без ограничений и бороться за '
        . '<strong>' . h($prize) . '</strong> и денежные призы.</p>'
        . '<ol>'
        . '<li>Переведите <strong>' . h($fee) . ' ₽</strong> (реквизиты в личном кабинете)</li>'
        . '<li>Укажите email или имя в комментарии к переводу</li>'
        . '<li>При желании приложите чек в кабинете — так быстрее подтвердим оплату</li>'
        . '</ol>'
        . '<p>С другом дешевле: один перевод <strong>' . h($pairFee) . ' ₽</strong> на двоих '
        . '(акция «Приведи друга»). <a href="' . h($rulesUrl) . '">Условия акции</a></p>'
        . '<p><a href="' . h($matchUrl) . '"><strong>Сделать прогноз на матч</strong></a><br>'
        . '<a href="' . h($dashboardUrl) . '"><strong>Перейти к оплате в кабинете</strong></a></p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}

/** Благодарственное письмо по окончании Лиги прогнозов ЧМ-2026. */
function mail_send_finale_thanks(string $toEmail, string $participantName): bool
{
    if (!mail_is_configured()) {
        return false;
    }

    $site = mail_public_base_url();
    $ratingUrl = $site . '/rating';
    $name = trim($participantName) !== '' ? trim($participantName) : 'Участник';
    $appName = (string) config('app.name', 'ЧМ-2026');

    $subject = 'Лига прогнозов ЧМ-2026 завершена — спасибо!';

    $plain = "Здравствуйте, {$name}!\n\n"
        . "Лига прогнозов ЧМ-2026 — завершена.\n\n"
        . "Спасибо всем, кто ставил прогнозы матч за матчем, следил за таблицей, спорил в чатах и дошёл с нами до финала. "
        . "Отдельно — спасибо за поддержку проекта: ваши сообщения, репосты и просто «я с вами» многое значили.\n\n"
        . "Без участников это был бы просто календарь игр. С вами получился живой чемпионат — с интригой, мини-лигами и своими героями.\n\n"
        . "Итоги уже на сайте: {$ratingUrl}\n\n"
        . "А мы говорим спасибо и до встречи в следующих сезонах.\n\n"
        . "С уважением,\n{$appName}\n{$site}";

    $safeName = h($name);
    $html = '<p>Здравствуйте, <strong>' . $safeName . '</strong>!</p>'
        . '<p><strong>Лига прогнозов ЧМ-2026 — завершена.</strong></p>'
        . '<p>Спасибо всем, кто ставил прогнозы матч за матчем, следил за таблицей, спорил в чатах и дошёл с нами до финала. '
        . 'Отдельно — спасибо за поддержку проекта: ваши сообщения, репосты и просто «я с вами» многое значили.</p>'
        . '<p>Без участников это был бы просто календарь игр. С вами получился живой чемпионат — с интригой, мини-лигами и своими героями.</p>'
        . '<p>Итоги уже на сайте: <a href="' . h($ratingUrl) . '">' . h($ratingUrl) . '</a></p>'
        . '<p>А мы говорим спасибо и до встречи в следующих сезонах.</p>'
        . '<p>С уважением,<br>' . h($appName) . '</p>';

    return mail_send_message($toEmail, $subject, $plain, $html);
}

/**
 * Разовая рассылка благодарности всем активным участникам.
 * Прогресс пишется в settings, чтобы можно было безопасно продолжить после обрыва.
 *
 * @return array{total:int,sent:int,failed:int,skipped:int}
 */
function run_finale_thanks_mailout(): array
{
    $out = ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
    if (!mail_is_configured()) {
        return $out;
    }

    $sentKey = 'finale_thanks_mailout_sent_ids';
    $doneKey = 'finale_thanks_mailout_completed_at';

    $doneAt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $doneAt->execute([$doneKey]);
    $alreadyDone = $doneAt->fetchColumn();
    if (is_string($alreadyDone) && $alreadyDone !== '') {
        $count = (int) db()->query(
            "SELECT COUNT(*) FROM users
             WHERE role = 'participant' AND payment_status = 'active'
               AND email IS NOT NULL AND email <> ''"
        )->fetchColumn();

        return ['total' => $count, 'sent' => 0, 'failed' => 0, 'skipped' => $count];
    }

    $rawStmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $rawStmt->execute([$sentKey]);
    $raw = $rawStmt->fetchColumn();
    $sentIds = [];
    if (is_string($raw) && $raw !== '') {
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $sentIds[$id] = true;
            }
        }
    }

    $users = db()->query(
        "SELECT id, name, email
         FROM users
         WHERE role = 'participant'
           AND payment_status = 'active'
           AND email IS NOT NULL
           AND email <> ''
         ORDER BY id ASC"
    )->fetchAll();

    $out['total'] = count($users);
    $persist = static function (array $sentIds) use ($sentKey): void {
        $value = implode(',', array_keys($sentIds));
        $stmt = db()->prepare(
            "INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
        );
        $stmt->execute([$sentKey, $value]);
    };

    foreach ($users as $user) {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId > 0 && isset($sentIds[$userId])) {
            $out['skipped']++;
            continue;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out['failed']++;
            continue;
        }
        $ok = mail_send_finale_thanks($email, (string) ($user['name'] ?? ''));
        if ($ok) {
            $out['sent']++;
            if ($userId > 0) {
                $sentIds[$userId] = true;
                $persist($sentIds);
            }
        } else {
            $out['failed']++;
            error_log('mail_send_finale_thanks failed for user #' . $userId . ' ' . $email);
        }
        usleep(80000);
    }

    if ($out['failed'] === 0 && ($out['sent'] + $out['skipped']) >= $out['total']) {
        $done = db()->prepare(
            "INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
        );
        $done->execute(['finale_thanks_mailout_completed_at', date('c')]);
    }

    return $out;
}
