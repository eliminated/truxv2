<?php if (!$notifications): ?>
  <div class="notificationMenu__empty muted">No notifications yet.</div>
<?php else: ?>
  <div class="notificationList notificationList--menu">
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

  <div class="notificationMenu__actions">
    <?php if ($unreadNotificationCount > 0): ?>
      <form method="post" action="<?= TRUX_BASE_URL ?>/notifications.php" class="notificationMenu__form">
        <?= trux_csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <input type="hidden" name="redirect" value="<?= trux_e($notificationRedirectPath) ?>">
        <button class="notificationMenu__action" type="submit">Mark all as read</button>
      </form>
    <?php endif; ?>
    <form method="post" action="<?= TRUX_BASE_URL ?>/notifications.php" class="notificationMenu__form" data-confirm="Clear every notification from your feed?">
      <?= trux_csrf_field() ?>
      <input type="hidden" name="action" value="clean_all">
      <input type="hidden" name="redirect" value="<?= trux_e($notificationRedirectPath) ?>">
      <button class="notificationMenu__action notificationMenu__action--danger" type="submit">Clean all</button>
    </form>
  </div>
<?php endif; ?>
