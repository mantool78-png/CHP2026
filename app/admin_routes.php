<?php

if ($method === 'GET' && $path === '/admin') {
    require_admin();

    $stats = [
        'participants' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'participant'")->fetchColumn(),
        'active' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status = 'active'")->fetchColumn(),
        'pending' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status = 'pending_payment'")->fetchColumn(),
        'matches' => (int) db()->query('SELECT COUNT(*) FROM matches')->fetchColumn(),
    ];

    view('admin/index', [
        'stats' => $stats,
        'prizePool' => prize_pool(),
        'teams' => db()->query('SELECT * FROM teams ORDER BY name')->fetchAll(),
        'championTeamId' => db()->query("SELECT setting_value FROM settings WHERE setting_key = 'champion_team_id'")->fetchColumn() ?: null,
    ]);
    return;
}

if ($method === 'POST' && $path === '/admin/champion') {
    verify_csrf();
    require_admin();

    $teamId = (int) ($_POST['team_id'] ?? 0);

    $setting = db()->prepare(
        "INSERT INTO settings (setting_key, setting_value, updated_at)
         VALUES ('champion_team_id', ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );
    $setting->execute([(string) $teamId]);

    db()->prepare('UPDATE champion_predictions SET points = CASE WHEN team_id = ? THEN 10 ELSE 0 END, updated_at = NOW()')
        ->execute([$teamId]);

    flash('success', 'Чемпион сохранен, бонусные очки пересчитаны.');
    redirect('/admin');
}

if ($method === 'GET' && $path === '/admin/password') {
    require_admin();

    view('admin/password');
    return;
}

if ($method === 'POST' && $path === '/admin/password') {
    verify_csrf();
    $admin = require_admin();

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $newPasswordConfirmation = (string) ($_POST['new_password_confirmation'] ?? '');

    if (!password_verify($currentPassword, $admin['password_hash'])) {
        flash('error', 'Текущий пароль указан неверно.');
        redirect('/admin/password');
    }

    if (strlen($newPassword) < 8) {
        flash('error', 'Новый пароль должен быть не короче 8 символов.');
        redirect('/admin/password');
    }

    if ($newPassword !== $newPasswordConfirmation) {
        flash('error', 'Новый пароль и подтверждение не совпадают.');
        redirect('/admin/password');
    }

    $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $admin['id']]);

    flash('success', 'Пароль администратора изменен.');
    redirect('/admin');
}

if ($method === 'GET' && $path === '/admin/teams') {
    require_admin();

    $teams = db()->query(
        "SELECT t.*,
                (
                    SELECT COUNT(*)
                    FROM matches m
                    WHERE m.home_team_id = t.id OR m.away_team_id = t.id
                ) AS matches_count,
                (
                    SELECT COUNT(*)
                    FROM champion_predictions cp
                    WHERE cp.team_id = t.id
                ) AS champion_predictions_count
         FROM teams t
         ORDER BY t.name"
    )->fetchAll();

    view('admin/teams', ['teams' => $teams]);
    return;
}

