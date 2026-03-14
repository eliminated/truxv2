<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

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

$activeConversationId = (int)($selectedConversation['id'] ?? 0);

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Messages</h1>
  <p class="muted">Private 1-to-1 conversations.</p>
</section>

<section class="messagesLayout" data-messages-active-conversation-id="<?= $activeConversationId ?>">
  <aside class="card messagesSidebar">
    <div class="card__body">
      <div class="messagesSidebar__head">
        <h2>Inbox</h2>
      </div>

      <?php if (!$conversations): ?>
        <div class="muted">No conversations yet. Start one from a user profile.</div>
      <?php else: ?>
        <div class="messagesList">
          <?php foreach ($conversations as $conversation): ?>
            <?php
            $conversationId = (int)$conversation['id'];
            $isActive = $conversationId === $activeConversationId;
            $unreadCount = (int)($conversation['unread_count'] ?? 0);
            $lastAt = (string)($conversation['last_message_created_at'] ?? $conversation['updated_at'] ?? '');
            ?>
            <a
              class="messagesList__item<?= $isActive ? ' is-active' : '' ?>"
              href="/messages.php?id=<?= $conversationId ?>"
              <?= $isActive ? 'aria-current="page"' : '' ?>>
              <div class="messagesList__row">
                <span class="messagesList__user">@<?= trux_e((string)$conversation['other_username']) ?></span>
                <?php if ($lastAt !== ''): ?>
                  <span
                    class="messagesList__time muted"
                    data-time-ago="1"
                    data-time-source="<?= trux_e($lastAt) ?>"
                    title="<?= trux_e(trux_format_exact_time($lastAt)) ?>">
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
    </div>
  </aside>

  <section class="card messagesThread">
    <div class="card__body">
      <?php if ($recipientUser): ?>
        <div class="messagesThread__head">
          <div>
            <h2>@<?= trux_e((string)$recipientUser['username']) ?></h2>
            <div class="muted">
              <?php if ($activeConversationId > 0): ?>
                Conversation active
              <?php else: ?>
                New conversation
              <?php endif; ?>
            </div>
          </div>
          <div class="row">
            <?php if ($activeConversationId > 0): ?>
              <form method="post" action="/mark_conversation_read.php" class="inline" data-no-fx="1">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="id" value="<?= $activeConversationId ?>">
                <button class="btn btn--small btn--ghost" type="submit">Mark as read</button>
              </form>
            <?php endif; ?>
            <a class="btn btn--small btn--ghost" href="/profile.php?u=<?= trux_e((string)$recipientUser['username']) ?>">View profile</a>
          </div>
        </div>

        <div class="messagesThread__messages">
          <?php if (!$selectedMessages): ?>
            <div class="muted">No messages yet. Say hello.</div>
          <?php else: ?>
            <?php foreach ($selectedMessages as $message): ?>
              <?php
              $isMine = (int)$message['sender_user_id'] === $viewerId;
              $messageTime = (string)$message['created_at'];
              ?>
              <article class="messageBubble<?= $isMine ? ' messageBubble--mine' : '' ?>">
                <div class="messageBubble__meta">
                  <span class="messageBubble__author"><?= $isMine ? 'You' : '@' . trux_e((string)$message['sender_username']) ?></span>
                  <span
                    class="muted"
                    data-time-ago="1"
                    data-time-source="<?= trux_e($messageTime) ?>"
                    title="<?= trux_e(trux_format_exact_time($messageTime)) ?>">
                    <?= trux_e(trux_time_ago($messageTime)) ?>
                  </span>
                </div>
                <div class="messageBubble__body"><?= trux_render_comment_body((string)$message['body']) ?></div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form class="messagesComposer" method="post" action="/send_message.php">
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
          <div class="row row--spaced">
            <span class="muted">Only text messages for now.</span>
            <button class="btn" type="submit">Send message</button>
          </div>
        </form>
      <?php else: ?>
        <div class="messagesThread__empty muted">
          Select a conversation from the inbox or start one from a user profile.
        </div>
      <?php endif; ?>
    </div>
  </section>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
