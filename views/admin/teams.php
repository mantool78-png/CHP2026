<section class="page-heading">
    <div>
        <p class="eyebrow">Админ-панель</p>
        <h1>Команды</h1>
    </div>
    <a class="button small secondary" href="/admin">Назад</a>
</section>

<section class="card">
    <h2>Сборные ЧМ-2026</h2>
    <p class="muted">
        48 участников турнира. Заполните рейтинг FIFA, форму и краткую справку — они показываются на карточке матча.
        Рейтинг обновляйте по
        <a class="table-link" href="https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/teams" rel="noopener noreferrer" target="_blank">официальной таблице FIFA</a>.
        Служебные записи плей-офф (например «1-е место группы A») здесь не отображаются.
    </p>
    <?php if (!$teams): ?>
        <p class="muted">Сборные не найдены. Обновите страницу — они подтянутся из справочника турнира.</p>
    <?php else: ?>
        <div class="admin-teams-list">
            <?php foreach ($teams as $team): ?>
                <?php
                    $teamId = (int) $team['id'];
                    $rankVal = $team['fifa_rank'] !== null && $team['fifa_rank'] !== '' ? (int) $team['fifa_rank'] : '';
                    $wcGroup = worldcup2026_group_for_team(
                        $team['code'] !== null && (string) $team['code'] !== '' ? (string) $team['code'] : null,
                        (string) $team['name']
                    );
                    $teamCode = $team['code'] !== null && (string) $team['code'] !== '' ? (string) $team['code'] : null;
                ?>
                <div class="admin-team-card" id="team-<?= $teamId ?>">
                    <p class="admin-team-card-title">
                        <?php render_team_with_flag($teamCode, (string) $team['name'], 'team-with-flag admin-team-card-title__name'); ?>
                        <?php if ($teamCode !== null): ?>
                            <span class="muted admin-team-card-code"><?= h($teamCode) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($wcGroup !== null): ?>
                        <p class="muted admin-team-wc-line">Группа ЧМ-2026: <strong><?= h($wcGroup) ?></strong> · <?= h(WORLD_CUP_2026_GROUP_LABEL_RU[$wcGroup] ?? '') ?></p>
                    <?php endif; ?>
                    <form method="post" action="/admin/teams/update" class="stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="team_id" value="<?= $teamId ?>">
                        <div class="team-form-inline">
                            <label>
                                Место FIFA
                                <input name="fifa_rank" type="number" min="1" max="300" value="<?= $rankVal !== '' ? (int) $rankVal : '' ?>" placeholder="рейтинг">
                            </label>
                            <label>
                                Форма (до 40 симв.)
                                <input name="form_last5" value="<?= h($team['form_last5'] ?? '') ?>" maxlength="40" placeholder="ВНВПВ">
                            </label>
                        </div>
                        <label>
                            Справка (до 600 симв.)
                            <textarea name="brief_note" rows="2" maxlength="600" placeholder="Стиль игры, ключевые игроки…"><?= h($team['brief_note'] ?? '') ?></textarea>
                        </label>
                        <div class="team-form-actions">
                            <span class="muted">
                                <?= (int) $team['matches_count'] ?> матчей,
                                <?= (int) $team['champion_predictions_count'] ?> прогнозов на чемпиона
                            </span>
                            <button class="button small" type="submit">Сохранить</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
