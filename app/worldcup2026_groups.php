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

/** Нормализация русского названия сборной для сопоставления (апострофы, пробелы). */
function worldcup2026_normalize_team_name_ru(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = str_replace(['’', '‘', '`', '´'], "'", $name);
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

    return trim($name);
}

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

    $n = worldcup2026_normalize_team_name_ru($name);
    foreach (WORLD_CUP_2026_GROUP_BY_RU_NAME as $alias => $group) {
        if (worldcup2026_normalize_team_name_ru($alias) === $n) {
            return $group;
        }
    }

    return null;
}

/**
 * Код FIFA (MEX, CIV, …) по коду или русскому названию из справочника.
 */
function worldcup2026_team_code(?string $code, string $name = ''): ?string
{
    if ($code !== null && $code !== '') {
        $u = strtoupper(trim($code));
        if ($u === 'IVO') {
            $u = 'CIV';
        }
        if (isset(WORLD_CUP_2026_TEAMS[$u])) {
            return $u;
        }
    }

    $n = worldcup2026_normalize_team_name_ru($name);
    if ($n === '') {
        return null;
    }

    foreach (WORLD_CUP_2026_TEAMS as $teamCode => $meta) {
        if (worldcup2026_normalize_team_name_ru($meta['name_ru']) === $n) {
            return $teamCode;
        }
    }

    foreach (WORLD_CUP_2026_GROUP_BY_RU_NAME as $alias => $group) {
        if (worldcup2026_normalize_team_name_ru($alias) !== $n) {
            continue;
        }

        foreach (WORLD_CUP_2026_TEAMS as $teamCode => $meta) {
            if ($meta['group'] !== $group) {
                continue;
            }
            $canonical = worldcup2026_normalize_team_name_ru($meta['name_ru']);
            if ($canonical === $n || str_replace("'", '', $canonical) === str_replace("'", '', $n)) {
                return $teamCode;
            }
        }
    }

    return null;
}

/** ID записи teams по коду FIFA или названию из справочника. */
function find_worldcup2026_team_id(?string $code, string $name = ''): ?int
{
    $resolved = worldcup2026_team_code($code, $name);
    if ($resolved === null) {
        return null;
    }

    $stmt = db()->prepare('SELECT id FROM teams WHERE UPPER(code) = ? LIMIT 1');
    $stmt->execute([$resolved]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    foreach (db()->query('SELECT id, name, code FROM teams')->fetchAll() as $row) {
        if (worldcup2026_team_code(
            $row['code'] !== null && (string) $row['code'] !== '' ? (string) $row['code'] : null,
            (string) $row['name']
        ) === $resolved) {
            return (int) $row['id'];
        }
    }

    return null;
}

/**
 * Сливает дубликаты сборных (разные апострофы в названии, импорт без code) в одну каноническую запись.
 */
function consolidate_worldcup2026_team_duplicates(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $rows = db()->query(
        'SELECT id, name, code, fifa_rank, brief_note, form_last5, api_team_id FROM teams ORDER BY id'
    )->fetchAll();
    $byCode = [];
    foreach ($rows as $row) {
        $resolved = worldcup2026_team_code(
            $row['code'] !== null && (string) $row['code'] !== '' ? (string) $row['code'] : null,
            (string) $row['name']
        );
        if ($resolved === null) {
            continue;
        }
        $byCode[$resolved][] = $row;
    }

    $scoreRow = static function (array $row): int {
        $score = 0;
        if ($row['code'] !== null && trim((string) $row['code']) !== '') {
            $score += 100;
        }
        if ($row['api_team_id'] !== null && (int) $row['api_team_id'] > 0) {
            $score += 20;
        }
        if ($row['fifa_rank'] !== null && $row['fifa_rank'] !== '') {
            $score += 10;
        }
        if (trim((string) ($row['brief_note'] ?? '')) !== '') {
            $score += 5;
        }
        if (trim((string) ($row['form_last5'] ?? '')) !== '') {
            $score += 5;
        }

        return $score;
    };

    $updateCanonical = db()->prepare(
        'UPDATE teams
         SET name = ?, code = ?,
             fifa_rank = COALESCE(?, fifa_rank),
             brief_note = COALESCE(?, brief_note),
             form_last5 = COALESCE(?, form_last5),
             api_team_id = COALESCE(?, api_team_id),
             updated_at = NOW()
         WHERE id = ?'
    );
    $rehome = db()->prepare('UPDATE matches SET home_team_id = ? WHERE home_team_id = ?');
    $reaway = db()->prepare('UPDATE matches SET away_team_id = ? WHERE away_team_id = ?');
    $rechamp = db()->prepare('UPDATE champion_predictions SET team_id = ? WHERE team_id = ?');
    $delete = db()->prepare('DELETE FROM teams WHERE id = ?');

    foreach ($byCode as $fifaCode => $group) {
        $meta = WORLD_CUP_2026_TEAMS[$fifaCode];
        usort($group, static fn (array $a, array $b): int => $scoreRow($b) <=> $scoreRow($a));
        $canonical = $group[0];
        $canonicalId = (int) $canonical['id'];

        $fifaRank = $canonical['fifa_rank'];
        $briefNote = $canonical['brief_note'];
        $formLast5 = $canonical['form_last5'];
        $apiTeamId = $canonical['api_team_id'];
        foreach (array_slice($group, 1) as $dup) {
            if (($fifaRank === null || $fifaRank === '') && $dup['fifa_rank'] !== null && $dup['fifa_rank'] !== '') {
                $fifaRank = $dup['fifa_rank'];
            }
            if (trim((string) ($briefNote ?? '')) === '' && trim((string) ($dup['brief_note'] ?? '')) !== '') {
                $briefNote = $dup['brief_note'];
            }
            if (trim((string) ($formLast5 ?? '')) === '' && trim((string) ($dup['form_last5'] ?? '')) !== '') {
                $formLast5 = $dup['form_last5'];
            }
            if (($apiTeamId === null || (int) $apiTeamId <= 0) && $dup['api_team_id'] !== null && (int) $dup['api_team_id'] > 0) {
                $apiTeamId = $dup['api_team_id'];
            }
        }

        $updateCanonical->execute([
            $meta['name_ru'],
            $fifaCode,
            $fifaRank !== null && $fifaRank !== '' ? (int) $fifaRank : null,
            trim((string) ($briefNote ?? '')) !== '' ? (string) $briefNote : null,
            trim((string) ($formLast5 ?? '')) !== '' ? (string) $formLast5 : null,
            $apiTeamId !== null && (int) $apiTeamId > 0 ? (int) $apiTeamId : null,
            $canonicalId,
        ]);

        foreach (array_slice($group, 1) as $dup) {
            $dupId = (int) $dup['id'];
            if ($dupId === $canonicalId) {
                continue;
            }
            $rehome->execute([$canonicalId, $dupId]);
            $reaway->execute([$canonicalId, $dupId]);
            $rechamp->execute([$canonicalId, $dupId]);
            $delete->execute([$dupId]);
        }
    }
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

    consolidate_worldcup2026_team_duplicates();

    $insert = db()->prepare(
        'INSERT INTO teams (name, code, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
    );

    foreach (WORLD_CUP_2026_TEAMS as $code => $meta) {
        if (find_worldcup2026_team_id($code, $meta['name_ru']) !== null) {
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
