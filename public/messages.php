<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'messages';
$pageLayout = 'app';

trux_require_login();
$me = trux_current_user();
if (!$me) {
  trux_redirect('/login.php');
}

$viewerId = (int)$me['id'];
$selectedConversationId = trux_int_param('id', 0);
$withUsername = trim(trux_str_param('with', ''));

$selectedConversation = null;
$selectedMessages = [];
$recipientUser = null;

if ($selectedConversationId > 0) {
  $selectedConversation = trux_fetch_direct_conversation_for_user($selectedConversationId, $viewerId);
  if (!$selectedConversation) {
    trux_flash_set('error', 'Conversation not found.');
    trux_redirect('/messages.php');
  }
} elseif ($withUsername !== '') {
  if (trux_is_report_system_user($withUsername)) {
    $systemUser = trux_fetch_report_system_user();
    if ($systemUser && (int)$systemUser['id'] !== $viewerId) {
      $existingConversation = trux_fetch_direct_conversation_between($viewerId, (int)$systemUser['id']);
      if ($existingConversation) {
        trux_redirect('/messages.php?id=' . (int)$existingConversation['id']);
      }
    }

    trux_flash_set('error', 'This inbox only appears after the system sends you an update.');
    trux_redirect('/messages.php');
  }

  $recipientUser = trux_fetch_user_by_username($withUsername);
  if (!$recipientUser || (int)$recipientUser['id'] === $viewerId) {
    trux_flash_set('error', 'User not found.');
    trux_redirect('/messages.php');
  }

  $selectedConversation = trux_fetch_direct_conversation_between($viewerId, (int)$recipientUser['id']);
}

$conversations = trux_fetch_direct_conversations($viewerId, 50);

if (!$selectedConversation && $withUsername === '' && $conversations) {
  $selectedConversation = trux_fetch_direct_conversation_for_user((int)$conversations[0]['id'], $viewerId);
}

if ($selectedConversation) {
  $selectedMessages = trux_fetch_direct_messages((int)$selectedConversation['id'], $viewerId, 150);
  if (!$recipientUser) {
    $recipientUser = trux_fetch_user_by_id((int)$selectedConversation['other_user_id']);
  }
}

$dmBlockedByViewer = $recipientUser && trux_has_blocked_user($viewerId, (int)$recipientUser['id']);
$dmBlockedByThem   = $recipientUser && trux_has_blocked_user((int)$recipientUser['id'], $viewerId);
$viewerDmRestricted = trux_moderation_is_user_dm_restricted($viewerId);
$recipientIsReportSystem = $recipientUser && trux_is_report_system_user((string)$recipientUser['username']);
$recipientLabel = $recipientUser
  ? trux_direct_message_actor_label((string)$recipientUser['username'], (string)($recipientUser['display_name'] ?? ''))
  : '';

