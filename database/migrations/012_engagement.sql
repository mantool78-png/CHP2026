CREATE TABLE IF NOT EXISTS site_polls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    options_json JSON NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_poll_votes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poll_id INT UNSIGNED NOT NULL,
    option_key VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    INDEX site_poll_votes_poll_option_idx (poll_id, option_key),
    INDEX site_poll_votes_poll_ip_idx (poll_id, ip_address),
    CONSTRAINT site_poll_votes_poll_fk FOREIGN KEY (poll_id) REFERENCES site_polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weekly_digest_log (
    user_id INT UNSIGNED NOT NULL,
    week_start DATE NOT NULL,
    sent_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, week_start),
    CONSTRAINT weekly_digest_log_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_polls (slug, title, options_json, is_active, sort_order, created_at) VALUES
(
    'group_b_exit',
    'Кто вылетит из группы B?',
    '[{"key":"usa","label":"США"},{"key":"par","label":"Парагвай"},{"key":"aus","label":"Австралия"},{"key":"tur","label":"Турция"}]',
    1,
    10,
    NOW()
),
(
    'final_total_goals',
    'Сколько голов будет в финале?',
    '[{"key":"0-2","label":"0–2"},{"key":"3-4","label":"3–4"},{"key":"5-6","label":"5–6"},{"key":"7plus","label":"7 и больше"}]',
    1,
    20,
    NOW()
)
ON DUPLICATE KEY UPDATE title = VALUES(title);
