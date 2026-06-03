<?php
declare(strict_types=1);

/** @var int $ymCounterId */
if (!isset($ymCounterId) || $ymCounterId <= 0) {
    return;
}
$id = (int) $ymCounterId;
$tagUrl = 'https://mc.yandex.ru/metrika/tag.js?id=' . $id;
?>
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    window.dataLayer = window.dataLayer || [];
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script',<?= json_encode($tagUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 'ym');

    ym(<?= $id ?>, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/<?= $id ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
