<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'messages';
$pageLayout = 'app';
$isThreadPartial = trux_str_param('partial', '') === 'thread';

$respondThreadPartialError = static function (int $statusCode, string $message): void {
  header('Content-Type: text/html; charset=utf-8');
  http_response_code($statusCode);
  echo '<div class="messagesThread__partialError" data-messages-thread-error="1">' . trux_e($message) . '</div>';
  exit;
};

$me = trux_current_user();
if (!$me) {
  if ($isThreadPartial) {
    $respondThreadPartialError(401, 'Please log in to continue.');
  }

  trux_require_login();
}

$viewerId = (int)$me['id'];
$selectedConversationId = trux_int_param('id', 0);
$withUsername = trim(trux_str_param('with', ''));

$selectedConversation = null;
$selectedMessages = [];
$oldestLoadedMessageId = 0;
$latestLoadedMessageId = 0;
$hasOlderMessages = false;
$recipientUser = null;

if ($selectedConversationId > 0) {
  $selectedConversation = trux_fetch_direct_conversation_for_user($selectedConversationId, $viewerId);
  if (!$selectedConversation) {
    if ($isThreadPartial) {
      $respondThreadPartialError(404, 'Conversation not found.');
    }

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

    if ($isThreadPartial) {
      $respondThreadPartialError(403, 'This inbox only appears after the system sends you an update.');
    }

    trux_flash_set('error', 'This inbox only appears after the system sends you an update.');
    trux_redirect('/messages.php');
  }

  $recipientUser = trux_fetch_user_by_username($withUsername);
  if (!$recipientUser || (int)$recipientUser['id'] === $viewerId) {
    if ($isThreadPartial) {
      $respondThreadPartialError(404, 'User not found.');
    }

    trux_flash_set('error', 'User not found.');
    trux_redirect('/messages.php');
  }

  $selectedConversation = trux_fetch_direct_conversation_between($viewerId, (int)$recipientUser['id']);
}

$conversations = trux_fetch_direct_conversations($viewerId, 50);

