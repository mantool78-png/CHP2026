<?php

declare(strict_types=1);

/**
 * Группы группового этапа ЧМ-2026 по официальной жеребьёвке (декабрь 2025).
 * Источник состава: https://en.wikipedia.org/wiki/2026_FIFA_World_Cup#Group_stage
 */

/**
 * Канонический справочник 48 сборных: код FIFA, группа, русское имя, slug флага (flagcdn).
 *
 * @var array<string, array{group: string, name_ru: string, flag: string}>
 */
const WORLD_CUP_2026_TEAMS = [
    'MEX' => ['group' => 'A', 'name_ru' => 'Мексика', 'flag' => 'mx'],
    'RSA' => ['group' => 'A', 'name_ru' => 'ЮАР', 'flag' => 'za'],
    'KOR' => ['group' => 'A', 'name_ru' => 'Республика Корея', 'flag' => 'kr'],
    'CZE' => ['group' => 'A', 'name_ru' => 'Чехия', 'flag' => 'cz'],
    'CAN' => ['group' => 'B', 'name_ru' => 'Канада', 'flag' => 'ca'],
    'BIH' => ['group' => 'B', 'name_ru' => 'Босния и Герцеговина', 'flag' => 'ba'],
    'QAT' => ['group' => 'B', 'name_ru' => 'Катар', 'flag' => 'qa'],
    'SUI' => ['group' => 'B', 'name_ru' => 'Швейцария', 'flag' => 'ch'],
    'BRA' => ['group' => 'C', 'name_ru' => 'Бразилия', 'flag' => 'br'],
    'MAR' => ['group' => 'C', 'name_ru' => 'Марокко', 'flag' => 'ma'],
    'HAI' => ['group' => 'C', 'name_ru' => 'Гаити', 'flag' => 'ht'],
    'SCO' => ['group' => 'C', 'name_ru' => 'Шотландия', 'flag' => 'gb-sct'],
    'USA' => ['group' => 'D', 'name_ru' => 'США', 'flag' => 'us'],
    'PAR' => ['group' => 'D', 'name_ru' => 'Парагвай', 'flag' => 'py'],
    'AUS' => ['group' => 'D', 'name_ru' => 'Австралия', 'flag' => 'au'],
    'TUR' => ['group' => 'D', 'name_ru' => 'Турция', 'flag' => 'tr'],
    'GER' => ['group' => 'E', 'name_ru' => 'Германия', 'flag' => 'de'],
    'CUW' => ['group' => 'E', 'name_ru' => 'Кюрасао', 'flag' => 'cw'],
    'CIV' => ['group' => 'E', 'name_ru' => 'Кот-д\'Ивуар', 'flag' => 'ci'],
    'ECU' => ['group' => 'E', 'name_ru' => 'Эквадор', 'flag' => 'ec'],
    'NED' => ['group' => 'F', 'name_ru' => 'Нидерланды', 'flag' => 'nl'],
    'JPN' => ['group' => 'F', 'name_ru' => 'Япония', 'flag' => 'jp'],
    'SWE' => ['group' => 'F', 'name_ru' => 'Швеция', 'flag' => 'se'],
    'TUN' => ['group' => 'F', 'name_ru' => 'Тунис', 'flag' => 'tn'],
    'BEL' => ['group' => 'G', 'name_ru' => 'Бельгия', 'flag' => 'be'],
    'EGY' => ['group' => 'G', 'name_ru' => 'Египет', 'flag' => 'eg'],
    'IRN' => ['group' => 'G', 'name_ru' => 'Иран', 'flag' => 'ir'],
    'NZL' => ['group' => 'G', 'name_ru' => 'Новая Зеландия', 'flag' => 'nz'],
    'ESP' => ['group' => 'H', 'name_ru' => 'Испания', 'flag' => 'es'],
    'CPV' => ['group' => 'H', 'name_ru' => 'Кабо-Верде', 'flag' => 'cv'],
    'KSA' => ['group' => 'H', 'name_ru' => 'Саудовская Аравия', 'flag' => 'sa'],
    'URU' => ['group' => 'H', 'name_ru' => 'Уругвай', 'flag' => 'uy'],
    'FRA' => ['group' => 'I', 'name_ru' => 'Франция', 'flag' => 'fr'],
    'SEN' => ['group' => 'I', 'name_ru' => 'Сенегал', 'flag' => 'sn'],
    'IRQ' => ['group' => 'I', 'name_ru' => 'Ирак', 'flag' => 'iq'],
    'NOR' => ['group' => 'I', 'name_ru' => 'Норвегия', 'flag' => 'no'],
    'ARG' => ['group' => 'J', 'name_ru' => 'Аргентина', 'flag' => 'ar'],
    'ALG' => ['group' => 'J', 'name_ru' => 'Алжир', 'flag' => 'dz'],
    'AUT' => ['group' => 'J', 'name_ru' => 'Австрия', 'flag' => 'at'],
    'JOR' => ['group' => 'J', 'name_ru' => 'Иордания', 'flag' => 'jo'],
    'POR' => ['group' => 'K', 'name_ru' => 'Португалия', 'flag' => 'pt'],
    'COD' => ['group' => 'K', 'name_ru' => 'ДР Конго', 'flag' => 'cd'],
    'UZB' => ['group' => 'K', 'name_ru' => 'Узбекистан', 'flag' => 'uz'],
    'COL' => ['group' => 'K', 'name_ru' => 'Колумбия', 'flag' => 'co'],
    'ENG' => ['group' => 'L', 'name_ru' => 'Англия', 'flag' => 'gb-eng'],
    'CRO' => ['group' => 'L', 'name_ru' => 'Хорватия', 'flag' => 'hr'],
    'GHA' => ['group' => 'L', 'name_ru' => 'Гана', 'flag' => 'gh'],
    'PAN' => ['group' => 'L', 'name_ru' => 'Панама', 'flag' => 'pa'],
];

