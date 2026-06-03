-- Группы команд и слоты плей-офф (TBD-команды, плейсхолдеры, bracket_code)

ALTER TABLE teams
    ADD COLUMN group_name CHAR(1) NULL COMMENT 'Группа A–L на групповом этапе' AFTER code;

ALTER TABLE matches
    MODIFY home_team_id INT UNSIGNED NULL,
    MODIFY away_team_id INT UNSIGNED NULL;

ALTER TABLE matches
    ADD COLUMN bracket_code VARCHAR(20) NULL COMMENT 'Код слота в сетке' AFTER stage,
    ADD COLUMN placeholder_home VARCHAR(50) NULL COMMENT 'Подпись хозяев до определения команды' AFTER away_team_id,
    ADD COLUMN placeholder_away VARCHAR(50) NULL COMMENT 'Подпись гостей до определения команды' AFTER placeholder_home;
