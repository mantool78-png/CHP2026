CREATE TABLE IF NOT EXISTS champion_poll_votes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    option_key VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    INDEX champion_poll_votes_option_idx (option_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