if ($method === 'POST' && $path === '/admin/teams/create') {
    verify_csrf();
    require_admin();

    $name = trim((string) ($_POST['name'] ?? ''));
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $code = $code === '' ? null : $code;

    if ($name === '') {
        flash('error', 'Укажите название команды.');
        redirect('/admin/teams');
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM teams WHERE name = ? OR (code IS NOT NULL AND code = ?)');
    $stmt->execute([$name, $code]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Команда с таким названием или кодом уже есть.');
        redirect('/admin/teams');
    }

    $stmt = db()->prepare('INSERT INTO teams (name, code, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
    $stmt->execute([$name, $code]);

    flash('success', 'Команда добавлена.');
    redirect('/admin/teams');
}

if ($method === 'POST' && $path === '/admin/teams/update') {
    verify_csrf();
    require_admin();

    $teamId = (int) ($_POST['team_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $code = $code === '' ? null : $code;

    if ($teamId <= 0 || $name === '') {
        flash('error', 'Проверьте название команды.');
        redirect('/admin/teams');
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM teams WHERE id <> ? AND (name = ? OR (code IS NOT NULL AND code = ?))');
    $stmt->execute([$teamId, $name, $code]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Команда с таким названием или кодом уже есть.');
        redirect('/admin/teams');
    }

    $stmt = db()->prepare('UPDATE teams SET name = ?, code = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$name, $code, $teamId]);

    flash('success', 'Команда обновлена.');
    redirect('/admin/teams');
}

if ($method === 'POST' && $path === '/admin/teams/delete') {
    verify_csrf();
    require_admin();

    $teamId = (int) ($_POST['team_id'] ?? 0);

    $stmt = db()->prepare(
        "SELECT
            (SELECT COUNT(*) FROM matches WHERE home_team_id = ? OR away_team_id = ?) +
            (SELECT COUNT(*) FROM champion_predictions WHERE team_id = ?) AS usage_count"
    );
    $stmt->execute([$teamId, $teamId, $teamId]);

    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Команду нельзя удалить: она уже используется в матчах или прогнозах.');
        redirect('/admin/teams');
    }

    $stmt = db()->prepare('DELETE FROM teams WHERE id = ?');
    $stmt->execute([$teamId]);

    flash('success', 'Команда удалена.');
    redirect('/admin/teams');
}

if ($method === 'GET' && $path === '/admin/matches/import') {
    require_admin();

    view('admin/matches_import');
    return;
}

if ($method === 'POST' && $path === '/admin/matches/import') {
    verify_csrf();
    require_admin();

    if (empty($_FILES['matches_csv']['tmp_name']) || !is_uploaded_file($_FILES['matches_csv']['tmp_name'])) {
        flash('error', 'Выберите CSV-файл для импорта.');
        redirect('/admin/matches/import');
    }

    $handle = fopen($_FILES['matches_csv']['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Не удалось прочитать CSV-файл.');
        redirect('/admin/matches/import');
    }

    $createdMatches = 0;
    $createdTeams = 0;
    $skippedRows = 0;
    $lineNumber = 0;

    db()->beginTransaction();

    try {
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;
            $row = array_map(static function ($value) {
                return trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF");
            }, $row);

            if ($row === [''] || count($row) < 4) {
                $skippedRows++;
                continue;
            }

            if ($lineNumber === 1 && import_row_is_header($row)) {
                continue;
            }

            [$stage, $homeTeamName, $awayTeamName, $startsAtRaw] = array_slice($row, 0, 4);
            $startsAt = normalize_import_datetime($startsAtRaw);

            if ($stage === '' || $homeTeamName === '' || $awayTeamName === '' || !$startsAt || $homeTeamName === $awayTeamName) {
                $skippedRows++;
                continue;
            }

            $homeTeam = find_or_create_team($homeTeamName);
            $awayTeam = find_or_create_team($awayTeamName);
            $createdTeams += $homeTeam['created'] + $awayTeam['created'];

            $duplicate = db()->prepare(
                'SELECT COUNT(*) FROM matches WHERE home_team_id = ? AND away_team_id = ? AND starts_at = ?'
            );
            $duplicate->execute([(int) $homeTeam['id'], (int) $awayTeam['id'], $startsAt]);

            if ((int) $duplicate->fetchColumn() > 0) {
                $skippedRows++;
                continue;
            }

            $stmt = db()->prepare(
                "INSERT INTO matches (stage, home_team_id, away_team_id, starts_at, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'scheduled', NOW(), NOW())"
            );
            $stmt->execute([$stage, (int) $homeTeam['id'], (int) $awayTeam['id'], $startsAt]);
            $createdMatches++;
        }

        fclose($handle);
        db()->commit();
    } catch (Throwable $e) {
        fclose($handle);
        db()->rollBack();
        flash('error', 'Импорт остановлен: ' . $e->getMessage());
        redirect('/admin/matches/import');
    }

    flash(
        'success',
        'Импорт завершен. Матчей добавлено: ' . $createdMatches .
        ', команд создано: ' . $createdTeams .
        ', строк пропущено: ' . $skippedRows . '.'
    );
    redirect('/admin/matches');
}

if ($method === 'GET' && $path === '/admin/users') {
    require_admin();

    $users = db()->query(
        "SELECT u.id, u.name, u.email, u.payment_status, u.created_at,
                COALESCE(ps.predictions_count, 0) AS predictions_count,
                COALESCE(ms.match_points, 0) AS match_points,
                COALESCE(ms.exact_scores_count, 0) AS exact_scores_count,
                COALESCE(ms.outcomes_count, 0) AS outcomes_count,
                COALESCE(cp.points, 0) AS champion_points,
                COALESCE(ms.match_points, 0) + COALESCE(cp.points, 0) AS total_points,
                champion.name AS champion_team
         FROM users u
         LEFT JOIN (
            SELECT user_id, COUNT(*) AS predictions_count
            FROM predictions
            GROUP BY user_id
         ) ps ON ps.user_id = u.id
         LEFT JOIN (
            SELECT user_id,
                   SUM(points) AS match_points,
                   SUM(CASE WHEN reason = 'Точный счет' THEN 1 ELSE 0 END) AS exact_scores_count,
                   SUM(CASE WHEN reason IN ('Точный счет', 'Угадан исход') THEN 1 ELSE 0 END) AS outcomes_count
            FROM scores
            GROUP BY user_id
         ) ms ON ms.user_id = u.id
         LEFT JOIN champion_predictions cp ON cp.user_id = u.id
         LEFT JOIN teams champion ON champion.id = cp.team_id
         WHERE u.role = 'participant'
         ORDER BY u.created_at DESC"
    )->fetchAll();

    view('admin/users', ['users' => $users]);
    return;
}

if ($method === 'GET' && $path === '/admin/user') {
    require_admin();

    $userId = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.payment_status, u.created_at,
                COALESCE(ps.predictions_count, 0) AS predictions_count,
                COALESCE(ms.match_points, 0) AS match_points,
                COALESCE(ms.exact_scores_count, 0) AS exact_scores_count,
                COALESCE(ms.outcomes_count, 0) AS outcomes_count,
                COALESCE(cp.points, 0) AS champion_points,
                COALESCE(ms.match_points, 0) + COALESCE(cp.points, 0) AS total_points,
                champion.name AS champion_team
         FROM users u
         LEFT JOIN (
            SELECT user_id, COUNT(*) AS predictions_count
            FROM predictions
            GROUP BY user_id
         ) ps ON ps.user_id = u.id
         LEFT JOIN (
            SELECT user_id,
                   SUM(points) AS match_points,
                   SUM(CASE WHEN reason = 'Точный счет' THEN 1 ELSE 0 END) AS exact_scores_count,
                   SUM(CASE WHEN reason IN ('Точный счет', 'Угадан исход') THEN 1 ELSE 0 END) AS outcomes_count
            FROM scores
            GROUP BY user_id
         ) ms ON ms.user_id = u.id
         LEFT JOIN champion_predictions cp ON cp.user_id = u.id
         LEFT JOIN teams champion ON champion.id = cp.team_id
         WHERE u.id = ? AND u.role = 'participant'
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $participant = $stmt->fetch();

    if (!$participant) {
        http_response_code(404);
        view('errors/404');
        return;
    }

    $stmt = db()->prepare(
        "SELECT p.*, m.stage, m.starts_at, m.home_score AS result_home_score, m.away_score AS result_away_score,
                ht.name AS home_team, at.name AS away_team,
                COALESCE(s.points, 0) AS points,
                s.reason
         FROM predictions p
         JOIN matches m ON m.id = p.match_id
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
         WHERE p.user_id = ?
         ORDER BY m.starts_at ASC"
    );
    $stmt->execute([$userId]);

    view('admin/user_detail', [
        'participant' => $participant,
        'predictions' => $stmt->fetchAll(),
    ]);
    return;
}

if ($method === 'POST' && $path === '/admin/users/activate') {
    verify_csrf();
    require_admin();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = db()->prepare("UPDATE users SET payment_status = 'active', updated_at = NOW() WHERE id = ? AND role = 'participant'");
    $stmt->execute([$userId]);

    $payment = db()->prepare(
        "INSERT INTO payments (user_id, amount_rub, status, confirmed_by, confirmed_at, created_at, updated_at)
         VALUES (?, ?, 'confirmed', ?, NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE status = 'confirmed', confirmed_by = VALUES(confirmed_by), confirmed_at = NOW(), updated_at = NOW()"
    );
    $payment->execute([$userId, (int) config('app.entry_fee_rub'), (int) current_user()['id']]);

    flash('success', 'Участник активирован.');
    redirect('/admin/users');
}

if ($method === 'POST' && $path === '/admin/users/block') {
    verify_csrf();
    require_admin();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = db()->prepare("UPDATE users SET payment_status = 'blocked', updated_at = NOW() WHERE id = ? AND role = 'participant'");
    $stmt->execute([$userId]);

    flash('success', 'Участник заблокирован.');
    redirect('/admin/users');
}

if ($method === 'GET' && $path === '/admin/matches') {
    require_admin();

    $stageFilters = [
        'all' => 'Все',
        'group' => 'Групповой этап',
        'round32' => '1/16 финала',
        'round16' => '1/8 финала',
        'quarter' => 'Четвертьфинал',
        'semi' => 'Полуфинал',
        'third' => 'Матч за 3 место',
        'final' => 'Финал',
    ];
    $activeStage = (string) ($_GET['stage'] ?? 'all');
    if (!array_key_exists($activeStage, $stageFilters)) {
        $activeStage = 'all';
    }

    $where = '';
    $params = [];
    if ($activeStage !== 'all') {
        $where = 'WHERE m.stage LIKE ?';
        $params[] = $stageFilters[$activeStage] . '%';
    }

    $stmt = db()->prepare(
        "SELECT m.*, ht.name AS home_team, at.name AS away_team
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         $where
         ORDER BY m.starts_at ASC"
    );
    $stmt->execute($params);

    view('admin/matches', [
        'matches' => $stmt->fetchAll(),
        'teams' => db()->query('SELECT * FROM teams ORDER BY name')->fetchAll(),
        'stageFilters' => $stageFilters,
        'activeStage' => $activeStage,
    ]);
    return;
}

if ($method === 'POST' && $path === '/admin/matches/create') {
    verify_csrf();
    require_admin();

    $stmt = db()->prepare(
        "INSERT INTO matches (stage, home_team_id, away_team_id, starts_at, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'scheduled', NOW(), NOW())"
    );
    $stmt->execute([
        trim((string) ($_POST['stage'] ?? 'Групповой этап')),
        (int) ($_POST['home_team_id'] ?? 0),
        (int) ($_POST['away_team_id'] ?? 0),
        trim((string) ($_POST['starts_at'] ?? '')),
    ]);

    flash('success', 'Матч добавлен.');
    redirect('/admin/matches');
}

if ($method === 'POST' && $path === '/admin/results') {
    verify_csrf();
    require_admin();

    $matchId = (int) ($_POST['match_id'] ?? 0);
    $homeScore = max(0, (int) ($_POST['home_score'] ?? 0));
    $awayScore = max(0, (int) ($_POST['away_score'] ?? 0));

    $stmt = db()->prepare(
        "UPDATE matches
         SET home_score = ?, away_score = ?, status = 'finished', updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$homeScore, $awayScore, $matchId]);
    recalculate_scores($matchId);

    flash('success', 'Результат сохранен, очки пересчитаны.');
    redirect('/admin/matches');
}

function import_row_is_header(array $row): bool
{
    $firstCell = function_exists('mb_strtolower') ? mb_strtolower($row[0], 'UTF-8') : strtolower($row[0]);

    return in_array($firstCell, ['стадия', 'stage'], true);
}

function normalize_import_datetime(string $value): ?string
{
    $value = trim(str_replace('T', ' ', $value));

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd.m.Y H:i:s',
        'd.m.Y H:i',
        'd/m/Y H:i',
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
}

function find_or_create_team(string $name): array
{
    $stmt = db()->prepare('SELECT id FROM teams WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return ['id' => (int) $id, 'created' => 0];
    }

    $stmt = db()->prepare('INSERT INTO teams (name, code, created_at, updated_at) VALUES (?, NULL, NOW(), NOW())');
    $stmt->execute([$name]);

    return ['id' => (int) db()->lastInsertId(), 'created' => 1];
}
