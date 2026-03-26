<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'notifications';
$pageLayout = 'app';

trux_require_login();
$me = trux_current_user();
if (!$me) {
  trux_redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $redirectPath = '/notifications.php';
  $rawRedirect = $_POST['redirect'] ?? '';
  if (is_string($rawRedirect)) {
    $candidateRedirect = trim($rawRedirect);
    if (
      $candidateRedirect !== ''
      && str_starts_with($candidateRedirect, '/')
      && !str_starts_with($candidateRedirect, '//')
      && !preg_match('/[\r\n]/', $candidateRedirect)
    ) {
      $redirectPath = $candidateRedirect;
    }
  }

  $action = $_POST['action'] ?? '';
  if (is_string($action) && $action === 'mark_all_read') {
    trux_mark_all_notifications_read((int)$me['id']);
    trux_flash_set('success', 'All notifications marked as read.');
  } elseif (is_string($action) && $action === 'clean_all') {
    trux_delete_all_notifications((int)$me['id']);
    trux_flash_set('success', 'All notifications cleared.');
  } else {
    trux_flash_set('error', 'Invalid notification action.');
  }
  trux_redirect($redirectPath);
}

$notifications = trux_fetch_notifications((int)$me['id'], 60);

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--notifications">
  <section class="inlineHeader inlineHeader--notifications">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Activity surface</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title">Notifications</h2>
        <p class="inlineHeader__copy">Recent activity across your posts, replies, mentions, follows, and moderation updates.</p>
      </div>
    </div>

    <div class="inlineHeader__aside">
      <?php if ($notifications): ?>
        <div class="row">
          <form method="post" action="<?= TRUX_BASE_URL ?>/notifications.php" class="inline">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <input type="hidden" name="redirect" value="/notifications.php">
            <button class="shellButton shellButton--ghost" type="submit">Mark all as read</button>
          </form>
          <form method="post" action="<?= TRUX_BASE_URL ?>/notifications.php" class="inline" data-confirm="Clear every notification from your feed?">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="clean_all">
            <input type="hidden" name="redirect" value="/notifications.php">
            <button class="shellButton shellButton--ghost shellButton--danger" type="submit">Clean all</button>
          </form>
        </div>
      <?php endif; ?>
      <div class="inlineHeader__meta">
        <span>@<?= trux_e((string)$me['username']) ?></span>
        <strong><?= count($notifications) ?> items</strong>
      </div>
    </div>
  </section>

  <section class="bandSurface bandSurface--notifications">
    <div class="bandSurface__head">
      <div>
        <span class="bandSurface__eyebrow">Latest</span>
        <h3>Notification stream</h3>
      </div>
      <span class="bandSurface__meta"><?= count($notifications) ?> loaded</span>
    </div>

    <?php if (!$notifications): ?>
      <section class="bandSurface bandSurface--empty bandSurface--nested">
        <strong>No notifications yet</strong>
        <p class="muted">When people interact with your content, updates will appear here.</p>
      </section>
    <?php else: ?>
      <div class="notificationList">
        <?php foreach ($notifications as $notification): ?>
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
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
