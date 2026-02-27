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
  <header class="topbar">
    <div class="container topbar__inner">
      <a class="brand" href="/"><?= trux_e(TRUX_APP_NAME) ?></a>

      <form class="search" method="get" action="/search.php" role="search">
        <input class="search__input" name="q" value="<?= trux_e($q) ?>" placeholder="Search users or posts..." maxlength="80">
        <button class="search__btn" type="submit">Search</button>
      </form>

      <nav class="nav">
        <?php if ($user): ?>
          <a href="/new_post.php">New Post</a>
          <a class="nav__icon" href="/profile.php?u=<?= trux_e(urlencode((string)$user['username'])) ?>" aria-label="Your profile" title="@<?= trux_e((string)$user['username']) ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
              <path fill="currentColor" d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2-8 4.5V21h16v-2.5C20 16 16.42 14 12 14Z" />
            </svg>
          </a>
          <form class="nav__form" method="post" action="/logout.php">
            <?= trux_csrf_field() ?>
            <button class="linklike" type="submit">Logout</button>
          </form>
        <?php else: ?>
          <a href="/login.php">Login</a>
          <a class="btn btn--small" href="/register.php">Create account</a>
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