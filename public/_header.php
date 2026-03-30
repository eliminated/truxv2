<?php

declare(strict_types=1);

$user = trux_current_user();
$pageFlashMessages = isset($pageFlashMessages) && is_array($pageFlashMessages)
  ? $pageFlashMessages
  : trux_flash_pull_all();
if (!isset($error) && isset($pageFlashMessages['error'][0])) {
  $error = (string)$pageFlashMessages['error'][0];
}
if (!isset($success) && isset($pageFlashMessages['success'][0])) {
  $success = (string)$pageFlashMessages['success'][0];
}
$renderPageFlash = isset($renderPageFlash) ? (bool)$renderPageFlash : true;
$pageToastMessages = [];
if ($renderPageFlash) {
  foreach (['success', 'error', 'info'] as $flashType) {
    $messages = $pageFlashMessages[$flashType] ?? [];
    if (!is_array($messages)) {
      continue;
    }

    foreach ($messages as $message) {
      $text = trim((string)$message);
      if ($text === '') {
        continue;
      }

      $pageToastMessages[] = [
        'type' => $flashType,
        'message' => $text,
      ];
    }
  }
}

$q = trux_str_param('q', '');
$pageKey = isset($pageKey) && is_string($pageKey) && trim($pageKey) !== ''
  ? trim($pageKey)
  : (string)pathinfo((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'), PATHINFO_FILENAME);
$pageLayout = isset($pageLayout) && is_string($pageLayout) && trim($pageLayout) !== ''
  ? trim($pageLayout)
  : 'app';
$pageSlug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $pageKey));
$pageSlug = trim($pageSlug, '-');
if ($pageSlug === '') {
  $pageSlug = 'home';
}
$isAuthenticated = $user !== null;
$bodyClasses = array_values(array_filter([
  'shell-mode',
  'shell--' . $pageLayout,
  'page--' . $pageSlug,
  $isAuthenticated ? 'is-authenticated' : 'is-guest',
]));

$unreadNotificationCount = $user ? trux_count_unread_notifications((int)$user['id']) : 0;
$unreadMessageCount = $user ? trux_count_unread_direct_messages((int)$user['id']) : 0;
$notificationMenuItems = $user ? trux_fetch_notifications((int)$user['id'], 10) : [];
$notificationBadgeLabel = $unreadNotificationCount > 99 ? '99+' : (string)$unreadNotificationCount;
$notificationRedirectPath = '/notifications.php';
$showProfileMenuPremium = false;
$showProfileMenuModeration = $user && trux_has_staff_role((string)($user['staff_role'] ?? 'user'), 'developer');
$moderationBadgeCounts = $showProfileMenuModeration ? trux_moderation_fetch_staff_badge_counts((int)$user['id'], (string)($user['staff_role'] ?? 'user')) : [];
$moderationBadgeTotal = (int)($moderationBadgeCounts['total'] ?? 0);
$showGlobalSearch = $pageLayout !== 'auth';
$pageContextLabel = match ($pageLayout) {
  'moderation' => 'Ops Workspace',
  'auth' => 'Gateway',
  default => 'Command Shell',
};
$pageContextTitle = match ($pageSlug) {
  'home' => 'Feed',
  'post-viewer' => 'Viewer',
  'edit-profile' => 'Profile Studio',
  'new-post' => 'Compose',
  default => ucwords(str_replace(['-', '_'], ' ', $pageSlug)),
};
$pageTitle = isset($pageTitle) && is_string($pageTitle) && trim($pageTitle) !== ''
  ? trim($pageTitle)
  : ($pageSlug === 'home' ? TRUX_APP_NAME : $pageContextTitle . ' - ' . TRUX_APP_NAME);
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
$verificationBannerRedirectPath = trux_safe_local_redirect_path($notificationRedirectPath, '/');
$verificationBannerVisible = $user !== null && empty($user['email_verified']);
$verificationBannerCooldownRemaining = $verificationBannerVisible
  ? trux_email_verification_cooldown_remaining_seconds((string)($user['email_verify_sent_at'] ?? ''))
  : 0;
$verificationBannerCanResend = $verificationBannerVisible && $verificationBannerCooldownRemaining === 0;
$mainCssPath = __DIR__ . '/assets/css/main.css';
$mainCssDir = dirname($mainCssPath);
$styleSheetManifest = [];
if (is_file($mainCssPath)) {
  $mainCssContents = (string)(file_get_contents($mainCssPath) ?: '');
  if ($mainCssContents !== '' && preg_match_all('/@import\s+url\("([^"]+)"\);/', $mainCssContents, $matches)) {
    foreach ($matches[1] as $importPath) {
      $relativeImport = ltrim((string)$importPath, './');
      $absoluteImportPath = $mainCssDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeImport);
      if (!is_file($absoluteImportPath)) {
        continue;
      }

      $styleSheetManifest[] = [
        'href' => TRUX_BASE_URL . '/assets/css/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativeImport),
        'version' => (int)(filemtime($absoluteImportPath) ?: 0),
      ];
    }
  }
}

$isPage = static function (array $slugs) use ($pageSlug): bool {
  return in_array($pageSlug, $slugs, true);
};

