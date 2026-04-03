<section class="utilityPanel">
  <div class="utilityPanel__head">
    <span class="utilityPanel__eyebrow">Signals</span>
    <h3>Discovery radar</h3>
    <p class="muted">Trending topics and people worth opening next.</p>
  </div>

  <div class="utilityPanel__stack">
    <section class="utilityBand">
      <div class="utilityBand__head">
        <div>
          <span class="utilityBand__eyebrow">Topic radar</span>
          <h4>Trending hashtags</h4>
        </div>
        <span><?= count($trendingHashtags) ?> live</span>
      </div>

      <?php if (!$trendingHashtags): ?>
        <div class="utilityBand__empty muted">No trending hashtags yet.</div>
      <?php else: ?>
        <div class="tagStack">
          <?php foreach ($trendingHashtags as $tag): ?>
            <?php
            $hashtag = (string)($tag['hashtag'] ?? '');
            $usageCount = (int)($tag['usage_count'] ?? 0);
            $recentHits = (int)($tag['recent_hits'] ?? 0);
            if ($hashtag === '') {
              continue;
            }
            ?>
            <a class="tagChip" href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode('#' . $hashtag) ?>&filter=hashtags">
              <span class="tagChip__signal" aria-hidden="true">TAG</span>
              <strong>#<?= trux_e($hashtag) ?></strong>
              <span><?= number_format($usageCount) ?> post<?= $usageCount === 1 ? '' : 's' ?><?= $recentHits > 0 ? ' · ' . number_format($recentHits) . ' recent' : '' ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="utilityBand">
      <div class="utilityBand__head">
        <div>
          <span class="utilityBand__eyebrow">Identity radar</span>
          <h4><?= $me ? 'Who to follow' : 'Suggested creators' ?></h4>
        </div>
        <span><?= count($suggestedUsers) ?> suggestions</span>
      </div>

      <?php if (!$suggestedUsers): ?>
        <div class="utilityBand__empty muted">
          <?= $me ? 'No suggestions yet. Check back as the network grows.' : 'No suggestions yet.' ?>
        </div>
      <?php else: ?>
        <div class="userStack">
          <?php foreach ($suggestedUsers as $suggestion): ?>
            <?php
            $suggestedId = (int)($suggestion['id'] ?? 0);
            $suggestedUsername = (string)($suggestion['username'] ?? '');
            if ($suggestedId <= 0 || $suggestedUsername === '') {
              continue;
            }

            $mutualCount = (int)($suggestion['mutual_count'] ?? 0);
            $followerCount = (int)($suggestion['follower_count'] ?? 0);
            $recentPosts = (int)($suggestion['recent_posts'] ?? 0);
            $displayName = trim((string)($suggestion['display_name'] ?? ''));
            ?>
            <div class="userBand">
              <span class="userBand__signal" aria-hidden="true">USR</span>
              <div class="userBand__copy">
                <div class="userBand__title">
                  <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode($suggestedUsername) ?>">@<?= trux_e($suggestedUsername) ?></a>
                  <?php if ($displayName !== ''): ?>
                    <span class="muted"><?= trux_e($displayName) ?></span>
                  <?php endif; ?>
                </div>
                <div class="userBand__meta muted">
                  <?php if ($me && $mutualCount > 0): ?>
                    <?= number_format($mutualCount) ?> mutual ·
                  <?php endif; ?>
                  <?= number_format($followerCount) ?> followers · <?= number_format($recentPosts) ?> recent post<?= $recentPosts === 1 ? '' : 's' ?>
                </div>
              </div>

              <?php if ($me): ?>
                <form method="post" action="<?= TRUX_BASE_URL ?>/follow.php" data-no-fx="1">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="action" value="follow">
                  <input type="hidden" name="user_id" value="<?= $suggestedId ?>">
                  <input type="hidden" name="user" value="<?= trux_e($suggestedUsername) ?>">
                  <input type="hidden" name="redirect" value="<?= trux_e($feedReturnPath) ?>">
                  <button class="shellButton shellButton--ghost" type="submit">Follow</button>
                </form>
              <?php else: ?>
                <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode($suggestedUsername) ?>">View</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</section>
