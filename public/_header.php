<?php

declare(strict_types=1);

$user = trux_current_user();
$error = trux_flash_get('error');
$success = trux_flash_get('success');

$q = trux_str_param('q', '');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= trux_e(TRUX_APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/style.css">
  <script defer src="/assets/app.js"></script>
</head>

<body>
  <!-- Page transition overlay -->
  <div id="pageFX" class="pagefx" aria-hidden="true">
    <div class="pagefx__bar"></div>
  </div>

  <header class="topbar">
    <div class="container topbar__inner">
      <a class="brand" href="/"><?= trux_e(TRUX_APP_NAME) ?></a>

      <div class="searchWrap">
        <form class="search" method="get" action="/search.php" role="search">
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
              <a class="menu__item" role="menuitem" href="/new_post.php">
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
              <a class="menu__item" role="menuitem" href="/profile.php?u=<?= trux_e($user['username']) ?>">
                Profile
                <span class="muted">@<?= trux_e($user['username']) ?></span>
              </a>

              <div class="menu__divider"></div>

              <form class="menu__form" method="post" action="/logout.php">
                <?= trux_csrf_field() ?>
                <button class="menu__danger" type="submit">Logout</button>
              </form>
            </div>
          </div>

        <?php else: ?>
          <a href="/login.php">Login</a>
          <a class="btn btn--small" href="/register.php">Create account</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="container page-enter">
    <?php if ($error): ?>
      <div class="flash flash--error"><?= trux_e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="flash flash--success"><?= trux_e($success) ?></div>
    <?php endif; ?>