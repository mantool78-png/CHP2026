<?php

return [
    'app' => [
        'name' => 'Прогнозы ЧМ-2026',
        'timezone' => 'Europe/Moscow',
        'entry_fee_rub' => 1000,
        'prize_pool_percent' => 90,
        'prize_distribution_percent' => [30, 20, 15, 10, 7, 5, 4, 3, 3, 3],
        'prediction_lock_minutes' => 5,
        'free_prediction_limit' => 5,
        'champion_prediction_deadline' => '',
        // Личный кабинет (до оплаты). Можно переопределить в админке: «Тексты оплаты».
        'payment_instructions' => 'Реквизиты для оплаты организатор сообщит отдельно.',
        'payment_comment_hint' => 'ЧМ-2026, ваш email или имя на сайте.',
        // Вопросы участников (Telegram, email). Редактируется также в админке «Тексты для участников».
        'organizer_contact' => '',
    ],
    'db' => [
        'host' => 'localhost',
        'database' => 'your_database_name',
        'username' => 'your_database_user',
        'password' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
];