$selfProfileUrl = $user
  ? TRUX_BASE_URL . '/profile.php?u=' . rawurlencode((string)$user['username'])
  : TRUX_BASE_URL . '/login.php';
$railAvatarPath = $user && is_string($user['avatar_path'] ?? null)
  ? trim((string)$user['avatar_path'])
  : '';
$railAvatarUrl = $railAvatarPath !== '' ? trux_public_url($railAvatarPath) : '';
$editProfileUrl = TRUX_BASE_URL . '/edit_profile.php';
$settingsUrl = TRUX_BASE_URL . '/settings.php';
$homeUrl = TRUX_BASE_URL . '/';
$homeRailActive = $isPage(['home', 'post-viewer']);
$desktopAccountRailActive = $user
  ? $isPage(['profile', 'edit-profile', 'settings', 'premium', 'appeal'])
  : $isPage(['login', 'register']);
$primaryRailAction = [
  'href' => $user ? TRUX_BASE_URL . '/new_post.php' : TRUX_BASE_URL . '/login.php',
  'label' => $user ? 'Create post' : 'Enter TruX',
  'meta' => $user ? 'Open composer' : 'Login to publish',
  'active' => $user ? $isPage(['new-post']) : $isPage(['login']),
];
$shellNavDesktopPanelId = 'shellNavDesktopPanel';
$shellNavMobilePanelId = 'shellNavMobilePanel';
$appRailItems = [
  [
    'kind' => 'search',
    'href' => TRUX_BASE_URL . '/search.php',
    'label' => 'Search',
    'meta' => 'People and posts',
    'icon' => 'search',
    'active' => $isPage(['search']),
  ],
];
if ($user) {
  $appRailItems[] = [
    'href' => TRUX_BASE_URL . '/messages.php',
    'label' => 'Inbox',
    'meta' => $unreadMessageCount > 0 ? $unreadMessageCount . ' unread' : 'Direct messages',
    'icon' => 'messages',
    'active' => $isPage(['messages']),
    'badge' => $unreadMessageCount > 0 ? (string)$unreadMessageCount : '',
  ];
  $appRailItems[] = [
    'href' => TRUX_BASE_URL . '/notifications.php',
    'label' => 'Activity',
    'meta' => $unreadNotificationCount > 0 ? $notificationBadgeLabel . ' unread' : 'Notifications',
    'icon' => 'activity',
    'active' => $isPage(['notifications']),
    'badge' => $unreadNotificationCount > 0 ? $notificationBadgeLabel : '',
  ];
  $appRailItems[] = [
    'href' => TRUX_BASE_URL . '/bookmarks.php',
    'label' => 'Saved',
    'meta' => 'Bookmarks',
    'icon' => 'bookmarks',
    'active' => $isPage(['bookmarks']),
  ];
  $appRailItems[] = [
    'href' => $settingsUrl,
    'label' => 'Studio',
    'meta' => 'Settings and profile',
    'icon' => 'studio',
    'active' => $isPage(['settings', 'profile', 'edit-profile', 'premium', 'appeal']),
  ];
  if ($showProfileMenuModeration) {
    $appRailItems[] = [
      'href' => TRUX_BASE_URL . '/moderation/',
      'label' => 'Moderation',
      'meta' => $moderationBadgeTotal > 0 ? $moderationBadgeTotal . ' waiting' : 'Staff tools',
      'icon' => 'moderation',
      'active' => $pageLayout === 'moderation',
      'badge' => $moderationBadgeTotal > 0 ? (string)$moderationBadgeTotal : '',
    ];
  }
} else {
  $appRailItems[] = [
    'href' => TRUX_BASE_URL . '/login.php',
    'label' => 'Login',
    'meta' => 'Access your account',
    'icon' => 'login',
    'active' => $isPage(['login']),
  ];
  $appRailItems[] = [
    'href' => TRUX_BASE_URL . '/register.php',
    'label' => 'Create account',
    'meta' => 'Join TruX',
    'icon' => 'register',
    'active' => $isPage(['register']),
  ];
}

