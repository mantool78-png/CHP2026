<?php
/**
 * Официальные маскоты FIFA World Cup 26™ (оформление на сайте).
 *
 * Перед подключением можно задать:
 * - $mascotSet string: trio | duo-wing | duo-americas | solo-eagle | solo-jaguar | solo-moose
 * - $mascotSize string: sm | md | lg
 * - $mascotCaption string: подпись под рядом (или пусто)
 * - $mascotExtraClass string: доп. классы на контейнер (например mascot-strip--home)
 */
$mascotSet = isset($mascotSet) ? (string) $mascotSet : 'trio';
$mascotSize = isset($mascotSize) ? (string) $mascotSize : 'md';
$mascotCaption = isset($mascotCaption) ? (string) $mascotCaption : '';
$mascotExtraClass = isset($mascotExtraClass) ? trim((string) $mascotExtraClass) : '';

$figures = [
    ['/assets/mascot-eagle.png', 'CLUTCH — маскот ЧМ-2026, символ США'],
    ['/assets/mascot-jaguar.png', 'ZAYA — маскот ЧМ-2026, символ Мексики'],
    ['/assets/mascot-moose.png', 'MAPLE — маскот ЧМ-2026, символ Канады'],
];

$bundles = [
    'trio' => [0, 1, 2],
    'duo-wing' => [0, 1],
    'duo-americas' => [1, 2],
    'solo-eagle' => [0],
    'solo-jaguar' => [1],
    'solo-moose' => [2],
];

if (!isset($bundles[$mascotSet])) {
    $mascotSet = 'trio';
}

$indices = $bundles[$mascotSet];
$sizeSuffix = $mascotSize === 'lg' ? ' mascot-cluster--lg' : ($mascotSize === 'sm' ? ' mascot-cluster--sm' : '');
$stripClasses = trim('mascot-strip ' . $mascotExtraClass);
$mascotSetClass = preg_replace('/[^a-z0-9-]/', '', $mascotSet);
$aria = 'Официальные маскоты чемпионата мира по футболу 2026';
?>
<aside class="<?= h($stripClasses) ?>" aria-label="<?= h($aria) ?>">
    <div class="mascot-cluster mascot-cluster--set-<?= h($mascotSetClass) ?><?= $sizeSuffix ?>">
        <?php foreach ($indices as $i): ?>
            <?php $row = $figures[$i]; ?>
            <img
                src="<?= h($row[0]) ?>"
                alt="<?= h($row[1]) ?>"
                width="280"
                height="420"
                loading="lazy"
                decoding="async"
            >
        <?php endforeach; ?>
    </div>
    <?php if ($mascotCaption !== ''): ?>
        <p class="mascot-caption muted"><?= h($mascotCaption) ?></p>
    <?php endif; ?>
</aside>
