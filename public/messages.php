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

if ($selectedConversation) {
  $selectedMessages = trux_fetch_direct_messages((int)$selectedConversation['id'], $viewerId, 150);
  if (!$recipientUser) {
    $recipientUser = trux_fetch_user_by_id((int)$selectedConversation['other_user_id']);
  }
}

$dmBlockedByViewer = $recipientUser && trux_has_blocked_user($viewerId, (int)$recipientUser['id']);
$dmBlockedByThem = $recipientUser && trux_has_blocked_user((int)$recipientUser['id'], $viewerId);
$viewerDmRestricted = trux_moderation_is_user_dm_restricted($viewerId);
$recipientIsReportSystem = $recipientUser && trux_is_report_system_user((string)$recipientUser['username']);
$recipientLabel = $recipientUser
  ? trux_direct_message_actor_label((string)$recipientUser['username'], (string)($recipientUser['display_name'] ?? ''))
  : '';

$activeConversationId = (int)($selectedConversation['id'] ?? 0);
$messagesLayoutClasses = [];
if ($recipientUser) {
  $messagesLayoutClasses[] = 'is-thread-open';
}
if ($activeConversationId > 0) {
  $messagesLayoutClasses[] = 'is-thread-active';
}

$viewerUsername = (string)($me['username'] ?? '');
$viewerAvatarUrl = trux_public_url((string)($me['avatar_path'] ?? ''));
$recipientUsername = $recipientUser ? (string)($recipientUser['username'] ?? '') : '';
$recipientAvatarUrl = $recipientUser ? trux_public_url((string)($recipientUser['avatar_path'] ?? '')) : '';
$conversationCount = count($conversations);
$threadStatusCopy = '';
if ($recipientUser) {
  if ($recipientIsReportSystem) {
    $threadStatusCopy = 'Automated updates only';
  } elseif ($activeConversationId > 0) {
    $threadStatusCopy = 'Private thread';
  } else {
    $threadStatusCopy = 'Ready to message';
  }
}

$canCompose = $recipientUser
  && !$recipientIsReportSystem
  && !$viewerDmRestricted
  && !$dmBlockedByViewer
  && !$dmBlockedByThem;

$renderDmAvatar = static function (
  string $username,
  string $avatarUrl = '',
  string $className = 'dmAvatar',
  string $fallbackLabel = ''
): string {
  $seed = $username !== '' ? $username : $fallbackLabel;
  $initialSeed = $username !== '' ? $username : ($fallbackLabel !== '' ? $fallbackLabel : 'T');
  $initial = strtoupper(mb_substr($initialSeed, 0, 1));
  $theme = trux_direct_message_avatar_theme($seed);

  ob_start();
  ?>
  <span class="<?= trux_e($className) ?> dmAvatar dmAvatar--<?= trux_e($theme) ?><?= $avatarUrl !== '' ? ' ' . trux_e($className) . '--image dmAvatar--image' : '' ?>" aria-hidden="true">
    <?php if ($avatarUrl !== ''): ?>
      <img class="<?= trux_e($className) ?>__image dmAvatar__image" src="<?= trux_e($avatarUrl) ?>" alt="" loading="lazy" decoding="async">
    <?php else: ?>
      <span class="<?= trux_e($className) ?>__fallback dmAvatar__fallback"><?= trux_e($initial) ?></span>
    <?php endif; ?>
  </span>
  <?php

  return trim((string)ob_get_clean());
};

