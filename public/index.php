<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$path = path();
$method = request_method();

try {
    if ($method === 'GET' && $path === '/') {
        $nextMatchStmt = db()->query(
            "SELECT m.*, ht.name AS home_team, at.name AS away_team
             FROM matches m
             JOIN teams ht ON ht.id = m.home_team_id
             JOIN teams at ON at.id = m.away_team_id
             WHERE m.starts_at > NOW()
             ORDER BY m.starts_at ASC
             LIMIT 1"
        );

        view('home', [
            'matches' => array_slice(upcoming_matches(), 0, 8),
            'leaders' => array_slice(leaderboard(), 0, 10),
            'prizePool' => prize_pool(),
            'nextMatch' => $nextMatchStmt->fetch() ?: null,
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/rules') {
        view('rules');
        return;
    }

    if ($method === 'GET' && $path === '/register') {
        view('auth/register');
        return;
    }

    if ($method === 'POST' && $path === '/register') {
        verify_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            flash('error', 'Заполните имя, корректный email и пароль минимум 8 символов.');
            redirect('/register');
        }

        $dup = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetch()) {
            flash('error', 'Аккаунт с таким email уже есть. Войдите или укажите другой адрес.');
            redirect('/register');
        }

        $stmt = db()->prepare(
            "INSERT INTO users (name, email, password_hash, role, payment_status, created_at, updated_at)
             VALUES (?, ?, ?, 'participant', 'pending_payment', NOW(), NOW())"
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

        $_SESSION['user_id'] = (int) db()->lastInsertId();
        flash('success', 'Регистрация завершена. Отправьте взнос и дождитесь подтверждения админа.');
        redirect('/dashboard');
    }

    if ($method === 'GET' && $path === '/login') {
        view('auth/login');
        return;
    }

    if ($method === 'POST' && $path === '/login') {
        verify_csrf();

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('error', 'Неверный email или пароль.');
            redirect('/login');
        }

        $_SESSION['user_id'] = (int) $user['id'];
        redirect(($user['role'] ?? '') === 'admin' ? '/admin' : '/dashboard');
    }

    if ($method === 'POST' && $path === '/logout') {
        verify_csrf();
        session_destroy();
        redirect('/');
    }

    if ($method === 'GET' && $path === '/dashboard') {
        $user = require_user();

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

        $availableDates = db()->query(
            'SELECT DISTINCT DATE(starts_at) AS match_date FROM matches ORDER BY match_date ASC'
        )->fetchAll();
        $activeDate = (string) ($_GET['date'] ?? '');
        if ($activeDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeDate)) {
            $activeDate = '';
        }

        $where = [];
        $params = [];
        if ($activeStage !== 'all') {
            $where[] = 'm.stage LIKE ?';
            $params[] = $stageFilters[$activeStage] . '%';
        }
        if ($activeDate !== '') {
            $where[] = 'DATE(m.starts_at) = ?';
            $params[] = $activeDate;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = db()->prepare(
            "SELECT m.*, ht.name AS home_team, at.name AS away_team
             FROM matches m
             JOIN teams ht ON ht.id = m.home_team_id
             JOIN teams at ON at.id = m.away_team_id
             $whereSql
             ORDER BY m.starts_at ASC"
        );
        $stmt->execute($params);

        view('user/dashboard', [
            'user' => $user,
            'matches' => $stmt->fetchAll(),
            'teams' => db()->query('SELECT * FROM teams ORDER BY name')->fetchAll(),
            'championPrediction' => user_champion_prediction((int) $user['id']),
            'participantSummary' => is_active_participant($user) ? participant_summary((int) $user['id']) : null,
            'freePredictionLimit' => free_prediction_limit(),
            'freePredictionsRemaining' => free_predictions_remaining((int) $user['id']),
            'championPredictionDeadline' => champion_prediction_deadline(),
            'championPredictionLocked' => champion_prediction_locked(),
            'badges' => participant_badges((int) $user['id']),
            'stageFilters' => $stageFilters,
            'activeStage' => $activeStage,
            'availableDates' => $availableDates,
            'activeDate' => $activeDate,
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/my-scores') {
        $user = require_user();

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
        $stmt->execute([(int) $user['id']]);
        $predictions = $stmt->fetchAll();

        $totalPoints = 0;
        $exactScores = 0;
        $outcomes = 0;
        foreach ($predictions as $prediction) {
            $points = (int) $prediction['points'];
            $totalPoints += $points;
            if (($prediction['reason'] ?? '') === 'Точный счет') {
                $exactScores++;
            }
            if (in_array($prediction['reason'] ?? '', ['Точный счет', 'Угадан исход'], true)) {
                $outcomes++;
            }
        }

        $championPrediction = user_champion_prediction((int) $user['id']);
        $championPoints = (int) ($championPrediction['points'] ?? 0);

        view('user/my_scores', [
            'user' => $user,
            'predictions' => $predictions,
            'totalPoints' => $totalPoints + $championPoints,
            'matchPoints' => $totalPoints,
            'championPoints' => $championPoints,
            'exactScores' => $exactScores,
            'outcomes' => $outcomes,
            'championPrediction' => $championPrediction,
            'badges' => participant_badges((int) $user['id']),
        ]);
        return;
    }

    if ($method === 'POST' && $path === '/predictions') {
        verify_csrf();
        $user = require_user();

        $matchId = (int) ($_POST['match_id'] ?? 0);
        $match = find_match($matchId);
        if (!$match || prediction_locked($match)) {
            flash('error', 'Прием прогнозов на этот матч уже закрыт.');
            redirect('/dashboard');
        }

        if (!can_make_prediction($user, $matchId)) {
            flash('error', 'Бесплатный лимит прогнозов закончился. Оплатите взнос, чтобы продолжить игру.');
            redirect('/dashboard');
        }

        $homeScore = max(0, (int) ($_POST['home_score'] ?? 0));
        $awayScore = max(0, (int) ($_POST['away_score'] ?? 0));

        $stmt = db()->prepare(
            "INSERT INTO predictions (user_id, match_id, home_score, away_score, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE home_score = VALUES(home_score), away_score = VALUES(away_score), updated_at = NOW()"
        );
        $stmt->execute([(int) $user['id'], $matchId, $homeScore, $awayScore]);

        flash('success', 'Прогноз сохранен.');
        redirect('/dashboard');
    }

    if ($method === 'POST' && $path === '/champion') {
        verify_csrf();
        $user = require_user();

        if (!is_active_participant($user)) {
            flash('error', 'Прогноз на чемпиона доступен после подтверждения оплаты.');
            redirect('/dashboard');
        }

        if (champion_prediction_locked()) {
            flash('error', 'Прием прогнозов на чемпиона уже закрыт.');
            redirect('/dashboard');
        }

        $teamId = (int) ($_POST['team_id'] ?? 0);
        if ($teamId <= 0) {
            flash('error', 'Выберите команду из списка.');
            redirect('/dashboard');
        }
        $teamCheck = db()->prepare('SELECT id FROM teams WHERE id = ? LIMIT 1');
        $teamCheck->execute([$teamId]);
        if (!$teamCheck->fetch()) {
            flash('error', 'Указанная команда не найдена.');
            redirect('/dashboard');
        }

        $stmt = db()->prepare(
            "INSERT INTO champion_predictions (user_id, team_id, points, created_at, updated_at)
             VALUES (?, ?, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE team_id = VALUES(team_id), updated_at = NOW()"
        );
        $stmt->execute([(int) $user['id'], $teamId]);

        flash('success', 'Прогноз на чемпиона сохранен.');
        redirect('/dashboard');
    }

    if ($method === 'GET' && $path === '/leaderboard') {
        view('leaderboard', [
            'leaders' => leaderboard(),
            'prizePool' => prize_pool(),
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/mini-leagues') {
        $user = require_user();

        view('user/mini_leagues', [
            'leagues' => user_mini_leagues((int) $user['id']),
        ]);
        return;
    }

    if ($method === 'POST' && $path === '/mini-leagues/create') {
        verify_csrf();
        $user = require_user();

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            flash('error', 'Укажите название мини-лиги до 120 символов.');
            redirect('/mini-leagues');
        }

        $code = generate_mini_league_code();
        $stmt = db()->prepare(
            "INSERT INTO mini_leagues (name, invite_code, owner_user_id, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$name, $code, (int) $user['id']]);
        $leagueId = (int) db()->lastInsertId();

        $member = db()->prepare('INSERT INTO mini_league_members (league_id, user_id, created_at) VALUES (?, ?, NOW())');
        $member->execute([$leagueId, (int) $user['id']]);

        flash('success', 'Мини-лига создана. Поделитесь кодом приглашения с друзьями.');
        redirect('/mini-league?id=' . $leagueId);
    }

    if ($method === 'POST' && $path === '/mini-leagues/join') {
        verify_csrf();
        $user = require_user();

        $code = strtoupper(trim((string) ($_POST['invite_code'] ?? '')));
        $league = find_mini_league_by_code($code);
        if (!$league) {
            flash('error', 'Мини-лига с таким кодом не найдена.');
            redirect('/mini-leagues');
        }

        $stmt = db()->prepare(
            "INSERT IGNORE INTO mini_league_members (league_id, user_id, created_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([(int) $league['id'], (int) $user['id']]);

        flash('success', 'Вы вступили в мини-лигу.');
        redirect('/mini-league?id=' . (int) $league['id']);
    }

    if ($method === 'GET' && $path === '/mini-league') {
        $user = require_user();
        $leagueId = (int) ($_GET['id'] ?? 0);
        $league = find_mini_league($leagueId);

        if (!$league || !user_in_mini_league($leagueId, (int) $user['id'])) {
            http_response_code(404);
            view('errors/404');
            return;
        }

        view('user/mini_league', [
            'league' => $league,
            'leaders' => mini_league_leaderboard($leagueId),
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/match') {
        $matchId = (int) ($_GET['id'] ?? 0);
        $match = find_match($matchId);
        if (!$match) {
            http_response_code(404);
            view('errors/404');
            return;
        }

        $predictions = [];
        $predictionStats = [
            'total' => 0,
            'home' => 0,
            'draw' => 0,
            'away' => 0,
            'popularScores' => [],
        ];
        if (match_started($match)) {
            $stmt = db()->prepare(
                "SELECT p.*, u.name
                 FROM predictions p
                 JOIN users u ON u.id = p.user_id
                 WHERE p.match_id = ?
                 ORDER BY u.name"
            );
            $stmt->execute([$matchId]);
            $predictions = $stmt->fetchAll();

            $scoreCounts = [];
            foreach ($predictions as $prediction) {
                $predictionStats['total']++;
                $outcome = match_outcome((int) $prediction['home_score'], (int) $prediction['away_score']);
                $predictionStats[$outcome]++;

                $scoreKey = (int) $prediction['home_score'] . ':' . (int) $prediction['away_score'];
                $scoreCounts[$scoreKey] = ($scoreCounts[$scoreKey] ?? 0) + 1;
            }
            arsort($scoreCounts);
            $predictionStats['popularScores'] = array_slice($scoreCounts, 0, 5, true);
        }

        view('match', [
            'match' => $match,
            'predictions' => $predictions,
            'predictionStats' => $predictionStats,
        ]);
        return;
    }

    require dirname(__DIR__) . '/app/admin_routes.php';
} catch (PDOException $e) {
    http_response_code(500);
    view('errors/500', ['message' => $e->getMessage()]);
}

http_response_code(404);
view('errors/404');