/** @var array<string, string> code uppercase => letter A–L */
const WORLD_CUP_2026_GROUP_BY_CODE = [
    'MEX' => 'A', 'RSA' => 'A', 'KOR' => 'A', 'CZE' => 'A',
    'CAN' => 'B', 'BIH' => 'B', 'QAT' => 'B', 'SUI' => 'B',
    'BRA' => 'C', 'MAR' => 'C', 'HAI' => 'C', 'SCO' => 'C',
    'USA' => 'D', 'PAR' => 'D', 'AUS' => 'D', 'TUR' => 'D',
    'GER' => 'E', 'CUW' => 'E', 'CIV' => 'E', 'ECU' => 'E',
    'NED' => 'F', 'JPN' => 'F', 'SWE' => 'F', 'TUN' => 'F',
    'BEL' => 'G', 'EGY' => 'G', 'IRN' => 'G', 'NZL' => 'G',
    'ESP' => 'H', 'CPV' => 'H', 'KSA' => 'H', 'URU' => 'H',
    'FRA' => 'I', 'SEN' => 'I', 'IRQ' => 'I', 'NOR' => 'I',
    'ARG' => 'J', 'ALG' => 'J', 'AUT' => 'J', 'JOR' => 'J',
    'POR' => 'K', 'COD' => 'K', 'UZB' => 'K', 'COL' => 'K',
    'ENG' => 'L', 'CRO' => 'L', 'GHA' => 'L', 'PAN' => 'L',
];

/**
 * Подписи групп (удобочитаемо для интерфейса).
 *
 * @var array<string, string>
 */
const WORLD_CUP_2026_GROUP_LABEL_RU = [
    'A' => 'Мексика · ЮАР · Республика Корея · Чехия',
    'B' => 'Канада · Босния и Герцеговина · Катар · Швейцария',
    'C' => 'Бразилия · Марокко · Гаити · Шотландия',
    'D' => 'США · Парагвай · Австралия · Турция',
    'E' => 'Германия · Кюрасао · Кот-д\'Ивуар · Эквадор',
    'F' => 'Нидерланды · Япония · Швеция · Тунис',
    'G' => 'Бельгия · Египет · Иран · Новая Зеландия',
    'H' => 'Испания · Кабо-Верде · Саудовская Аравия · Уругвай',
    'I' => 'Франция · Сенегал · Ирак · Норвегия',
    'J' => 'Аргентина · Алжир · Австрия · Иордания',
    'K' => 'Португалия · ДР Конго · Узбекистан · Колумбия',
    'L' => 'Англия · Хорватия · Гана · Панама',
];

