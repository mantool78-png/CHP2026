<?php

if ($method === 'GET' && $path === '/admin') {
    require_admin();

    $stats = [
        'participants' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'participant'")->fetchColumn(),
        'active' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status = 'active'")->fetchColumn(),
        'pending' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'participant' AND payment_status = 'pending_payment'")->fetchColumn(),
        'matches' => (int) db()->query('SELECT COUNT(*) FROM matches')->fetchColumn(),
    ];

    $championTeamId = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'champion_team_id'")->fetchColumn() ?: null;

    $collectedFeesRub = (int) db()->query(
        "SELECT COALESCE(SUM(amount_rub), 0) FROM payments WHERE status = 'confirmed'"
    )->fetchColumn();

    $activeParticipantsTable = db()->query(
        "SELECT u.id, u.name, u.email,
                pay.amount_rub AS payment_amount_rub,
                pay.confirmed_at
         FROM users u
         LEFT JOIN payments pay ON pay.user_id = u.id AND pay.status = 'confirmed'
         WHERE u.role = 'participant' AND u.payment_status = 'active'
         ORDER BY pay.confirmed_at IS NULL ASC, pay.confirmed_at DESC, u.name ASC"
    )->fetchAll();

    $pendingParticipantsTable = db()->query(
        "SELECT u.id, u.name, u.email, u.created_at
         FROM users u
         WHERE u.role = 'participant' AND u.payment_status = 'pending_payment'
         ORDER BY u.created_at DESC"
    )->fetchAll();

    view('admin/index', [
        'stats' => $stats,
        'prizePool' => prize_pool(),
        'collectedFeesRub' => $collectedFeesRub,
        'activeParticipantsTable' => $activeParticipantsTable,
        'pendingParticipantsTable' => $pendingParticipantsTable,
        'teams' => teams_for_champion_select_with_current(
            $championTeamId ? ['team_id' => (int) $championTeamId] : null
        ),
        'championTeamId' => $championTeamId,
    ]);
    return;
}

