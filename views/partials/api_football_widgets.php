<?php
/**
 * @var string|null $apiFootballWidgetContext matches|tournament
 */
$apiFootballWidgetContext = $apiFootballWidgetContext ?? 'matches';
$showWidgets = api_football_widgets_enabled();
$apiKey = $showWidgets ? api_football_widget_api_key() : '';
$leagueId = (int) (api_football_settings()['league_id'] ?? 1);
$season = (int) (api_football_settings()['season'] ?? 2026);

if (!$showWidgets || $apiKey === '') {
    return;
}
?>
<section class="card api-football-widgets-wrap">
    <details<?= $apiFootballWidgetContext === 'tournament' ? ' open' : '' ?>>
        <summary>Справочно: календарь FIFA</summary>
        <p class="muted small-print api-football-widgets-note">
            Только для просмотра. Очки конкурса считаются по матчам и результатам на этом сайте, не по этому блоку.
        </p>
        <api-sports-widget
            data-type="config"
            data-key="<?= h($apiKey) ?>"
            data-sport="football"
            data-lang="en"
            data-theme="dark"
            data-show-errors="true"
            data-show-logos="true"
            data-refresh="60"
            data-target-game="modal"
            data-target-standings="modal"
        ></api-sports-widget>
        <h3 class="api-football-widget-heading">Расписание</h3>
        <api-sports-widget
            data-type="games"
            data-league="<?= (int) $leagueId ?>"
            data-season="<?= (int) $season ?>"
        ></api-sports-widget>
        <h3 class="api-football-widget-heading">Таблица лиги</h3>
        <api-sports-widget
            data-type="standings"
            data-league="<?= (int) $leagueId ?>"
            data-season="<?= (int) $season ?>"
        ></api-sports-widget>
    </details>
</section>
<?php
static $apiSportsWidgetScriptQueued = false;
if (!$apiSportsWidgetScriptQueued):
    $apiSportsWidgetScriptQueued = true;
?>
    <script type="module" src="https://widgets.api-sports.io/3.1.0/widgets.js"></script>
<?php endif; ?>
