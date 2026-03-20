<?php

declare(strict_types=1);

$user = trux_current_user();
$error = trux_flash_get('error');
$success = trux_flash_get('success');

$q = trux_str_param('q', '');
$bodyClasses = ['appearance--classic', 'motion--reduced'];
$unreadNotificationCount = $user ? trux_count_unread_notifications((int)$user['id']) : 0;
$unreadMessageCount = $user ? trux_count_unread_direct_messages((int)$user['id']) : 0;
$notificationMenuItems = $user ? trux_fetch_notifications((int)$user['id'], 60) : [];
$notificationBadgeLabel = $unreadNotificationCount > 99 ? '99+' : (string)$unreadNotificationCount;
$notificationRedirectPath = '/notifications.php';
$showProfileMenuEditProfile = false;
$showProfileMenuPremium = false; // Placeholder stays available in code until Premium is ready.
$basePath = (string)(parse_url(TRUX_BASE_URL, PHP_URL_PATH) ?? '');
$rawRequestUri = $_SERVER['REQUEST_URI'] ?? '';
if (is_string($rawRequestUri) && $rawRequestUri !== '') {
  $candidateRedirect = trim($rawRequestUri);
  if ($basePath !== '' && str_starts_with($candidateRedirect, $basePath)) {
    $candidateRedirect = substr($candidateRedirect, strlen($basePath));
  }
  if ($candidateRedirect === '' || !str_starts_with($candidateRedirect, '/')) {
    $candidateRedirect = '/' . ltrim($candidateRedirect, '/');
  }
  if (!str_starts_with($candidateRedirect, '//') && !preg_match('/[\r\n]/', $candidateRedirect)) {
    $notificationRedirectPath = $candidateRedirect;
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= trux_e(TRUX_APP_NAME) ?></title>
  <?php
  $faviconVersion = max(
    (int)(filemtime(__DIR__ . '/favicon.php') ?: 0),
    (int)(filemtime(dirname(__DIR__) . '/src/logo/trux_logo.png') ?: 0)
  );
  ?>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>">
  <link rel="stylesheet" href="<?= TRUX_BASE_URL ?>/assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
  <link rel="stylesheet" href="<?= TRUX_BASE_URL ?>/assets/mobile.css?v=<?= filemtime(__DIR__ . '/assets/mobile.css') ?>">
  <script defer src="<?= TRUX_BASE_URL ?>/assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
  <script>window.TRUX_BASE_URL = "<?= TRUX_BASE_URL ?>";</script>
</head>

<body class="<?= trux_e(implode(' ', $bodyClasses)) ?>">
  <header class="topbar">
    <div class="container topbar__inner">
      <div class="brand" aria-label="<?= trux_e(TRUX_APP_NAME) ?>">
        <div class="brand__core">
          <span class="brand__logoWrap" aria-hidden="true">
            <img
              class="brand__logo"
              src="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>"
              alt=""
              width="32"
              height="32"
              loading="eager"
              decoding="async">
          </span>
          <span class="brand__label"><?= trux_e(TRUX_APP_NAME) ?></span>
        </div>

        <a class="brand__home" href="<?= TRUX_BASE_URL ?>/" aria-label="Go to home" title="Home">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4 10.8 12 4l8 6.8M7 9.9V20h10V9.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </a>
      </div>

      <div class="searchWrap">
        <form class="search" method="get" action="<?= TRUX_BASE_URL ?>/search.php" role="search">
          <input class="search__input" name="q" value="<?= trux_e($q) ?>" placeholder="Search users or posts..." maxlength="80">
          <button class="search__btn" type="submit" aria-label="Search">
            <span class="search__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false">
                <path fill="currentColor" d="M10.5 4a6.5 6.5 0 1 0 4.02 11.61l4.43 4.43a1 1 0 0 0 1.41-1.41l-4.43-4.43A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9a4.5 4.5 0 0 1 0-9Z" />
              </svg>
            </span>
            <span class="search__label" aria-hidden="true">Search</span>
            <span class="sweep" aria-hidden="true"></span>
          </button>
        </form>

        <?php if ($user): ?>
          <div class="nav__menu nav__menu--plus">
            <button class="nav__icon nav__icon--plus" type="button" aria-label="Create" title="Create">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M19 11H13V5a1 1 0 0 0-2 0v6H5a1 1 0 0 0 0 2h6v6a1 1 0 0 0 2 0v-6h6a1 1 0 0 0 0-2Z" />
              </svg>
            </button>

            <div class="menu__panel" role="menu" aria-label="Create menu">
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/new_post.php">
                New Post
                <span class="muted">Write</span>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <nav class="nav">
        <?php if ($user): ?>
          <div class="nav__menu nav__menu--notifications">
            <button class="navNotify__btn" type="button" aria-label="Notifications" title="Notifications">
              <span class="navNotify__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                  <path fill="currentColor" d="M12 3a5 5 0 0 0-5 5v1.2c0 .9-.28 1.78-.81 2.5l-1.1 1.54A2 2 0 0 0 6.72 17h10.56a2 2 0 0 0 1.63-3.16l-1.1-1.54A4.3 4.3 0 0 1 17 9.2V8a5 5 0 0 0-5-5Zm0 18a2.75 2.75 0 0 0 2.58-1.8.75.75 0 0 0-.7-1.02h-3.76a.75.75 0 0 0-.7 1.02A2.75 2.75 0 0 0 12 21Z" />
                </svg>
              </span>
              <span class="navNotify__label" aria-hidden="true">Notification</span>
              <?php if ($unreadNotificationCount > 0): ?>
                <span class="navNotify__badge"><?= trux_e($notificationBadgeLabel) ?></span>
              <?php endif; ?>
              <span class="sweep" aria-hidden="true"></span>
            </button>

            <div class="menu__panel menu__panel--notifications" aria-label="Notifications">
              <div class="notificationMenu__head">
                <div class="notificationMenu__titleWrap">
                  <div class="notificationMenu__title">Notifications</div>
                  <div class="notificationMenu__subtitle muted">
                    <?= $unreadNotificationCount > 0 ? trux_e($notificationBadgeLabel . ' unread') : 'All caught up' ?>
                  </div>
                </div>
                <div class="notificationMenu__actions">
                  <?php if ($unreadNotificationCount > 0): ?>
                    <form method="post" action="<?= TRUX_BASE_URL ?>/notifications.php" class="notificationMenu__form">
                      <?= trux_csrf_field() ?>
                      <input type="hidden" name="action" value="mark_all_read">
                      <input type="hidden" name="redirect" value="<?= trux_e($notificationRedirectPath) ?>">
                      <button class="notificationMenu__action" type="submit">Mark all as read</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($notificationMenuItems): ?>
                    <form method="post" action="<?= TRUX_BASE_URL ?>/notifications.php" class="notificationMenu__form" data-confirm="Clear every notification from your feed?">
                      <?= trux_csrf_field() ?>
                      <input type="hidden" name="action" value="clean_all">
                      <input type="hidden" name="redirect" value="<?= trux_e($notificationRedirectPath) ?>">
                      <button class="notificationMenu__action notificationMenu__action--danger" type="submit">Clean all</button>
                    </form>
                  <?php endif; ?>
                  <a class="notificationMenu__link" href="<?= TRUX_BASE_URL ?>/notifications.php">Open page</a>
                </div>
              </div>

              <?php if (!$notificationMenuItems): ?>
                <div class="notificationMenu__empty muted">No notifications yet.</div>
              <?php else: ?>
                <div class="notificationList notificationList--menu">
                  <?php foreach ($notificationMenuItems as $notification): ?>
                    <a class="notificationItem<?= empty($notification['read_at']) ? ' is-unread' : '' ?>" href="<?= trux_e(trux_notification_url($notification)) ?>">
                      <div class="notificationItem__body">
                        <div class="notificationItem__text"><?= trux_e(trux_notification_text($notification)) ?></div>
                        <div
                          class="notificationItem__time muted"
                          data-time-ago="1"
                          data-time-source="<?= trux_e((string)$notification['created_at']) ?>"
                          title="<?= trux_e(trux_format_exact_time((string)$notification['created_at'])) ?>">
                          <?= trux_e(trux_time_ago((string)$notification['created_at'])) ?>
                        </div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- PROFILE dropdown -->
          <div class="nav__menu nav__menu--profile">
            <button class="nav__icon nav__icon--profile" type="button" aria-label="Profile menu" title="@<?= trux_e($user['username']) ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 12a4.2 4.2 0 1 0-4.2-4.2A4.2 4.2 0 0 0 12 12Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z" />
              </svg>
            </button>

            <div class="menu__panel" role="menu" aria-label="Profile menu">
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e($user['username']) ?>">
                Profile
                <span class="muted">@<?= trux_e($user['username']) ?></span>
              </a>
              <?php if ($showProfileMenuEditProfile): ?>
                <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/edit_profile.php">
                  Edit Profile
                  <span class="muted">Name, bio, media</span>
                </a>
              <?php endif; ?>
              <?php if ($showProfileMenuPremium): ?>
                <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/premium.php">
                  <span class="menu__itemLabel">
                    <svg class="menu__itemIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <path fill="currentColor" d="M12 2L3 10l9 12 9-12-9-8Zm0 3.1L17.1 10 12 16.8 6.9 10 12 5.1Z" />
                    </svg>
                    Premium
                  </span>
                  <span class="muted">Coming soon</span>
                </a>
              <?php endif; ?>
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/messages.php">
                Messages
                <?php if ($unreadMessageCount > 0): ?>
                  <span class="menuBadge"><?= (int)$unreadMessageCount ?></span>
                <?php else: ?>
                  <span class="muted">Inbox</span>
                <?php endif; ?>
              </a>
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/bookmarks.php">
                Bookmarks
                <span class="muted">Saved items</span>
              </a>
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/settings.php">
                Settings
                <span class="muted">Account</span>
              </a>

              <div class="menu__divider"></div>

              <form class="menu__form" method="post" action="<?= TRUX_BASE_URL ?>/logout.php">
                <?= trux_csrf_field() ?>
                <button class="menu__danger" type="submit">Logout</button>
              </form>
            </div>
          </div>

        <?php else: ?>
          <a href="<?= TRUX_BASE_URL ?>/login.php">Login</a>
          <a class="btn btn--small" href="<?= TRUX_BASE_URL ?>/register.php">Create account</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="container">
    <?php if ($error): ?>
      <div class="flash flash--error"><?= trux_e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="flash flash--success"><?= trux_e($success) ?></div>
    <?php endif; ?>
