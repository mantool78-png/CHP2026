-- Расширяем значения настроек (реквизиты и подсказки могут быть длинными).
-- Выполните на уже развёрнутой базе (phpMySQL на Beget и т.п.).
ALTER TABLE settings
    MODIFY setting_value TEXT NOT NULL;
