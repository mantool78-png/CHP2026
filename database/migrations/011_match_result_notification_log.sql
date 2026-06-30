-- Лог писем «результат матча» (user + match + счёт на момент отправки).

CREATE TABLE match_result_notification_log (
    user_id INT UNSIGNED NOT NULL,
    match_id INT UNSIGNED NOT NULL,
    result_home TINYINT UNSIGNED NOT NULL,
    result_away TINYINT UNSIGNED NOT NULL,
    sent_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, match_id),
    CONSTRAINT match_result_notification_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT match_result_notification_match_fk FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
