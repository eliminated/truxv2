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

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Search</h1>
  <p class="muted">Find users or posts. Try “@username” or a keyword.</p>
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
              <span class="muted">· joined</span>
              <span class="muted" title="<?= trux_e(trux_format_exact_time((string)$u['created_at'])) ?>">
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
      <article class="card post">
        <div class="card__body">
          <div class="post__meta">
            <a class="post__user" href="/profile.php?u=<?= trux_e((string)$p['username']) ?>">@<?= trux_e((string)$p['username']) ?></a>
            <span class="muted">·</span>
            <a class="muted" href="/post.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a>
            <span class="muted">·</span>
            <span class="muted" title="<?= trux_e(trux_format_exact_time((string)$p['created_at'])) ?>">
              <?= trux_e(trux_time_ago((string)$p['created_at'])) ?>
            </span>

            <?php if ($me && (int)$p['user_id'] === (int)$me['id']): ?>
              <span class="muted">·</span>
              <form class="inline" method="post" action="/delete_post.php" data-confirm="Delete this post?">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="linklike linklike--danger" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </div>

          <div class="post__body"><?= nl2br(trux_e((string)$p['body'])) ?></div>

          <?php if (!empty($p['image_path'])): ?>
            <div class="post__image">
              <img src="<?= trux_e((string)$p['image_path']) ?>" alt="Post image">
            </div>
          <?php endif; ?>
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