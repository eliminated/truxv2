<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$before = trux_int_param('before', 0);
$limit = 20;
$requestedFeed = trux_str_param('feed', 'all');
$feedMode = $requestedFeed === 'following' ? 'following' : 'all';

$me = trux_current_user();
if (!$me && $feedMode === 'following') {
    $feedMode = 'all';
}

$followingCount = 0;
if ($feedMode === 'following' && $me) {
    $followCounts = trux_follow_counts((int)$me['id']);
    $followingCount = (int)($followCounts['following'] ?? 0);
}

$posts = $feedMode === 'following' && $me
    ? trux_fetch_following_feed((int)$me['id'], $limit, $before > 0 ? $before : null)
    : trux_fetch_feed($limit, $before > 0 ? $before : null);
$nextBefore = null;
if (count($posts) > 0) {
    $last = $posts[count($posts) - 1];
    $nextBefore = (int)$last['id'];
}

$interactionMap = trux_fetch_post_interactions(
    trux_collect_post_ids($posts),
    $me ? (int)$me['id'] : null
);

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>TruX Feed</h1>
  <p class="muted">
    <?php if ($feedMode === 'following' && $me): ?>
      Latest posts from people you follow, plus your own posts.
    <?php elseif (trux_is_logged_in()): ?>
      Share something. Keep it real.
    <?php else: ?>
      Log in to post. You can still browse the feed.
    <?php endif; ?>
  </p>

  <div class="feedSwitch" aria-label="Feed mode">
    <a
      class="feedSwitch__item<?= $feedMode === 'all' ? ' is-active' : '' ?>"
      href="/"
      <?= $feedMode === 'all' ? 'aria-current="page"' : '' ?>>
      For You
    </a>

    <?php if ($me): ?>
      <a
        class="feedSwitch__item<?= $feedMode === 'following' ? ' is-active' : '' ?>"
        href="/?feed=following"
        <?= $feedMode === 'following' ? 'aria-current="page"' : '' ?>>
        Following
      </a>
    <?php else: ?>
      <a class="feedSwitch__item is-disabled" href="/login.php">
        Following
      </a>
    <?php endif; ?>
  </div>
</section>

<section class="feed">
  <?php if (!$posts): ?>
    <div class="card">
      <div class="card__body">
        <?php if ($feedMode === 'following' && $me && $followingCount === 0): ?>
          You are not following anyone yet. Find people to follow from search or browse the global feed first.
        <?php elseif ($feedMode === 'following' && $me): ?>
          No posts from people you follow yet. Check back later or switch to the global feed.
        <?php else: ?>
          No posts yet. Be the first to post.
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($posts as $p): ?>
    <?php $editedAt = isset($p['edited_at']) && $p['edited_at'] !== null ? (string)$p['edited_at'] : ''; ?>
    <article class="card post" data-post-id="<?= (int)$p['id'] ?>">
      <div class="card__body">
        <div class="post__head">
          <a class="post__avatar" href="/profile.php?u=<?= trux_e((string)$p['username']) ?>" aria-label="View @<?= trux_e((string)$p['username']) ?> profile"></a>

          <div class="post__meta">
            <div class="post__nameRow">
              <a class="post__user" href="/profile.php?u=<?= trux_e((string)$p['username']) ?>">@<?= trux_e((string)$p['username']) ?></a>
            </div>
            <div class="post__subRow">
              <span
                class="post__time"
                title="<?= trux_e(trux_format_exact_time((string)$p['created_at'])) ?>"
                data-time-ago="1"
                data-time-source="<?= trux_e((string)$p['created_at']) ?>">
                <?= trux_e(trux_time_ago((string)$p['created_at'])) ?>
              </span>
              <?php if ($editedAt !== ''): ?>
                <span class="editedMeta" data-post-edited-for="<?= (int)$p['id'] ?>">
                  <span class="editedMeta__label">EDITED AT</span>
                  <span
                    class="editedMeta__time"
                    title="<?= trux_e(trux_format_exact_time($editedAt)) ?>"
                    data-time-ago="1"
                    data-time-source="<?= trux_e($editedAt) ?>">
                    <?= trux_e(trux_time_ago($editedAt)) ?>
                  </span>
                </span>
              <?php endif; ?>
              <span class="post__dot" aria-hidden="true">&bull;</span>
              <a class="post__id" href="/post.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a>
            </div>
          </div>

          <?php if ($me && (int)$p['user_id'] === (int)$me['id']): ?>
            <div class="post__actions">
              <?php
              $entityType = 'post';
              $entityId = (int)$p['id'];
              require __DIR__ . '/_owner_actions_menu.php';
              ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="post__body"><?= trux_render_post_body((string)$p['body']) ?></div>

        <?php if (!empty($p['image_path'])): ?>
          <div class="post__image">
            <img src="<?= trux_e((string)$p['image_path']) ?>" alt="Post image" loading="lazy" decoding="async">
          </div>
        <?php endif; ?>

        <?php
        $postId = (int)$p['id'];
        $stats = $interactionMap[$postId] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false];
        $isLoggedIn = (bool)$me;
        require __DIR__ . '/_post_actions_bar.php';
        ?>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if ($nextBefore): ?>
    <div class="pager">
      <a
        class="btn"
        href="<?= $feedMode === 'following'
          ? '/?feed=following&before=' . (int)$nextBefore
          : '/?before=' . (int)$nextBefore ?>">
        Load more
      </a>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
