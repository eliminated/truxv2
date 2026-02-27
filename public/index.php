<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$before = trux_int_param('before', 0);
$limit = 20;

$posts = trux_fetch_feed($limit, $before > 0 ? $before : null);
$nextBefore = null;
if (count($posts) > 0) {
    $last = $posts[count($posts) - 1];
    $nextBefore = (int)$last['id'];
}

$me = trux_current_user();

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>TruX Feed</h1>
  <p class="muted">
    <?php if (trux_is_logged_in()): ?>
      Share something. Keep it real.
    <?php else: ?>
      Log in to post. You can still browse the feed.
    <?php endif; ?>
  </p>
</section>

<section class="feed">
  <?php if (!$posts): ?>
    <div class="card">
      <div class="card__body">No posts yet. Be the first to post.</div>
    </div>
  <?php endif; ?>

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
      <a class="btn" href="/?before=<?= (int)$nextBefore ?>">Load more</a>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>