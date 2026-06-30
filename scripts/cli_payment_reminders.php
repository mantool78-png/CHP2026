#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (!mail_is_configured()) {
    fwrite(STDERR, "mail not configured (config.php → mail.enabled)\n");
    exit(2);
}

$r = run_pending_payment_reminder_mailout();
fwrite(STDOUT, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
exit($r['failed'] > 0 ? 1 : 0);
