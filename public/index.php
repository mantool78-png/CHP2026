<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$path = path();
$method = request_method();

try {
    if ($method === 'GET' && $path === '/robots.txt') {
        header('Content-Type: text/plain; charset=UTF-8');
        $siteOrigin = preg_replace('#/+$#', '', absolute_url('/'));
        echo "User-agent: *\nAllow: /\n\nSitemap: {$siteOrigin}/sitemap.xml\n";
        return;
    }

    if ($method === 'GET' && $path === '/sitemap.xml') {
        header('Content-Type: application/xml; charset=UTF-8');
        $siteOrigin = preg_replace('#/+$#', '', absolute_url('/'));
        $today = gmdate('Y-m-d');
        $publicPaths = ['/', '/rules', '/terms', '/privacy', '/prizes', '/tournament', '/matches', '/rating', '/rating/stages', '/compare', '/predictions', '/faq', '/register', '/login'];
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($publicPaths as $suffix) {
            $locRaw = $siteOrigin . ($suffix === '/' ? '/' : $suffix);
            $loc = htmlspecialchars($locRaw, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $priority = $suffix === '/' ? '1.0' : '0.8';
            echo "<url><loc>{$loc}</loc><lastmod>{$today}</lastmod><changefreq>weekly</changefreq><priority>{$priority}</priority></url>";
        }
        echo '</urlset>';
        return;
    }

    if ($method === 'GET' && $path === '/') {
        $nextMatchStmt = db()->query(
            "SELECT m.*, ht.name AS home_team, at.name AS away_team, ht.code AS home_code, at.code AS away_code
             FROM matches m
             JOIN teams ht ON ht.id = m.home_team_id
             JOIN teams at ON at.id = m.away_team_id
             WHERE m.starts_at > NOW()
               AND m.home_team_id IS NOT NULL
               AND m.away_team_id IS NOT NULL
             ORDER BY m.starts_at ASC
             LIMIT 1"
        );

        view('home', [
            'matches' => array_slice(upcoming_matches(), 0, 8),
            'scheduleHighlights' => home_schedule_highlights(),
            'activity' => engagement_home_activity_snapshot(),
            'sitePolls' => site_polls_active(),
            'leaders' => array_slice(leaderboard(), 0, 10),
            'prizePool' => prize_pool(),
            'nextMatch' => $nextMatchStmt->fetch() ?: null,
            'daysUntilKickoff' => contest_days_until_kickoff(),
            'registrationStats' => contest_registration_stats(),
            'pageTitle' => 'Лига прогнозов на матчи ЧМ-2026 — iPhone 17e и денежные призы',
            'pageDescription' => 'Конкурс прогнозов на матчи ЧМ-2026: первые 5 прогнозов бесплатно, главный приз iPhone 17e, денежные призы за 2–5 места. Турнир прогнозистов с прозрачными правилами.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/rules') {
        view('rules', [
            'pageTitle' => 'Правила конкурса прогнозов на ЧМ-2026',
            'pageDescription' => 'Правила конкурса прогнозов на футбол: начисление очков за исход и точный счёт, бесплатные прогнозы, дедлайны и акция «Приведи друга».',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/terms') {
        view('terms', [
            'pageTitle' => 'Условия участия и взнос',
            'pageDescription' => 'Юридические условия участия в конкурсе прогнозов: взнос, подтверждение платежа, доступ к игре после оплаты и ответственность сторон.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/privacy') {
        view('privacy', [
            'pageTitle' => 'Персональные данные и конфиденциальность',
            'pageDescription' => 'Какие данные участников мы собираем, как храним аккаунт и платёжную информацию и как связаться по вопросам персональных данных.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/tournament') {
        $tab = (string) ($_GET['tab'] ?? 'groups');
        if (!in_array($tab, ['groups', 'playoff'], true)) {
            $tab = 'groups';
        }
        view('tournament', [
            'tournamentTab' => $tab,
            'groupStandings' => get_group_standings(),
            'playoffRounds' => tournament_playoff_by_stage(tournament_playoff_matches()),
            'pageTitle' => 'Турнир ЧМ-2026: группы и плей-офф',
            'pageDescription' => 'Турнирные таблицы группового этапа и сетка плей-офф чемпионата мира — для прогнозов и ориентира по расписанию.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/prizes') {
        view('prizes', [
            'prizePool' => prize_pool(),
            'distribution' => prize_distribution(),
            'pageTitle' => 'Призы чемпионата прогнозов ЧМ-2026 — iPhone и деньги',
            'pageDescription' => 'Призы конкурса прогнозов: Apple iPhone 17e 256 GB победителю и фиксированные денежные выплаты местам 2–5. Прозрачный призовой фонд.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/faq') {
        view('faq', [
            'pageTitle' => 'Частые вопросы — конкурс прогнозов на ЧМ-2026',
            'pageDescription' => 'FAQ: взнос, бесплатные прогнозы, начисление очков, призы, организатор и акция «Приведи друга» в конкурсе прогнозов на чемпионат мира 2026.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/matches') {
        $scheduleFilter = matches_schedule_filter_key($_GET['filter'] ?? null);
        view('matches', [
            'matches' => matches_for_schedule($scheduleFilter),
            'scheduleFilter' => $scheduleFilter,
            'scheduleFilterTabs' => matches_schedule_filter_tabs(),
            'pageTitle' => 'Расписание матчей ЧМ-2026 — прогнозы на чемпионат мира',
            'pageDescription' => 'Расписание матчей чемпионата мира по футболу 2026 для конкурса прогнозов: даты, время МСК и ссылки на прогнозы.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/register') {
        view('auth/register', [
            'pageTitle' => 'Регистрация — конкурс прогнозов ЧМ-2026',
            'pageDescription' => 'Создай аккаунт участника, прими правила и начни бесплатные прогнозы до подтверждения взноса.',
        ]);
        return;
    }

    if ($method === 'POST' && $path === '/register') {
        verify_csrf();

        $name = normalize_participant_display_name((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $termsAccepted = isset($_POST['terms_accepted']);

        if ($name === '' || mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            flash('error', 'Укажите имя и фамилию или ник (минимум 2 символа), корректный email и пароль минимум 8 символов.');
            redirect('/register');
        }

        if ($password !== $passwordConfirmation) {
            flash('error', 'Пароль и подтверждение не совпадают.');
            redirect('/register');
        }

        if (!$termsAccepted) {
            flash('error', 'Подтвердите согласие с правилами конкурса и обработкой персональных данных.');
            redirect('/register');
        }

        $dup = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetch()) {
            flash('error', 'Аккаунт с таким email уже есть. Войдите или укажите другой адрес.');
            redirect('/register');
        }

        if (participant_display_name_taken($name)) {
            flash('error', 'Такое имя уже занято в таблице. Укажите фамилию, инициалы или другой ник — имена участников не должны повторяться.');
            redirect('/register');
        }

        $stmt = db()->prepare(
            "INSERT INTO users (name, email, password_hash, role, payment_status, created_at, updated_at)
             VALUES (?, ?, ?, 'participant', 'pending_payment', NOW(), NOW())"
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

        $newUserId = (int) db()->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;

        mail_send_registration_welcome($email, $name);

        $afterInvite = complete_pending_mini_league_join_for_user($newUserId);
        if ($afterInvite !== null) {
            flash('notice', 'Отправьте взнос и дождитесь подтверждения админа — тогда откроется доступ к прогнозам.');
            redirect($afterInvite);
        }
        flash('success', 'Регистрация завершена. Отправьте взнос и дождитесь подтверждения админа.');
        redirect('/#home-predictions');
    }

    if ($method === 'GET' && $path === '/login') {
        view('auth/login', [
            'pageTitle' => 'Вход для участников',
            'pageDescription' => 'Авторизуйся для просмотра прогнозов, результатов матчей и участия в мини-лигах конкурса ЧМ-2026.',
        ]);
        return;
    }

    if ($method === 'POST' && $path === '/login') {
        verify_csrf();

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($email !== '') {
            $limit = login_rate_limit_status($email);
            if ($limit['locked']) {
                $minutes = max(1, (int) ceil(((int) $limit['seconds']) / 60));
                flash('error', 'Слишком много неверных попыток входа. Попробуйте через ' . $minutes . ' мин.');
                redirect('/login');
            }
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($email !== '') {
                record_failed_login($email);
            }
            flash('error', 'Неверный email или пароль.');
            redirect('/login');
        }

        if (($user['role'] ?? '') === 'participant' && ($user['payment_status'] ?? '') === 'blocked') {
            flash('error', 'Доступ к аккаунту заблокирован. Свяжитесь с организаторами конкурса.');
            redirect('/login');
        }

        clear_failed_logins($email);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $afterInvite = complete_pending_mini_league_join_for_user((int) $user['id']);
        if ($afterInvite !== null) {
            redirect($afterInvite);
        }
        redirect(($user['role'] ?? '') === 'admin' ? '/admin' : '/dashboard');
    }

    if ($method === 'POST' && $path === '/logout') {
        verify_csrf();
        session_destroy();
        redirect('/');
    }

    if ($method === 'POST' && $path === '/payment-receipt') {
        verify_csrf();
        $user = require_user();
        if (!user_can_upload_payment_receipt($user)) {
            flash('error', 'Загрузка чека сейчас недоступна.');
            redirect('/dashboard');
        }
        try {
            save_payment_receipt_from_upload((int) $user['id'], $_FILES['receipt'] ?? null);
            flash('success', 'Чек отправлен. Дождитесь подтверждения оплаты — организаторы проверят платёж и откроют полный доступ.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            flash('error', 'Не удалось сохранить чек. Попробуйте позже.');
        }
        redirect('/dashboard');
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
            "SELECT m.*, ht.name AS home_team, at.name AS away_team, ht.code AS home_code, at.code AS away_code
             FROM matches m
             JOIN teams ht ON ht.id = m.home_team_id
             JOIN teams at ON at.id = m.away_team_id
             $whereSql
             ORDER BY m.starts_at ASC"
        );
        $stmt->execute($params);

        $todayDate = date('Y-m-d');
        $dashboardMatches = [];
        foreach ($stmt->fetchAll() as $match) {
            if (!prediction_locked($match)) {
                $dashboardMatches[] = $match;
                continue;
            }

            if ($activeDate !== '') {
                $dashboardMatches[] = $match;
                continue;
            }

            if (date('Y-m-d', strtotime((string) $match['starts_at'])) === $todayDate) {
                $dashboardMatches[] = $match;
            }
        }

        $championPrediction = user_champion_prediction((int) $user['id']);

        view('user/dashboard', [
            'user' => $user,
            'matches' => $dashboardMatches,
            'teams' => teams_for_champion_select_with_current($championPrediction),
            'championPrediction' => $championPrediction,
            'participantSummary' => ($user['payment_status'] ?? '') !== 'blocked'
                ? participant_summary((int) $user['id'])
                : null,
            'freePredictionLimit' => free_prediction_limit(),
            'freePredictionsRemaining' => free_predictions_remaining((int) $user['id']),
            'championPredictionDeadline' => champion_prediction_deadline(),
            'championPredictionLocked' => champion_prediction_locked(),
            'badges' => participant_badges((int) $user['id']),
            'engagementStats' => engagement_participant_stats((int) $user['id']),
            'engagementBadges' => engagement_participant_badges((int) $user['id']),
            'stageFilters' => $stageFilters,
            'activeStage' => $activeStage,
            'availableDates' => $availableDates,
            'activeDate' => $activeDate,
            'paymentReceipt' => (($user['payment_status'] ?? '') === 'pending_payment')
                ? payment_receipt_for_user((int) $user['id'])
                : null,
            'pageTitle' => 'Личный кабинет — мои прогнозы',
            'pageDescription' => 'Список матчей, сохранённые прогнозы на ЧМ-2026 и выбор победителя турнира в конкурсном кабинете.',
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
            if (($prediction['reason'] ?? '') === 'Угадан исход') {
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
            'pageTitle' => 'Мои очки и история прогнозов',
            'pageDescription' => 'Сводка набранных очков, статистика угаданных исходов и сохранённые прогнозы чемпиона турнира.',
        ]);
        return;
    }

    if ($method === 'POST' && $path === '/predictions') {
        verify_csrf();
        $user = require_user();

        $returnStage = (string) ($_POST['return_stage'] ?? 'all');
        $returnDate = (string) ($_POST['return_date'] ?? '');
        $returnTo = (string) ($_POST['return_to'] ?? '');
        $matchId = (int) ($_POST['match_id'] ?? 0);
        $predictionBack = static function () use ($returnTo, $returnStage, $returnDate, $matchId): string {
            return prediction_save_return_url($returnTo, $returnStage, $returnDate, $matchId);
        };

        $match = find_match($matchId);
        if (!$match || prediction_locked($match)) {
            flash('error', 'Прием прогнозов на этот матч уже закрыт.');
            redirect($predictionBack());
        }

        if (!can_make_prediction($user, $matchId)) {
            flash('error', free_trial_prediction_denied_message($user, $matchId));
            redirect($predictionBack());
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
        redirect($predictionBack());
    }

    if ($method === 'POST' && $path === '/champion-poll/vote') {
        verify_csrf();
        $optionKey = trim((string) ($_POST['option_key'] ?? ''));
        $options = champion_poll_options();
        if (!isset($options[$optionKey])) {
            flash('error', 'Неверный вариант ответа.');
            redirect('/');
        }

        $cookieName = 'voted_champion_poll';
        $alreadyVoted = isset($_COOKIE[$cookieName]);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!$alreadyVoted && $ip) {
            if (db_table_exists('champion_poll_votes')) {
                $stmt = db()->prepare('SELECT COUNT(*) FROM champion_poll_votes WHERE ip_address = ?');
                $stmt->execute([$ip]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $alreadyVoted = true;
                }
            }
        }

        if ($alreadyVoted) {
            setcookie($cookieName, '1', time() + 30 * 86400, '/');
            flash('notice', 'Вы уже проголосовали в этом опросе.');
            redirect('/#champion-poll');
        }

        $userId = current_user() ? (int) current_user()['id'] : null;

        if (db_table_exists('champion_poll_votes')) {
            $stmt = db()->prepare(
                "INSERT INTO champion_poll_votes (option_key, ip_address, user_id, created_at)
                 VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([$optionKey, $ip, $userId]);
        }

        setcookie($cookieName, '1', time() + 30 * 86400, '/');

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'results' => champion_poll_results()]);
            return;
        }

        flash('success', 'Спасибо! Ваш голос учтен.');
        redirect('/#champion-poll');
    }

    if ($method === 'POST' && $path === '/poll/vote') {
        verify_csrf();
        $pollId = (int) ($_POST['poll_id'] ?? 0);
        $optionKey = trim((string) ($_POST['option_key'] ?? ''));

        if ($pollId < 1 || !db_table_exists('site_polls')) {
            flash('error', 'Опрос не найден.');
            redirect('/');
        }

        $stmt = db()->prepare('SELECT id, slug, options_json FROM site_polls WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$pollId]);
        $poll = $stmt->fetch();
        if (!$poll) {
            flash('error', 'Опрос не найден.');
            redirect('/');
        }

        $options = json_decode((string) ($poll['options_json'] ?? '[]'), true);
        $validKeys = [];
        if (is_array($options)) {
            foreach ($options as $opt) {
                if (is_array($opt) && isset($opt['key'])) {
                    $validKeys[(string) $opt['key']] = true;
                }
            }
        }
        if (!isset($validKeys[$optionKey])) {
            flash('error', 'Неверный вариант ответа.');
            redirect('/');
        }

        if (user_has_voted_site_poll($pollId)) {
            setcookie('voted_poll_' . $pollId, '1', time() + 30 * 86400, '/');
            flash('notice', 'Вы уже проголосовали в этом опросе.');
            redirect('/#poll-' . (string) ($poll['slug'] ?? $pollId));
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userId = current_user() ? (int) current_user()['id'] : null;

        if (db_table_exists('site_poll_votes')) {
            $insert = db()->prepare(
                'INSERT INTO site_poll_votes (poll_id, option_key, ip_address, user_id, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $insert->execute([$pollId, $optionKey, $ip, $userId]);
        }

        setcookie('voted_poll_' . $pollId, '1', time() + 30 * 86400, '/');
        flash('success', 'Спасибо! Ваш голос учтен.');
        redirect('/#poll-' . (string) ($poll['slug'] ?? $pollId));
    }

    if ($method === 'POST' && $path === '/champion') {
        verify_csrf();
        $user = require_user();

        $returnStage = (string) ($_POST['return_stage'] ?? 'all');
        $returnDate = (string) ($_POST['return_date'] ?? '');
        $championBack = static function () use ($returnStage, $returnDate): string {
            return dashboard_return_url($returnStage, $returnDate, 'champion-pick');
        };

        if (!is_active_participant($user)) {
            flash('error', 'Прогноз на чемпиона доступен после подтверждения оплаты.');
            redirect($championBack());
        }

        if (champion_prediction_locked()) {
            flash('error', 'Прием прогнозов на чемпиона уже закрыт.');
            redirect($championBack());
        }

        $teamId = (int) ($_POST['team_id'] ?? 0);
        if ($teamId <= 0) {
            flash('error', 'Выберите команду из списка.');
            redirect($championBack());
        }
        $teamCheck = db()->prepare('SELECT * FROM teams WHERE id = ? LIMIT 1');
        $teamCheck->execute([$teamId]);
        $teamRow = $teamCheck->fetch();
        if (!$teamRow || !team_is_champion_pick_candidate($teamRow)) {
            flash('error', 'Выберите страну-участницу чемпионата из списка, а не слот расписания.');
            redirect($championBack());
        }

        $stmt = db()->prepare(
            "INSERT INTO champion_predictions (user_id, team_id, points, created_at, updated_at)
             VALUES (?, ?, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE team_id = VALUES(team_id), updated_at = NOW()"
        );
        $stmt->execute([(int) $user['id'], $teamId]);

        flash('success', 'Прогноз на чемпиона сохранен.');
        redirect($championBack());
    }

    if ($method === 'GET' && $path === '/leaderboard') {
        header('Location: /rating', true, 301);
        exit;
    }

    if ($method === 'GET' && $path === '/rating') {
        $leaders = leaderboard();
        view('leaderboard', [
            'leaders' => $leaders,
            'prizePool' => prize_pool(),
            'distribution' => prize_distribution(),
            'championPredictionsPublic' => champion_predictions_public(),
            'championTeamsByUser' => champion_predictions_public()
                ? transparent_champion_teams_by_user_ids(array_map(static fn (array $row): int => (int) $row['id'], $leaders))
                : [],
            'pageTitle' => 'Рейтинг участников — таблица конкурса прогнозов ЧМ-2026',
            'pageDescription' => 'Турнирная таблица прогнозистов: очки за матчи ЧМ-2026, точные счета и прогноз на чемпиона. Соревнование за iPhone и денежные призы.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/rating/pdf') {
        $leaders = leaderboard();
        $championPublic = champion_predictions_public();
        $championTeamsByUser = $championPublic
            ? transparent_champion_teams_by_user_ids(array_map(static fn (array $row): int => (int) $row['id'], $leaders))
            : [];
        pdf_export_leaderboard($leaders, $championPublic, $championTeamsByUser);
        return;
    }

    if ($method === 'GET' && $path === '/rating/stages') {
        $stagePrizesOverview = engagement_stage_prizes_overview();
        $prizeKeys = engagement_stage_prize_keys();
        $sliceTab = (string) ($_GET['tab'] ?? '');
        $prizeKey = trim((string) ($_GET['prize'] ?? ''));
        $viewMode = 'prize';
        $tab = 'day';
        $days = engagement_match_days_with_results();
        $day = engagement_latest_match_day();
        $leaders = [];
        $expert = null;
        $prizeOverview = null;
        $tabLabel = 'Промежуточные призы';

        if (in_array($sliceTab, ['day', 'group', 'playoff'], true)) {
            $viewMode = 'slice';
            $tab = $sliceTab;
            $day = trim((string) ($_GET['day'] ?? ''));
            if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !in_array($day, $days, true)) {
                $day = engagement_latest_match_day();
            }

            $leaders = match ($tab) {
                'group' => engagement_leaderboard_group_stage(),
                'playoff' => engagement_leaderboard_playoff(),
                default => $day ? engagement_leaderboard_for_msk_date($day) : [],
            };
            $leaders = array_slice($leaders, 0, 50);
            $expert = ($tab === 'day' && $day) ? engagement_expert_of_match_day($day) : null;
            $tabLabels = [
                'day' => 'Лучшие за игровой день',
                'group' => 'Рейтинг группового этапа',
                'playoff' => 'Рейтинг плей-офф',
            ];
            $tabLabel = $tabLabels[$tab] ?? 'Турнирные срезы';
        } else {
            if ($prizeKey === '' || !in_array($prizeKey, $prizeKeys, true)) {
                $prizeKey = engagement_default_stage_prize_tab();
            }

            foreach ($stagePrizesOverview as $row) {
                if (($row['key'] ?? '') === $prizeKey) {
                    $prizeOverview = $row;
                    break;
                }
            }

            $leaders = engagement_stage_prize_leaderboard($prizeKey, 50);
            $tabLabel = (string) ($prizeOverview['title'] ?? 'Промежуточные призы');
        }

        $pageTitle = $tabLabel . ' — конкурс прогнозов ЧМ-2026';
        $pageDescription = $viewMode === 'prize'
            ? 'Таблица претендентов на промежуточный приз: ' . $tabLabel . '. Очки только за матчи этапа.'
            : 'Отдельные таблицы за игровой день, групповой этап и плей-офф. Промежуточные призы и эксперт тура.';

        view('rating_stages', [
            'viewMode' => $viewMode,
            'prizeKey' => $prizeKey,
            'prizeOverview' => $prizeOverview,
            'stagePrizesOverview' => $stagePrizesOverview,
            'tab' => $tab,
            'day' => $day,
            'days' => $days,
            'leaders' => $leaders,
            'expert' => $expert,
            'tabLabel' => $tabLabel,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/compare') {
        $userA = (int) ($_GET['a'] ?? 0);
        $userB = (int) ($_GET['b'] ?? 0);
        $comparison = null;
        if ($userA > 0 && $userB > 0) {
            if ($userA === $userB) {
                flash('error', 'Выберите двух разных участников.');
            } else {
                $comparison = engagement_compare_participants($userA, $userB);
            }
        }

        view('compare', [
            'participants' => engagement_compare_participant_options(),
            'comparison' => $comparison,
            'userA' => $userA,
            'userB' => $userB,
            'pageTitle' => 'Сравнить с другом — конкурс прогнозов ЧМ-2026',
            'pageDescription' => 'Сравнение двух участников: кто на каких матчах обошёл соперника по очкам.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/predictions/pdf') {
        $stageKey = transparent_started_match_stage_key($_GET['stage'] ?? null);
        $nameQuery = trim((string) ($_GET['q'] ?? ''));

        $matrix = transparent_predictions_matrix($stageKey);
        if ($nameQuery !== '') {
            $needle = mb_strtolower($nameQuery, 'UTF-8');
            $matrix['participants'] = array_values(array_filter(
                $matrix['participants'],
                static function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower((string) $row['name'], 'UTF-8'), $needle);
                }
            ));
        }

        $stageLabel = $stageKey === 'all' ? 'Все начавшиеся' : $stageKey;
        pdf_export_predictions_matrix($matrix, $stageLabel);
        return;
    }

    if ($method === 'GET' && $path === '/predictions') {
        $tab = predictions_transparency_tab_key($_GET['tab'] ?? null);
        $stageKey = transparent_started_match_stage_key($_GET['stage'] ?? null);
        $nameQuery = trim((string) ($_GET['q'] ?? ''));

        $participants = transparent_participants_overview();
        if ($nameQuery !== '') {
            $needle = mb_strtolower($nameQuery, 'UTF-8');
            $participants = array_values(array_filter(
                $participants,
                static function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower((string) $row['name'], 'UTF-8'), $needle);
                }
            ));
        }

        $matrix = $tab === 'matrix' ? transparent_predictions_matrix($stageKey) : ['participants' => [], 'matches' => [], 'cells' => []];
        if ($tab === 'matrix' && $nameQuery !== '') {
            $needle = mb_strtolower($nameQuery, 'UTF-8');
            $matrix['participants'] = array_values(array_filter(
                $matrix['participants'],
                static function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower((string) $row['name'], 'UTF-8'), $needle);
                }
            ));
        }

        view('predictions', [
            'tab' => $tab,
            'stageKey' => $stageKey,
            'nameQuery' => $nameQuery,
            'participants' => $participants,
            'matrix' => $matrix,
            'matchStages' => transparent_started_match_stages(),
            'championPredictions' => transparent_champion_predictions(),
            'championDistribution' => transparent_champion_team_distribution(),
            'championLocked' => champion_prediction_locked(),
            'championDeadline' => champion_prediction_deadline(),
            'pageTitle' => 'Открытые прогнозы участников — прозрачность конкурса ЧМ-2026',
            'pageDescription' => 'Все прогнозы участников после старта матчей, матрица очков и прогнозы на чемпиона мира после закрытия приёма.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/participant') {
        $participantId = (int) ($_GET['id'] ?? 0);
        $participant = public_participant($participantId);
        if (!$participant) {
            http_response_code(404);
            view('errors/404');
            return;
        }

        $summary = transparent_participant_summary($participantId);
        $championPrediction = champion_prediction_locked() ? user_champion_prediction($participantId) : null;

        view('participant', [
            'participant' => $participant,
            'summary' => $summary,
            'engagementStats' => engagement_participant_stats($participantId),
            'engagementBadges' => engagement_participant_badges($participantId),
            'predictions' => public_participant_predictions($participantId),
            'futurePredictionsCount' => public_participant_future_predictions_count($participantId),
            'championPrediction' => $championPrediction,
            'championLocked' => champion_prediction_locked(),
            'back' => participant_back_navigation(),
            'pageTitle' => (string) $participant['name'] . ' · прогнозы участника ЧМ-2026',
            'pageDescription' => 'Публичные прогнозы участника ' . (string) $participant['name'] . ': счета матчей, начисленные очки и прогноз на чемпиона.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/mini-leagues/join') {
        $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
        if ($code === '' || mb_strlen($code) > 16) {
            flash('error', 'В ссылке не указан код приглашения.');
            redirect(current_user() ? '/mini-leagues' : '/login');
        }

        $league = find_mini_league_by_code($code);
        if (!$league) {
            flash('error', 'Мини-лига с таким кодом не найдена.');
            redirect(current_user() ? '/mini-leagues' : '/login');
        }

        $user = current_user();
        if (!$user) {
            $_SESSION['pending_mini_league_invite'] = $code;
            flash(
                'notice',
                'Войдите или зарегистрируйтесь — вы автоматически вступите в мини-лигу «' . $league['name'] . '».'
            );
            redirect('/login');
        }

        view('user/mini_league_join_confirm', [
            'league' => $league,
            'pageTitle' => 'Вступление в мини-лигу',
            'pageDescription' => 'Подтвердите вступление в группу «' . $league['name'] . '».',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/mini-leagues') {
        $user = require_user();

        view('user/mini_leagues', [
            'leagues' => user_mini_leagues((int) $user['id']),
            'pageTitle' => 'Мои мини-лиги',
            'pageDescription' => 'Список ваших групп конкурса с друзьями: коды приглашений и переход к отдельным рейтингам.',
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

        flash('success', 'Вы в мини-лиге «' . $league['name'] . '».');
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

        $tab = mini_league_tab_key($_GET['tab'] ?? null);
        $stageKey = transparent_started_match_stage_key($_GET['stage'] ?? null);

        view('user/mini_league', [
            'league' => $league,
            'tab' => $tab,
            'stageKey' => $stageKey,
            'leaders' => mini_league_leaderboard($leagueId),
            'matrix' => $tab === 'matrix' ? mini_league_predictions_matrix($leagueId, $stageKey) : ['participants' => [], 'matches' => [], 'cells' => []],
            'matchStages' => transparent_started_match_stages(),
            'championPredictions' => $tab === 'champions' ? mini_league_champion_predictions($leagueId) : [],
            'championLocked' => champion_prediction_locked(),
            'championDeadline' => champion_prediction_deadline(),
            'pageTitle' => '«' . $league['name'] . '» · мини-лига',
            'pageDescription' => 'Рейтинг и прогнозы участников вашей мини-лиги на ЧМ-2026.',
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/match/pdf') {
        $matchId = (int) ($_GET['id'] ?? 0);
        $match = find_match($matchId);
        if (!$match) {
            http_response_code(404);
            view('errors/404');
            return;
        }
        if (!match_started($match)) {
            http_response_code(403);
            echo 'Прогнозы откроются после начала матча.';
            return;
        }

        $stmt = db()->prepare(
            "SELECT p.*, u.name, u.id AS user_id,
                    COALESCE(s.points, 0) AS points, s.reason
             FROM predictions p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
             WHERE p.match_id = ?
             ORDER BY u.name"
        );
        $stmt->execute([$matchId]);
        pdf_export_match_predictions($match, $stmt->fetchAll());
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
        if (match_started($match)) {
            $stmt = db()->prepare(
                "SELECT p.*, u.name, u.id AS user_id,
                        COALESCE(s.points, 0) AS points, s.reason
                 FROM predictions p
                 JOIN users u ON u.id = p.user_id
                 LEFT JOIN scores s ON s.user_id = p.user_id AND s.match_id = p.match_id
                 WHERE p.match_id = ?
                 ORDER BY u.name"
            );
            $stmt->execute([$matchId]);
            $predictions = $stmt->fetchAll();
        }

        $predictionStats = match_prediction_distribution($matchId);

        $h2h = ['matches' => [], 'summary' => ['home_wins' => 0, 'away_wins' => 0, 'draws' => 0, 'total' => 0], 'cached_at' => null, 'error' => null];
        $homeApiId = (int) ($match['home_api_team_id'] ?? 0);
        $awayApiId = (int) ($match['away_api_team_id'] ?? 0);
        if ($homeApiId > 0 && $awayApiId > 0) {
            $h2h = api_football_match_h2h($homeApiId, $awayApiId, 8);
        }

        view('match', [
            'match' => $match,
            'predictions' => $predictions,
            'predictionStats' => $predictionStats,
            'tournamentProgress' => match_teams_tournament_progress($match),
            'h2h' => $h2h,
            'h2hEnabled' => $homeApiId > 0 && $awayApiId > 0,
            'pageTitle' => $match['home_team'] . ' — ' . $match['away_team'] . ' · прогнозы ЧМ-2026',
            'pageDescription' => $match['stage'] . ': ' . $match['home_team'] . ' — ' . $match['away_team'] . '. Прогнозы и голосование участников после стартового свистка.',
        ]);
        return;
    }

    require dirname(__DIR__) . '/app/admin_routes.php';
} catch (PDOException $e) {
    error_log('PDO error: ' . $e->getMessage());
    http_response_code(500);
    view('errors/500', ['message' => app_debug() ? $e->getMessage() : null]);
}

http_response_code(404);
view('errors/404');
