-- Лог одноразовых напоминаний «за час до матча» (user + match).

CREATE TABLE prediction_reminder_log (
    user_id INT UNSIGNED NOT NULL,
    match_id INT UNSIGNED NOT NULL,
    sent_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, match_id),
    CONSTRAINT prediction_reminder_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT prediction_reminder_match_fk FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