$railIcon = static function (string $name): string {
  return match ($name) {
    'compose' => '<path d="M5.25 18.75h3.45l8.85-8.85a1.7 1.7 0 0 0 0-2.4l-1-1a1.7 1.7 0 0 0-2.4 0l-8.9 8.9v3.35Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12.75 7.75 16.25 11.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
    'home' => '<path d="M4 10.8 12 4l8 6.8M7 9.9V20h10V9.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'search' => '<path d="M10.5 4a6.5 6.5 0 1 0 4.02 11.61l4.43 4.43a1 1 0 0 0 1.41-1.41l-4.43-4.43A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9a4.5 4.5 0 0 1 0-9Z" fill="currentColor"/>',
    'messages' => '<path d="M4.75 6.75h14.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H9.5l-4.75 3v-3H4.75a1.5 1.5 0 0 1-1.5-1.5v-7.5a1.5 1.5 0 0 1 1.5-1.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
    'activity' => '<path d="M12 3a5 5 0 0 0-5 5v1.2c0 .9-.28 1.78-.81 2.5l-1.1 1.54A2 2 0 0 0 6.72 17h10.56a2 2 0 0 0 1.63-3.16l-1.1-1.54A4.3 4.3 0 0 1 17 9.2V8a5 5 0 0 0-5-5Zm0 18a2.75 2.75 0 0 0 2.58-1.8.75.75 0 0 0-.7-1.02h-3.76a.75.75 0 0 0-.7 1.02A2.75 2.75 0 0 0 12 21Z" fill="currentColor"/>',
    'bookmarks' => '<path d="M7 4.8h10a1 1 0 0 1 1 1V20l-6-3.8L6 20V5.8a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
    'studio' => '<path d="M4.75 12.25 12 4.75l7.25 7.5M8.25 10.5v8h7.5v-8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'moderation' => '<path d="M12 4.75 18.5 7v4.5c0 4.1-2.63 7.76-6.5 9.25-3.87-1.49-6.5-5.15-6.5-9.25V7L12 4.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9.5 12.25 11.2 14l3.55-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'login' => '<path d="M10.25 4.75H7A2.25 2.25 0 0 0 4.75 7v10A2.25 2.25 0 0 0 7 19.25h3.25M14.5 16.25 19.25 12 14.5 7.75M8.75 12h10.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'register' => '<path d="M12 4.75v14.5M4.75 12h14.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="12" r="8.25" fill="none" stroke="currentColor" stroke-width="1.7"/>',
    default => '<path d="M4 10.8 12 4l8 6.8M7 9.9V20h10V9.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  };
};

$menuIcon = static function (string $name): string {
  return match ($name) {
    'profile' => '<path d="M12 12a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Zm-6.75 6.2c0-3.01 3.02-4.95 6.75-4.95s6.75 1.94 6.75 4.95" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'edit' => '<path d="m5.5 16.75-.75 3.5 3.5-.75L18.5 9.25a1.77 1.77 0 0 0 0-2.5l-1.25-1.25a1.77 1.77 0 0 0-2.5 0L5.5 16.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m13.5 6.75 3.75 3.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
    'premium' => '<path d="M6 18.25 8.4 8.5l3.6 4 3.6-4 2.4 9.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m7.9 8.35 4.1-3.1 4.1 3.1" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'messages' => '<path d="M4.75 6.75h14.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H9.5l-4.75 3v-3H4.75a1.5 1.5 0 0 1-1.5-1.5v-7.5a1.5 1.5 0 0 1 1.5-1.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
    'bookmarks' => '<path d="M7 4.8h10a1 1 0 0 1 1 1V20l-6-3.8L6 20V5.8a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
    'settings' => '<path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm8 3.5-.95-.55a1 1 0 0 1-.46-1.18l.3-1.04-1.7-1.7-1.04.3a1 1 0 0 1-1.18-.46L14.42 5h-2.84l-.55.95a1 1 0 0 1-1.18.46l-1.04-.3-1.7 1.7.3 1.04a1 1 0 0 1-.46 1.18L4 12l.95.55a1 1 0 0 1 .46 1.18l-.3 1.04 1.7 1.7 1.04-.3a1 1 0 0 1 1.18.46l.55.95h2.84l.55-.95a1 1 0 0 1 1.18-.46l1.04.3 1.7-1.7-.3-1.04a1 1 0 0 1 .46-1.18L20 12Z" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/>',
    'moderation' => '<path d="M12 4.75 18.5 7v4.5c0 4.1-2.63 7.76-6.5 9.25-3.87-1.49-6.5-5.15-6.5-9.25V7L12 4.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9.5 12.25 11.2 14l3.55-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'logout' => '<path d="M10 5.25H7A2.25 2.25 0 0 0 4.75 7.5v9A2.25 2.25 0 0 0 7 18.75h3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M13.75 8.25 19 12l-5.25 3.75M18.5 12H9.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'login' => '<path d="M10.25 4.75H7A2.25 2.25 0 0 0 4.75 7v10A2.25 2.25 0 0 0 7 19.25h3.25M14.5 16.25 19.25 12 14.5 7.75M8.75 12h10.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'register' => '<path d="M12 4.75v14.5M4.75 12h14.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="12" r="8.25" fill="none" stroke="currentColor" stroke-width="1.7"/>',
    default => '<path d="M4 10.8 12 4l8 6.8M7 9.9V20h10V9.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
  };
};

$navToggleIcon = static function (string $state): string {
  return match ($state) {
    'open' => '<path d="m6.75 6.75 10.5 10.5M17.25 6.75 6.75 17.25" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    default => '<path d="M5.75 7.25h12.5M5.75 12h12.5M5.75 16.75h12.5" fill="none" stroke="currentColor" stroke-width="1.95" stroke-linecap="round"/>',
  };
};

$renderShellNavToggle = static function (string $target, string $panelId, string $extraClass = '') use ($navToggleIcon): void {
  $className = 'shellNavToggle' . ($extraClass !== '' ? ' ' . $extraClass : '');
  ?>
  <button
    class="<?= trux_e($className) ?>"
    type="button"
    data-shell-nav-toggle
    data-shell-nav-target="<?= trux_e($target) ?>"
    aria-expanded="false"
    aria-controls="<?= trux_e($panelId) ?>"
    aria-label="Toggle navigation menu">
    <span class="shellNavToggle__icon shellNavToggle__icon--closed" aria-hidden="true">
      <svg viewBox="0 0 24 24" focusable="false"><?= $navToggleIcon('closed') ?></svg>
    </span>
    <span class="shellNavToggle__icon shellNavToggle__icon--open" aria-hidden="true">
      <svg viewBox="0 0 24 24" focusable="false"><?= $navToggleIcon('open') ?></svg>
    </span>
  </button>
  <?php
};

$renderAppRailItems = static function (array $items, string $surface = 'desktop') use ($railIcon): void {
  foreach ($items as $item):
    $itemHref = trim((string)($item['href'] ?? ''));
    $itemLabel = trim((string)($item['label'] ?? ''));
    $itemMeta = trim((string)($item['meta'] ?? ''));
    $itemIconName = trim((string)($item['icon'] ?? 'home'));
    $itemBadge = trim((string)($item['badge'] ?? ''));
    $itemKind = trim((string)($item['kind'] ?? 'link'));
    $itemIsActive = !empty($item['active']);
    $itemClasses = 'railNav__item' . ($itemIsActive ? ' is-active' : '');
    if ($surface === 'mobile' && $itemKind === 'search'):
      ?>
      <button class="<?= trux_e($itemClasses) ?> railNav__item--button" type="button" data-shell-nav-open-sheet="search">
        <span class="railNav__signal" aria-hidden="true"></span>
        <span class="railNav__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false"><?= $railIcon($itemIconName) ?></svg>
        </span>
        <span class="railNav__copy">
          <strong><?= trux_e($itemLabel) ?></strong>
          <span class="railNav__copyMeta">
            <small><?= trux_e($itemMeta) ?></small>
          </span>
        </span>
        <?php if ($itemBadge !== ''): ?>
          <span class="railNav__badge"><?= trux_e($itemBadge) ?></span>
        <?php endif; ?>
      </button>
      <?php
      continue;
    endif;

    if ($itemHref === '') {
      continue;
    }
    ?>
    <a class="<?= trux_e($itemClasses) ?>" href="<?= trux_e($itemHref) ?>" <?= $itemIsActive ? 'aria-current="page"' : '' ?>>
      <span class="railNav__signal" aria-hidden="true"></span>
      <span class="railNav__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false"><?= $railIcon($itemIconName) ?></svg>
      </span>
      <span class="railNav__copy">
        <strong><?= trux_e($itemLabel) ?></strong>
        <span class="railNav__copyMeta">
          <small><?= trux_e($itemMeta) ?></small>
        </span>
      </span>
      <?php if ($itemBadge !== ''): ?>
        <span class="railNav__badge"><?= trux_e($itemBadge) ?></span>
      <?php endif; ?>
    </a>
    <?php
  endforeach;
};

$renderShellNavFooter = static function (string $surface = 'desktop') use (
  $user,
  $selfProfileUrl,
  $editProfileUrl,
  $settingsUrl,
  $railAvatarUrl,
  $menuIcon
): void {
  $panelClass = 'railPresence railPresence--panel' . ($surface === 'mobile' ? ' railPresence--drawer' : '');
  if ($user):
    ?>
    <div class="<?= trux_e($panelClass) ?>">
      <div class="railPresence__card railPresence__card--profile">
        <a class="railPresence__identity" href="<?= $selfProfileUrl ?>">
          <span class="railPresence__avatar" aria-hidden="true">
            <?php if ($railAvatarUrl !== ''): ?>
              <img class="railPresence__avatarImage" src="<?= trux_e($railAvatarUrl) ?>" alt="">
            <?php else: ?>
              <?= strtoupper(substr((string)$user['username'], 0, 1)) ?>
            <?php endif; ?>
          </span>
          <span class="railPresence__copy">
            <strong>@<?= trux_e((string)$user['username']) ?></strong>
            <small><?= trux_e((string)($user['display_name'] ?? 'Personal workspace')) ?></small>
          </span>
        </a>
        <div class="railPresence__tools" aria-label="Profile actions">
          <a class="railPresence__tool" href="<?= $editProfileUrl ?>" aria-label="Edit profile" title="Edit profile">
            <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('edit') ?></svg>
          </a>
          <a class="railPresence__tool" href="<?= $settingsUrl ?>" aria-label="Settings" title="Settings">
            <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('settings') ?></svg>
          </a>
          <form class="railPresence__toolForm" method="post" action="<?= TRUX_BASE_URL ?>/logout.php">
            <?= trux_csrf_field() ?>
            <button class="railPresence__tool" type="submit" aria-label="Logout" title="Logout">
              <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('logout') ?></svg>
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php
  else:
    ?>
    <div class="<?= trux_e($panelClass) ?>">
      <div class="railPresence__guest">
        <small class="railPresence__eyebrow">Access state</small>
        <strong>Guest browsing</strong>
        <span>Sign in to post, save, and join conversations.</span>
      </div>
      <div class="railPresence__actions">
        <a class="railPresence__action" href="<?= TRUX_BASE_URL ?>/login.php">Login</a>
        <a class="railPresence__action" href="<?= TRUX_BASE_URL ?>/register.php">Create account</a>
      </div>
    </div>
    <?php
  endif;
};

$opsModules = [];
if ($pageLayout === 'moderation' && isset($moderationMe, $moderationStaffRole)) {
  $opsModules = trux_visible_moderation_modules($moderationStaffRole);
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= trux_e($pageTitle) ?></title>
  <?php
  $faviconVersion = max(
    (int)(filemtime(__DIR__ . '/favicon.php') ?: 0),
    (int)(filemtime(dirname(__DIR__) . '/src/logo/trux_logo.png') ?: 0)
  );
  ?>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>">
  <?php if ($styleSheetManifest): ?>
    <?php foreach ($styleSheetManifest as $styleSheet): ?>
      <link rel="stylesheet" href="<?= trux_e((string)$styleSheet['href']) ?>?v=<?= (int)$styleSheet['version'] ?>">
    <?php endforeach; ?>
  <?php else: ?>
    <link rel="stylesheet" href="<?= TRUX_BASE_URL ?>/assets/css/main.css?v=<?= (int)(filemtime($mainCssPath) ?: 0) ?>">
  <?php endif; ?>
  <script defer src="<?= TRUX_BASE_URL ?>/assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
  <script>window.TRUX_BASE_URL = "<?= TRUX_BASE_URL ?>";</script>
</head>

<body class="<?= trux_e(implode(' ', $bodyClasses)) ?>">
  <div id="pageFX" class="pagefx" aria-hidden="true">
    <div class="pagefx__bar"></div>
  </div>

  <div class="shellAtmosphere" aria-hidden="true">
    <div class="shellAtmosphere__grid"></div>
    <div class="shellAtmosphere__orb shellAtmosphere__orb--one"></div>
    <div class="shellAtmosphere__orb shellAtmosphere__orb--two"></div>
    <div class="shellAtmosphere__orb shellAtmosphere__orb--three"></div>
  </div>

  <?php if ($pageToastMessages): ?>
    <div class="toastPayloads" data-page-toasts="1" hidden>
      <?php foreach ($pageToastMessages as $toastMessage): ?>
        <div
          data-page-toast="1"
          data-toast-type="<?= trux_e((string)$toastMessage['type']) ?>"
          data-toast-message="<?= trux_e((string)$toastMessage['message']) ?>"></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($pageLayout === 'auth'): ?>
    <div class="authShell">
      <header class="authTopbar">
        <a class="shellBrand shellBrand--auth" href="<?= TRUX_BASE_URL ?>/">
          <span class="shellBrand__mark">
            <img src="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>" alt="" width="28" height="28" loading="eager" decoding="async">
          </span>
          <span class="shellBrand__copy">
            <strong><?= trux_e(TRUX_APP_NAME) ?></strong>
            <span>Gateway lattice</span>
          </span>
        </a>
        <nav class="authTopbar__nav" aria-label="Account navigation">
          <a class="authTopbar__link<?= $isPage(['login']) ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/login.php">Login</a>
          <a class="authTopbar__link authTopbar__link--strong<?= $isPage(['register']) ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/register.php">Create account</a>
        </nav>
      </header>

      <main class="authStage">
        <div class="authCanvas">
  <?php elseif ($pageLayout === 'moderation'): ?>
    <div class="opsShell">
      <aside class="opsRail" aria-label="Moderation navigation">
        <div class="opsRail__head">
          <a class="shellBrand shellBrand--ops" href="<?= TRUX_BASE_URL ?>/moderation/">
            <span class="shellBrand__mark">
              <img src="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>" alt="" width="28" height="28" loading="eager" decoding="async">
            </span>
            <span class="shellBrand__copy">
              <strong><?= trux_e(TRUX_APP_NAME) ?></strong>
              <span>Oversight lattice</span>
            </span>
          </a>

          <div class="opsRail__staff">
            <span class="opsRail__eyebrow">Staff access</span>
            <strong><?= trux_e(ucfirst((string)($moderationStaffRole ?? 'developer'))) ?></strong>
            <span><?= $user ? '@' . trux_e((string)$user['username']) : 'Signed in' ?></span>
          </div>
        </div>

        <nav class="opsRail__nav" aria-label="Moderation modules">
          <?php foreach ($opsModules as $moduleKey => $module): ?>
            <?php $moduleBadge = (int)($moderationBadgeCounts[$moduleKey] ?? 0); ?>
            <a class="opsRail__link<?= (($moderationActiveKey ?? '') === $moduleKey) ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL . $module['path'] ?>">
              <span class="railNav__signal" aria-hidden="true"></span>
              <span class="opsRail__linkMain">
                <strong><?= trux_e((string)$module['title']) ?></strong>
                <small><?= trux_e((string)$module['description']) ?></small>
              </span>
              <?php if ($moduleBadge > 0): ?>
                <span class="opsRail__badge"><?= $moduleBadge ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </nav>

        <div class="opsRail__footer">
          <a class="opsRail__miniLink" href="<?= TRUX_BASE_URL ?>/">Return to app</a>
          <a class="opsRail__miniLink" href="<?= $settingsUrl ?>">Account settings</a>
        </div>
      </aside>

      <div class="opsViewport">
        <header class="opsTopbar">
          <div class="opsTopbar__title">
            <span class="opsTopbar__eyebrow"><?= trux_e($pageContextLabel) ?></span>
            <h1><?= trux_e($pageContextTitle) ?></h1>
          </div>

          <div class="opsTopbar__actions">
            <?php if ($showGlobalSearch): ?>
              <form class="topSearch topSearch--compact" method="get" action="<?= TRUX_BASE_URL ?>/search.php" role="search">
                <label class="topSearch__field">
                  <span class="topSearch__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path fill="currentColor" d="M10.5 4a6.5 6.5 0 1 0 4.02 11.61l4.43 4.43a1 1 0 0 0 1.41-1.41l-4.43-4.43A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9a4.5 4.5 0 0 1 0-9Z" />
                    </svg>
                  </span>
                  <input class="topSearch__input" name="q" value="<?= trux_e($q) ?>" placeholder="Search people, posts, hashtags" maxlength="80">
                  <span class="topSearch__scope" aria-hidden="true">SCAN</span>
                </label>
              </form>
            <?php endif; ?>
            <a class="shellButton shellButton--ghost" href="<?= $selfProfileUrl ?>">Operator profile</a>
          </div>
        </header>

        <?php if ($verificationBannerVisible): ?>
          <section class="verificationBanner verificationBanner--ops" role="status" aria-live="polite">
            <div class="verificationBanner__copy">
              <strong>Verify your email to unlock password changes and linked-account actions.</strong>
              <span>A recognized domain does not prove inbox ownership. Posting stays available until you click the verification link we sent.</span>
            </div>
            <div class="verificationBanner__actions">
              <form method="post" action="<?= TRUX_BASE_URL ?>/resend-verification.php" class="verificationBanner__form">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= trux_e($verificationBannerRedirectPath) ?>">
                <button class="shellButton shellButton--accent" type="submit" <?= !$verificationBannerCanResend ? 'disabled' : '' ?>>Resend verification email</button>
              </form>
              <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php?section=account">Open account settings</a>
            </div>
            <?php if (!$verificationBannerCanResend): ?>
              <small class="verificationBanner__meta"><?= trux_e(trux_email_verification_cooldown_text($verificationBannerCooldownRemaining)) ?></small>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <main class="opsContent">
          <div class="opsCanvas">
  <?php else: ?>
    <div class="appShell">
      <aside class="appRail" aria-label="Primary navigation" data-shell-nav="desktop">
        <div class="appRail__shell">
          <?php $renderShellNavToggle('desktop', $shellNavDesktopPanelId, 'shellNavToggle--rail'); ?>

          <div class="appRail__authWrap">
            <?php if ($user): ?>
              <a class="appRail__authControl appRail__authControl--profile<?= $desktopAccountRailActive ? ' is-active' : '' ?>" href="<?= $selfProfileUrl ?>" aria-label="Profile" title="Profile" <?= $desktopAccountRailActive ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('profile') ?></svg>
              </a>
            <?php else: ?>
              <a class="appRail__authControl appRail__authControl--login<?= $desktopAccountRailActive ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/login.php" aria-label="Login" title="Login" <?= $desktopAccountRailActive ? 'aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('login') ?></svg>
              </a>
            <?php endif; ?>
          </div>
        </div>

        <section class="appRail__flyout" id="<?= trux_e($shellNavDesktopPanelId) ?>" data-shell-nav-panel aria-label="Secondary navigation">
          <div class="appRail__flyoutInner">
            <div class="appRail__flyoutHead">
              <div class="appRail__flyoutBar">
                <a class="shellBrand appRail__brandLink" href="<?= $homeUrl ?>" aria-label="Go to home" <?= $homeRailActive ? 'aria-current="page"' : '' ?>>
                  <span class="shellBrand__mark">
                    <img src="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>" alt="" width="28" height="28" loading="eager" decoding="async">
                  </span>
                  <span class="shellBrand__copy">
                    <strong><?= trux_e(TRUX_APP_NAME) ?></strong>
                    <span>Command lattice</span>
                  </span>
                </a>

                <?php $renderShellNavToggle('desktop', $shellNavDesktopPanelId, 'shellNavToggle--drawer shellNavToggle--panel'); ?>
              </div>
            </div>

            <a class="railCompose<?= !empty($primaryRailAction['active']) ? ' is-active' : '' ?>" href="<?= trux_e((string)$primaryRailAction['href']) ?>" <?= !empty($primaryRailAction['active']) ? 'aria-current="page"' : '' ?>>
              <span class="railCompose__plus" aria-hidden="true">+</span>
              <span class="railCompose__copy">
                <strong><?= trux_e((string)$primaryRailAction['label']) ?></strong>
                <small><?= trux_e((string)$primaryRailAction['meta']) ?></small>
              </span>
            </a>

            <nav class="railNav railNav--panel" aria-label="Secondary app links">
              <?php $renderAppRailItems($appRailItems, 'desktop'); ?>
            </nav>

            <?php $renderShellNavFooter('desktop'); ?>
          </div>
        </section>
      </aside>

      <div class="shellViewport">
        <header class="shellTopbar">
          <div class="shellTopbar__mobileCore" aria-label="Primary mobile navigation">
            <div class="shellBrand shellBrand--compact shellBrand--static" aria-label="<?= trux_e(TRUX_APP_NAME) ?> brand">
              <span class="shellBrand__mark">
                <img src="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>" alt="" width="28" height="28" loading="eager" decoding="async">
              </span>
              <span class="shellBrand__copy shellBrand__copy--compact">
                <strong><?= trux_e(TRUX_APP_NAME) ?></strong>
                <span>Command lattice</span>
              </span>
            </div>

            <a class="railHomeButton railHomeButton--mobile<?= $homeRailActive ? ' is-active' : '' ?>" href="<?= $homeUrl ?>" aria-label="Go to home" <?= $homeRailActive ? 'aria-current="page"' : '' ?>>
              <svg viewBox="0 0 24 24" focusable="false"><?= $railIcon('home') ?></svg>
            </a>

            <?php $renderShellNavToggle('mobile', $shellNavMobilePanelId, 'shellNavToggle--mobile'); ?>
          </div>

          <div class="shellTopbar__title">
            <span class="shellTopbar__eyebrow"><?= trux_e($pageContextLabel) ?></span>
            <h1><?= trux_e($pageContextTitle) ?></h1>
          </div>

          <div class="shellTopbar__actions">
            <?php if ($showGlobalSearch): ?>
              <form class="topSearch" method="get" action="<?= TRUX_BASE_URL ?>/search.php" role="search">
                <label class="topSearch__field">
                  <span class="topSearch__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                      <path fill="currentColor" d="M10.5 4a6.5 6.5 0 1 0 4.02 11.61l4.43 4.43a1 1 0 0 0 1.41-1.41l-4.43-4.43A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9a4.5 4.5 0 0 1 0-9Z" />
                    </svg>
                  </span>
                  <input class="topSearch__input" name="q" value="<?= trux_e($q) ?>" placeholder="Search users, posts, or hashtags" maxlength="80">
                  <span class="topSearch__scope" aria-hidden="true">SCAN</span>
                </label>
              </form>
            <?php endif; ?>

            <?php if ($user): ?>
              <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/new_post.php">Open compose</a>

              <div class="nav__menu shellMenu shellMenu--notifications">
                <button class="shellAction shellAction--icon" type="button" aria-label="Notifications">
                  <svg viewBox="0 0 24 24" focusable="false">
                    <path fill="currentColor" d="M12 3a5 5 0 0 0-5 5v1.2c0 .9-.28 1.78-.81 2.5l-1.1 1.54A2 2 0 0 0 6.72 17h10.56a2 2 0 0 0 1.63-3.16l-1.1-1.54A4.3 4.3 0 0 1 17 9.2V8a5 5 0 0 0-5-5Zm0 18a2.75 2.75 0 0 0 2.58-1.8.75.75 0 0 0-.7-1.02h-3.76a.75.75 0 0 0-.7 1.02A2.75 2.75 0 0 0 12 21Z" />
                  </svg>
                  <?php if ($unreadNotificationCount > 0): ?>
                    <span class="shellAction__badge"><?= trux_e($notificationBadgeLabel) ?></span>
                  <?php endif; ?>
                </button>

                <div class="menu__panel menu__panel--notifications" aria-label="Notifications">
                  <div class="notificationMenu__head">
                    <div class="notificationMenu__titleWrap">
                      <div class="notificationMenu__title">Notifications</div>
                      <div class="notificationMenu__subtitle muted">
                        <?= $unreadNotificationCount > 0 ? trux_e($notificationBadgeLabel . ' unread') : 'All caught up' ?>
                      </div>
                    </div>
                    <a class="notificationMenu__link" href="<?= TRUX_BASE_URL ?>/notifications.php">Open page</a>
                  </div>

                  <?php if (!$notificationMenuItems): ?>
                    <div class="notificationMenu__empty muted">No notifications yet.</div>
                  <?php else: ?>
                    <div class="notificationList notificationList--menu">
                      <?php foreach ($notificationMenuItems as $notification): ?>
                        <a class="notificationItem<?= empty($notification['read_at']) ? ' is-unread' : '' ?>" href="<?= trux_e(trux_notification_url($notification)) ?>">
                          <div class="notificationItem__body">
                            <div class="notificationItem__text"><?= trux_e(trux_notification_text($notification)) ?></div>
                            <div class="notificationItem__time muted" data-time-ago="1" data-time-source="<?= trux_e((string)$notification['created_at']) ?>" title="<?= trux_e(trux_format_exact_time((string)$notification['created_at'])) ?>">
                              <?= trux_e(trux_time_ago((string)$notification['created_at'])) ?>
                            </div>
                          </div>
                        </a>
                      <?php endforeach; ?>
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
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="nav__menu shellMenu shellMenu--profile">
                <button class="shellAction shellAction--profile" type="button" aria-label="Profile menu" aria-haspopup="menu" title="@<?= trux_e((string)$user['username']) ?>">
                  <span class="shellAction__profileMark"><?= strtoupper(substr((string)$user['username'], 0, 1)) ?></span>
                </button>

                <div class="menu__panel" role="menu" aria-label="Profile menu">
                  <a class="menu__item menu__item--lead" role="menuitem" href="<?= $selfProfileUrl ?>">
                    <span class="menu__itemIcon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('profile') ?></svg>
                    </span>
                    <span class="menu__itemMain">
                      <span class="menu__itemTitle">Profile</span>
                      <span class="menu__itemMeta">@<?= trux_e((string)$user['username']) ?></span>
                    </span>
                  </a>
                  <a class="menu__item" role="menuitem" href="<?= $editProfileUrl ?>">
                    <span class="menu__itemIcon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('edit') ?></svg>
                    </span>
                    <span class="menu__itemMain">
                      <span class="menu__itemTitle">Edit profile</span>
                      <span class="menu__itemMeta">Identity and media</span>
                    </span>
                  </a>
                  <?php if ($showProfileMenuPremium): ?>
                    <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/premium.php">
                      <span class="menu__itemIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('premium') ?></svg>
                      </span>
                      <span class="menu__itemMain">
                        <span class="menu__itemTitle">Premium</span>
                        <span class="menu__itemMeta">Coming soon</span>
                      </span>
                    </a>
                  <?php endif; ?>
                  <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/messages.php">
                    <span class="menu__itemIcon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('messages') ?></svg>
                    </span>
                    <span class="menu__itemMain">
                      <span class="menu__itemTitle">Messages</span>
                      <span class="menu__itemMeta">Inbox</span>
                    </span>
                    <?php if ($unreadMessageCount > 0): ?>
                      <span class="menuBadge"><?= (int)$unreadMessageCount ?></span>
                    <?php endif; ?>
                  </a>
                  <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/bookmarks.php">
                    <span class="menu__itemIcon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('bookmarks') ?></svg>
                    </span>
                    <span class="menu__itemMain">
                      <span class="menu__itemTitle">Bookmarks</span>
                      <span class="menu__itemMeta">Saved items</span>
                    </span>
                  </a>
                  <a class="menu__item" role="menuitem" href="<?= $settingsUrl ?>">
                    <span class="menu__itemIcon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('settings') ?></svg>
                    </span>
                    <span class="menu__itemMain">
                      <span class="menu__itemTitle">Settings</span>
                      <span class="menu__itemMeta">Workspace</span>
                    </span>
                  </a>
                  <?php if ($showProfileMenuModeration): ?>
                    <a class="menu__item" role="menuitem" href="<?= TRUX_BASE_URL ?>/moderation/">
                      <span class="menu__itemIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('moderation') ?></svg>
                      </span>
                      <span class="menu__itemMain">
                        <span class="menu__itemTitle">Moderation</span>
                        <span class="menu__itemMeta">Staff workspace</span>
                      </span>
                      <?php if ($moderationBadgeTotal > 0): ?>
                        <span class="menuBadge"><?= $moderationBadgeTotal ?></span>
                      <?php endif; ?>
                    </a>
                  <?php endif; ?>

                  <div class="menu__divider"></div>

                  <form class="menu__form" method="post" action="<?= TRUX_BASE_URL ?>/logout.php">
                    <?= trux_csrf_field() ?>
                    <button class="menu__button menu__button--danger" type="submit">
                      <span class="menu__itemIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('logout') ?></svg>
                      </span>
                      <span class="menu__itemMain">
                        <span class="menu__itemTitle">Logout</span>
                        <span class="menu__itemMeta">End current session</span>
                      </span>
                    </button>
                  </form>
                </div>
              </div>
            <?php else: ?>
              <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/login.php">Login</a>
              <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/register.php">Create account</a>
            <?php endif; ?>
          </div>
        </header>

        <?php if ($verificationBannerVisible): ?>
          <section class="verificationBanner" role="status" aria-live="polite">
            <div class="verificationBanner__copy">
              <strong>Verify your email to unlock password changes and linked-account actions.</strong>
              <span>A recognized domain does not prove inbox ownership. Posting stays available until you click the verification link we sent.</span>
            </div>
            <div class="verificationBanner__actions">
              <form method="post" action="<?= TRUX_BASE_URL ?>/resend-verification.php" class="verificationBanner__form">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= trux_e($verificationBannerRedirectPath) ?>">
                <button class="shellButton shellButton--accent" type="submit" <?= !$verificationBannerCanResend ? 'disabled' : '' ?>>Resend verification email</button>
              </form>
              <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php?section=account">Open account settings</a>
            </div>
            <?php if (!$verificationBannerCanResend): ?>
              <small class="verificationBanner__meta"><?= trux_e(trux_email_verification_cooldown_text($verificationBannerCooldownRemaining)) ?></small>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <main class="shellContent">
          <div class="sceneCanvas">
  <?php endif; ?>
