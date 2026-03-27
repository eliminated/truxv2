<?php
declare(strict_types=1);

$messageId = (int)($message['id'] ?? 0);
if ($messageId <= 0) {
  return;
}

$conversationId = $conversationId > 0 ? $conversationId : (int)($message['conversation_id'] ?? 0);
$isMine = (int)($message['sender_user_id'] ?? 0) === $viewerId;
$messageTime = (string)($message['created_at'] ?? '');
$messageSenderUsername = (string)($message['sender_username'] ?? '');
$canReportMessage = !$isMine && !trux_is_report_system_user($messageSenderUsername);
$messageReportLabel = 'Message #' . $messageId . ' from @' . $messageSenderUsername;
$messageReportUrl = TRUX_BASE_URL . '/messages.php?id=' . $conversationId . '#message-' . $messageId;
?>
<article id="message-<?= $messageId ?>" class="messageBubble<?= $isMine ? ' messageBubble--mine' : '' ?>" data-message-bubble="1" data-message-id="<?= $messageId ?>">
  <div class="messageBubble__meta">
    <div class="messageBubble__metaMain">
      <span class="messageBubble__author"><?= $isMine ? 'You' : trux_e(trux_direct_message_actor_label($messageSenderUsername, (string)($message['sender_display_name'] ?? ''))) ?></span>
      <?php if ($messageTime !== ''): ?>
        <span class="muted" data-time-ago="1" data-time-source="<?= trux_e($messageTime) ?>" title="<?= trux_e(trux_format_exact_time($messageTime)) ?>">
          <?= trux_e(trux_time_ago($messageTime)) ?>
        </span>
      <?php endif; ?>
    </div>
    <?php if ($canReportMessage): ?>
      <div class="contentMenu messageBubble__menu" data-content-menu="1">
        <button class="contentMenu__trigger" type="button" aria-label="Open message actions" data-content-menu-trigger="1">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6.5 12a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Z" fill="currentColor" />
          </svg>
        </button>
        <div class="contentMenu__panel" role="menu" aria-label="Message actions">
          <button
            class="contentMenu__item contentMenu__item--danger"
            type="button"
            role="menuitem"
            data-report-action="1"
            data-report-target-type="message"
            data-report-target-id="<?= $messageId ?>"
            data-report-open-url="<?= trux_e($messageReportUrl) ?>"
            data-report-target-label="<?= trux_e($messageReportLabel) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M6 20V5m0 0h9l-1.5 3L15 11H6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>Report message</span>
          </button>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <div class="messageBubble__body"><?= trux_render_comment_body((string)($message['body'] ?? '')) ?></div>
</article>
