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
$interactionMap = trux_fetch_post_interactions(
    [(int)$post['id']],
    $me ? (int)$me['id'] : null
);
$postStats = $interactionMap[(int)$post['id']] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false];

require_once __DIR__ . '/_header.php';
?>

<article class="card post post--single" data-post-id="<?= (int)$post['id'] ?>">
  <div class="card__body">
    <div class="post__head">
      <a class="post__avatar" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>" aria-label="View @<?= trux_e((string)$post['username']) ?> profile"></a>

      <div class="post__meta">
        <div class="post__nameRow">
          <a class="post__user" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>">@<?= trux_e((string)$post['username']) ?></a>
        </div>
        <div class="post__subRow">
          <span
            class="post__time"
            title="<?= trux_e(trux_format_exact_time((string)$post['created_at'])) ?>"
            data-time-ago="1"
            data-time-source="<?= trux_e((string)$post['created_at']) ?>">
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

    <?php
    $postId = (int)$post['id'];
    $stats = $postStats;
    $isLoggedIn = (bool)$me;
    require __DIR__ . '/_post_actions_bar.php';
    ?>

    <div class="row row--spaced">
      <a class="btn btn--small" href="/">Back to feed</a>
      <a class="btn btn--small btn--ghost" href="/profile.php?u=<?= trux_e((string)$post['username']) ?>">View profile</a>
    </div>
  </div>
</article>

<?php require_once __DIR__ . '/_footer.php'; ?>