/** @var array<string, string> lowercase Russian name => letter */
const WORLD_CUP_2026_GROUP_BY_RU_NAME = [
    // A
    'мексика' => 'A',
    'юар' => 'A', 'южная африка' => 'A',
    'республика корея' => 'A', 'южная корея' => 'A',
    'чехия' => 'A',
    // B
    'канада' => 'B',
    'босния и герцеговина' => 'B',
    'катар' => 'B',
    'швейцария' => 'B',
    // C
    'бразилия' => 'C',
    'марокко' => 'C',
    'гаити' => 'C',
    'шотландия' => 'C',
    // D
    'сша' => 'D',
    'парагвай' => 'D',
    'австралия' => 'D',
    'турция' => 'D',
    // E
    'германия' => 'E',
    'кюрасао' => 'E', 'кюрасо' => 'E',
    "кот-д'ивуар" => 'E', 'кот-дивуар' => 'E', 'кот д\'ивуар' => 'E',
    'берег слоновой кости' => 'E', 'кот-д’ивуар' => 'E',
    'эквадор' => 'E',
    // F
    'нидерланды' => 'F',
    'япония' => 'F',
    'швеция' => 'F',
    'тунис' => 'F',
    // G
    'бельгия' => 'G',
    'египет' => 'G',
    'иран' => 'G',
    'новая зеландия' => 'G',
    // H
    'испания' => 'H',
    'кабо-верде' => 'H',
    'саудовская аравия' => 'H',
    'уругвай' => 'H',
    // I
    'франция' => 'I',
    'сенегал' => 'I',
    'ирак' => 'I',
    'норвегия' => 'I',
    // J
    'аргентина' => 'J',
    'алжир' => 'J',
    'австрия' => 'J',
    'иордания' => 'J',
    // K
    'португалия' => 'K',
    'др конго' => 'K',
    'узбекистан' => 'K',
    'колумбия' => 'K',
    // L
    'англия' => 'L',
    'хорватия' => 'L',
    'гана' => 'L',
    'панама' => 'L',
];

/**
 * Буква группы A–L для команды по коду FIFA или русскому названию.
 */
function worldcup2026_group_for_team(?string $code, string $name): ?string
{
    $resolved = worldcup2026_team_code($code, $name);
    if ($resolved !== null) {
        return WORLD_CUP_2026_TEAMS[$resolved]['group'];
    }

    if ($code !== null && $code !== '') {
        $u = strtoupper(trim($code));
        if (isset(WORLD_CUP_2026_GROUP_BY_CODE[$u])) {
            return WORLD_CUP_2026_GROUP_BY_CODE[$u];
        }
    }

    $n = mb_strtolower(trim($name), 'UTF-8');

    return WORLD_CUP_2026_GROUP_BY_RU_NAME[$n] ?? null;
}

/**
 * Код FIFA (MEX, CIV, …) по коду или русскому названию из справочника.
 */
function worldcup2026_team_code(?string $code, string $name = ''): ?string
{
    if ($code !== null && $code !== '') {
        $u = strtoupper(trim($code));
        if (isset(WORLD_CUP_2026_TEAMS[$u])) {
            return $u;
        }
    }

    $n = mb_strtolower(trim($name), 'UTF-8');
    if ($n === '') {
        return null;
    }

    foreach (WORLD_CUP_2026_TEAMS as $teamCode => $meta) {
        if (mb_strtolower($meta['name_ru'], 'UTF-8') === $n) {
            return $teamCode;
        }
    }

    $group = WORLD_CUP_2026_GROUP_BY_RU_NAME[$n] ?? null;
    if ($group === null) {
        return null;
    }

    foreach (WORLD_CUP_2026_TEAMS as $teamCode => $meta) {
        if ($meta['group'] === $group && mb_strtolower($meta['name_ru'], 'UTF-8') === $n) {
            return $teamCode;
        }
    }

    return null;
}

