<?php
declare(strict_types=1);

$postId = isset($postId) ? (int)$postId : 0;
$postUrl = isset($postUrl) ? (string)$postUrl : trux_post_viewer_url($postId);
$postUsername = isset($postUsername) ? trim((string)$postUsername) : '';
$isOwner = !empty($isOwner);
$isLoggedIn = !empty($isLoggedIn);
$bookmarked = !empty($bookmarked);
$postIsPinned = !empty($postIsPinned ?? false);
$muteLabel = $postUsername !== '' ? 'Mute @' . $postUsername : 'Mute user';
$muteMessage = $postUsername !== '' ? 'Mute controls for @' . $postUsername . ' are coming soon.' : 'Mute controls are coming soon.';
?>
<div class="contentMenu contentMenu--post" data-content-menu="1">
  <button
    class="contentMenu__trigger"
    type="button"
    aria-label="Open post actions"
    data-content-menu-trigger="1">
    <svg class="contentMenu__triggerGlyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <circle class="contentMenu__triggerDot" cx="6.5" cy="12" r="1.5" />
      <circle class="contentMenu__triggerDot" cx="12" cy="12" r="1.5" />
      <circle class="contentMenu__triggerDot" cx="17.5" cy="12" r="1.5" />
    </svg>
  </button>

  <div class="contentMenu__panel" role="menu" aria-label="Post actions">
    <div class="contentMenu__summary">
      <strong>Post controls</strong>
      <small><?= $postUsername !== '' ? '@' . trux_e($postUsername) : 'Transmission item' ?></small>
    </div>
    <?php if ($isOwner): ?>
      <button
        class="contentMenu__item"
        type="button"
        role="menuitem"
        data-owner-edit="1"
        data-owner-type="post"
        data-owner-id="<?= $postId ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M4 20h4.2l9.8-9.8-4.2-4.2L4 15.8V20Zm11.1-13.9 4.2 4.2 1.4-1.4a1.5 1.5 0 0 0 0-2.1l-2.1-2.1a1.5 1.5 0 0 0-2.1 0l-1.4 1.4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
        </svg>
        <span>Edit</span>
      </button>
    <?php endif; ?>

    <?php if ($isLoggedIn): ?>
      <button
        class="contentMenu__item<?= $bookmarked ? ' is-active' : '' ?>"
        type="button"
        role="menuitem"
        data-owner-bookmark="1"
        data-owner-type="post"
        data-owner-id="<?= $postId ?>"
        aria-label="<?= $bookmarked ? 'Remove bookmark' : 'Bookmark' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="m7 5 5-2 5 2v14l-5-2-5 2V5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
        </svg>
        <span data-owner-bookmark-label="1"><?= $bookmarked ? 'Saved' : 'Bookmark' ?></span>
      </button>
    <?php else: ?>
      <a
        class="contentMenu__item"
        role="menuitem"
        href="<?= TRUX_BASE_URL ?>/login.php"
        aria-label="Log in to bookmark this post">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="m7 5 5-2 5 2v14l-5-2-5 2V5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
        </svg>
        <span>Bookmark</span>
      </a>
    <?php endif; ?>

    <a class="contentMenu__item" role="menuitem" href="<?= trux_e($postUrl) ?>" data-no-fx="1" data-post-open-viewer-link="1">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M14 5h5v5M10 14 19 5M19 13v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Open viewer page</span>
    </a>

    <button
      class="contentMenu__item"
      type="button"
      role="menuitem"
      data-post-copy-link="1"
      data-post-url="<?= trux_e($postUrl) ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M10.75 13.25a3 3 0 0 1 0-4.24l2.12-2.12a3 3 0 1 1 4.24 4.24l-.88.88M13.25 10.75a3 3 0 0 1 0 4.24l-2.12 2.12a3 3 0 0 1-4.24-4.24l.88-.88" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Copy link</span>
    </button>

    <?php if (!$isOwner): ?>
      <button
        class="contentMenu__item"
        type="button"
        role="menuitem"
        data-post-placeholder-action="1"
        data-post-placeholder-label="Not interested"
        data-post-placeholder-message="Personalized feed controls are coming soon.">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M4.75 12h14.5M7.75 7.75l8.5 8.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Not interested</span>
      </button>

      <button
        class="contentMenu__item"
        type="button"
        role="menuitem"
        data-post-placeholder-action="1"
        data-post-placeholder-label="<?= trux_e($muteLabel) ?>"
        data-post-placeholder-message="<?= trux_e($muteMessage) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 12a3.5 3.5 0 1 0 0-.01V12ZM5 19a7 7 0 0 1 14 0M4.5 4.5l15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span><?= trux_e($muteLabel) ?></span>
      </button>

      <?php if ($isLoggedIn): ?>
        <button
          class="contentMenu__item contentMenu__item--danger"
          type="button"
          role="menuitem"
          data-report-action="1"
          data-report-target-type="post"
          data-report-target-id="<?= $postId ?>"
          data-report-open-url="<?= trux_e($postUrl) ?>"
          data-report-target-label="<?= trux_e($postUsername !== '' ? 'Post #' . $postId . ' by @' . $postUsername : 'Post #' . $postId) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6 20V5m0 0h9l-1.5 3L15 11H6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Report</span>
        </button>
      <?php else: ?>
        <a
          class="contentMenu__item contentMenu__item--danger"
          role="menuitem"
          href="<?= TRUX_BASE_URL ?>/login.php"
          aria-label="Log in to report this post">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6 20V5m0 0h9l-1.5 3L15 11H6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Report</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($isOwner): ?>
      <button
        class="contentMenu__item<?= $postIsPinned ? ' is-active' : '' ?>"
        type="button"
        role="menuitem"
        data-pin-post="1"
        data-post-id="<?= $postId ?>"
        aria-label="<?= $postIsPinned ? 'Unpin from profile' : 'Pin to profile' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 2.5 9.5 8H5l3.5 5.5-1 6L12 17l4.5 2.5-1-6L19 8h-4.5L12 2.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
        </svg>
        <span><?= $postIsPinned ? 'Unpin' : 'Pin to profile' ?></span>
      </button>
    <?php endif; ?>

    <?php if ($isOwner): ?>
      <button
        class="contentMenu__item contentMenu__item--danger"
        type="button"
        role="menuitem"
        data-owner-delete="1"
        data-owner-type="post"
        data-owner-id="<?= $postId ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M4.75 7.5h14.5M9.5 7.5V5.75a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V7.5M7.5 7.5l.9 11.2a2 2 0 0 0 2 1.8h3.2a2 2 0 0 0 2-1.8l.9-11.2M10.25 11v6.25M13.75 11v6.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Delete</span>
      </button>
    <?php endif; ?>
  </div>
</div>
