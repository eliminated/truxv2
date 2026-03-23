<?php
declare(strict_types=1);

$profileMenuUsername = isset($profileMenuUsername) ? trim((string)$profileMenuUsername) : '';
$profileMenuUserId = isset($profileMenuUserId) ? (int)$profileMenuUserId : 0;
$profileMenuReturnPath = isset($profileMenuReturnPath) ? (string)$profileMenuReturnPath : '/';
$profileMenuCanAssignRoles = !empty($profileMenuCanAssignRoles);
$profileMenuIsBlocked = !empty($profileMenuIsBlocked);
$profileMenuIsMuted = !empty($profileMenuIsMuted);
$profileMenuCanModerate = !empty($profileMenuCanModerate);
$profileMenuCanModerateWrite = !empty($profileMenuCanModerateWrite);
$profileMenuHasUserCase = !empty($profileMenuHasUserCase);
$profileMenuIsWatchlisted = !empty($profileMenuIsWatchlisted);
$profileMenuUserCasePath = isset($profileMenuUserCasePath) ? (string)$profileMenuUserCasePath : '/moderation/user_review.php?user_id=' . $profileMenuUserId;
$profileMenuStaffAccessPath = isset($profileMenuStaffAccessPath) ? (string)$profileMenuStaffAccessPath : '/moderation/staff.php?user_id=' . $profileMenuUserId;
?>
<div class="contentMenu contentMenu--profile" data-content-menu="1">
  <button
    class="contentMenu__trigger"
    type="button"
    aria-label="Open profile actions"
    data-content-menu-trigger="1">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M6.5 12a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Z" fill="currentColor" />
    </svg>
  </button>

  <div class="contentMenu__panel" role="menu" aria-label="Profile actions">
    <?php if (!$profileMenuIsBlocked): ?>
      <form class="contentMenu__form" method="post" action="<?= TRUX_BASE_URL ?>/mute_user.php">
        <?= trux_csrf_field() ?>
        <input type="hidden" name="action" value="<?= $profileMenuIsMuted ? 'unmute' : 'mute' ?>">
        <input type="hidden" name="user_id" value="<?= $profileMenuUserId ?>">
        <input type="hidden" name="user" value="<?= trux_e($profileMenuUsername) ?>">
        <button class="contentMenu__item<?= $profileMenuIsMuted ? ' is-active' : '' ?>" type="submit" role="menuitem">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 12a3.5 3.5 0 1 0 0-.01V12ZM5 19a7 7 0 0 1 14 0M4.5 4.5l15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span><?= $profileMenuIsMuted ? 'Unmute' : 'Mute' ?></span>
        </button>
      </form>

      <a class="contentMenu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/messages.php?with=<?= trux_e(rawurlencode($profileMenuUsername)) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M4.75 6.75h14.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H9.5l-4.75 3v-3H4.75a1.5 1.5 0 0 1-1.5-1.5v-7.5a1.5 1.5 0 0 1 1.5-1.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
        </svg>
        <span>Message</span>
      </a>
    <?php endif; ?>

    <form
      class="contentMenu__form"
      method="post"
      action="<?= TRUX_BASE_URL ?>/block_user.php"
      <?= !$profileMenuIsBlocked ? 'data-confirm="Block @' . trux_e($profileMenuUsername) . '? They won\'t be able to see your profile or message you."' : '' ?>>
      <?= trux_csrf_field() ?>
      <input type="hidden" name="action" value="<?= $profileMenuIsBlocked ? 'unblock' : 'block' ?>">
      <input type="hidden" name="user_id" value="<?= $profileMenuUserId ?>">
      <button class="contentMenu__item contentMenu__item--danger<?= $profileMenuIsBlocked ? ' is-active' : '' ?>" type="submit" role="menuitem">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M6.5 6.5 17.5 17.5M17.5 6.5 6.5 17.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>
        <span><?= $profileMenuIsBlocked ? 'Unblock' : 'Block' ?></span>
      </button>
    </form>

    <div class="contentMenu__divider" aria-hidden="true"></div>

    <button
      class="contentMenu__item"
      type="button"
      role="menuitem"
      data-report-action="1"
      data-report-target-type="user"
      data-report-target-id="<?= $profileMenuUserId ?>"
      data-report-open-url="<?= trux_e(TRUX_BASE_URL . '/profile.php?u=' . rawurlencode($profileMenuUsername)) ?>"
      data-report-target-label="<?= trux_e('User @' . $profileMenuUsername) ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M6 20V5m0 0h9l-1.5 3L15 11H6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Report profile</span>
    </button>

    <?php if ($profileMenuCanModerate): ?>
      <div class="contentMenu__divider" aria-hidden="true"></div>

      <a class="contentMenu__item" role="menuitem" href="<?= TRUX_BASE_URL . $profileMenuUserCasePath ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M7 5.75h10M7 10.75h10M7 15.75h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>
        <span><?= $profileMenuHasUserCase ? 'Open user case' : 'Start user case' ?></span>
      </a>

      <?php if ($profileMenuCanModerateWrite): ?>
        <form class="contentMenu__form" method="post" action="<?= TRUX_BASE_URL ?>/moderation/user_review.php">
          <?= trux_csrf_field() ?>
          <input type="hidden" name="action" value="toggle_watchlist_inline">
          <input type="hidden" name="user_id" value="<?= $profileMenuUserId ?>">
          <input type="hidden" name="watchlisted" value="<?= $profileMenuIsWatchlisted ? '0' : '1' ?>">
          <input type="hidden" name="redirect" value="<?= trux_e($profileMenuReturnPath) ?>">
          <button class="contentMenu__item<?= $profileMenuIsWatchlisted ? ' is-active' : '' ?>" type="submit" role="menuitem">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 5.75v12.5M5.75 12h12.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            <span><?= $profileMenuIsWatchlisted ? 'Remove from watchlist' : 'Add to watchlist' ?></span>
          </button>
        </form>
      <?php endif; ?>

      <a class="contentMenu__item" role="menuitem" href="<?= TRUX_BASE_URL . $profileMenuUserCasePath ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M7 5.75h10M7 10.75h10M7 15.75h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>
        <span>View account notes</span>
      </a>
    <?php endif; ?>

    <?php if ($profileMenuCanAssignRoles && $profileMenuUserId > 0): ?>
      <div class="contentMenu__divider" aria-hidden="true"></div>
      <a class="contentMenu__item" role="menuitem" href="<?= TRUX_BASE_URL . $profileMenuStaffAccessPath ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 4.75 18.5 7v4.5c0 4.1-2.63 7.76-6.5 9.25-3.87-1.49-6.5-5.15-6.5-9.25V7L12 4.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
          <path d="M9.5 12.25 11.2 14l3.55-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Manage staff access</span>
      </a>
    <?php endif; ?>
  </div>
</div>
