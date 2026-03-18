<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();
$me = trux_current_user();
if (!$me) {
    trux_redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (is_string($action) && $action === 'mark_all_read') {
        trux_mark_all_notifications_read((int)$me['id']);
        trux_flash_set('success', 'All notifications marked as read.');
    } else {
        trux_flash_set('error', 'Invalid notification action.');
    }
    trux_redirect('/notifications.php');
}

$notifications = trux_fetch_notifications((int)$me['id'], 60);

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Notifications</h1>
  <p class="muted">Recent activity related to your posts, comments, mentions, and follows.</p>
</section>

<section class="card notificationsCard">
  <div class="card__body">
    <div class="row row--spaced">
      <h2 class="h2">Latest</h2>
      <?php if ($notifications): ?>
        <form method="post" action="<?= TRUX_BASE_URL ?>/notifications.php" class="inline">
          <?= trux_csrf_field() ?>
          <input type="hidden" name="action" value="mark_all_read">
          <button class="btn btn--small btn--ghost" type="submit">Mark all as read</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$notifications): ?>
      <div class="muted">No notifications yet.</div>
    <?php else: ?>
      <div class="notificationList">
        <?php foreach ($notifications as $notification): ?>
          <a class="notificationItem" href="<?= trux_e(trux_notification_url($notification)) ?>">
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
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