$renderDmEmptyIcon = static function (): string {
  return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4.75 6.75h14.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H8.75l-4 2.55V8.25a1.5 1.5 0 0 1 1.5-1.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 11.25h8M8 14.25h5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
};

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--messages">
  <section
    class="messagesLayout<?= $messagesLayoutClasses ? ' ' . trux_e(implode(' ', $messagesLayoutClasses)) : '' ?>"
    data-messages-layout="1"
    data-messages-active-conversation-id="<?= $activeConversationId ?>">
    <section class="messagesMobileBar" aria-label="Messages navigation">
      <div class="messagesMobileBar__state messagesMobileBar__state--inbox">
        <div class="messagesMobileBar__titleWrap">
          <h2 class="messagesMobileBar__title">Messages</h2>
        </div>
        <div class="messagesMobileBar__actions">
          <button
            class="shellAction messagesMobileBar__iconButton"
            type="button"
            aria-label="Start a new message"
            data-shell-sheet-open="message-recipient"
            data-new-message-open="1">
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M4 19.25h3.75L18.6 8.4a1.8 1.8 0 0 0 0-2.55l-.45-.45a1.8 1.8 0 0 0-2.55 0L4.75 16.25V20h3.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
              <path d="m13.75 7.25 3 3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
          </button>
          <?= $renderDmAvatar($viewerUsername, $viewerAvatarUrl, 'messagesMobileBar__avatar', '@' . $viewerUsername) ?>
        </div>
      </div>

      <div class="messagesMobileBar__state messagesMobileBar__state--thread<?= $recipientUser ? '' : ' is-empty' ?>">
        <button class="messagesMobileBar__back" type="button" data-thread-back="1" aria-label="Back to messages">
          <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
            <path d="M15.5 5.75 8.5 12l7 6.25" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Back</span>
        </button>

        <?php if ($recipientUser): ?>
          <div class="messagesMobileBar__threadIdentity">
            <?= $renderDmAvatar($recipientUsername, $recipientAvatarUrl, 'messagesMobileBar__threadAvatar', $recipientLabel) ?>
            <div class="messagesMobileBar__threadCopy">
              <strong><?= trux_e($recipientLabel) ?></strong>
              <span class="messagesMobileBar__threadStatus">
                <span class="messagesThread__statusDot" aria-hidden="true"></span>
                <span><?= trux_e($threadStatusCopy) ?></span>
              </span>
            </div>
          </div>
        <?php endif; ?>

        <button
          class="shellAction messagesMobileBar__iconButton"
          type="button"
          aria-label="Open thread actions"
          data-shell-sheet-open="message-actions"
          <?= $recipientUser ? '' : 'hidden' ?>>
          <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
            <path d="M6.5 12a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Zm5.5 0a1.5 1.5 0 1 0 0-.01V12Z" fill="currentColor" />
          </svg>
        </button>
      </div>
    </section>

    <aside class="messagesSidebar workspacePane" data-messages-sidebar="1">
      <div class="messagesSidebar__header">
        <div class="messagesSidebar__titleWrap">
          <h2>Messages</h2>
          <p class="muted">@<?= trux_e($viewerUsername) ?> · <?= $conversationCount ?> conversation<?= $conversationCount === 1 ? '' : 's' ?></p>
        </div>
      </div>

      <div class="messagesSidebar__search">
        <label class="topSearch__field messagesSidebar__searchField">
          <span class="topSearch__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path fill="currentColor" d="M10.5 4a6.5 6.5 0 1 0 4.02 11.61l4.43 4.43a1 1 0 0 0 1.41-1.41l-4.43-4.43A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9a4.5 4.5 0 0 1 0-9Z" />
            </svg>
          </span>
          <input
            class="topSearch__input"
            type="text"
            placeholder="Search conversations..."
            autocomplete="off"
            data-conversation-search="1">
        </label>
      </div>

      <div class="messagesSidebar__listWrap">
        <div class="messagesList" data-conversation-list="1">
          <?php if (!$conversations): ?>
            <div class="messagesList__empty">
              <strong>No conversations yet</strong>
              <p class="muted">Start a new one from a user profile or the new message button.</p>
            </div>
          <?php else: ?>
            <?php foreach ($conversations as $conversation): ?>
              <?php
              $conversationId = (int)$conversation['id'];
              $isActive = $conversationId === $activeConversationId;
              $unreadCount = (int)($conversation['unread_count'] ?? 0);
              $lastAt = (string)($conversation['last_message_created_at'] ?? $conversation['updated_at'] ?? '');
              $otherUsername = (string)($conversation['other_username'] ?? '');
              $otherDisplayName = (string)($conversation['other_display_name'] ?? '');
              $conversationLabel = trux_direct_message_actor_label($otherUsername, $otherDisplayName);
              $preview = trux_direct_message_preview((string)($conversation['last_message_body'] ?? ''));
              $conversationAvatarUrl = trux_public_url((string)($conversation['other_avatar_path'] ?? ''));
              ?>
              <a
                class="messagesList__item<?= $isActive ? ' is-active' : '' ?>"
                href="<?= TRUX_BASE_URL ?>/messages.php?id=<?= $conversationId ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
                data-conversation-item="1"
                data-search-handle="<?= trux_e(strtolower($conversationLabel . ' ' . $otherUsername)) ?>"
                data-search-preview="<?= trux_e(strtolower($preview)) ?>">
                <?= $renderDmAvatar($otherUsername, $conversationAvatarUrl, 'messagesList__avatar', $conversationLabel) ?>

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
            <?php endforeach; ?>
          <?php endif; ?>

          <div class="messagesList__empty messagesList__empty--search" data-conversation-empty="1" hidden>
            <strong>No matches found</strong>
            <p class="muted">Try a different username or preview keyword.</p>
          </div>
        </div>
      </div>

      <div class="messagesSidebar__footer">
        <button
          class="messagesSidebar__new shellButton shellButton--ghost"
          type="button"
          data-shell-sheet-open="message-recipient"
          data-new-message-open="1">
          + New message
        </button>
      </div>
    </aside>

    <section class="messagesThread workspacePane" data-messages-thread="1">
      <?php if ($recipientUser): ?>
        <header class="messagesThread__head">
          <div class="messagesThread__identity">
            <?= $renderDmAvatar($recipientUsername, $recipientAvatarUrl, 'messagesThread__avatar', $recipientLabel) ?>
            <div class="messagesThread__identityCopy">
              <h3><?= trux_e($recipientLabel) ?></h3>
              <div class="messagesThread__status">
                <span class="messagesThread__statusDot" aria-hidden="true"></span>
                <span><?= trux_e($threadStatusCopy) ?></span>
              </div>
            </div>
          </div>

          <div class="messagesThread__headActions">
            <?php if ($activeConversationId > 0): ?>
              <form method="post" action="<?= TRUX_BASE_URL ?>/mark_conversation_read.php" class="inline" data-no-fx="1">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="id" value="<?= $activeConversationId ?>">
                <button class="btn btn--small btn--ghost" type="submit">Mark as read</button>
              </form>
            <?php endif; ?>

            <?php if (!$recipientIsReportSystem): ?>
              <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e(rawurlencode($recipientUsername)) ?>">View profile</a>
            <?php endif; ?>
          </div>
        </header>

        <div class="messagesThread__messages">
          <?php if ($dmBlockedByThem): ?>
            <div class="messagesThread__stateCard messagesThread__stateCard--center">
              <div class="messagesThread__stateIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
              <div class="messagesThread__stateBody">
                <strong><?= trux_e($recipientLabel) ?> has blocked you</strong>
                <p class="muted">You can review older messages here, but new replies are unavailable.</p>
              </div>
            </div>
          <?php elseif ($viewerDmRestricted): ?>
            <div class="messagesThread__stateCard messagesThread__stateCard--center">
              <div class="messagesThread__stateIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
              <div class="messagesThread__stateBody">
                <strong>Direct messages are restricted</strong>
                <p class="muted">You can still read threads and moderation updates, but sending new messages is currently disabled.</p>
              </div>
            </div>
          <?php elseif ($dmBlockedByViewer): ?>
            <div class="messagesThread__stateCard messagesThread__stateCard--center">
              <div class="messagesThread__stateIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
              <div class="messagesThread__stateBody">
                <strong>You have blocked <?= trux_e($recipientLabel) ?></strong>
                <p class="muted">Unblock this user from the thread actions or their profile to start messaging again.</p>
              </div>
            </div>
          <?php elseif (!$selectedMessages): ?>
            <div class="messagesThread__stateCard messagesThread__stateCard--center">
              <div class="messagesThread__stateIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
              <div class="messagesThread__stateBody">
                <strong><?= $recipientIsReportSystem ? 'No updates yet' : 'No messages yet' ?></strong>
                <p class="muted"><?= $recipientIsReportSystem ? 'This inbox will show automated moderation updates when they are available.' : 'This is the start of your conversation. Send the first message below.' ?></p>
              </div>
            </div>
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

        <?php if ($canCompose): ?>
          <form class="messagesComposer" method="post" action="<?= TRUX_BASE_URL ?>/send_message.php" data-messages-composer="1">
            <?= trux_csrf_field() ?>
            <?php if ($activeConversationId > 0): ?>
              <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
            <?php else: ?>
              <input type="hidden" name="recipient_id" value="<?= (int)$recipientUser['id'] ?>">
            <?php endif; ?>

            <div class="messagesComposer__row">
              <textarea
                name="body"
                rows="1"
                maxlength="2000"
                required
                placeholder="Write a message to <?= trux_e($recipientLabel) ?>..."
                data-mention-input="1"
                data-messages-input="1"></textarea>
              <button class="shellButton shellButton--accent messagesComposer__submit" type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M4.75 12h12.5M12.5 5.75 19 12l-6.5 6.25" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>Send message</span>
              </button>
            </div>

            <div class="messagesComposer__hint muted">Only text messages for now.</div>
          </form>
        <?php elseif ($recipientIsReportSystem): ?>
          <div class="messagesThread__footerNote muted">This inbox only sends automated report updates. Replies are not accepted.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="messagesThread__empty">
          <div class="messagesThread__emptyIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
          <div class="messagesThread__emptyCopy">
            <h3>Select a conversation</h3>
            <p class="muted">Or start a new one from a user profile.</p>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </section>

  <div class="shellSheet" data-shell-sheet="message-recipient" hidden>
    <div class="shellSheet__backdrop" data-shell-sheet-close="1"></div>
    <section class="shellSheet__panel messagesRecipientSheet" role="dialog" aria-modal="true" aria-labelledby="messageRecipientTitle">
      <header class="shellSheet__head">
        <div>
          <span class="shellSheet__eyebrow">New message</span>
          <h2 id="messageRecipientTitle">Start a conversation</h2>
        </div>
        <button class="iconBtn" type="button" aria-label="Close new message panel" data-shell-sheet-close="1">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
      </header>

      <div class="messagesRecipientSheet__form">
        <div class="messagesRecipientSheet__lookup">
          <label class="field">
            <span>Username</span>
            <input
              type="text"
              maxlength="32"
              autocomplete="off"
              spellcheck="false"
              placeholder="Search @username"
              data-message-recipient-input="1">
          </label>

          <div class="messagesRecipientSheet__results" data-message-recipient-results="1"></div>
        </div>

        <div class="messagesRecipientSheet__status muted" data-message-recipient-status="1">
          Search for a username to open an existing conversation or start a new one.
        </div>
      </div>
    </section>
  </div>

  <div class="shellSheet" data-shell-sheet="message-actions" hidden>
    <div class="shellSheet__backdrop" data-shell-sheet-close="1"></div>
    <section class="shellSheet__panel messagesActionSheet" role="dialog" aria-modal="true" aria-labelledby="messageActionsTitle">
      <header class="shellSheet__head">
        <div>
          <span class="shellSheet__eyebrow">Thread actions</span>
          <h2 id="messageActionsTitle"><?= $recipientUser ? trux_e($recipientLabel) : 'Conversation' ?></h2>
        </div>
        <button class="iconBtn" type="button" aria-label="Close thread actions" data-shell-sheet-close="1">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
      </header>

      <?php if ($recipientUser): ?>
        <div class="shellSheet__stack">
          <?php if ($activeConversationId > 0): ?>
            <form class="shellSheet__form" method="post" action="<?= TRUX_BASE_URL ?>/mark_conversation_read.php">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="id" value="<?= $activeConversationId ?>">
              <button class="shellSheet__link" type="submit">
                <span class="shellSheet__itemIcon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M5.75 12.5 9.5 16.25 18.25 7.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </span>
                <span class="shellSheet__itemMain">
                  <strong>Mark as read</strong>
                  <span>Clear unread indicators for this thread.</span>
                </span>
              </button>
            </form>
          <?php endif; ?>

          <?php if (!$recipientIsReportSystem): ?>
            <a class="shellSheet__link" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e(rawurlencode($recipientUsername)) ?>">
              <span class="shellSheet__itemIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                  <path d="M12 12a3.5 3.5 0 1 0 0-.01V12ZM5 19a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <span class="shellSheet__itemMain">
                <strong>View profile</strong>
                <span>Open <?= trux_e($recipientLabel) ?>'s profile page.</span>
              </span>
            </a>

            <form
              class="shellSheet__form"
              method="post"
              action="<?= TRUX_BASE_URL ?>/block_user.php"
              <?= !$dmBlockedByViewer ? 'data-confirm="Block ' . trux_e($recipientLabel) . '? They will no longer be able to message you."' : '' ?>>
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="<?= $dmBlockedByViewer ? 'unblock' : 'block' ?>">
              <input type="hidden" name="user_id" value="<?= (int)$recipientUser['id'] ?>">
              <button class="shellSheet__link" type="submit">
                <span class="shellSheet__itemIcon<?= $dmBlockedByViewer ? '' : ' shellSheet__itemIcon--danger' ?>" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M6.5 6.5 17.5 17.5M17.5 6.5 6.5 17.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                  </svg>
                </span>
                <span class="shellSheet__itemMain">
                  <strong><?= $dmBlockedByViewer ? 'Unblock user' : 'Block user' ?></strong>
                  <span><?= $dmBlockedByViewer ? 'Allow direct messages again from this user.' : 'Prevent this user from messaging you.' ?></span>
                </span>
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="messagesRecipientSheet__status muted">Open a conversation to see thread actions here.</div>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