if ($method === 'POST' && $path === '/admin/champion') {
    verify_csrf();
    require_admin();

    $teamId = (int) ($_POST['team_id'] ?? 0);

    if ($teamId <= 0) {
        flash('error', 'Выберите команду из списка.');
        redirect('/admin');
    }

    $teamStmt = db()->prepare('SELECT * FROM teams WHERE id = ? LIMIT 1');
    $teamStmt->execute([$teamId]);
    $teamRow = $teamStmt->fetch();
    if (!$teamRow || !team_is_champion_pick_candidate($teamRow)) {
        flash('error', 'Выберите реальную сборную-победителя, а не слот из расписания.');
        redirect('/admin');
    }

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

if ($method === 'GET' && $path === '/admin/settings') {
    require_admin();

    view('admin/site_settings', [
        'paymentInstructions' => payment_instructions(),
        'paymentCommentHint' => payment_comment_hint(),
        'organizerContact' => organizer_contact(),
    ]);
    return;
}

if ($method === 'POST' && $path === '/admin/settings') {
    verify_csrf();
    require_admin();

    $payment = trim((string) ($_POST['payment_instructions'] ?? ''));
    $comment = trim((string) ($_POST['payment_comment_hint'] ?? ''));
    $organizer = trim((string) ($_POST['organizer_contact'] ?? ''));

    if (mb_strlen($payment) > 8000 || mb_strlen($comment) > 500 || mb_strlen($organizer) > 1500) {
        flash('error', 'Текст слишком длинный. Сократите и попробуйте снова.');
        redirect('/admin/settings');
    }

    $delete = db()->prepare('DELETE FROM settings WHERE setting_key = ?');
    $upsert = db()->prepare(
        "INSERT INTO settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );

    if ($payment === '') {
        $delete->execute(['site_payment_instructions']);
    } else {
        $upsert->execute(['site_payment_instructions', $payment]);
    }

    if ($comment === '') {
        $delete->execute(['site_payment_comment_hint']);
    } else {
        $upsert->execute(['site_payment_comment_hint', $comment]);
    }

    if ($organizer === '') {
        $delete->execute(['site_organizer_contact']);
    } else {
        $upsert->execute(['site_organizer_contact', $organizer]);
    }

    flash('success', 'Сохранено. Пустое поле снова берёт значение из config.php (если оно там задано).');
    redirect('/admin/settings');
}

if ($method === 'POST' && $path === '/admin/settings/reset') {
    verify_csrf();
    require_admin();

    $stmt = db()->prepare(
        "DELETE FROM settings WHERE setting_key IN ('site_payment_instructions', 'site_payment_comment_hint', 'site_organizer_contact')"
    );
    $stmt->execute();

    flash('success', 'Сброшено: снова используются значения из файла config.php на сервере.');
    redirect('/admin/settings');
}

if ($method === 'GET' && $path === '/admin/teams') {
    require_admin();
    ensure_worldcup2026_teams_in_db();

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

    $teams = array_values(array_filter($teams, static function (array $team): bool {
        return worldcup2026_is_participant_team(
            $team['code'] !== null && (string) $team['code'] !== '' ? (string) $team['code'] : null,
            (string) $team['name']
        );
    }));
    worldcup2026_sort_teams_for_admin($teams);

    view('admin/teams', ['teams' => $teams]);
    return;
}

if ($method === 'POST' && $path === '/admin/teams/create') {
    verify_csrf();
    require_admin();

    flash('error', 'Состав сборных ЧМ-2026 фиксирован — добавление команд отключено.');
    redirect('/admin/teams');
}

if ($method === 'POST' && $path === '/admin/teams/update') {
    verify_csrf();
    require_admin();

    $teamId = (int) ($_POST['team_id'] ?? 0);
    $teamsUrl = static function (?int $anchorTeamId = null): string {
        $url = '/admin/teams';
        if ($anchorTeamId !== null && $anchorTeamId > 0) {
            $url .= '#team-' . $anchorTeamId;
        }

        return $url;
    };

    if ($teamId <= 0) {
        flash('error', 'Команда не найдена.');
        redirect($teamsUrl());
    }

    $stmt = db()->prepare('SELECT * FROM teams WHERE id = ? LIMIT 1');
    $stmt->execute([$teamId]);
    $team = $stmt->fetch();
    if (!$team || !worldcup2026_is_participant_team(
        $team['code'] !== null && (string) $team['code'] !== '' ? (string) $team['code'] : null,
        (string) $team['name']
    )) {
        flash('error', 'Редактировать можно только сборные участников ЧМ-2026.');
        redirect($teamsUrl($teamId));
    }

    $fifaRankRaw = trim((string) ($_POST['fifa_rank'] ?? ''));
    $fifaRank = $fifaRankRaw !== '' ? max(1, (int) $fifaRankRaw) : null;
    $briefNote = trim((string) ($_POST['brief_note'] ?? ''));
    $formLast5 = trim((string) ($_POST['form_last5'] ?? ''));
    if (mb_strlen($briefNote) > 600) {
        flash('error', 'Справка о команде слишком длинная (максимум 600 символов).');
        redirect($teamsUrl($teamId));
    }
    if (mb_strlen($formLast5) > 40) {
        flash('error', 'Поле «форма» не длиннее 40 символов.');
        redirect($teamsUrl($teamId));
    }

    $stmt = db()->prepare(
        'UPDATE teams SET fifa_rank = ?, brief_note = ?, form_last5 = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$fifaRank, $briefNote === '' ? null : $briefNote, $formLast5 === '' ? null : $formLast5, $teamId]);

    flash('success', 'Данные сборной сохранены.');
    redirect($teamsUrl($teamId));
}

if ($method === 'POST' && $path === '/admin/teams/delete') {
    verify_csrf();
    require_admin();

    $teamId = (int) ($_POST['team_id'] ?? 0);
    $teamsUrl = $teamId > 0 ? '/admin/teams#team-' . $teamId : '/admin/teams';

    flash('error', 'Удаление сборных отключено — состав турнира фиксирован.');
    redirect($teamsUrl);
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
                "INSERT INTO matches (stage, bracket_code, home_team_id, away_team_id, placeholder_home, placeholder_away, starts_at, status, created_at, updated_at)
                 VALUES (?, NULL, ?, ?, NULL, NULL, ?, 'scheduled', NOW(), NOW())"
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

if ($method === 'GET' && $path === '/admin/user/receipt') {
    require_admin();

    $userId = (int) ($_GET['id'] ?? 0);
    $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'participant' LIMIT 1");
    $check->execute([$userId]);
    if (!$check->fetch()) {
        http_response_code(404);
        exit('Участник не найден');
    }

    $receipt = payment_receipt_for_user($userId);
    if (!$receipt) {
        http_response_code(404);
        exit('Чек не загружен');
    }

    output_payment_receipt_http($receipt);
}

if ($method === 'GET' && $path === '/admin/mini-leagues') {
    require_admin();

    $leagues = admin_all_mini_leagues();
    $totalMembers = 0;
    foreach ($leagues as $league) {
        $totalMembers += (int) ($league['members_count'] ?? 0);
    }

    view('admin/mini_leagues', [
        'leagues' => $leagues,
        'totalLeagues' => count($leagues),
        'totalMembers' => $totalMembers,
    ]);
    return;
}

if ($method === 'GET' && $path === '/admin/mini-league') {
    require_admin();

    $leagueId = (int) ($_GET['id'] ?? 0);
    $league = find_mini_league($leagueId);
    if (!$league) {
        http_response_code(404);
        view('errors/404');
        return;
    }

    view('admin/mini_league', [
        'league' => $league,
        'members' => admin_mini_league_members($leagueId),
        'leaders' => mini_league_leaderboard($leagueId),
        'inviteLink' => absolute_url('/mini-leagues/join?code=' . rawurlencode((string) $league['invite_code'])),
    ]);
    return;
}

if ($method === 'GET' && $path === '/admin/users') {
    require_admin();

    $statusFilters = [
        'all' => 'Все',
        'pending_payment' => 'Ожидают оплаты',
        'active' => 'Активные',
        'blocked' => 'Заблокированные',
    ];
    $activeStatus = (string) ($_GET['status'] ?? 'all');
    if (!array_key_exists($activeStatus, $statusFilters)) {
        $activeStatus = 'all';
    }

    $where = "WHERE u.role = 'participant'";
    $params = [];
    if ($activeStatus !== 'all') {
        $where .= ' AND u.payment_status = ?';
        $params[] = $activeStatus;
    }

    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.payment_status, u.created_at,
                pay.amount_rub AS payment_amount_rub,
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
                   SUM(CASE WHEN reason = 'Угадан исход' THEN 1 ELSE 0 END) AS outcomes_count
            FROM scores
            GROUP BY user_id
         ) ms ON ms.user_id = u.id
         LEFT JOIN champion_predictions cp ON cp.user_id = u.id
         LEFT JOIN teams champion ON champion.id = cp.team_id
         LEFT JOIN payments pay ON pay.user_id = u.id AND pay.status = 'confirmed'
         $where
         ORDER BY u.created_at DESC"
    );
    $stmt->execute($params);

    view('admin/users', [
        'users' => $stmt->fetchAll(),
        'statusFilters' => $statusFilters,
        'activeStatus' => $activeStatus,
        'freePredictionLimit' => free_prediction_limit(),
    ]);
    return;
}

if ($method === 'GET' && $path === '/admin/user') {
    require_admin();

    $userId = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.payment_status, u.created_at,
                pay.amount_rub AS payment_amount_rub,
                pr.original_name AS receipt_original_name,
                pr.mime_type AS receipt_mime_type,
                pr.size_bytes AS receipt_size_bytes,
                pr.updated_at AS receipt_uploaded_at,
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
                   SUM(CASE WHEN reason = 'Угадан исход' THEN 1 ELSE 0 END) AS outcomes_count
            FROM scores
            GROUP BY user_id
         ) ms ON ms.user_id = u.id
         LEFT JOIN champion_predictions cp ON cp.user_id = u.id
         LEFT JOIN teams champion ON champion.id = cp.team_id
         LEFT JOIN payments pay ON pay.user_id = u.id AND pay.status = 'confirmed'
         LEFT JOIN payment_receipts pr ON pr.user_id = u.id
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

if ($method === 'POST' && $path === '/admin/user/reset-password') {
    verify_csrf();
    require_admin();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

    $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'participant' LIMIT 1");
    $check->execute([$userId]);
    if (!$check->fetch()) {
        flash('error', 'Участник не найден.');
        redirect('/admin/users');
    }

    if (strlen($newPassword) < 8) {
        flash('error', 'Пароль должен быть не короче 8 символов.');
        redirect('/admin/user?id=' . $userId);
    }

    if ($newPassword !== $confirm) {
        flash('error', 'Пароль и подтверждение не совпадают.');
        redirect('/admin/user?id=' . $userId);
    }

    $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ? AND role = ?');
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId, 'participant']);

    flash('success', 'Пароль участника обновлён. Сообщите ему новый пароль по доверенному каналу.');
    redirect('/admin/user?id=' . $userId);
}

