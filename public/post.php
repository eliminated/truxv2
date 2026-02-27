<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$id = trux_int_param('id', 0);
if ($id <= 0) {
    http_response_code(404);
    trux_flash_set('error', 'Post not found.');
    trux_redirect('/');
}

$post = trux_fetch_post_by_id($id);
if (!$post) {
    http_response_code(404);
    trux_flash_set('error', 'Post not found.');
    trux_redirect('/');
}

$me = trux_current_user();

require_once __DIR__ . '/_header.php';
?>

<article class="card post post--single">
  <div class="card__body">
    <div class="post__meta">
      <a class="post__user" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>">@<?= trux_e((string)$post['username']) ?></a>
      <span class="muted">·</span>
      <span class="muted">#<?= (int)$post['id'] ?></span>
      <span class="muted">·</span>
      <span class="muted" title="<?= trux_e(trux_format_exact_time((string)$post['created_at'])) ?>">
        <?= trux_e(trux_time_ago((string)$post['created_at'])) ?>
      </span>

      <?php if ($me && (int)$post['user_id'] === (int)$me['id']): ?>
        <span class="muted">·</span>
        <form class="inline" method="post" action="/delete_post.php" data-confirm="Delete this post?">
          <?= trux_csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
          <button class="linklike linklike--danger" type="submit">Delete</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="post__body"><?= nl2br(trux_e((string)$post['body'])) ?></div>

    <?php if (!empty($post['image_path'])): ?>
      <div class="post__image">
        <img src="<?= trux_e((string)$post['image_path']) ?>" alt="Post image">
      </div>
    <?php endif; ?>

    <div class="row row--spaced">
      <a class="btn btn--small" href="/">Back to feed</a>
      <a class="btn btn--small btn--ghost" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>">View profile</a>
    </div>
  </div>
</article>

<?php require_once __DIR__ . '/_footer.php'; ?>