if ($selectedConversation) {
  $selectedMessages = trux_fetch_direct_messages((int)$selectedConversation['id'], $viewerId, 150);
  if ($selectedMessages) {
    $loadedMessageIds = array_values(array_filter(array_map(
      static fn (array $message): int => (int)($message['id'] ?? 0),
      $selectedMessages
    )));
    if ($loadedMessageIds) {
      $oldestLoadedMessageId = min($loadedMessageIds);
      $latestLoadedMessageId = max($loadedMessageIds);
      $hasOlderMessages = trux_direct_conversation_has_older_messages(
        (int)$selectedConversation['id'],
        $viewerId,
        $oldestLoadedMessageId
      );
    }
  }
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
$recipientUsername = $recipientUser ? (string)($recipientUser['username'] ?? '') : '';
$recipientHandle = $recipientUsername !== '' ? '@' . $recipientUsername : '';

$activeConversationId = (int)($selectedConversation['id'] ?? 0);
$viewerReactionPickerJson = json_encode(
  trux_direct_message_fetch_viewer_top_reactions($viewerId),
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
$messagesLayoutClasses = [];
if ($recipientUser) {
  $messagesLayoutClasses[] = 'is-thread-open';
}
if ($activeConversationId > 0) {
  $messagesLayoutClasses[] = 'is-thread-active';
}

$viewerUsername = (string)($me['username'] ?? '');
$viewerAvatarUrl = trux_public_url((string)($me['avatar_path'] ?? ''));
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

$pageTitle = $recipientUser
  ? 'Messages · ' . $recipientLabel . ' · ' . TRUX_APP_NAME
  : 'Messages · ' . TRUX_APP_NAME;

$canCompose = $recipientUser
  && !$recipientIsReportSystem
  && !$viewerDmRestricted
  && !$dmBlockedByViewer
  && !$dmBlockedByThem;

$threadNoticeTitle = '';
$threadNoticeCopy = '';
if ($recipientUser) {
  if ($dmBlockedByThem) {
    $threadNoticeTitle = $recipientLabel . ' has blocked you';
    $threadNoticeCopy = 'You can still review the thread history here, but new replies are unavailable.';
  } elseif ($viewerDmRestricted) {
    $threadNoticeTitle = 'Direct messages are restricted';
    $threadNoticeCopy = 'You can still read existing threads and moderation updates, but sending new messages is currently disabled.';
  } elseif ($dmBlockedByViewer) {
    $threadNoticeTitle = 'You have blocked ' . $recipientLabel;
    $threadNoticeCopy = 'Unblock this user from the thread actions or their profile to resume messaging.';
  }
}

$renderDmEmptyIcon = static function (): string {
  return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4.75 6.75h14.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H8.75l-4 2.55V8.25a1.5 1.5 0 0 1 1.5-1.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 11.25h8M8 14.25h5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
};

$renderMessagesMobileThreadState = static function () use (
  $recipientUser,
  $recipientUsername,
  $recipientAvatarUrl,
  $recipientLabel,
  $recipientHandle,
  $threadStatusCopy
): string {
  ob_start();
  ?>
  <div class="messagesMobileBar__state messagesMobileBar__state--thread<?= $recipientUser ? '' : ' is-empty' ?>">
    <button class="messagesMobileBar__back" type="button" data-thread-back="1" aria-label="Back to messages">
      <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
        <path d="M15.5 5.75 8.5 12l7 6.25" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Back</span>
    </button>

    <?php if ($recipientUser): ?>
      <div class="messagesMobileBar__threadIdentity">
        <?= trux_render_direct_message_avatar($recipientUsername, $recipientAvatarUrl, 'messagesMobileBar__threadAvatar', $recipientLabel) ?>
        <div class="messagesMobileBar__threadCopy">
          <span class="messagesMobileBar__eyebrow">Active transmission</span>
          <strong><?= trux_e($recipientLabel) ?></strong>
          <span class="messagesMobileBar__threadStatus">
            <span class="messagesThread__statusDot" aria-hidden="true"></span>
            <span class="messagesThread__statusHandle"><?= trux_e($recipientHandle) ?></span>
            <span aria-hidden="true">&middot;</span>
            <span data-thread-status-copy="1"><?= trux_e($threadStatusCopy) ?></span>
          </span>
          <span class="messagesThread__typing" data-thread-typing-indicator="1" hidden>
            <span class="messagesThread__typingDots" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
            </span>
            <span class="messagesThread__typingText">typing...</span>
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
  <?php

  return trim((string)ob_get_clean());
};

$renderMessagesThread = static function () use (
  $recipientUser,
  $recipientUsername,
  $recipientAvatarUrl,
  $recipientLabel,
  $recipientHandle,
  $recipientIsReportSystem,
  $threadStatusCopy,
  $activeConversationId,
  $latestLoadedMessageId,
  $oldestLoadedMessageId,
  $hasOlderMessages,
  $threadNoticeTitle,
  $threadNoticeCopy,
  $renderDmEmptyIcon,
  $selectedMessages,
  $viewerReactionPickerJson,
  $viewerId,
  $canCompose
): string {
  ob_start();
  ?>
  <section class="messagesThread workspacePane" data-messages-thread="1">
    <?php if ($recipientUser): ?>
      <header class="messagesThread__head">
        <div class="messagesThread__identity">
          <?= trux_render_direct_message_avatar($recipientUsername, $recipientAvatarUrl, 'messagesThread__avatar', $recipientLabel) ?>
          <div class="messagesThread__identityCopy">
            <span class="messagesThread__eyebrow">Active transmission</span>
            <h3><?= trux_e($recipientLabel) ?></h3>
            <div class="messagesThread__status">
              <span class="messagesThread__statusDot" aria-hidden="true"></span>
              <span class="messagesThread__statusHandle"><?= trux_e($recipientHandle) ?></span>
              <span aria-hidden="true">&middot;</span>
              <span data-thread-status-copy="1"><?= trux_e($threadStatusCopy) ?></span>
            </div>
            <div class="messagesThread__typing" data-thread-typing-indicator="1" hidden>
              <span class="messagesThread__typingDots" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
              </span>
              <span class="messagesThread__typingText">typing...</span>
            </div>
          </div>
        </div>

        <div class="messagesThread__headActions">
          <?php if (!$recipientIsReportSystem): ?>
            <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e(rawurlencode($recipientUsername)) ?>">View profile</a>
          <?php endif; ?>
        </div>
      </header>

      <div class="messagesThread__messagesWrap">
        <div class="messagesThread__transportState" hidden data-message-transport-state="1"></div>

        <div
          class="messagesThread__messages"
          data-message-list="1"
          data-conversation-id="<?= $activeConversationId ?>"
          data-latest-message-id="<?= $latestLoadedMessageId ?>"
          data-oldest-message-id="<?= $oldestLoadedMessageId ?>"
          data-message-picker-reactions="<?= trux_e(is_string($viewerReactionPickerJson) ? $viewerReactionPickerJson : '[]') ?>"
          data-has-more-before="<?= $hasOlderMessages ? '1' : '0' ?>">
          <?php if ($activeConversationId > 0): ?>
            <div class="messagesThread__loadRow" data-message-load-row="1"<?= $hasOlderMessages ? '' : ' hidden' ?>>
              <button class="shellButton shellButton--ghost messagesThread__loadButton" type="button" data-message-load-older="1">
                Load older messages
              </button>
            </div>
          <?php endif; ?>

          <?php if ($threadNoticeTitle !== ''): ?>
            <div class="messagesThread__stateCard messagesThread__stateCard--notice">
              <div class="messagesThread__stateIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
              <div class="messagesThread__stateBody">
                <strong><?= trux_e($threadNoticeTitle) ?></strong>
                <p class="muted"><?= trux_e($threadNoticeCopy) ?></p>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$selectedMessages): ?>
            <div class="messagesThread__stateCard messagesThread__stateCard--center" data-message-empty-state="1">
              <div class="messagesThread__stateIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
              <div class="messagesThread__stateBody">
                <strong><?= $recipientIsReportSystem ? 'No updates yet' : 'Start a private thread with ' . trux_e($recipientHandle) ?></strong>
                <p class="muted"><?= $recipientIsReportSystem ? 'This inbox will show automated moderation updates when they are available.' : 'No messages yet. Send the first transmission below to open the thread.' ?></p>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($selectedMessages as $message): ?>
              <?= trux_render_direct_message_bubble($message, $viewerId, $activeConversationId) ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <button class="shellButton shellButton--accent messagesThread__jumpLatest" type="button" hidden data-message-jump-latest="1">
          <span data-message-jump-label="1">Jump to latest</span>
        </button>
      </div>

      <?php if ($canCompose): ?>
        <form
          class="messagesComposer"
          method="post"
          action="<?= TRUX_BASE_URL ?>/send_message.php"
          enctype="multipart/form-data"
          data-messages-composer="1"
          data-messages-max-attachments="<?= trux_direct_message_max_attachments() ?>"
          data-no-fx="1">
          <?= trux_csrf_field() ?>
          <?php if ($activeConversationId > 0): ?>
            <input type="hidden" name="conversation_id" value="<?= $activeConversationId ?>">
          <?php else: ?>
            <input type="hidden" name="recipient_id" value="<?= (int)$recipientUser['id'] ?>">
          <?php endif; ?>
          <input type="hidden" name="reply_to_message_id" value="" data-messages-reply-input="1">

          <div class="messagesComposer__replyContext" hidden data-messages-reply-context="1">
            <div class="messagesComposer__replyBody">
              <strong data-messages-reply-title="1">Replying</strong>
              <span data-messages-reply-preview="1"></span>
            </div>
            <button class="messagesComposer__replyClear" type="button" aria-label="Clear reply" data-messages-reply-clear="1">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              </svg>
            </button>
          </div>

          <div class="messagesComposer__preview" data-messages-attachment-preview="1" hidden></div>

          <div class="messagesComposer__row">
            <div class="messagesComposer__dock" data-messages-composer-dock="1">
              <div class="messagesComposer__cell messagesComposer__cell--attach">
                <div class="messagesComposer__attachWrap" data-messages-attachment-menu="1">
                  <button
                    class="messagesComposer__attach"
                    type="button"
                    data-messages-attachment-trigger="1"
                    aria-label="Open attachment options"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    title="Open attachment options">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="20" height="20">
                      <path d="M12 5.25v13.5M5.25 12h13.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                    </svg>
                  </button>

                  <div class="messagesComposer__attachMenu" role="menu" hidden data-messages-attachment-dropdown="1">
                    <button class="messagesComposer__attachOption" type="button" role="menuitem" data-messages-attachment-action="files">
                      <span class="messagesComposer__attachOptionIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                          <path d="M9.25 7.25h7.5A1.75 1.75 0 0 1 18.5 9v8.75a1.75 1.75 0 0 1-1.75 1.75H7.25A1.75 1.75 0 0 1 5.5 17.75V11" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                          <path d="M9.25 4.75v5.5m-2.75-2.75h5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                      </span>
                      <span>Add files</span>
                    </button>
                    <button class="messagesComposer__attachOption" type="button" role="menuitem" data-messages-attachment-action="photos">
                      <span class="messagesComposer__attachOptionIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                          <rect x="4.75" y="6" width="14.5" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.7" />
                          <path d="m8 14.5 2.8-2.8a1.2 1.2 0 0 1 1.7 0l2.2 2.2 1.3-1.3a1.2 1.2 0 0 1 1.7 0l1.55 1.55" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                          <circle cx="9" cy="10" r="1.25" fill="currentColor" />
                        </svg>
                      </span>
                      <span>Insert Photos</span>
                    </button>
                    <button class="messagesComposer__attachOption" type="button" role="menuitem" data-messages-attachment-action="video">
                      <span class="messagesComposer__attachOptionIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                          <rect x="4.75" y="7" width="10.5" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.7" />
                          <path d="m15.25 10.2 3.8-2.2v8l-3.8-2.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                      </span>
                      <span>Insert Video</span>
                    </button>
                    <button class="messagesComposer__attachOption" type="button" role="menuitem" data-messages-attachment-action="voice">
                      <span class="messagesComposer__attachOptionIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                          <path d="M12 4.75a2.75 2.75 0 0 1 2.75 2.75v4.5a2.75 2.75 0 1 1-5.5 0V7.5A2.75 2.75 0 0 1 12 4.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                          <path d="M7.75 11.25a4.25 4.25 0 0 0 8.5 0M12 15.5v3.75M9.25 19.25h5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                      </span>
                      <span>Record Voice</span>
                    </button>
                    <button class="messagesComposer__attachOption" type="button" role="menuitem" data-messages-attachment-action="emoji">
                      <span class="messagesComposer__attachOptionIcon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                          <circle cx="12" cy="12" r="7.25" fill="none" stroke="currentColor" stroke-width="1.7" />
                          <path d="M9.2 14.15a4.4 4.4 0 0 0 5.6 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                          <path d="M9.25 10h.01M14.75 10h.01" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                        </svg>
                      </span>
                      <span>Insert Emoji</span>
                    </button>
                    <div class="messagesComposer__attachHint muted">Images and PDFs only. Enter to send, Shift+Enter for newline.</div>
                  </div>
                </div>

                <input
                  type="file"
                  name="attachments[]"
                  accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"
                  multiple
                  hidden
                  data-messages-attachment-input="1">
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  multiple
                  hidden
                  data-messages-image-input="1">
              </div>

              <div class="messagesComposer__cell messagesComposer__cell--input">
                <div class="messagesComposer__inputWrap">
                  <textarea
                    name="body"
                    rows="1"
                    maxlength="2000"
                    placeholder="Write a message to <?= trux_e($recipientHandle) ?>..."
                    data-mention-input="1"
                    data-messages-input="1"></textarea>
                </div>
              </div>

              <div class="messagesComposer__cell messagesComposer__cell--send">
                <button class="shellButton shellButton--accent messagesComposer__submit" type="submit" data-messages-submit="1" aria-label="Send message">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="m4.75 12 14-7-3.1 7 3.1 7-14-7Zm0 0h10.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <span class="u-visually-hidden">Send message</span>
                </button>
              </div>
            </div>
          </div>

          <div class="messagesComposer__emojiPanel" hidden data-messages-emoji-panel="1">
            <div class="messagesComposer__emojiHead">
              <input
                class="messagesComposer__emojiSearch"
                type="text"
                placeholder="Search emoji..."
                autocomplete="off"
                spellcheck="false"
                data-messages-emoji-search="1">
            </div>
            <div class="messagesComposer__emojiRecent" hidden data-messages-emoji-recent="1"></div>
            <div class="messagesComposer__emojiTabs" data-messages-emoji-tabs="1"></div>
            <div class="messagesComposer__emojiGrid" data-messages-emoji-grid="1"></div>
            <div class="messagesComposer__emojiVariants" hidden data-messages-emoji-variants="1"></div>
          </div>
        </form>
      <?php elseif ($recipientIsReportSystem): ?>
        <div class="messagesThread__footerNote muted">This inbox only sends automated report updates. Replies are not accepted.</div>
      <?php endif; ?>
    <?php else: ?>
      <div class="messagesThread__empty">
        <div class="messagesThread__emptyIcon" aria-hidden="true"><?= $renderDmEmptyIcon() ?></div>
        <div class="messagesThread__emptyCopy">
          <span class="messagesThread__emptyEyebrow">Standby state</span>
          <h3>Select a conversation</h3>
          <p class="muted">Or start a new one from a user profile.</p>
        </div>
      </div>
    <?php endif; ?>
  </section>
  <?php

  return trim((string)ob_get_clean());
};

$renderThreadActionsSheet = static function () use (
  $recipientUser,
  $recipientLabel,
  $activeConversationId,
  $recipientIsReportSystem,
  $recipientUsername,
  $dmBlockedByViewer
): string {
  ob_start();
  ?>
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
  <?php

  return trim((string)ob_get_clean());
};

$renderBubbleActionsSheet = static function (): string {
  ob_start();
  ?>
  <div class="shellSheet" data-shell-sheet="message-bubble-actions" hidden>
    <div class="shellSheet__backdrop" data-shell-sheet-close="1"></div>
    <section class="shellSheet__panel messagesActionSheet messagesActionSheet--message" role="dialog" aria-modal="true" aria-labelledby="messageBubbleActionsTitle">
      <header class="shellSheet__head">
        <div>
          <span class="shellSheet__eyebrow">Message actions</span>
          <h2 id="messageBubbleActionsTitle">Select an action</h2>
        </div>
        <button class="iconBtn" type="button" aria-label="Close message actions" data-shell-sheet-close="1">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
      </header>

      <div class="shellSheet__stack">
        <div class="messagesActionSheet__summary">
          <strong data-message-sheet-title="1">Message</strong>
          <span class="muted" data-message-sheet-meta="1">Choose how to handle this message.</span>
        </div>

        <button class="shellSheet__link" type="button" hidden data-message-sheet-copy="1">
          <span class="shellSheet__itemIcon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M9 9.75h8.25A1.75 1.75 0 0 1 19 11.5v7.75A1.75 1.75 0 0 1 17.25 21H9A1.75 1.75 0 0 1 7.25 19.25V11.5A1.75 1.75 0 0 1 9 9.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
              <path d="M5.75 14.25V6.75A1.75 1.75 0 0 1 7.5 5h7.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="shellSheet__itemMain">
            <strong>Copy text</strong>
            <span>Copy the message body to your clipboard.</span>
          </span>
        </button>

        <button class="shellSheet__link" type="button" hidden data-message-sheet-edit="1">
          <span class="shellSheet__itemIcon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="m5.5 16.75-.75 3.5 3.5-.75L18.5 9.25a1.77 1.77 0 0 0 0-2.5l-1.25-1.25a1.77 1.77 0 0 0-2.5 0L5.5 16.75Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
              <path d="m13.5 6.75 3.75 3.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="shellSheet__itemMain">
            <strong>Edit message</strong>
            <span>Update the text while the edit window is still open.</span>
          </span>
        </button>

        <button class="shellSheet__link" type="button" hidden data-message-sheet-unsend="1">
          <span class="shellSheet__itemIcon shellSheet__itemIcon--danger" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="shellSheet__itemMain">
            <strong>Delete message</strong>
            <span>Remove the message for everyone before the grace window closes.</span>
          </span>
        </button>

        <button class="shellSheet__link" type="button" hidden data-message-sheet-report="1">
          <span class="shellSheet__itemIcon shellSheet__itemIcon--danger" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M6 20V5m0 0h9l-1.5 3L15 11H6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <span class="shellSheet__itemMain">
            <strong>Report message</strong>
            <span>Send this message to the moderation queue.</span>
          </span>
        </button>

        <button class="shellSheet__link" type="button" hidden data-message-sheet-time="1">
          <span class="shellSheet__itemIcon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M12 4.75A7.25 7.25 0 1 0 19.25 12 7.25 7.25 0 0 0 12 4.75Z" fill="none" stroke="currentColor" stroke-width="1.7"/>
              <path d="M12 8.25v4l2.75 1.75" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="shellSheet__itemMain">
            <strong>View timestamp</strong>
            <span>See the exact send time for this message.</span>
          </span>
        </button>
      </div>
    </section>
  </div>
  <?php

  return trim((string)ob_get_clean());
};

$renderThreadPartialResponse = static function () use (
  $activeConversationId,
  $recipientUser,
  $pageTitle,
  $renderMessagesThread,
  $renderMessagesMobileThreadState,
  $renderThreadActionsSheet,
  $renderBubbleActionsSheet
): string {
  ob_start();
  ?>
  <div
    data-messages-thread-response="1"
    data-messages-active-conversation-id="<?= $activeConversationId ?>"
    data-thread-open="<?= $recipientUser ? '1' : '0' ?>"
    data-document-title="<?= trux_e($pageTitle) ?>">
    <?= $renderMessagesThread() ?>
    <?= $renderMessagesMobileThreadState() ?>
    <?= $renderThreadActionsSheet() ?>
    <?= $renderBubbleActionsSheet() ?>
  </div>
  <?php

  return trim((string)ob_get_clean());
};

if ($isThreadPartial) {
  header('Content-Type: text/html; charset=utf-8');
  http_response_code(200);
  echo $renderThreadPartialResponse();
  exit;
}

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--messages">
  <section
    class="messagesLayout<?= $messagesLayoutClasses ? ' ' . trux_e(implode(' ', $messagesLayoutClasses)) : '' ?>"
    data-messages-layout="1"
    data-messages-active-conversation-id="<?= $activeConversationId ?>">
    <section class="messagesMobileBar" aria-label="Messages navigation">
      <div class="messagesMobileBar__core" aria-label="Primary mobile navigation">
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

      <div class="messagesMobileBar__state messagesMobileBar__state--inbox">
        <div class="messagesMobileBar__titleWrap">
          <span class="messagesMobileBar__eyebrow">Comms grid</span>
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
          <?= trux_render_direct_message_avatar($viewerUsername, $viewerAvatarUrl, 'messagesMobileBar__avatar', '@' . $viewerUsername) ?>
        </div>
      </div>

      <?= $renderMessagesMobileThreadState() ?>
    </section>

    <aside class="messagesSidebar workspacePane" data-messages-sidebar="1">
      <div class="messagesSidebar__header">
        <div class="messagesSidebar__titleWrap">
          <span class="messagesSidebar__eyebrow">Channel relay</span>
          <h2>Messages</h2>
          <p class="muted">@<?= trux_e($viewerUsername) ?> &middot; <?= $conversationCount ?> conversation<?= $conversationCount === 1 ? '' : 's' ?></p>
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
            <span class="messagesSidebar__searchAction" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false">
                <path d="M10.5 5.25a5.25 5.25 0 1 0 3.24 9.39l3.56 3.56a.9.9 0 0 0 1.27-1.27l-3.56-3.56a5.25 5.25 0 0 0-4.51-8.12Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <span>Find</span>
            </span>
          </label>
        </div>
        <div class="messagesSidebar__statusGrid" aria-hidden="true">
          <div class="messagesSidebar__status">
            <span>Threads</span>
            <strong><?= $conversationCount ?></strong>
          </div>
          <div class="messagesSidebar__status">
            <span>Focus</span>
            <strong><?= $recipientUser ? 'Engaged' : 'Standby' ?></strong>
          </div>
        </div>
      </div>

      <div class="messagesSidebar__listWrap">
        <div class="messagesList" data-conversation-list="1">
          <?php if (!$conversations): ?>
            <div class="messagesList__empty" data-conversation-list-empty="1">
              <strong>No conversations yet</strong>
              <p class="muted">Start a new one from a user profile or the new message button.</p>
            </div>
          <?php else: ?>
            <?php foreach ($conversations as $conversation): ?>
              <?= trux_render_direct_conversation_item($conversation, $activeConversationId) ?>
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

    <?= $renderMessagesThread() ?>
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

  <?= $renderThreadActionsSheet() ?>
  <?= $renderBubbleActionsSheet() ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
