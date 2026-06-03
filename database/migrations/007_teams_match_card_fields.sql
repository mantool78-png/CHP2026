-- Справка на карточке матча: рейтинг FIFA и текст от администратора (источник ранга — сайт FIFA по турниру).

ALTER TABLE teams
    ADD COLUMN fifa_rank SMALLINT UNSIGNED NULL COMMENT 'Место в рейтинге FIFA (на дату обновления)' AFTER code,
    ADD COLUMN brief_note VARCHAR(600) NULL COMMENT 'Краткая справка о сборной' AFTER fifa_rank,
    ADD COLUMN form_last5 VARCHAR(40) NULL COMMENT 'Форма последних матчей, напр. ВНВПВ' AFTER brief_note;