if ($method === 'POST' && $path === '/admin/user/rename') {
    verify_csrf();
    require_admin();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $name = normalize_participant_display_name((string) ($_POST['name'] ?? ''));

    $check = db()->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'participant' LIMIT 1");
    $check->execute([$userId]);
    $participantRow = $check->fetch();
    if (!$participantRow) {
        flash('error', 'Участник не найден.');
        redirect('/admin/users');
    }

    if ($name === '' || mb_strlen($name) < 2) {
        flash('error', 'Укажите ник не короче 2 символов.');
        redirect('/admin/user?id=' . $userId);
    }

    if (mb_strlen($name) > 120) {
        flash('error', 'Ник не длиннее 120 символов.');
        redirect('/admin/user?id=' . $userId);
    }

    $currentName = normalize_participant_display_name((string) $participantRow['name']);
    if (mb_strtolower($name, 'UTF-8') === mb_strtolower($currentName, 'UTF-8')) {
        flash('notice', 'Ник не изменился.');
        redirect('/admin/user?id=' . $userId);
    }

    if (participant_display_name_taken($name, $userId)) {
        flash('error', 'Такой ник уже занят другим участником. Выберите другое имя.');
        redirect('/admin/user?id=' . $userId);
    }

    $stmt = db()->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ? AND role = ?');
    $stmt->execute([$name, $userId, 'participant']);

    flash('success', 'Ник участника обновлён: «' . $name . '».');
    redirect('/admin/user?id=' . $userId);
}

