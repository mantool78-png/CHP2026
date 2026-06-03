<?php

declare(strict_types=1);

/**
 * Идемпотентное применение схемы миграции 008 (группы + слоты плей-офф).
 *
 * @return list<string> человекочитаемые шаги
 */
function migration_008_apply(PDO $pdo): array
{
    $log = [];

    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    };

    $columnNullable = static function (PDO $pdo, string $table, string $column): ?string {
        $stmt = $pdo->prepare(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch();

        return $row ? (string) $row['IS_NULLABLE'] : null;
    };

    if (!$hasColumn($pdo, 'teams', 'group_name')) {
        $pdo->exec(
            "ALTER TABLE teams
             ADD COLUMN group_name CHAR(1) NULL COMMENT 'Группа A–L на групповом этапе' AFTER code"
        );
        $log[] = 'Добавлено teams.group_name';
    } else {
        $log[] = 'teams.group_name уже существует';
    }

    $homeNull = $columnNullable($pdo, 'matches', 'home_team_id');
    if ($homeNull === 'NO') {
        $pdo->exec(
            'ALTER TABLE matches
             MODIFY home_team_id INT UNSIGNED NULL,
             MODIFY away_team_id INT UNSIGNED NULL'
        );
        $log[] = 'matches: home_team_id и away_team_id теперь NULL';
    } else {
        $log[] = 'matches FK уже nullable или таблица нестандартна — MODIFY пропущен';
    }

    if (!$hasColumn($pdo, 'matches', 'bracket_code')) {
        $pdo->exec(
            "ALTER TABLE matches
             ADD COLUMN bracket_code VARCHAR(20) NULL COMMENT 'Код слота в сетке' AFTER stage,
             ADD COLUMN placeholder_home VARCHAR(50) NULL COMMENT 'Подпись хозяев до определения команды' AFTER away_team_id,
             ADD COLUMN placeholder_away VARCHAR(50) NULL COMMENT 'Подпись гостей до определения команды' AFTER placeholder_home"
        );
        $log[] = 'Добавлены bracket_code и placeholder_* в matches';
    } else {
        $log[] = 'matches.bracket_code уже существует';
    }

    return $log;
}
