<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'home';
$pageLayout = 'app';

$partial = trux_str_param('partial', '');
$before = trux_int_param('before', 0);
$discoveryPage = max(1, trux_int_param('page', 1));
$limit = 12;
$requestedFeed = trux_str_param('feed', 'all');
$feedMode = $requestedFeed === 'following' ? 'following' : 'all';
$usedAlgrDiscovery = false;
$algrDiscoveryPage = null;

$me = trux_current_user();
if (!$me && $feedMode === 'following') {
  $feedMode = 'all';
}

$viewerId = $me ? (int) $me['id'] : null;
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

// Serve the desktop discovery rail separately so the homepage can render the timeline first.
if ($partial === 'discovery-rail') {
  if ($feedMode !== 'all') {
    http_response_code(400);
    exit;
  }

  $trendingHashtags = trux_fetch_trending_hashtags(8);
  $suggestedUsers = trux_fetch_discovery_suggestions($viewerId, $me ? 6 : 7);

  require __DIR__ . '/_discovery_rail.php';
  exit;
}

$followingCount = 0;
if ($feedMode === 'following' && $me) {
  $followCounts = trux_follow_counts((int) $me['id']);
  $followingCount = (int) ($followCounts['following'] ?? 0);
}

if ($feedMode === 'following' && $me) {
  $posts = trux_fetch_following_feed((int)$me['id'], $limit, $before > 0 ? $before : null);
} else {
  $algrDiscoveryPage = trux_fetch_discovery_feed_via_algr($viewerId, $limit, $discoveryPage);

  if (is_array($algrDiscoveryPage)) {
    $posts = is_array($algrDiscoveryPage['posts'] ?? null) ? $algrDiscoveryPage['posts'] : [];
    $usedAlgrDiscovery = true;
  } else {
    $posts = trux_fetch_discovery_feed($viewerId, $limit, $discoveryPage);
  }
}

$nextBefore = null;
$nextDiscoveryPage = null;
if ($feedMode === 'following') {
  if (count($posts) > 0) {
    $last = $posts[count($posts) - 1];
    $nextBefore = (int)$last['id'];
  }
} else {
  if ($usedAlgrDiscovery) {
    $nextDiscoveryPage = !empty($algrDiscoveryPage['has_more']) ? $discoveryPage + 1 : null;
  } elseif (count($posts) === $limit) {
    $nextDiscoveryPage = $discoveryPage + 1;
  }
}

$interactionMap = trux_fetch_post_interactions(
  trux_collect_post_ids($posts),
  $me ? (int) $me['id'] : null
);

$_quotedIds = array_filter(array_unique(array_map(static fn($p) => (int)($p['quoted_post_id'] ?? 0), $posts)));
$quotedPostMap = $_quotedIds ? trux_fetch_quoted_posts_batch(array_values($_quotedIds)) : [];
$pollMap = trux_fetch_polls_for_posts(trux_collect_post_ids($posts), $me ? (int)$me['id'] : null);
unset($_quotedIds);

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
      <div class="commandReadoutGrid" aria-hidden="true">
        <div class="commandReadout">
          <span>Source</span>
          <strong><?= $feedMode === 'following' ? 'Follow graph' : 'Open discovery' ?></strong>
        </div>
        <div class="commandReadout">
          <span>Output</span>
          <strong><?= count($posts) ?> packets</strong>
        </div>
      </div>
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
      <?php
      $discoveryRailSrc = TRUX_BASE_URL . '/?' . http_build_query([
        'partial' => 'discovery-rail',
        'feed' => 'all',
        'page' => $discoveryPage,
      ]);
      $discoveryRailFallbackHref = TRUX_BASE_URL . '/search.php';
      ?>
      <aside class="feedScene__rail">
        <div
          class="discoveryRailShell"
          data-discovery-rail-root="1"
          data-discovery-rail-src="<?= trux_e($discoveryRailSrc) ?>"
          data-discovery-rail-fallback-href="<?= trux_e($discoveryRailFallbackHref) ?>">
          <section class="utilityPanel">
            <div class="utilityPanel__head">
              <span class="utilityPanel__eyebrow">Signals</span>
              <h3>Discovery radar</h3>
              <p class="muted">Trending topics and people worth opening next.</p>
            </div>

            <div class="utilityPanel__stack" data-discovery-rail-body="1">
              <section class="utilityBand">
                <div class="utilityBand__head">
                  <div>
                    <span class="utilityBand__eyebrow">Stand by</span>
                    <h4>Loading discovery radar</h4>
                  </div>
                  <span>Deferred</span>
                </div>
                <div class="utilityBand__empty muted discoveryRailShell__status">Loading trending topics and suggested accounts...</div>
              </section>
            </div>
          </section>
        </div>
        <noscript>
          <section class="utilityPanel discoveryRailShell__noscript">
            <div class="utilityBand__empty muted">
              Discovery radar loads after first paint. <a href="<?= trux_e($discoveryRailFallbackHref) ?>">Open search</a> to browse the network.
            </div>
          </section>
        </noscript>
      </aside>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
