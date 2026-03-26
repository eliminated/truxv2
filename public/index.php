<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'home';
$pageLayout = 'app';

$before = trux_int_param('before', 0);
$discoveryPage = max(1, trux_int_param('page', 1));
$limit = 20;
$requestedFeed = trux_str_param('feed', 'all');
$feedMode = $requestedFeed === 'following' ? 'following' : 'all';

$me = trux_current_user();
if (!$me && $feedMode === 'following') {
  $feedMode = 'all';
}

$followingCount = 0;
if ($feedMode === 'following' && $me) {
  $followCounts = trux_follow_counts((int) $me['id']);
  $followingCount = (int) ($followCounts['following'] ?? 0);
}

$viewerId = $me ? (int) $me['id'] : null;
$posts = $feedMode === 'following' && $me
  ? trux_fetch_following_feed((int) $me['id'], $limit, $before > 0 ? $before : null)
  : trux_fetch_discovery_feed($viewerId, $limit, $discoveryPage);

$nextBefore = null;
$nextDiscoveryPage = null;
if ($feedMode === 'following') {
  if (count($posts) > 0) {
    $last = $posts[count($posts) - 1];
    $nextBefore = (int) $last['id'];
  }
} else {
  if (count($posts) === $limit) {
    $nextDiscoveryPage = $discoveryPage + 1;
  }
}

$trendingHashtags = [];
$suggestedUsers = [];
if ($feedMode === 'all') {
  $trendingHashtags = trux_fetch_trending_hashtags(8);
  $suggestedUsers = trux_fetch_discovery_suggestions($viewerId, $me ? 6 : 7);
}

$feedReturnParams = ['feed' => $feedMode];
if ($feedMode === 'following' && $before > 0) {
  $feedReturnParams['before'] = $before;
} elseif ($feedMode === 'all' && $discoveryPage > 1) {
  $feedReturnParams['page'] = $discoveryPage;
}
$feedReturnPath = '/?' . http_build_query($feedReturnParams);
if ($feedMode === 'all' && $discoveryPage === 1) {
  $feedReturnPath = '/';
}

$interactionMap = trux_fetch_post_interactions(
  trux_collect_post_ids($posts),
  $me ? (int) $me['id'] : null
);

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--feed">
  <section class="inlineHeader inlineHeader--feed">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Command feed</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title"><?= $feedMode === 'following' ? 'Following stream' : 'Discovery stream' ?></h2>
        <p class="inlineHeader__copy">
        <?php if ($feedMode === 'following' && $me): ?>
          Latest posts from people you follow, plus your own posts.
        <?php elseif (trux_is_logged_in()): ?>
          Fresh discovery ordered by recency, engagement, and social proximity.
        <?php else: ?>
          Explore the public network. Sign in to personalize the stream.
        <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="inlineHeader__aside">
      <div class="inlineHeader__meta">
        <span><?= $me ? 'Signed in as @' . trux_e((string)$me['username']) : 'Guest browsing' ?></span>
        <strong><?= $feedMode === 'following' ? 'Following mode' : 'Discovery mode' ?></strong>
      </div>
    </div>
  </section>

  <div class="feedScene">
    <div class="feedScene__main">
      <section class="timelineFrame">
        <div class="timelineFrame__head">
          <div>
            <span class="timelineFrame__eyebrow"><?= $feedMode === 'following' ? 'Following' : 'Discovery' ?></span>
            <h3><?= $feedMode === 'following' ? 'Recent posts from your network' : 'Live timeline' ?></h3>
          </div>
          <div class="timelineFrame__actions">
            <nav class="segmented" aria-label="Feed mode">
              <a class="segmented__item<?= $feedMode === 'all' ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/" <?= $feedMode === 'all' ? 'aria-current="page"' : '' ?>>
                For you
              </a>
              <?php if ($me): ?>
                <a class="segmented__item<?= $feedMode === 'following' ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/?feed=following" <?= $feedMode === 'following' ? 'aria-current="page"' : '' ?>>
                  Following
                </a>
              <?php else: ?>
                <a class="segmented__item is-disabled" href="<?= TRUX_BASE_URL ?>/login.php">
                  Following
                </a>
              <?php endif; ?>
            </nav>
            <span class="muted"><?= count($posts) ?> loaded</span>
          </div>
        </div>

        <div class="timeline" data-auto-pager-list="home-posts">
          <?php if (!$posts): ?>
            <section class="bandSurface bandSurface--empty">
              <strong>No posts yet</strong>
              <p class="muted">
                <?php if ($feedMode === 'following' && $me && $followingCount === 0): ?>
                  You are not following anyone yet. Explore the network first, then come back to your following stream.
                <?php elseif ($feedMode === 'following' && $me): ?>
                  Nobody in your following stream has posted yet. Switch back to discovery or check again later.
                <?php else: ?>
                  Discovery does not have enough posts yet. Be the first to publish.
                <?php endif; ?>
              </p>
            </section>
          <?php endif; ?>

          <?php foreach ($posts as $p): ?>
            <?php
            $postRecord = $p;
            $postViewer = $me;
            $postInteractionStats = $interactionMap[(int)$p['id']] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false];
            require __DIR__ . '/_post_card.php';
            ?>
          <?php endforeach; ?>
        </div>

        <?php if ($nextBefore || $nextDiscoveryPage): ?>
          <div class="pager" data-auto-pager="home-posts">
            <a
              class="shellButton shellButton--ghost"
              data-no-fx="1"
              href="<?php
              if ($feedMode === 'following') {
                echo TRUX_BASE_URL . '/?feed=following&before=' . (int)$nextBefore;
              } else {
                echo TRUX_BASE_URL . '/?page=' . (int)$nextDiscoveryPage;
              }
              ?>">
              Load more
            </a>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <?php if ($feedMode === 'all'): ?>
      <aside class="feedScene__rail">
        <section class="utilityPanel">
          <div class="utilityPanel__head">
            <span class="utilityPanel__eyebrow">Signals</span>
            <h3>Discovery radar</h3>
            <p class="muted">Trending topics and people worth opening next.</p>
          </div>

          <div class="utilityPanel__stack">
            <section class="utilityBand">
              <div class="utilityBand__head">
                <h4>Trending hashtags</h4>
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
                      <strong>#<?= trux_e($hashtag) ?></strong>
                      <span><?= number_format($usageCount) ?> post<?= $usageCount === 1 ? '' : 's' ?><?= $recentHits > 0 ? ' · ' . number_format($recentHits) . ' recent' : '' ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="utilityBand">
              <div class="utilityBand__head">
                <h4><?= $me ? 'Who to follow' : 'Suggested creators' ?></h4>
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
      </aside>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