if ($method === 'POST' && $path === '/admin/users/activate') {
    verify_csrf();
    require_admin();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $amountRub = (int) ($_POST['amount_rub'] ?? entry_fee_rub());
    $allowedAmounts = array_keys(payment_amount_options());
    if (!in_array($amountRub, $allowedAmounts, true)) {
        $amountRub = entry_fee_rub();
    }

    $stmt = db()->prepare("UPDATE users SET payment_status = 'active', updated_at = NOW() WHERE id = ? AND role = 'participant'");
    $stmt->execute([$userId]);

    $payment = db()->prepare(
        "INSERT INTO payments (user_id, amount_rub, status, confirmed_by, confirmed_at, created_at, updated_at)
         VALUES (?, ?, 'confirmed', ?, NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE amount_rub = VALUES(amount_rub), status = 'confirmed', confirmed_by = VALUES(confirmed_by), confirmed_at = NOW(), updated_at = NOW()"
    );
    $payment->execute([$userId, $amountRub, (int) current_user()['id']]);

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

if ($method === 'POST' && $path === '/admin/users/delete') {
    verify_csrf();
    require_admin();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $adminId = (int) (current_user()['id'] ?? 0);

    if ($userId === $adminId) {
        flash('error', 'Нельзя удалить собственную учётную запись.');
        redirect('/admin/users');
    }

    $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'participant' LIMIT 1");
    $check->execute([$userId]);
    if (!$check->fetch()) {
        flash('error', 'Участник не найден.');
        redirect('/admin/users');
    }

    $receipt = payment_receipt_for_user($userId);
    if ($receipt) {
        delete_payment_receipt_file((string) ($receipt['file_path'] ?? ''));
    }

    $del = db()->prepare('DELETE FROM users WHERE id = ? AND role = ?');
    $del->execute([$userId, 'participant']);

    flash('success', 'Участник удалён вместе с прогнозами и связанными данными.');
    redirect('/admin/users');
}

if ($method === 'GET' && $path === '/admin/api-football') {
    require_admin();

    if (!api_football_schema_ready()) {
        view('admin/api_football', [
            'configured' => api_football_configured(),
            'settings' => api_football_settings(),
            'schemaReady' => false,
            'migrationUrl' => absolute_url('/public/apply_migration_009.php?token=' . rawurlencode(api_football_cron_token() ?: mail_settings()['reminder_cron_token'])),
            'lastSyncAt' => null,
            'syncLog' => [],
            'teamStats' => ['total' => 0, 'mapped' => 0],
            'matchStats' => ['total' => 0, 'mapped' => 0, 'api_source' => 0, 'manual_scored' => 0],
            'unmappedMatches' => [],
        ]);
        return;
    }

    $teamStats = api_football_worldcup_team_stats();

    $matchStats = db()->query(
        "SELECT
            COUNT(*) AS total,
            SUM(api_fixture_id IS NOT NULL) AS mapped,
            SUM(result_source = 'api') AS api_source,
            SUM(result_source = 'manual' AND home_score IS NOT NULL) AS manual_scored
         FROM matches
         WHERE home_team_id IS NOT NULL AND away_team_id IS NOT NULL"
    )->fetch();

    $unmappedMatches = db()->query(
        "SELECT m.id, m.stage, m.starts_at, m.api_fixture_id, m.result_source,
                ht.name AS home_team, at.name AS away_team,
                ht.api_team_id AS home_api, at.api_team_id AS away_api
         FROM matches m
         LEFT JOIN teams ht ON ht.id = m.home_team_id
         LEFT JOIN teams at ON at.id = m.away_team_id
         WHERE m.home_team_id IS NOT NULL AND m.away_team_id IS NOT NULL
           AND (m.api_fixture_id IS NULL OR ht.api_team_id IS NULL OR at.api_team_id IS NULL)
         ORDER BY m.starts_at ASC
         LIMIT 80"
    )->fetchAll();

    view('admin/api_football', [
        'configured' => api_football_configured(),
        'settings' => api_football_settings(),
        'schemaReady' => true,
        'migrationUrl' => '',
        'lastSyncAt' => api_football_last_sync_at(),
        'syncLog' => api_football_recent_sync_log(25),
        'teamStats' => $teamStats,
        'matchStats' => $matchStats ?: ['total' => 0, 'mapped' => 0, 'api_source' => 0, 'manual_scored' => 0],
        'unmappedMatches' => $unmappedMatches,
    ]);
    return;
}

if ($method === 'POST' && $path === '/admin/api-football/map-teams') {
    verify_csrf();
    require_admin();
    if (!api_football_configured()) {
        flash('error', 'Включите API-Football в config.php (enabled и api_key).');
        redirect('/admin/api-football');
    }
    try {
        $r = api_football_map_teams();
    } catch (Throwable $e) {
        flash('error', 'Ошибка привязки команд: ' . $e->getMessage());
        redirect('/admin/api-football');
    }
    $msg = 'Команды: привязано ' . $r['mapped'] . ', пропущено ' . $r['skipped'] . '.';
    if ($r['errors'] !== []) {
        flash('error', $msg . ' Ошибки: ' . implode('; ', array_slice($r['errors'], 0, 5)));
    } else {
        flash('success', $msg);
    }
    redirect('/admin/api-football');
}

if ($method === 'POST' && $path === '/admin/api-football/map-fixtures') {
    verify_csrf();
    require_admin();
    if (!api_football_configured()) {
        flash('error', 'Включите API-Football в config.php.');
        redirect('/admin/api-football');
    }
    try {
        $r = api_football_map_fixtures();
    } catch (Throwable $e) {
        flash('error', 'Ошибка привязки матчей: ' . $e->getMessage());
        redirect('/admin/api-football');
    }
    $msg = 'Матчи: привязано ' . $r['mapped'] . ', неоднозначно ' . $r['ambiguous'] . ', без пары ' . $r['unmatched'] . '.';
    if ($r['errors'] !== []) {
        flash('error', $msg . ' ' . implode('; ', array_slice($r['errors'], 0, 3)));
    } else {
        flash('success', $msg);
    }
    redirect('/admin/api-football');
}

if ($method === 'POST' && $path === '/admin/api-football/sync-now') {
    verify_csrf();
    require_admin();
    if (!api_football_configured()) {
        flash('error', 'Включите API-Football в config.php.');
        redirect('/admin/api-football');
    }
    try {
        $r = run_api_football_sync();
    } catch (Throwable $e) {
        flash('error', 'Ошибка синхронизации: ' . $e->getMessage());
        redirect('/admin/api-football');
    }
    flash(
        'success',
        'Синхронизация: проверено ' . $r['checked'] . ', завершено ' . $r['finished'] . ', live ' . $r['live'] . ', ошибок ' . $r['errors'] . '.'
    );
    redirect('/admin/api-football');
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
         LEFT JOIN teams ht ON ht.id = m.home_team_id
         LEFT JOIN teams at ON at.id = m.away_team_id
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

    $stage = trim((string) ($_POST['stage'] ?? ''));
    $startsAtRaw = trim((string) ($_POST['starts_at'] ?? ''));
    $bracketCode = trim((string) ($_POST['bracket_code'] ?? ''));
    $bracketCode = $bracketCode === '' ? null : mb_substr($bracketCode, 0, 20);
    $placeholderHome = trim((string) ($_POST['placeholder_home'] ?? ''));
    $placeholderAway = trim((string) ($_POST['placeholder_away'] ?? ''));
    $placeholderHome = $placeholderHome === '' ? null : mb_substr($placeholderHome, 0, 50);
    $placeholderAway = $placeholderAway === '' ? null : mb_substr($placeholderAway, 0, 50);

    $homeId = (int) ($_POST['home_team_id'] ?? 0);
    $awayId = (int) ($_POST['away_team_id'] ?? 0);
    $homeId = $homeId > 0 ? $homeId : null;
    $awayId = $awayId > 0 ? $awayId : null;

    if ($stage === '' || $startsAtRaw === '') {
        flash('error', 'Укажите стадию и дату начала матча.');
        redirect('/admin/matches');
    }

    if ($homeId !== null) {
        $chk = db()->prepare('SELECT id FROM teams WHERE id = ? LIMIT 1');
        $chk->execute([$homeId]);
        if (!$chk->fetch()) {
            flash('error', 'Команда хозяев не найдена.');
            redirect('/admin/matches');
        }
    }
    if ($awayId !== null) {
        $chk = db()->prepare('SELECT id FROM teams WHERE id = ? LIMIT 1');
        $chk->execute([$awayId]);
        if (!$chk->fetch()) {
            flash('error', 'Команда гостей не найдена.');
            redirect('/admin/matches');
        }
    }
    if ($homeId !== null && $awayId !== null && $homeId === $awayId) {
        flash('error', 'Хозяева и гости не могут быть одной командой.');
        redirect('/admin/matches');
    }

    $stmt = db()->prepare(
        "INSERT INTO matches (stage, bracket_code, home_team_id, away_team_id, placeholder_home, placeholder_away, starts_at, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())"
    );
    $stmt->execute([$stage, $bracketCode, $homeId, $awayId, $placeholderHome, $placeholderAway, $startsAtRaw]);

    flash('success', 'Матч добавлен.');
    redirect('/admin/matches');
}

if ($method === 'POST' && $path === '/admin/matches/update') {
    verify_csrf();
    require_admin();

    $matchId = (int) ($_POST['match_id'] ?? 0);
    if ($matchId <= 0) {
        flash('error', 'Матч не указан.');
        redirect('/admin/matches');
    }

    $stage = trim((string) ($_POST['stage'] ?? ''));
    $startsAtRaw = trim((string) ($_POST['starts_at'] ?? ''));
    $bracketCode = trim((string) ($_POST['bracket_code'] ?? ''));
    $bracketCode = $bracketCode === '' ? null : mb_substr($bracketCode, 0, 20);
    $placeholderHome = trim((string) ($_POST['placeholder_home'] ?? ''));
    $placeholderAway = trim((string) ($_POST['placeholder_away'] ?? ''));
    $placeholderHome = $placeholderHome === '' ? null : mb_substr($placeholderHome, 0, 50);
    $placeholderAway = $placeholderAway === '' ? null : mb_substr($placeholderAway, 0, 50);

    $homeId = (int) ($_POST['home_team_id'] ?? 0);
    $awayId = (int) ($_POST['away_team_id'] ?? 0);
    $homeId = $homeId > 0 ? $homeId : null;
    $awayId = $awayId > 0 ? $awayId : null;

    if ($stage === '' || $startsAtRaw === '') {
        flash('error', 'Укажите стадию и дату начала матча.');
        redirect('/admin/matches');
    }

    if ($homeId !== null) {
        $chk = db()->prepare('SELECT id FROM teams WHERE id = ? LIMIT 1');
        $chk->execute([$homeId]);
        if (!$chk->fetch()) {
            flash('error', 'Команда хозяев не найдена.');
            redirect('/admin/matches');
        }
    }
    if ($awayId !== null) {
        $chk = db()->prepare('SELECT id FROM teams WHERE id = ? LIMIT 1');
        $chk->execute([$awayId]);
        if (!$chk->fetch()) {
            flash('error', 'Команда гостей не найдена.');
            redirect('/admin/matches');
        }
    }
    if ($homeId !== null && $awayId !== null && $homeId === $awayId) {
        flash('error', 'Хозяева и гости не могут быть одной командой.');
        redirect('/admin/matches');
    }

    $stmt = db()->prepare(
        "UPDATE matches
         SET stage = ?, bracket_code = ?, home_team_id = ?, away_team_id = ?, placeholder_home = ?, placeholder_away = ?, starts_at = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$stage, $bracketCode, $homeId, $awayId, $placeholderHome, $placeholderAway, $startsAtRaw, $matchId]);

    flash('success', 'Матч обновлён.');
    $stageKeys = ['all', 'group', 'round32', 'round16', 'quarter', 'semi', 'third', 'final'];
    $returnStage = (string) ($_POST['return_stage'] ?? 'all');
    if (!in_array($returnStage, $stageKeys, true)) {
        $returnStage = 'all';
    }
    $query = $returnStage === 'all' ? '' : ('?stage=' . rawurlencode($returnStage));
    redirect('/admin/matches' . $query . '#match-' . $matchId);
}

if ($method === 'POST' && $path === '/admin/results') {
    verify_csrf();
    require_admin();

    $matchId = (int) ($_POST['match_id'] ?? 0);
    if ($matchId <= 0) {
        flash('error', 'Матч не указан.');
        redirect('/admin/matches');
    }

    $chk = db()->prepare('SELECT COUNT(*) FROM matches WHERE id = ?');
    $chk->execute([$matchId]);
    if ((int) $chk->fetchColumn() === 0) {
        flash('error', 'Матч не найден.');
        redirect('/admin/matches');
    }

    if (!empty($_POST['clear_result'])) {
        clear_match_result($matchId);
        flash('success', 'Результат сброшен, начисленные за матч очки удалены из зачёта.');
    } else {
        try {
            apply_match_result(
                $matchId,
                max(0, (int) ($_POST['home_score'] ?? 0)),
                max(0, (int) ($_POST['away_score'] ?? 0)),
                'manual'
            );
            flash('success', 'Результат сохранён, очки пересчитаны.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
    }
    $stageKeys = ['all', 'group', 'round32', 'round16', 'quarter', 'semi', 'third', 'final'];
    $returnStage = (string) ($_POST['return_stage'] ?? 'all');
    if (!in_array($returnStage, $stageKeys, true)) {
        $returnStage = 'all';
    }
    $query = $returnStage === 'all' ? '' : ('?stage=' . rawurlencode($returnStage));
    redirect('/admin/matches' . $query . '#match-' . $matchId);
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
