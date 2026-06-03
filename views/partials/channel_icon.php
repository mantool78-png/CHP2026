<?php
/** SVG Telegram или PNG MAX. Ожидает $iconChannel = 'telegram'|'max'. */
$iconChannel = $iconChannel ?? '';
?>
<?php if ($iconChannel === 'telegram'): ?>
    <svg class="channel-icon-svg channel-icon-svg--telegram" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">
        <circle cx="24" cy="24" r="24" fill="#229ED9"/>
        <path fill="#fff" d="M10.6 23.4 32.7 14.9c1-.4 1.8.2 1.5 1.4l-3.8 18c-.3 1.2-1 1.5-2 .9l-5.5-4.1-2.7 2.6c-.3.3-.5.5-1.1.5l.4-5.7 10.2-9.2c.4-.4-.1-.6-.7-.2L14.6 28.5l-5.5-1.7c-1.2-.4-1.2-1.2.5-1.8z"/>
    </svg>
<?php elseif ($iconChannel === 'max'): ?>
    <img
        class="channel-icon-img channel-icon-img--max"
        src="/assets/max-icon.png"
        alt=""
        width="48"
        height="48"
        decoding="async"
        aria-hidden="true"
    >
<?php endif; ?>
