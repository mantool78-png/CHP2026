<?php
/** @var list<array<string,mixed>> $sitePolls */
$sitePolls = $sitePolls ?? site_polls_active();
if ($sitePolls === []) {
    return;
}
?>
<section class="card site-polls-block">
    <div class="site-polls-intro">
        <h2>Опросы</h2>
        <p class="muted small-print">Быстрое голосование — на рейтинг не влияет.</p>
    </div>
    <div class="site-polls-grid">
        <?php foreach ($sitePolls as $poll): ?>
            <?php
            $pollId = (int) $poll['id'];
            $results = site_poll_results($pollId);
            $hasVoted = user_has_voted_site_poll($pollId);
            $showResults = $hasVoted || isset($_GET['poll_results']);
            ?>
            <article class="site-poll-item" id="poll-<?= h((string) $poll['slug']) ?>">
                <div class="site-poll-head">
                    <h3><?= h((string) $poll['title']) ?></h3>
                    <span class="site-poll-votes"><?= (int) $results['total'] ?></span>
                </div>
                <?php if ($showResults): ?>
                    <div class="site-poll-results">
                        <?php foreach ($results['options'] as $res): ?>
                            <div class="site-poll-result-row">
                                <div class="site-poll-result-bar" style="width: <?= max(4, (int) $res['percent']) ?>%;"></div>
                                <div class="site-poll-result-content">
                                    <span><?= h((string) $res['label']) ?></span>
                                    <strong><?= (int) $res['percent'] ?>%</strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <form method="post" action="/poll/vote" class="site-poll-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="poll_id" value="<?= $pollId ?>">
                        <div class="site-poll-options">
                            <?php foreach ($poll['options'] as $opt): ?>
                                <?php if (!is_array($opt)) {
                                    continue;
                                } ?>
                                <button
                                    type="submit"
                                    name="option_key"
                                    value="<?= h((string) ($opt['key'] ?? '')) ?>"
                                    class="site-poll-option-btn"
                                ><?= h((string) ($opt['label'] ?? '')) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
