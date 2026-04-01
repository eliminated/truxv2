<?php
declare(strict_types=1);

$quotedPostRecord = is_array($quotedPostRecord ?? null) ? $quotedPostRecord : [];
$quotedPreviewDeleted = !empty($quotedPreviewDeleted);
$quotedPreviewWrapperClass = trim((string)($quotedPreviewWrapperClass ?? ''));
$quotedPreviewWrapperAttr = trim('post__quotedWrap ' . $quotedPreviewWrapperClass . ($quotedPreviewDeleted ? ' post__quotedWrap--deleted' : ''));

if (!$quotedPostRecord && !$quotedPreviewDeleted) {
  return;
}
?>
<div class="<?= trux_e($quotedPreviewWrapperAttr) ?>">
  <?php if ($quotedPreviewDeleted): ?>
    <p class="muted">The original post was deleted.</p>
    </div>
    <?php return; ?>
  <?php endif; ?>

  <?php
  $quotedPostId = (int)($quotedPostRecord['id'] ?? 0);
  $quotedPostUsername = trim((string)($quotedPostRecord['username'] ?? ''));
  $quotedPostDisplayName = trim((string)($quotedPostRecord['display_name'] ?? ''));
  $quotedPostCreatedAt = trim((string)($quotedPostRecord['created_at'] ?? ''));
  $quotedPostBody = (string)($quotedPostRecord['body'] ?? '');
  $quotedPostImagePath = trim((string)($quotedPostRecord['image_path'] ?? ''));
  $quotedPostImageUrl = $quotedPostImagePath !== '' ? trux_public_url($quotedPostImagePath) : '';
  ?>
  <article class="quotedPost" data-post-id="<?= $quotedPostId ?>">
    <header class="quotedPost__head">
      <?php if ($quotedPostDisplayName !== ''): ?>
        <span class="quotedPost__displayName"><?= trux_e($quotedPostDisplayName) ?></span>
      <?php endif; ?>
      <?php if ($quotedPostUsername !== ''): ?>
        <a class="quotedPost__author" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e($quotedPostUsername) ?>">@<?= trux_e($quotedPostUsername) ?></a>
      <?php endif; ?>
      <?php if ($quotedPostCreatedAt !== ''): ?>
        <span class="quotedPost__time" data-time-ago="1" data-time-source="<?= trux_e($quotedPostCreatedAt) ?>" title="<?= trux_e(trux_format_exact_time($quotedPostCreatedAt)) ?>">
          <?= trux_e(trux_time_ago($quotedPostCreatedAt)) ?>
        </span>
      <?php endif; ?>
    </header>

    <div class="quotedPost__body"><?= trux_render_post_body($quotedPostBody) ?></div>

    <?php if ($quotedPostImageUrl !== ''): ?>
      <div class="quotedPost__media">
        <img src="<?= trux_e($quotedPostImageUrl) ?>" alt="Quoted post image" loading="lazy" decoding="async">
      </div>
    <?php endif; ?>
  </article>
</div>
