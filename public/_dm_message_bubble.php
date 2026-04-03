<?php
declare(strict_types=1);

$messageId = (int)($message['id'] ?? 0);
if ($messageId <= 0) {
  return;
}

$conversationId = $conversationId > 0 ? $conversationId : (int)($message['conversation_id'] ?? 0);
$isMine = !empty($message['is_mine']) || (int)($message['sender_user_id'] ?? 0) === $viewerId;
$messageTime = (string)($message['created_at'] ?? '');
$exactTime = (string)($message['exact_time'] ?? ($messageTime !== '' ? trux_format_exact_time($messageTime) : ''));
$messageSenderUsername = (string)($message['sender_username'] ?? '');
$messageSenderDisplayName = (string)($message['sender_display_name'] ?? '');
$messageBody = (string)($message['body'] ?? '');
$isEdited = !empty($message['is_edited']);
$isUnsent = !empty($message['is_unsent']);
$canEdit = !empty($message['can_edit']);
$canUnsend = !empty($message['can_unsend']);
$attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
$attachmentCount = count($attachments);
$canReportMessage = !$isMine && !$isUnsent && !trux_is_report_system_user($messageSenderUsername);
$canCopyBody = !$isUnsent && trim($messageBody) !== '';
$messageReportLabel = 'Message #' . $messageId . ' from @' . $messageSenderUsername;
$messageReportUrl = TRUX_BASE_URL . '/messages.php?id=' . $conversationId . '#message-' . $messageId;
$isRead = !empty($message['is_read']);
$replyContext = is_array($message['reply_context'] ?? null) ? $message['reply_context'] : null;
$replyTargetId = (int)($replyContext['message_id'] ?? 0);
$replySenderUsername = (string)($replyContext['sender_username'] ?? '');
$replyPreview = trim((string)($replyContext['preview'] ?? ''));
$replyLabel = $replySenderUsername !== '' ? '@' . $replySenderUsername : 'deleted message';
$reactions = is_array($message['reactions'] ?? null) ? $message['reactions'] : [];
$viewerReaction = trux_direct_message_normalize_reaction((string)($reactions['viewer_reaction'] ?? ''));
$reactionTotal = max(0, (int)($reactions['total_count'] ?? 0));
$reactionItems = [];
foreach ((is_array($reactions['items'] ?? null) ? $reactions['items'] : []) as $reactionItem) {
  if (!is_array($reactionItem)) {
    continue;
  }

  $reactionSlug = trux_direct_message_normalize_reaction((string)($reactionItem['reaction'] ?? ''));
  $reactionCount = max(0, (int)($reactionItem['count'] ?? 0));
  if ($reactionSlug === '' || $reactionCount < 1) {
    continue;
  }

  $reactionItems[] = [
    'reaction' => $reactionSlug,
    'count' => $reactionCount,
  ];
}
$reactionBadgeItems = array_slice($reactionItems, 0, 3);
$hasReactionBadge = $reactionTotal > 0 && $reactionBadgeItems !== [];
$canQuickDelete = $isMine && $canUnsend && !$isUnsent;
$hasQuickActions = !$isUnsent;
$bubbleClasses = ['messageBubble'];
if ($isMine) {
  $bubbleClasses[] = 'messageBubble--mine';
}
if ($isEdited) {
  $bubbleClasses[] = 'messageBubble--edited';
}
if ($isUnsent) {
  $bubbleClasses[] = 'messageBubble--unsent';
}
if ($attachmentCount > 0) {
  $bubbleClasses[] = 'messageBubble--hasAttachments';
}
?>
<article
  id="message-<?= $messageId ?>"
  class="<?= trux_e(implode(' ', $bubbleClasses)) ?>"
  data-message-bubble="1"
  data-message-id="<?= $messageId ?>"
  data-message-day-key="<?= trux_e((string)($message['day_key'] ?? '')) ?>"
  data-message-day-label="<?= trux_e((string)($message['day_label'] ?? '')) ?>"
  data-message-exact-time="<?= trux_e($exactTime) ?>"
  data-message-body-raw="<?= trux_e($messageBody) ?>"
  data-message-sender-username="<?= trux_e($messageSenderUsername) ?>"
  data-message-reply-to-id="<?= $replyTargetId ?>"
  data-message-viewer-reaction="<?= trux_e($viewerReaction) ?>"
  data-message-reaction-total-count="<?= $reactionTotal ?>"
  data-message-can-edit="<?= $canEdit ? '1' : '0' ?>"
  data-message-can-unsend="<?= $canUnsend ? '1' : '0' ?>"
  data-message-is-unsent="<?= $isUnsent ? '1' : '0' ?>">
  <div class="messageBubble__meta">
    <div class="messageBubble__metaMain">
      <span class="messageBubble__author"><?= $isMine ? 'You' : trux_e(trux_direct_message_actor_label($messageSenderUsername, $messageSenderDisplayName)) ?></span>
      <?php if ($messageTime !== ''): ?>
        <span class="muted" data-time-ago="1" data-time-source="<?= trux_e($messageTime) ?>" title="<?= trux_e($exactTime) ?>">
          <?= trux_e((string)($message['time_ago'] ?? trux_time_ago($messageTime))) ?>
        </span>
      <?php endif; ?>
      <?php if ($isEdited): ?>
        <span class="messageBubble__statusTag" title="<?= trux_e((string)($message['edited_at'] ?? '')) ?>">Edited</span>
      <?php endif; ?>
      <?php if ($isUnsent): ?>
        <span class="messageBubble__statusTag">Deleted</span>
      <?php endif; ?>
    </div>
    <div class="contentMenu messageBubble__menu" data-content-menu="1" data-dm-message-menu="1">
      <button class="contentMenu__trigger" type="button" aria-label="Open message actions" data-content-menu-trigger="1">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M6.5 12a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Z" fill="currentColor" />
        </svg>
      </button>
      <div class="contentMenu__panel" role="menu" aria-label="Message actions">
        <?php if ($canCopyBody): ?>
          <button class="contentMenu__item" type="button" role="menuitem" data-message-copy="1" data-message-id="<?= $messageId ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M9 9.75h8.25A1.75 1.75 0 0 1 19 11.5v7.75A1.75 1.75 0 0 1 17.25 21H9A1.75 1.75 0 0 1 7.25 19.25V11.5A1.75 1.75 0 0 1 9 9.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
              <path d="M5.75 14.25V6.75A1.75 1.75 0 0 1 7.5 5h7.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Copy text</span>
          </button>
        <?php endif; ?>
        <?php if ($isMine && $canEdit): ?>
          <button class="contentMenu__item" type="button" role="menuitem" data-message-edit="1" data-message-id="<?= $messageId ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="m5.5 16.75-.75 3.5 3.5-.75L18.5 9.25a1.77 1.77 0 0 0 0-2.5l-1.25-1.25a1.77 1.77 0 0 0-2.5 0L5.5 16.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
              <path d="m13.5 6.75 3.75 3.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
            <span>Edit message</span>
          </button>
        <?php endif; ?>
        <?php if ($isMine && $canUnsend): ?>
          <button class="contentMenu__item contentMenu__item--danger" type="button" role="menuitem" data-message-unsend="1" data-message-id="<?= $messageId ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Delete message</span>
          </button>
        <?php endif; ?>
        <?php if ($canReportMessage): ?>
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
        <?php endif; ?>
        <?php if ($exactTime !== ''): ?>
          <button class="contentMenu__item" type="button" role="menuitem" data-message-show-time="1" data-message-id="<?= $messageId ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 4.75A7.25 7.25 0 1 0 19.25 12 7.25 7.25 0 0 0 12 4.75Z" fill="none" stroke="currentColor" stroke-width="1.7"/>
              <path d="M12 8.25v4l2.75 1.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>View timestamp</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="messageBubble__content" data-message-content="1">
    <?php if ($replyContext && $replyTargetId > 0): ?>
      <button
        class="messageBubble__replyContext"
        type="button"
        data-message-reply-jump="1"
        data-target-message-id="<?= $replyTargetId ?>"
        aria-label="Jump to the original message from <?= trux_e($replyLabel) ?>">
        <span class="messageBubble__replyContextLabel">Replying to <?= trux_e($replyLabel) ?></span>
        <?php if ($replyPreview !== ''): ?>
          <span class="messageBubble__replyContextPreview"><?= trux_e($replyPreview) ?></span>
        <?php endif; ?>
      </button>
    <?php endif; ?>

    <?php if ($isUnsent): ?>
      <div class="messageBubble__body messageBubble__body--muted"><em><?= trux_e(trux_direct_message_deleted_copy()) ?></em></div>
    <?php elseif ($messageBody !== ''): ?>
      <div class="messageBubble__body"><?= (string)($message['body_html'] ?? trux_render_direct_message_body($messageBody)) ?></div>
    <?php endif; ?>
    <?= trux_render_direct_message_attachments($message) ?>
  </div>
  <?php if ($hasQuickActions): ?>
    <div class="messageBubble__hoverActions" data-message-hover-actions="1" aria-label="Quick message actions">
      <button
        class="messageBubble__hoverAction<?= $viewerReaction !== '' ? ' is-active' : '' ?>"
        type="button"
        data-message-quick-action="react"
        data-message-reaction-trigger="1"
        aria-label="React to message"
        aria-pressed="<?= $viewerReaction !== '' ? 'true' : 'false' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M8.25 10.75V20H6.5a1.75 1.75 0 0 1-1.75-1.75v-5.75A1.75 1.75 0 0 1 6.5 10.75h1.75Zm2.5 9.25h4.65a2.6 2.6 0 0 0 2.52-2l1.05-4.55a2.27 2.27 0 0 0-2.22-2.8h-3v-4A2.4 2.4 0 0 0 11.35 4.3L9.7 8.05a5.37 5.37 0 0 0-.45 2.18v8.02c0 .97.78 1.75 1.75 1.75Z" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <button class="messageBubble__hoverAction" type="button" data-message-quick-action="reply" aria-label="Reply to message">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M10.25 8.25 5.75 12l4.5 3.75M6.25 12h7.5a4.5 4.5 0 0 1 4.5 4.5v.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <?php if ($canQuickDelete): ?>
        <button class="messageBubble__hoverAction messageBubble__hoverAction--danger" type="button" data-message-quick-action="delete" aria-label="Delete message">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M5.75 7.25h12.5M9.25 7.25V5.5h5.5v1.75m-7.5 0 1 10.25a1.5 1.5 0 0 0 1.5 1.35h4.5a1.5 1.5 0 0 0 1.5-1.35l1-10.25M10.5 10.5v5.25m3 0V10.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      <?php endif; ?>
      <div class="messageBubble__reactionPicker" hidden data-message-reaction-picker="1"></div>
    </div>
  <?php endif; ?>
  <?php if ($canQuickDelete): ?>
    <div class="messageBubble__deleteConfirm" hidden data-message-delete-confirm="1">
      <span>Delete this message?</span>
      <button type="button" data-message-delete-confirm-yes="1">Yes</button>
      <button type="button" data-message-delete-confirm-no="1">No</button>
    </div>
  <?php endif; ?>
  <div
    class="messageBubble__reactionBadge"
    <?= $hasReactionBadge ? '' : ' hidden' ?>
    data-message-reaction-badge="1"
    aria-label="<?= trux_e($reactionTotal === 1 ? '1 reaction' : $reactionTotal . ' reactions') ?>">
    <span class="messageBubble__reactionCluster" aria-hidden="true">
      <?php foreach ($reactionBadgeItems as $reactionItem): ?>
        <?= trux_render_direct_message_reaction_html((string)$reactionItem['reaction']) ?>
      <?php endforeach; ?>
    </span>
    <span data-message-reaction-count="1"><?= $reactionTotal ?></span>
  </div>
  <?php if ($isMine && !$isUnsent): ?>
    <div class="messageBubble__readStatus" aria-label="<?= $isRead ? 'Read' : 'Delivered' ?>" data-message-read-status="<?= $isRead ? 'read' : 'sent' ?>">
      <?php if ($isRead): ?>
        <svg class="messageBubble__readTick messageBubble__readTick--read" viewBox="0 0 20 14" aria-hidden="true" focusable="false" width="16" height="16">
          <path d="M1 7 6 12 13 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M7 7 12 12 19 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      <?php else: ?>
        <svg class="messageBubble__readTick messageBubble__readTick--sent" viewBox="0 0 14 14" aria-hidden="true" focusable="false" width="14" height="14">
          <path d="M1 7 6 12 13 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</article>
