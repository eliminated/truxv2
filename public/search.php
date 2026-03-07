<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$q = trim(trux_str_param('q', ''));
$before = trux_int_param('before', 0);

$term = $q;
if (str_starts_with($term, '@')) {
    $term = ltrim($term, '@');
}

$users = [];
$posts = [];
$nextBefore = null;

if ($term !== '' && mb_strlen($term) >= 2) {
    $users = trux_search_users($term, 10);
    $posts = trux_search_posts($term, 20, $before > 0 ? $before : null);

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
  <p class="muted">Find users or posts. Try "@username" or a keyword.</p>
</section>

<?php if ($term === '' || mb_strlen($term) < 2): ?>
  <div class="card">
    <div class="card__body">
      Enter at least 2 characters to search.
    </div>
  </div>
<?php else: ?>

  <section class="card">
    <div class="card__body">
      <h2 class="h2">Users</h2>
      <?php if (!$users): ?>
        <div class="muted">No matching users.</div>
      <?php else: ?>
        <ul class="list clean">
          <?php foreach ($users as $u): ?>
            <li>
              <a href="/profile.php?u=<?= trux_e((string)$u['username']) ?>">@<?= trux_e((string)$u['username']) ?></a>
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

  <section class="feed">
    <div class="card">
      <div class="card__body">
        <h2 class="h2">Posts</h2>
        <?php if (!$posts): ?>
          <div class="muted">No matching posts.</div>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ($posts as $p): ?>
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
                <span class="post__dot" aria-hidden="true">&bull;</span>
                <a class="post__id" href="/post.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a>
              </div>
            </div>

            <?php if ($me && (int)$p['user_id'] === (int)$me['id']): ?>
              <div class="post__actions">
                <form class="inline" method="post" action="/delete_post.php" data-confirm="Delete this post?">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="iconBtn iconBtn--danger" type="submit" aria-label="Delete post" title="Delete">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <path d="M4.75 7.5h14.5M9.5 7.5V5.75a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V7.5M7.5 7.5l.9 11.2a2 2 0 0 0 2 1.8h3.2a2 2 0 0 0 2-1.8l.9-11.2M10.25 11v6.25M13.75 11v6.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>

          <div class="post__body"><?= nl2br(trux_e((string)$p['body'])) ?></div>

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
        <a class="btn" href="/search.php?q=<?= urlencode($q) ?>&before=<?= (int)$nextBefore ?>">Load more</a>
      </div>
    <?php endif; ?>
  </section>

<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>

