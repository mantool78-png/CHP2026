<?php
if (!api_football_widgets_enabled()) {
    return;
}
$apiKey = api_football_widget_api_key();
if ($apiKey === '') {
    return;
}
$leagueId = (int) (api_football_settings()['league_id'] ?? 1);
$season = (int) (api_football_settings()['season'] ?? 2026);
?>
<section class="card api-football-widgets-wrap">
    <details>
        <summary>Расписание FIFA (виджет API-Sports)</summary>
        <p class="muted small-print">Справочно. Зачёт конкурса прогнозов&nbsp;&mdash; по результатам и таблицам на этом сайте. Виджет использует ваш API-ключ (ограничьте домен в dashboard API-Football).</p>
        <api-sports-widget data-type="games" data-league="<?= (int) $leagueId ?>" data-season="<?= (int) $season ?>"></api-sports-widget>
        <api-sports-widget
            data-type="config"
            data-key="<?= h($apiKey) ?>"
            data-sport="football"
            data-lang="ru"
            data-theme="dark"
            data-show-logos="true"
        ></api-sports-widget>
    </details>
</section>
<script src="https://widgets.api-sports.io/3.1.0/widgets.js" defer></script>
