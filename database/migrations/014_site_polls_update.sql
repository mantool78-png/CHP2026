UPDATE site_polls SET is_active = 0 WHERE slug = 'shock_where';

INSERT INTO site_polls (slug, title, options_json, is_active, sort_order, created_at) VALUES
(
    'host_semifinal',
    'Хозяева турнира дойдут до четвертьфинала?',
    '[{"key":"no","label":"Нет"},{"key":"all_early","label":"Все раньше"},{"key":"min2","label":"Минимум 2 сборные"},{"key":"mex_only","label":"Только Мексика"}]',
    1,
    30,
    NOW()
),
(
    'favorite_exit_first',
    'Кто из фаворитов вылетит первым?',
    '[{"key":"esp","label":"Испания"},{"key":"arg","label":"Аргентина"},{"key":"fra","label":"Франция"},{"key":"eng","label":"Англия"},{"key":"por","label":"Португалия"}]',
    1,
    40,
    NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    options_json = VALUES(options_json),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);
