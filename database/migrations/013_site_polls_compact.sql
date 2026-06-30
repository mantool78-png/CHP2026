INSERT INTO site_polls (slug, title, options_json, is_active, sort_order, created_at) VALUES
(
    'group_b_exit',
    'Группа B: кто не выйдет в плей-офф?',
    '[{"key":"usa","label":"США"},{"key":"par","label":"Парагвай"},{"key":"aus","label":"Австралия"},{"key":"tur","label":"Турция"}]',
    1,
    10,
    NOW()
),
(
    'final_total_goals',
    'Сколько голов будет в финале?',
    '[{"key":"0-1","label":"0–1"},{"key":"2-3","label":"2–3"},{"key":"4plus","label":"4+"},{"key":"aet","label":"Решит доп. время или пенальти"}]',
    1,
    20,
    NOW()
),
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
),
(
    'bra_arg_depth',
    'Бразилия или Аргентина — кто пройдёт дальше?',
    '[{"key":"bra","label":"Бразилия"},{"key":"arg","label":"Аргентина"},{"key":"tie","label":"Примерно одинаково"},{"key":"neither","label":"Ни одна до финала"}]',
    1,
    50,
    NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    options_json = VALUES(options_json),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);
