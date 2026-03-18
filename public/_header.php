<?php

declare(strict_types=1);

$user = trux_current_user();
$error = trux_flash_get('error');
$success = trux_flash_get('success');

$q = trux_str_param('q', '');
$bodyClasses = ['appearance--classic', 'motion--reduced'];
$unreadNotificationCount = $user ? trux_count_unread_notifications((int)$user['id']) : 0;
$unreadMessageCount = $user ? trux_count_unread_direct_messages((int)$user['id']) : 0;
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= trux_e(TRUX_APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= TRUX_BASE_URL ?>/assets/style.css">
  <script defer src="<?= TRUX_BASE_URL ?>/assets/app.js"></script>
  <script>window.TRUX_BASE_URL = "<?= TRUX_BASE_URL ?>";</script>
</head>

<body class="<?= trux_e(implode(' ', $bodyClasses)) ?>">
  <header class="topbar">
    <div class="container topbar__inner">
      <a class="brand" href="<?= TRUX_BASE_URL ?>/"><?= trux_e(TRUX_APP_NAME) ?></a>

      <div class="searchWrap">
        <form class="search" method="get" action="<?= TRUX_BASE_URL ?>/search.php" role="search">
          <input class="search__input" name="q" value="<?= trux_e($q) ?>" placeholder="Search users or posts..." maxlength="80">
          <button class="search__btn" type="submit">
            Search
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
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/edit_profile.php">
                Edit Profile
                <span class="muted">Name, bio, media</span>
              </a>
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/premium.php">
                <span class="menu__itemLabel">
                  <svg class="menu__itemIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M12 2L3 10l9 12 9-12-9-8Zm0 3.1L17.1 10 12 16.8 6.9 10 12 5.1Z" />
                  </svg>
                  Premium
                </span>
                <span class="muted">Coming soon</span>
              </a>
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/messages.php">
                Messages
                <?php if ($unreadMessageCount > 0): ?>
                  <span class="menuBadge"><?= (int)$unreadMessageCount ?></span>
                <?php else: ?>
                  <span class="muted">Inbox</span>
                <?php endif; ?>
              </a>
              <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/notifications.php">
                Notifications
                <?php if ($unreadNotificationCount > 0): ?>
                  <span class="menuBadge"><?= (int)$unreadNotificationCount ?></span>
                <?php else: ?>
                  <span class="muted">All caught up</span>
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