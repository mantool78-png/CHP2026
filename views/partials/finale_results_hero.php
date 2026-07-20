<?php
/**
 * Hero итогов конкурса (эскиз: топ-5 слева, чемпион/флаг справа).
 *
 * @var array{
 *     top: list<array<string,mixed>>,
 *     final_match: ?array<string,mixed>,
 *     champion_team: ?array<string,mixed>,
 *     participants_count: int
 * } $finale
 * @var bool $finaleIsDraft
 */
$finaleIsDraft = $finaleIsDraft ?? false;
$top = $finale['top'] ?? [];
$championTeam = $finale['champion_team'] ?? null;

$champName = $championTeam ? (string) $championTeam['name'] : 'Испания';
$champCode = $championTeam['code'] ?? 'ESP';
$champFlag = worldcup2026_flag_path(is_string($champCode) ? $champCode : 'ESP', $champName)
    ?: '/assets/flags/ESP.svg';

$prizeLabelFor = static function (?array $prize): string {
    if ($prize === null) {
        return '';
    }
    if (!empty($prize['is_main_prize'])) {
        return (string) $prize['label'];
    }

    return number_format((int) ($prize['amount_rub'] ?? 0), 0, ',', ' ') . ' ₽';
};
?>
<section class="finale-close-hero<?= $finaleIsDraft ? ' finale-close-hero--draft' : '' ?>" aria-label="Итоги конкурса">
    <?php if ($finaleIsDraft): ?>
        <span class="finale-close-hero-draft">Черновик</span>
    <?php endif; ?>

    <div class="finale-close-hero-left">
        <p class="finale-close-hero-kicker">Конкурс завершён</p>
        <h1 class="finale-close-hero-title">
            Лига прогнозов<br>
            на&nbsp;матчи ЧМ-2026<br>
            <span class="finale-close-hero-title-accent">завершена</span>
        </h1>

        <ol class="finale-close-podium" aria-label="Топ-5 призёров">
            <?php foreach ($top as $row): ?>
                <?php
                $place = (int) $row['place'];
                $prizeText = $prizeLabelFor($row['prize'] ?? null);
                ?>
                <li class="finale-close-podium-row<?= $place === 1 ? ' is-first' : '' ?>">
                    <span class="finale-close-podium-place"><?= $place ?> место</span>
                    <span class="finale-close-podium-name"><?= h((string) $row['name']) ?></span>
                    <span class="finale-close-podium-meta">
                        <strong><?= (int) $row['total_points'] ?></strong> оч.
                        <?php if ($prizeText !== ''): ?>
                            <span class="finale-close-podium-prize"><?= h($prizeText) ?></span>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="finale-close-hero-actions">
            <a class="button" href="/rating">Полный рейтинг</a>
            <a class="button secondary" href="/prizes">Призы</a>
        </div>
    </div>

    <div class="finale-close-hero-right">
        <p class="finale-close-champ-kicker">Чемпион мира 2026</p>
        <h2 class="finale-close-champ-name"><?= h(mb_strtoupper($champName, 'UTF-8')) ?></h2>
        <div class="finale-close-flag-wrap">
            <img
                class="finale-close-flag"
                src="<?= h($champFlag) ?>"
                alt="Флаг: <?= h($champName) ?>"
                width="640"
                height="427"
                decoding="async"
                loading="eager"
            >
        </div>
    </div>
</section>
