<?php
declare(strict_types=1);

$conversationId = (int)($conversation['id'] ?? 0);
if ($conversationId <= 0) {
  return;
}

$isActive = $conversationId === $activeConversationId;
$unreadCount = (int)($conversation['unread_count'] ?? 0);
$lastAt = (string)($conversation['last_message_created_at'] ?? $conversation['updated_at'] ?? '');
$otherUsername = (string)($conversation['other_username'] ?? '');
$otherDisplayName = (string)($conversation['other_display_name'] ?? '');
$conversationLabel = trux_direct_message_actor_label($otherUsername, $otherDisplayName);
$preview = trim((string)($conversation['last_message_preview'] ?? ''));
if ($preview === '') {
  $preview = trux_direct_message_preview((string)($conversation['last_message_body'] ?? ''));
}
$conversationAvatarUrl = trux_public_url((string)($conversation['other_avatar_path'] ?? ''));
?>
<a
  class="messagesList__item<?= $isActive ? ' is-active' : '' ?>"
  href="<?= TRUX_BASE_URL ?>/messages.php?id=<?= $conversationId ?>"
  <?= $isActive ? 'aria-current="page"' : '' ?>
  data-conversation-item="1"
  data-conversation-id="<?= $conversationId ?>"
  data-last-message-id="<?= (int)($conversation['last_message_id'] ?? 0) ?>"
  data-unread-count="<?= $unreadCount ?>"
  data-search-text="<?= trux_e(strtolower($conversationLabel . ' ' . $otherUsername . ' ' . $preview)) ?>">
  <span class="messagesList__signal" aria-hidden="true">CH</span>
  <?= trux_render_direct_message_avatar($otherUsername, $conversationAvatarUrl, 'messagesList__avatar', $conversationLabel) ?>

  <div class="messagesList__content">
    <div class="messagesList__row messagesList__row--top">
      <span class="messagesList__user"><?= trux_e($conversationLabel) ?></span>
      <?php if ($lastAt !== ''): ?>
        <span class="messagesList__time muted" data-time-ago="1" data-time-source="<?= trux_e($lastAt) ?>" title="<?= trux_e(trux_format_exact_time($lastAt)) ?>">
          <?= trux_e(trux_time_ago($lastAt)) ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="messagesList__row messagesList__row--bottom">
      <span class="messagesList__preview muted"><?= trux_e($preview) ?></span>
      <span class="messagesList__unread<?= $unreadCount > 0 ? ' is-visible' : '' ?>" aria-hidden="true"></span>
    </div>
  </div>
</a>
