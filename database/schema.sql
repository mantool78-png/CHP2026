CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'participant') NOT NULL DEFAULT 'participant',
    payment_status ENUM('pending_payment', 'active', 'blocked') NOT NULL DEFAULT 'pending_payment',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    amount_rub INT UNSIGNED NOT NULL,
    status ENUM('pending', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending',
    comment VARCHAR(255) NULL,
    confirmed_by INT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT payments_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT payments_admin_fk FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    code VARCHAR(12) NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stage VARCHAR(120) NOT NULL,
    home_team_id INT UNSIGNED NOT NULL,
    away_team_id INT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    home_score TINYINT UNSIGNED NULL,
    away_score TINYINT UNSIGNED NULL,
    status ENUM('scheduled', 'live', 'finished') NOT NULL DEFAULT 'scheduled',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX matches_starts_at_idx (starts_at),
    CONSTRAINT matches_home_team_fk FOREIGN KEY (home_team_id) REFERENCES teams(id),
    CONSTRAINT matches_away_team_fk FOREIGN KEY (away_team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE predictions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    match_id INT UNSIGNED NOT NULL,
    home_score TINYINT UNSIGNED NOT NULL,
    away_score TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY predictions_user_match_unique (user_id, match_id),
    CONSTRAINT predictions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT predictions_match_fk FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    match_id INT UNSIGNED NOT NULL,
    points TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reason VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY scores_user_match_unique (user_id, match_id),
    CONSTRAINT scores_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT scores_match_fk FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE champion_predictions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    team_id INT UNSIGNED NOT NULL,
    points TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT champion_predictions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT champion_predictions_team_fk FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mini_leagues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    invite_code VARCHAR(16) NOT NULL UNIQUE,
    owner_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT mini_leagues_owner_fk FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mini_league_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    league_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY mini_league_members_unique (league_id, user_id),
    CONSTRAINT mini_league_members_league_fk FOREIGN KEY (league_id) REFERENCES mini_leagues(id) ON DELETE CASCADE,
    CONSTRAINT mini_league_members_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_attempt_at DATETIME NOT NULL,
    INDEX login_attempts_last_attempt_idx (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('entry_fee_rub', '1000', NOW()),
('prize_pool_percent', '90', NOW()),
('prediction_lock_minutes', '5', NOW()),
('champion_team_id', '', NOW());

INSERT INTO users (name, email, password_hash, role, payment_status, created_at, updated_at) VALUES
('Администратор', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCkYm4y8oI5K3M', 'admin', 'active', NOW(), NOW());

INSERT INTO teams (name, code, created_at, updated_at) VALUES
('Аргентина', 'ARG', NOW(), NOW()),
('Бразилия', 'BRA', NOW(), NOW()),
('Франция', 'FRA', NOW(), NOW()),
('Германия', 'GER', NOW(), NOW()),
('Испания', 'ESP', NOW(), NOW()),
('Англия', 'ENG', NOW(), NOW()),
('Португалия', 'POR', NOW(), NOW()),
('Нидерланды', 'NED', NOW(), NOW()),
('США', 'USA', NOW(), NOW()),
('Мексика', 'MEX', NOW(), NOW()),
('Канада', 'CAN', NOW(), NOW()),
('Япония', 'JPN', NOW(), NOW());

INSERT INTO matches (stage, home_team_id, away_team_id, starts_at, status, created_at, updated_at) VALUES
('Тестовый матч', 1, 2, '2026-06-11 22:00:00', 'scheduled', NOW(), NOW()),
('Тестовый матч', 3, 4, '2026-06-12 22:00:00', 'scheduled', NOW(), NOW());
