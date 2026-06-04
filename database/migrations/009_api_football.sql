-- API-Football: привязка сборных и матчей, источник результата, лог синхронизации.

ALTER TABLE teams
    ADD COLUMN api_team_id INT UNSIGNED NULL COMMENT 'ID команды в API-Football' AFTER form_last5,
    ADD UNIQUE KEY teams_api_team_id_unique (api_team_id);

ALTER TABLE matches
    ADD COLUMN api_fixture_id INT UNSIGNED NULL COMMENT 'ID матча (fixture) в API-Football' AFTER status,
    ADD COLUMN result_source ENUM('manual', 'api') NOT NULL DEFAULT 'manual' AFTER api_fixture_id,
    ADD COLUMN api_synced_at DATETIME NULL AFTER result_source,
    ADD UNIQUE KEY matches_api_fixture_id_unique (api_fixture_id);

CREATE TABLE api_football_sync_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    message VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX api_football_sync_log_created_idx (created_at),
    CONSTRAINT api_football_sync_log_match_fk FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
