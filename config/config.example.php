<?php

return [
    'app' => [
        'name' => 'Прогнозы ЧМ-2026',
        'timezone' => 'Europe/Moscow',
        'entry_fee_rub' => 1500,
        // Скидка за пару: сумма referral_discount_rub с каждого при двух полных взносах;
        // пара платит одним переводом referral_pair = 2 * (entry_fee_rub - referral_discount_rub).
        'referral_discount_rub' => 500,
        'referral_discount_limit_per_account' => 1,
        // Главный приз (место 1) — натуральная форма; фото в public/assets/
        'prize_main_title' => 'Apple iPhone 17e 256 GB',
        'prize_main_subtitle' => 'Новейший iPhone для самого удачливого прогнозиста.',
        'prize_main_image' => '/assets/prize-iphone.png',
        // Места 2–5: фиксированные денежные призы (руб.)
        'prize_cash_by_place' => [
            2 => 10000,
            3 => 5000,
            4 => 4000,
            5 => 2000,
        ],
        // Промежуточные призы (6 × 1000 ₽ по умолчанию: 1–3 тур, 1/16, 1/8, финальный отрезок).
        // 'stage_prizes' => [ ... ],
        'prediction_lock_minutes' => 5,
        'free_prediction_limit' => 8,
        'champion_prediction_deadline' => '',
        // Личный кабинет (до оплаты). Можно переопределить в админке: «Тексты оплаты».
        'payment_instructions' => 'Оплата — переводом на счёт в Сбербанке.' . "\n\n"
            . '• Если ваш банк — Сбербанк или Т‑Банк (Тинькофф): отсканируйте QR‑код ниже в приложении банка и переведите нужную сумму (действует только для переводов из Сбербанка и Т‑Банка).' . "\n\n"
            . '• Если у вас другой банк: переведите по номеру телефона +79068551541 через СБП или перевод по номеру в вашем приложении.',
        'payment_comment_hint' => 'ЧМ-2026, ваш email или имя на сайте.',
        'payment_transfer_phone' => '+79068551541',
        'payment_transfer_bank' => 'Сбербанк',
        'payment_transfer_recipient' => 'Павел Олегович Ф.',
        // Вопросы участников (доп. текст в подвале и кабинете). Ссылки Telegram + MAX заданы в коде (partials/official_channels.php).
        'organizer_contact' => '',
        // Яндекс.Метрика: числовой ID счётчика из интерфейса Метрики. 0 или пусто — код не подключается.
        'yandex_metrika_id' => 0,
        // Канонический URL сайта (https://домен) — для писем, ссылок и cron (не доверяйте HTTP_HOST на проде).
        'public_site_url' => '',
        // Подробные ошибки БД в браузере. На проде всегда false.
        'debug' => false,
        // Веб-скрипты public/apply_migration_*.php. После применения миграций на проде — false.
        'web_migrations_enabled' => true,
        // Организатор (для условий участия и прозрачности взносов).
        'organizer_person_name' => 'Федоров Павел Олегович',
        'organizer_telegram_url' => 'https://t.me/Mantoo1978',
        'organizer_telegram_handle' => '@Mantoo1978',
    ],
    'db' => [
        'host' => 'localhost',
        'database' => 'your_database_name',
        'username' => 'your_database_user',
        'password' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
    // Почта: приветствие при регистрации и напоминание за ~1 ч до матча (cron).
    // transport=mail использует PHP mail() на хостинге без SMTP-пароля.
    // transport=smtp использует SMTP; на Beget часто: smtp.beget.com, порт 465 + ssl.
    'mail' => [
        'enabled' => false,
        'transport' => 'mail',
        'from_email' => 'noreply@example.com',
        'from_name' => 'Прогнозы ЧМ-2026',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_password' => '',
        // tls (STARTTLS, обычно 587) | ssl (SMTPS, обычно 465) | пусто
        'smtp_encryption' => 'tls',
        // Секрет для GET https://ваш-сайт/cron_match_reminders.php?token=...
        'reminder_cron_token' => 'сгенерируйте_длинную_случайную_строку',
        // Письма с результатом матча участникам, которые ставили прогноз (после сохранения счёта).
        'match_result_notifications_enabled' => true,
        // Отдельный токен для apply_migration_*.php (рекомендуется). Пусто — используется reminder_cron_token.
        'migration_web_token' => '',
    ],
    // API-Football (api-sports.io): авто-результаты и опционально виджеты.
    // Ключ: https://dashboard.api-football.com — ограничьте домен сайта в настройках ключа.
    'api_football' => [
        'enabled' => false,
        'base_url' => 'https://v3.football.api-sports.io',
        'api_key' => '',
        // Отдельный ключ только для виджетов в браузере (domain lock). Пусто — используется api_key.
        'widget_api_key' => '',
        'league_id' => 1,
        'season' => 2026,
        'timezone' => 'Europe/Moscow',
        'cron_token' => 'сгенерируйте_длинную_случайную_строку_для_cron',
        // Виджеты на /matches и /tournament (ключ в браузере — domain lock в dashboard).
        // Переключатель в админке /admin/api-football имеет приоритет над этим флагом.
        'widgets_enabled' => false,
        'widgets_cache_seconds' => 120,
        // Статистический обзор на карточке матча (endpoint /predictions API-Football).
        'predictions_enabled' => true,
        'predictions_cache_seconds' => 86400,
    ],
];
