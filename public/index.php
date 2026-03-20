<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

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

<section class="hero">
  <h1>TruX Feed</h1>
  <p class="muted">
    <?php if ($feedMode === 'following' && $me): ?>
      Latest posts from people you follow, plus your own posts.
    <?php elseif (trux_is_logged_in()): ?>
      Discovery 1.0 ranks posts using freshness, engagement, and your social graph.
    <?php else: ?>
      Explore trending content. Log in for personalized discovery.
    <?php endif; ?>
  </p>

  <div class="feedSwitch" aria-label="Feed mode">
    <a class="feedSwitch__item<?= $feedMode === 'all' ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/"
      <?= $feedMode === 'all' ? 'aria-current="page"' : '' ?>>
      For You
    </a>

    <?php if ($me): ?>
      <a class="feedSwitch__item<?= $feedMode === 'following' ? ' is-active' : '' ?>"
        href="<?= TRUX_BASE_URL ?>/?feed=following" <?= $feedMode === 'following' ? 'aria-current="page"' : '' ?>>
        Following
      </a>
    <?php else: ?>
      <a class="feedSwitch__item is-disabled" href="<?= TRUX_BASE_URL ?>/login.php">
        Following
      </a>
    <?php endif; ?>
  </div>
</section>

<?php if ($feedMode === 'all'): ?>
  <section class="card discoveryBlock" aria-label="Discovery modules">
    <div class="card__body discoveryBlock__body">
      <div class="discoveryBlock__head">
        <h2 class="h2">Discovery 1.0</h2>
        <p class="muted discoveryBlock__sub">Signals: freshness, engagement, and social proximity</p>
      </div>

      <div class="discoveryGrid">
        <article class="discoveryPane">
          <div class="discoveryPane__head">
            <h3 class="h2">Trending hashtags</h3>
            <p class="muted discoveryPane__desc">Based on recent post activity.</p>
          </div>

          <?php if (!$trendingHashtags): ?>
            <div class="muted discoveryEmpty">No trending hashtags yet.</div>
          <?php else: ?>
            <div class="discoveryList">
              <?php foreach ($trendingHashtags as $tag): ?>
                <?php
                $hashtag = (string) ($tag['hashtag'] ?? '');
                $usageCount = (int) ($tag['usage_count'] ?? 0);
                $recentHits = (int) ($tag['recent_hits'] ?? 0);
                if ($hashtag === '') {
                  continue;
                }
                ?>
                <a class="discoveryTag"
                  href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode('#' . $hashtag) ?>&filter=hashtags">
                  <span class="discoveryTag__name">#<?= trux_e($hashtag) ?></span>
                  <span class="discoveryTag__meta">
                    <?= number_format($usageCount) ?> post<?= $usageCount === 1 ? '' : 's' ?>
                    <?php if ($recentHits > 0): ?>
                      &middot; <?= number_format($recentHits) ?> in last 24h
                    <?php endif; ?>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>

        <article class="discoveryPane">
          <div class="discoveryPane__head">
            <h3 class="h2"><?= $me ? 'Who to follow' : 'Suggested creators' ?></h3>
            <p class="muted discoveryPane__desc">
              <?= $me
                ? 'Picked from mutual connections, follower momentum, and posting activity.'
                : 'Picked from follower momentum and posting activity.' ?>
            </p>
          </div>

          <?php if (!$suggestedUsers): ?>
            <div class="muted discoveryEmpty">
              <?= $me ? 'No suggestions yet. Check back as the network grows.' : 'No suggestions yet.' ?>
            </div>
          <?php else: ?>
            <div class="discoveryUserList">
              <?php foreach ($suggestedUsers as $suggestion): ?>
                <?php
                $suggestedId = (int) ($suggestion['id'] ?? 0);
                $suggestedUsername = (string) ($suggestion['username'] ?? '');
                if ($suggestedId <= 0 || $suggestedUsername === '') {
                  continue;
                }

                $mutualCount = (int) ($suggestion['mutual_count'] ?? 0);
                $followerCount = (int) ($suggestion['follower_count'] ?? 0);
                $recentPosts = (int) ($suggestion['recent_posts'] ?? 0);
                $displayName = trim((string) ($suggestion['display_name'] ?? ''));
                ?>
                <div class="discoveryUser">
                  <div class="discoveryUser__info">
                    <div class="discoveryUser__line">
                      <a class="discoveryUser__name"
                        href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode($suggestedUsername) ?>">@<?= trux_e($suggestedUsername) ?></a>
                      <?php if ($displayName !== ''): ?>
                        <span class="muted"><?= trux_e($displayName) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="muted discoveryUser__meta">
                      <?php if ($me && $mutualCount > 0): ?>
                        <?= number_format($mutualCount) ?> mutual connection<?= $mutualCount === 1 ? '' : 's' ?> &middot;
                      <?php endif; ?>
                      <?= number_format($followerCount) ?> follower<?= $followerCount === 1 ? '' : 's' ?> &middot;
                      <?= number_format($recentPosts) ?> recent post<?= $recentPosts === 1 ? '' : 's' ?>
                    </div>
                  </div>

                  <?php if ($me): ?>
                    <form method="post" action="<?= TRUX_BASE_URL ?>/follow.php" class="inline" data-no-fx="1">
                      <?= trux_csrf_field() ?>
                      <input type="hidden" name="action" value="follow">
                      <input type="hidden" name="user_id" value="<?= $suggestedId ?>">
                      <input type="hidden" name="user" value="<?= trux_e($suggestedUsername) ?>">
                      <input type="hidden" name="redirect" value="<?= trux_e($feedReturnPath) ?>">
                      <button class="btn btn--small" type="submit">Follow</button>
                    </form>
                  <?php else: ?>
                    <a class="btn btn--small btn--ghost"
                      href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode($suggestedUsername) ?>">View</a>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="feed">
  <div data-auto-pager-list="home-posts">
    <?php if (!$posts): ?>
      <div class="card">
        <div class="card__body">
          <?php if ($feedMode === 'following' && $me && $followingCount === 0): ?>
            You are not following anyone yet. Find people to follow from search or browse the global feed first.
          <?php elseif ($feedMode === 'following' && $me): ?>
            No posts from people you follow yet. Check back later or switch to the global feed.
          <?php else: ?>
            Discovery does not have enough posts yet. Be the first to post.
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($posts as $p): ?>
      <?php
      $postId = (int) $p['id'];
      $postUrl = TRUX_BASE_URL . '/post.php?id=' . $postId;
      $postStats = $interactionMap[$postId] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false];
      $postBookmarked = (bool) ($postStats['bookmarked'] ?? false);
      $postIsOwner = $me && (int) $p['user_id'] === (int) $me['id'];
      $editedAt = isset($p['edited_at']) && $p['edited_at'] !== null ? (string) $p['edited_at'] : '';
      ?>
      <article class="card post" data-post-id="<?= $postId ?>" data-post-click-target="1" data-post-url="<?= trux_e($postUrl) ?>">
        <div class="card__body">
          <div class="post__head">
            <?php
            $postAvatarPath = trim((string) ($p['avatar_path'] ?? ''));
            $postAvatarUrl = $postAvatarPath !== '' ? trux_public_url($postAvatarPath) : '';
            ?>
            <a class="post__avatar<?= $postAvatarUrl !== '' ? ' post__avatar--image' : '' ?>"
              href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string) $p['username']) ?>"
              aria-label="View @<?= trux_e((string) $p['username']) ?> profile">
              <?php if ($postAvatarUrl !== ''): ?>
                <img class="post__avatarImage" src="<?= trux_e($postAvatarUrl) ?>" alt="" loading="lazy" decoding="async">
              <?php endif; ?>
            </a>

            <div class="post__meta">
              <div class="post__nameRow">
                <a class="post__user"
                  href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string) $p['username']) ?>">@<?= trux_e((string) $p['username']) ?></a>
              </div>
              <div class="post__subRow">
                <span class="post__time" title="<?= trux_e(trux_format_exact_time((string) $p['created_at'])) ?>"
                  data-time-ago="1" data-time-source="<?= trux_e((string) $p['created_at']) ?>">
                  <?= trux_e(trux_time_ago((string) $p['created_at'])) ?>
                </span>
                <?php if ($editedAt !== ''): ?>
                  <span class="editedMeta" data-post-edited-for="<?= (int) $p['id'] ?>">
                    <span class="editedMeta__label">EDITED AT</span>
                    <span class="editedMeta__time" title="<?= trux_e(trux_format_exact_time($editedAt)) ?>" data-time-ago="1"
                      data-time-source="<?= trux_e($editedAt) ?>">
                      <?= trux_e(trux_time_ago($editedAt)) ?>
                    </span>
                  </span>
                <?php endif; ?>
                <span class="post__dot" aria-hidden="true">&bull;</span>
                <a class="post__id" href="<?= TRUX_BASE_URL ?>/post.php?id=<?= (int) $p['id'] ?>">#<?= (int) $p['id'] ?></a>
              </div>
            </div>

            <div class="post__actions">
              <?php
              $isOwner = $postIsOwner;
              $isLoggedIn = (bool) $me;
              $bookmarked = $postBookmarked;
              $postUsername = (string) $p['username'];
              require __DIR__ . '/_post_content_menu.php';
              ?>
            </div>
          </div>

          <div class="post__body"><?= trux_render_post_body((string) $p['body']) ?></div>

          <?php
          $postImagePath = trim((string) ($p['image_path'] ?? ''));
          $postImageUrl = $postImagePath !== '' ? trux_public_url($postImagePath) : '';
          ?>
          <?php if ($postImageUrl !== ''): ?>
            <div class="post__image">
              <img src="<?= trux_e($postImageUrl) ?>" alt="Post image" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>

          <?php
          $stats = $postStats;
          $isLoggedIn = (bool) $me;
          require __DIR__ . '/_post_actions_bar.php';
          ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($nextBefore || $nextDiscoveryPage): ?>
    <div class="pager" data-auto-pager="home-posts">
      <a
        class="btn"
        data-no-fx="1"
        href="<?php
        if ($feedMode === 'following') {
          echo TRUX_BASE_URL . '/?feed=following&before=' . (int) $nextBefore;
        } else {
          echo TRUX_BASE_URL . '/?page=' . (int) $nextDiscoveryPage;
        }
        ?>">
        Load more
      </a>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
