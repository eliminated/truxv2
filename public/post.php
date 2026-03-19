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

<?php $editedAt = isset($post['edited_at']) && $post['edited_at'] !== null ? (string)$post['edited_at'] : ''; ?>
<article class="card post post--single" data-post-id="<?= (int)$post['id'] ?>">
  <div class="card__body">
    <div class="post__head">
      <?php
      $postAvatarPath = trim((string)($post['avatar_path'] ?? ''));
      $postAvatarUrl = $postAvatarPath !== '' ? trux_public_url($postAvatarPath) : '';
      ?>
      <a class="post__avatar<?= $postAvatarUrl !== '' ? ' post__avatar--image' : '' ?>" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$post['username']) ?>" aria-label="View @<?= trux_e((string)$post['username']) ?> profile">
        <?php if ($postAvatarUrl !== ''): ?>
          <img class="post__avatarImage" src="<?= trux_e($postAvatarUrl) ?>" alt="" loading="lazy" decoding="async">
        <?php endif; ?>
      </a>

      <div class="post__meta">
        <div class="post__nameRow">
          <a class="post__user" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$post['username']) ?>">@<?= trux_e((string)$post['username']) ?></a>
        </div>
        <div class="post__subRow">
          <span
            class="post__time"
            title="<?= trux_e(trux_format_exact_time((string)$post['created_at'])) ?>"
            data-time-ago="1"
            data-time-source="<?= trux_e((string)$post['created_at']) ?>">
            <?= trux_e(trux_time_ago((string)$post['created_at'])) ?>
          </span>
          <?php if ($editedAt !== ''): ?>
            <span class="editedMeta" data-post-edited-for="<?= (int)$post['id'] ?>">
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
          <span class="post__id">#<?= (int)$post['id'] ?></span>
        </div>
      </div>

      <?php if ($me && (int)$post['user_id'] === (int)$me['id']): ?>
        <div class="post__actions">
          <?php
          $entityType = 'post';
          $entityId = (int)$post['id'];
          require __DIR__ . '/_owner_actions_menu.php';
          ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="post__body"><?= trux_render_post_body((string)$post['body']) ?></div>

    <?php
    $postImagePath = trim((string)($post['image_path'] ?? ''));
    $postImageUrl = $postImagePath !== '' ? trux_public_url($postImagePath) : '';
    ?>
    <?php if ($postImageUrl !== ''): ?>
      <div class="post__image">
        <img src="<?= trux_e($postImageUrl) ?>" alt="Post image">
      </div>
    <?php endif; ?>

    <?php
    $postId = (int)$post['id'];
    $stats = $postStats;
    $isLoggedIn = (bool)$me;
    require __DIR__ . '/_post_actions_bar.php';
    ?>

    <div class="row row--spaced">
      <a class="btn btn--small" href="<?= TRUX_BASE_URL ?>/">Back to feed</a>
      <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$post['username']) ?>">View profile</a>
    </div>
  </div>
</article>

<?php require_once __DIR__ . '/_footer.php'; ?>
