<div class="header-channels">
    <a
        class="header-channel header-channel--telegram"
        href="<?= h(contest_telegram_channel_url()) ?>"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Telegram @chpwc2026 — обсуждение и новости"
        title="Telegram @chpwc2026"
    >
        <?php $iconChannel = 'telegram'; require __DIR__ . '/channel_icon.php'; ?>
    </a>
    <a
        class="header-channel header-channel--max"
        href="<?= h(contest_max_channel_url()) ?>"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="MAX — <?= h(contest_max_channel_name()) ?>"
        title="MAX — <?= h(contest_max_channel_name()) ?>"
    >
        <?php $iconChannel = 'max'; require __DIR__ . '/channel_icon.php'; ?>
    </a>
</div>