$activeConversationId = (int)($selectedConversation['id'] ?? 0);

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--messages">
  <section class="inlineHeader inlineHeader--messages">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Workspace</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title">Messages</h2>
        <p class="inlineHeader__copy">Private 1-to-1 conversations in a focused inbox and thread workspace.</p>
      </div>
    </div>
    <div class="inlineHeader__aside">
      <div class="inlineHeader__meta">
        <span>@<?= trux_e((string)$me['username']) ?></span>
        <strong><?= count($conversations) ?> conversation<?= count($conversations) === 1 ? '' : 's' ?></strong>
      </div>
    </div>
  </section>

  <section class="messagesLayout<?= $activeConversationId > 0 ? ' is-thread-active' : '' ?>" data-messages-active-conversation-id="<?= $activeConversationId ?>">
    <aside class="messagesSidebar workspacePane">
      <div class="workspacePane__head">
        <div>
          <span class="workspacePane__eyebrow">Inbox</span>
          <h3>Recent conversations</h3>
        </div>
      </div>

      <?php if (!$conversations): ?>
        <div class="workspacePane__empty muted">No conversations yet. Start one from a user profile.</div>
      <?php else: ?>
        <div class="messagesList">
          <?php foreach ($conversations as $conversation): ?>
            <?php
            $conversationId = (int)$conversation['id'];
            $isActive = $conversationId === $activeConversationId;
            $unreadCount = (int)($conversation['unread_count'] ?? 0);
            $lastAt = (string)($conversation['last_message_created_at'] ?? $conversation['updated_at'] ?? '');
            ?>
            <a class="messagesList__item<?= $isActive ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/messages.php?id=<?= $conversationId ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
              <div class="messagesList__row">
                <span class="messagesList__user"><?= trux_e(trux_direct_message_actor_label((string)$conversation['other_username'], (string)($conversation['other_display_name'] ?? ''))) ?></span>
                <?php if ($lastAt !== ''): ?>
                  <span class="messagesList__time muted" data-time-ago="1" data-time-source="<?= trux_e($lastAt) ?>" title="<?= trux_e(trux_format_exact_time($lastAt)) ?>">
                    <?= trux_e(trux_time_ago($lastAt)) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="messagesList__row">
                <span class="messagesList__preview muted"><?= trux_e(trux_direct_message_preview((string)($conversation['last_message_body'] ?? ''))) ?></span>
                <?php if ($unreadCount > 0): ?>
                  <span class="menuBadge"><?= $unreadCount ?></span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </aside>

    <section class="messagesThread workspacePane">
      <?php if ($recipientUser && $dmBlockedByThem): ?>
        <div class="messagesThread__state messagesThread__state--center">
          <p class="messagesThread__stateTitle"><?= trux_e($recipientLabel) ?> has blocked you</p>
          <p class="muted">You are not able to send messages to this user.</p>
        </div>
      <?php elseif ($recipientUser && $viewerDmRestricted): ?>
        <div class="messagesThread__state messagesThread__state--center">
          <p class="messagesThread__stateTitle">Direct messages are restricted</p>
          <p class="muted">You can still read messages and receive moderation updates, but you cannot send new direct messages right now.</p>
        </div>
      <?php elseif ($recipientUser && $dmBlockedByViewer): ?>
        <div class="messagesThread__state messagesThread__state--center">
          <p class="messagesThread__stateTitle">You have blocked <?= trux_e($recipientLabel) ?></p>
          <p class="muted">Unblock this user from their profile to send messages.</p>
        </div>
      <?php elseif ($recipientUser): ?>
        <header class="messagesThread__head">
          <div class="messagesThread__titleBlock">
            <a class="messagesThread__back shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/messages.php">Back to inbox</a>
            <h3><?= trux_e($recipientLabel) ?></h3>
            <div class="muted">
              <?php if ($recipientIsReportSystem): ?>
                Automated moderation updates. Replies are disabled for this inbox.
              <?php elseif ($activeConversationId > 0): ?>
                Conversation active
              <?php else: ?>
                New conversation
              <?php endif; ?>
            </div>
          </div>
          <div class="row messagesThread__toolbar">
            <?php if ($activeConversationId > 0): ?>
              <form method="post" action="<?= TRUX_BASE_URL ?>/mark_conversation_read.php" class="inline" data-no-fx="1">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="id" value="<?= $activeConversationId ?>">
                <button class="shellButton shellButton--ghost" type="submit">Mark as read</button>
              </form>
            <?php endif; ?>
            <?php if (!$recipientIsReportSystem): ?>
              <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$recipientUser['username']) ?>">View profile</a>
            <?php endif; ?>
          </div>
        </header>

        <div class="messagesThread__messages">
          <?php if (!$selectedMessages): ?>
            <div class="muted"><?= $recipientIsReportSystem ? 'No moderation updates yet.' : 'No messages yet. Say hello.' ?></div>
          <?php else: ?>
            <?php foreach ($selectedMessages as $message): ?>
              <?php
              $isMine = (int)$message['sender_user_id'] === $viewerId;
              $messageTime = (string)$message['created_at'];
              $messageId = (int)$message['id'];
              $messageSenderUsername = (string)($message['sender_username'] ?? '');
              $canReportMessage = !$isMine && !trux_is_report_system_user($messageSenderUsername);
              $messageReportLabel = 'Message #' . $messageId . ' from @' . $messageSenderUsername;
              $messageReportUrl = TRUX_BASE_URL . '/messages.php?id=' . $activeConversationId . '#message-' . $messageId;
              ?>
              <article id="message-<?= $messageId ?>" class="messageBubble<?= $isMine ? ' messageBubble--mine' : '' ?>">
                <div class="messageBubble__meta">
                  <div class="messageBubble__metaMain">
                    <span class="messageBubble__author"><?= $isMine ? 'You' : trux_e(trux_direct_message_actor_label($messageSenderUsername, (string)($message['sender_display_name'] ?? ''))) ?></span>
                    <span class="muted" data-time-ago="1" data-time-source="<?= trux_e($messageTime) ?>" title="<?= trux_e(trux_format_exact_time($messageTime)) ?>">
                      <?= trux_e(trux_time_ago($messageTime)) ?>
                    </span>
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
                <div class="messageBubble__body"><?= trux_render_comment_body((string)$message['body']) ?></div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($recipientIsReportSystem): ?>
          <div class="messagesThread__empty muted messagesThread__state">
            This inbox only sends automated report updates. Replies are not accepted.
          </div>
        <?php elseif ($viewerDmRestricted): ?>
          <div class="messagesThread__empty muted messagesThread__state">
            Direct messaging is currently disabled on your account. You can still review this thread and receive system updates here.
          </div>
        <?php else: ?>
          <form class="messagesComposer" method="post" action="<?= TRUX_BASE_URL ?>/send_message.php">
            <?= trux_csrf_field() ?>
            <?php if ($activeConversationId > 0): ?>
              <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
            <?php else: ?>
              <input type="hidden" name="recipient_id" value="<?= (int)$recipientUser['id'] ?>">
            <?php endif; ?>
            <label class="field">
              <span>Message</span>
              <textarea name="body" rows="4" maxlength="2000" required placeholder="Write a private message..." data-mention-input="1"></textarea>
            </label>
            <div class="messagesComposer__actions">
              <span class="muted">Only text messages for now.</span>
              <button class="shellButton shellButton--accent" type="submit">Send message</button>
            </div>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <div class="messagesThread__empty muted messagesThread__state">
          Select a conversation from the inbox or start one from a user profile.
        </div>
      <?php endif; ?>
    </section>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
