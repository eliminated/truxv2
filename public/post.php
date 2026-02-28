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
    <div class="post__head">
      <a class="post__avatar" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>" aria-label="View @<?= trux_e((string)$post['username']) ?> profile"></a>

      <div class="post__meta">
        <div class="post__nameRow">
          <a class="post__user" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>">@<?= trux_e((string)$post['username']) ?></a>
        </div>
        <div class="post__subRow">
          <span class="post__time" title="<?= trux_e(trux_format_exact_time((string)$post['created_at'])) ?>">
            <?= trux_e(trux_time_ago((string)$post['created_at'])) ?>
          </span>
          <span class="post__dot" aria-hidden="true">&bull;</span>
          <span class="post__id">#<?= (int)$post['id'] ?></span>
        </div>
      </div>

      <?php if ($me && (int)$post['user_id'] === (int)$me['id']): ?>
        <div class="post__actions">
          <form class="inline" method="post" action="/delete_post.php" data-confirm="Delete this post?">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
            <button class="iconBtn iconBtn--danger" type="submit" aria-label="Delete post" title="Delete">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M4.75 7.5h14.5M9.5 7.5V5.75a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V7.5M7.5 7.5l.9 11.2a2 2 0 0 0 2 1.8h3.2a2 2 0 0 0 2-1.8l.9-11.2M10.25 11v6.25M13.75 11v6.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <div class="post__body"><?= nl2br(trux_e((string)$post['body'])) ?></div>

    <?php if (!empty($post['image_path'])): ?>
      <div class="post__image">
        <img src="<?= trux_e((string)$post['image_path']) ?>" alt="Post image">
      </div>
    <?php endif; ?>

    <div class="post__actionsBar" aria-label="Post actions">
      <button class="postAct" type="button" aria-label="Like (coming soon)">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 20.2s-6.8-4.4-8.9-8.2c-1.7-3 .2-7 3.8-7 2 0 3.1 1.1 4.1 2.5 1-1.4 2.1-2.5 4.1-2.5 3.6 0 5.5 4 3.8 7-2.1 3.8-8.9 8.2-8.9 8.2Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Like</span>
      </button>
      <button class="postAct" type="button" aria-label="Comment (coming soon)">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M20 14.6c0 2-1.8 3.6-4 3.6H9l-4 3V6.8c0-2 1.8-3.6 4-3.6h7c2.2 0 4 1.6 4 3.6v7.8Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Comment</span>
      </button>
      <button class="postAct" type="button" aria-label="Share (coming soon)">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M14 5h5v5M10 14 19 5M19 13v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Share</span>
      </button>
    </div>

    <div class="row row--spaced">
      <a class="btn btn--small" href="/">Back to feed</a>
      <a class="btn btn--small btn--ghost" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>">View profile</a>
    </div>
  </div>
</article>

<?php require_once __DIR__ . '/_footer.php'; ?>
