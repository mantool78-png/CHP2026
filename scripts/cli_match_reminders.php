#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$r = run_match_prediction_reminders();
fwrite(STDOUT, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
