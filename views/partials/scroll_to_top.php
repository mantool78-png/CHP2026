<button type="button" class="scroll-top-btn" id="scroll-top-btn" hidden aria-label="Наверх" title="Наверх">
    ↑
</button>
<script>
(function () {
    var btn = document.getElementById('scroll-top-btn');
    if (!btn) {
        return;
    }
    var threshold = 420;
    function toggle() {
        btn.hidden = window.scrollY < threshold;
    }
    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    window.addEventListener('scroll', toggle, { passive: true });
    toggle();
})();
</script>
