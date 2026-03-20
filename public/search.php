<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$q = trim(trux_str_param('q', ''));
$before = trux_int_param('before', 0);
$rawFilter = $_GET['filter'] ?? null;
$requestedFilter = is_string($rawFilter) ? trim($rawFilter) : '';
$searchFilter = $requestedFilter === 'hashtags' ? 'hashtags' : 'all';

$term = $q;
if (str_starts_with($term, '@')) {
    $term = ltrim($term, '@');
}

if ($requestedFilter === '' && str_starts_with($q, '#')) {
    $searchFilter = 'hashtags';
}

$users = [];
$posts = [];
$nextBefore = null;
$hashtag = $searchFilter === 'hashtags' ? trux_normalize_hashtag($term) : '';
$hasValidSearch = $searchFilter === 'hashtags'
    ? $hashtag !== ''
    : $term !== '' && mb_strlen($term) >= 2;

if ($hasValidSearch) {
    if ($searchFilter === 'hashtags') {
        $posts = trux_search_posts_by_hashtag($hashtag, 20, $before > 0 ? $before : null);
    } else {
        $users = trux_search_users($term, 10);
        $posts = trux_search_posts($term, 20, $before > 0 ? $before : null);
    }

    if (count($posts) > 0) {
        $last = $posts[count($posts) - 1];
        $nextBefore = (int)$last['id'];
    }
}

$me = trux_current_user();
$interactionMap = trux_fetch_post_interactions(
    trux_collect_post_ids($posts),
    $me ? (int)$me['id'] : null
);

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Search</h1>
  <p class="muted">
    <?php if ($searchFilter === 'hashtags'): ?>
      Filter posts by hashtag. Try <code>#php</code> or <code>#updates</code>.
    <?php else: ?>
      Find users or posts. Try "@username", a keyword, or switch to hashtag-only search.
    <?php endif; ?>
  </p>

  <div class="feedSwitch" aria-label="Search filter">
    <a
      class="feedSwitch__item<?= $searchFilter === 'all' ? ' is-active' : '' ?>"
      href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode($q) ?>&filter=all"
      <?= $searchFilter === 'all' ? 'aria-current="page"' : '' ?>>
      All
    </a>
    <a
      class="feedSwitch__item<?= $searchFilter === 'hashtags' ? ' is-active' : '' ?>"
      href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode($q) ?>&filter=hashtags"
      <?= $searchFilter === 'hashtags' ? 'aria-current="page"' : '' ?>>
      Hashtags
    </a>
  </div>
</section>

<?php if (!$hasValidSearch): ?>
  <div class="card">
    <div class="card__body">
      <?php if ($searchFilter === 'hashtags'): ?>
        Enter a hashtag using letters, numbers, or underscore. Examples: <code>#php</code>, <code>#release_notes</code>.
      <?php else: ?>
        Enter at least 2 characters to search.
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>

  <?php if ($searchFilter !== 'hashtags'): ?>
    <section class="card">
      <div class="card__body">
        <h2 class="h2">Users</h2>
        <?php if (!$users): ?>
          <div class="muted">No matching users.</div>
        <?php else: ?>
          <ul class="list clean">
            <?php foreach ($users as $u): ?>
              <li>
                <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$u['username']) ?>">@<?= trux_e((string)$u['username']) ?></a>
                <span class="muted">&bull; joined</span>
                <span
                  class="muted"
                  title="<?= trux_e(trux_format_exact_time((string)$u['created_at'])) ?>"
                  data-time-ago="1"
                  data-time-source="<?= trux_e((string)$u['created_at']) ?>">
                  <?= trux_e(trux_time_ago((string)$u['created_at'])) ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="feed">
    <div class="card">
      <div class="card__body">
        <h2 class="h2">
          <?php if ($searchFilter === 'hashtags'): ?>
            Posts tagged #<?= trux_e($hashtag) ?>
          <?php else: ?>
            Posts
          <?php endif; ?>
        </h2>
        <?php if (!$posts): ?>
          <div class="muted">
            <?php if ($searchFilter === 'hashtags'): ?>
              No posts found for #<?= trux_e($hashtag) ?>.
            <?php else: ?>
              No matching posts.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div data-auto-pager-list="search-posts">
      <?php foreach ($posts as $p): ?>
        <?php
        $postId = (int)$p['id'];
        $postUrl = trux_post_viewer_url($postId);
        $postStats = $interactionMap[$postId] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false];
        $postBookmarked = (bool)($postStats['bookmarked'] ?? false);
        $postIsOwner = $me && (int)$p['user_id'] === (int)$me['id'];
        $editedAt = isset($p['edited_at']) && $p['edited_at'] !== null ? (string)$p['edited_at'] : '';
        ?>
        <article class="card post" data-post-id="<?= $postId ?>" data-post-click-target="1" data-post-url="<?= trux_e($postUrl) ?>">
          <div class="card__body">
            <div class="post__head">
              <?php
              $postAvatarPath = trim((string)($p['avatar_path'] ?? ''));
              $postAvatarUrl = $postAvatarPath !== '' ? trux_public_url($postAvatarPath) : '';
              ?>
              <a class="post__avatar<?= $postAvatarUrl !== '' ? ' post__avatar--image' : '' ?>" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$p['username']) ?>" aria-label="View @<?= trux_e((string)$p['username']) ?> profile">
                <?php if ($postAvatarUrl !== ''): ?>
                  <img class="post__avatarImage" src="<?= trux_e($postAvatarUrl) ?>" alt="" loading="lazy" decoding="async">
                <?php endif; ?>
              </a>

              <div class="post__meta">
                <div class="post__nameRow">
                  <a class="post__user" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$p['username']) ?>">@<?= trux_e((string)$p['username']) ?></a>
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
                  <a class="post__id" href="<?= trux_e(trux_post_viewer_url((int)$p['id'])) ?>">#<?= (int)$p['id'] ?></a>
                </div>
              </div>

              <div class="post__actions">
                <?php
                $isOwner = $postIsOwner;
                $isLoggedIn = (bool)$me;
                $bookmarked = $postBookmarked;
                $postUsername = (string)$p['username'];
                require __DIR__ . '/_post_content_menu.php';
                ?>
              </div>
            </div>

            <div class="post__body"><?= trux_render_post_body((string)$p['body']) ?></div>

            <?php
            $postImagePath = trim((string)($p['image_path'] ?? ''));
            $postImageUrl = $postImagePath !== '' ? trux_public_url($postImagePath) : '';
            ?>
            <?php if ($postImageUrl !== ''): ?>
              <div class="post__image">
                <img src="<?= trux_e($postImageUrl) ?>" alt="Post image" loading="lazy" decoding="async">
              </div>
            <?php endif; ?>

            <?php
            $stats = $postStats;
            $isLoggedIn = (bool)$me;
            require __DIR__ . '/_post_actions_bar.php';
            ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($nextBefore): ?>
      <div class="pager" data-auto-pager="search-posts">
        <a
          class="btn"
          data-no-fx="1"
          href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode($q) ?>&filter=<?= urlencode($searchFilter) ?>&before=<?= (int)$nextBefore ?>">
          Load more
        </a>
      </div>
    <?php endif; ?>
  </section>

<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
