<?php
declare(strict_types=1);

$postRecord = is_array($postRecord ?? null) ? $postRecord : [];
$postId = (int)($postRecord['id'] ?? 0);
if ($postId <= 0) {
  return;
}

$postViewer = is_array($postViewer ?? null) ? $postViewer : null;
$postInteractionStats = is_array($postInteractionStats ?? null) ? $postInteractionStats : [];
$postCardClasses = trim((string)($postCardClasses ?? ''));
$postContextTimeSource = trim((string)($postContextTimeSource ?? ''));
$postContextTimePrefix = trim((string)($postContextTimePrefix ?? ''));
$postViewerLinks = is_array($postViewerLinks ?? null) ? $postViewerLinks : [];

$postUrl = trux_post_viewer_url($postId);
$postStats = array_merge(
  ['likes' => 0, 'comments' => 0, 'shares' => 0, 'bookmarks' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false],
  $postInteractionStats
);
$postBookmarked = (bool)($postStats['bookmarked'] ?? false);
$postIsOwner = $postViewer && (int)($postRecord['user_id'] ?? 0) === (int)$postViewer['id'];
$postEditedAt = isset($postRecord['edited_at']) && $postRecord['edited_at'] !== null ? trim((string)$postRecord['edited_at']) : '';
$postUsername = trim((string)($postRecord['username'] ?? ''));
$postDisplayName = trim((string)($postRecord['display_name'] ?? ''));
$postAvatarPath = trim((string)($postRecord['avatar_path'] ?? ''));
$postAvatarUrl = $postAvatarPath !== '' ? trux_public_url($postAvatarPath) : '';
$postImagePath = trim((string)($postRecord['image_path'] ?? ''));
$postImageUrl = $postImagePath !== '' ? trux_public_url($postImagePath) : '';
$postCardClassAttr = trim('post streamItem ' . $postCardClasses);
?>
<article class="<?= trux_e($postCardClassAttr) ?>" data-post-id="<?= $postId ?>" data-post-click-target="1" data-post-url="<?= trux_e($postUrl) ?>">
  <div class="post__gutter">
    <a class="post__avatar<?= $postAvatarUrl !== '' ? ' post__avatar--image' : '' ?>" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e($postUsername) ?>" aria-label="View @<?= trux_e($postUsername) ?> profile">
      <?php if ($postAvatarUrl !== ''): ?>
        <img class="post__avatarImage" src="<?= trux_e($postAvatarUrl) ?>" alt="" loading="lazy" decoding="async">
      <?php else: ?>
        <span class="post__avatarFallback"><?= trux_e(strtoupper(substr($postUsername !== '' ? $postUsername : 'T', 0, 1))) ?></span>
      <?php endif; ?>
    </a>
    <span class="post__rail" aria-hidden="true"></span>
  </div>

  <div class="post__band">
    <header class="post__head">
      <div class="post__meta">
        <div class="post__nameRow">
          <?php if ($postDisplayName !== ''): ?>
            <a class="post__displayName" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e($postUsername) ?>"><?= trux_e($postDisplayName) ?></a>
          <?php endif; ?>
          <a class="post__user" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e($postUsername) ?>">@<?= trux_e($postUsername) ?></a>
        </div>

        <div class="post__subRow">
          <span class="post__time" title="<?= trux_e(trux_format_exact_time((string)$postRecord['created_at'])) ?>" data-time-ago="1" data-time-source="<?= trux_e((string)$postRecord['created_at']) ?>">
            <?= trux_e(trux_time_ago((string)$postRecord['created_at'])) ?>
          </span>

          <?php if ($postEditedAt !== ''): ?>
            <span class="editedMeta" data-post-edited-for="<?= $postId ?>">
              <span class="editedMeta__label">Edited</span>
              <span class="editedMeta__time" title="<?= trux_e(trux_format_exact_time($postEditedAt)) ?>" data-time-ago="1" data-time-source="<?= trux_e($postEditedAt) ?>">
                <?= trux_e(trux_time_ago($postEditedAt)) ?>
              </span>
            </span>
          <?php endif; ?>

          <span class="post__dot" aria-hidden="true">&bull;</span>
          <a class="post__id" href="<?= trux_e($postUrl) ?>">#<?= $postId ?></a>

          <?php if ($postContextTimeSource !== ''): ?>
            <span class="post__dot" aria-hidden="true">&bull;</span>
            <span class="post__contextMeta muted">
              <?php if ($postContextTimePrefix !== ''): ?>
                <?= trux_e($postContextTimePrefix) ?>
              <?php endif; ?>
              <span data-time-ago="1" data-time-source="<?= trux_e($postContextTimeSource) ?>" title="<?= trux_e(trux_format_exact_time($postContextTimeSource)) ?>">
                <?= trux_e(trux_time_ago($postContextTimeSource)) ?>
              </span>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="post__actions">
        <?php
        $isOwner = $postIsOwner;
        $isLoggedIn = (bool)$postViewer;
        $bookmarked = $postBookmarked;
        require __DIR__ . '/_post_content_menu.php';
        ?>
      </div>
    </header>

    <div class="post__body"><?= trux_render_post_body((string)$postRecord['body']) ?></div>

    <?php if ($postImageUrl !== ''): ?>
      <div class="post__media">
        <div class="post__image">
          <img src="<?= trux_e($postImageUrl) ?>" alt="Post image" loading="lazy" decoding="async">
        </div>
      </div>
    <?php endif; ?>

    <?php
    $stats = $postStats;
    $isLoggedIn = (bool)$postViewer;
    require __DIR__ . '/_post_actions_bar.php';
    ?>

    <?php if ($postViewerLinks): ?>
      <div class="post__viewerLinks">
        <?php foreach ($postViewerLinks as $postViewerLink): ?>
          <?php
          $viewerHref = trim((string)($postViewerLink['href'] ?? ''));
          $viewerLabel = trim((string)($postViewerLink['label'] ?? ''));
          if ($viewerHref === '' || $viewerLabel === '') {
            continue;
          }
          $viewerClass = trim((string)($postViewerLink['class'] ?? 'shellButton shellButton--ghost'));
          ?>
          <a class="<?= trux_e($viewerClass) ?>" href="<?= trux_e($viewerHref) ?>"><?= trux_e($viewerLabel) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</article>
