<div class="hero-channels">
    <p class="hero-channels-eyebrow">Не пропусти новости ЧМ-2026</p>
    <p class="hero-channels-lead">Обсуждай матчи, делись прогнозами и читай анонсы в официальных каналах</p>
    <div class="hero-channels-grid">
        <a
            class="hero-channel hero-channel--telegram"
            href="<?= h(contest_telegram_channel_url()) ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <span class="hero-channel-icon" aria-hidden="true">
                <?php $iconChannel = 'telegram'; require __DIR__ . '/channel_icon.php'; ?>
            </span>
            <span class="hero-channel-text">
                <strong>Telegram</strong>
                <span>@chpwc2026 · обсуждение и новости</span>
            </span>
        </a>
        <a
            class="hero-channel hero-channel--max"
            href="<?= h(contest_max_channel_url()) ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <span class="hero-channel-icon hero-channel-icon--max" aria-hidden="true">
                <?php $iconChannel = 'max'; require __DIR__ . '/channel_icon.php'; ?>
            </span>
            <span class="hero-channel-text">
                <strong>MAX</strong>
                <span><?= h(contest_max_channel_name()) ?> · анонсы турнира</span>
            </span>
        </a>
    </div>
</div>