/** Путь к SVG-флагу сборной (или null). */
function worldcup2026_flag_path(?string $code, string $name = ''): ?string
{
    $resolved = worldcup2026_team_code($code, $name);
    if ($resolved === null) {
        return null;
    }

    return '/assets/flags/' . $resolved . '.svg';
}

/**
 * @return list<string> коды FIFA в группе A–L
 */
function worldcup2026_codes_in_group(string $letter): array
{
    $letter = strtoupper(trim($letter));
    $codes = [];
    foreach (WORLD_CUP_2026_TEAMS as $code => $meta) {
        if ($meta['group'] === $letter) {
            $codes[] = $code;
        }
    }

    return $codes;
}

/** Сборная из официального справочника 48 участников ЧМ-2026. */
function worldcup2026_is_participant_team(?string $code, string $name): bool
{
    return worldcup2026_team_code($code, $name) !== null;
}

/**
 * @param array<int, array<string, mixed>> $teams
 */
function worldcup2026_sort_teams_for_admin(array &$teams): void
{
    usort($teams, static function (array $a, array $b): int {
        $codeA = worldcup2026_team_code(
            $a['code'] !== null && (string) $a['code'] !== '' ? (string) $a['code'] : null,
            (string) $a['name']
        );
        $codeB = worldcup2026_team_code(
            $b['code'] !== null && (string) $b['code'] !== '' ? (string) $b['code'] : null,
            (string) $b['name']
        );
        $groupA = $codeA !== null ? WORLD_CUP_2026_TEAMS[$codeA]['group'] : 'Z';
        $groupB = $codeB !== null ? WORLD_CUP_2026_TEAMS[$codeB]['group'] : 'Z';
        if ($groupA !== $groupB) {
            return strcmp($groupA, $groupB);
        }
        $nameA = $codeA !== null ? WORLD_CUP_2026_TEAMS[$codeA]['name_ru'] : (string) $a['name'];
        $nameB = $codeB !== null ? WORLD_CUP_2026_TEAMS[$codeB]['name_ru'] : (string) $b['name'];

        return strcmp($nameA, $nameB);
    });
}

/** Добавляет в БД отсутствующие сборные из справочника ЧМ-2026. */
function ensure_worldcup2026_teams_in_db(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $find = db()->prepare('SELECT id FROM teams WHERE UPPER(code) = ? OR name = ? LIMIT 1');
    $insert = db()->prepare(
        'INSERT INTO teams (name, code, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
    );

    foreach (WORLD_CUP_2026_TEAMS as $code => $meta) {
        $find->execute([$code, $meta['name_ru']]);
        if ($find->fetch()) {
            continue;
        }
        $insert->execute([$meta['name_ru'], $code]);
    }
}

/** Название сборной с SVG-флагом. */
function render_team_with_flag(?string $code, string $name, string $wrapperClass = 'team-with-flag'): void
{
    $resolved = worldcup2026_team_code($code, $name);
    $flagPath = $resolved !== null ? worldcup2026_flag_path($resolved, '') : null;

    echo '<span class="' . h($wrapperClass) . '">';
    if ($flagPath !== null) {
        echo '<img class="team-flag" src="' . h($flagPath) . '" alt="" width="24" height="16" loading="lazy" decoding="async">';
    }
    echo '<span class="team-with-flag__name">' . h($name) . '</span>';
    echo '</span>';
}

/** Заголовок матча: две сборные с флагами. */
function render_match_teams_with_flags(?string $homeCode, string $homeName, ?string $awayCode, string $awayName, string $wrapperClass = 'match-teams-with-flags'): void
{
    echo '<span class="' . h($wrapperClass) . '">';
    render_team_with_flag($homeCode, $homeName);
    echo '<span class="match-teams-with-flags__vs">—</span>';
    render_team_with_flag($awayCode, $awayName);
    echo '</span>';
}
