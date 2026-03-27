<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$isJson = trux_str_param('format', '') === 'json';

$respond = static function (bool $ok, int $statusCode, string $message, array $payload = []) use ($isJson): void {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode(array_merge(['ok' => $ok], $payload, [$ok ? 'message' : 'error' => $message]));
        exit;
    }

    trux_flash_set($ok ? 'success' : 'error', $message);
};

$redirectTo = static function (string $path = '/messages.php') use ($isJson): void {
    if ($isJson) {
        exit;
    }

    trux_redirect($path);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(false, 405, 'Method not allowed.');
    $redirectTo('/messages.php');
}

$me = trux_current_user();
if (!$me) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Please log in to continue.',
            'login_url' => TRUX_BASE_URL . '/login.php',
        ]);
        exit;
    }

    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$viewerId = (int)$me['id'];
$body = trim((string)($_POST['body'] ?? ''));
$conversationIdRaw = $_POST['conversation_id'] ?? null;
$recipientIdRaw = $_POST['recipient_id'] ?? null;

if ($body === '' || mb_strlen($body) > 2000) {
    $respond(false, 400, 'Message must be 1-2000 characters.');
    $redirectTo('/messages.php');
}

$conversationId = 0;
$recipientUser = null;

if (is_string($conversationIdRaw) && preg_match('/^\d+$/', $conversationIdRaw)) {
    $conversationId = (int)$conversationIdRaw;
    $conversation = trux_fetch_direct_conversation_for_user($conversationId, $viewerId);
    if (!$conversation) {
        $respond(false, 404, 'Conversation not found.');
        $redirectTo('/messages.php');
    }

    $recipientUser = trux_fetch_user_by_id((int)$conversation['other_user_id']);
} elseif (is_string($recipientIdRaw) && preg_match('/^\d+$/', $recipientIdRaw)) {
    $recipientUser = trux_fetch_user_by_id((int)$recipientIdRaw);
}

if (!$recipientUser || (int)$recipientUser['id'] === $viewerId) {
    $respond(false, 404, 'Recipient not found.');
    $redirectTo('/messages.php');
}

$recipientUsername = (string)($recipientUser['username'] ?? '');
$redirectPath = $conversationId > 0
    ? '/messages.php?id=' . $conversationId
    : '/messages.php?with=' . rawurlencode($recipientUsername);

if (trux_is_report_system_user($recipientUsername)) {
    $respond(false, 400, 'This inbox is read-only.');
    $redirectTo('/messages.php');
}

if (trux_block_exists_between($viewerId, (int)$recipientUser['id'])) {
    $respond(false, 403, 'You cannot message this user.');
    $redirectTo($redirectPath);
}

if (trux_moderation_is_user_dm_restricted($viewerId)) {
    $respond(false, 403, 'Your account currently cannot send direct messages.');
    $redirectTo($redirectPath);
}

$sendResult = trux_send_direct_message_record($viewerId, (int)$recipientUser['id'], $body);
if (!$sendResult) {
    $respond(false, 500, 'Could not send message.');
    $redirectTo('/messages.php?with=' . rawurlencode($recipientUsername));
}

$savedConversationId = (int)($sendResult['conversation_id'] ?? 0);
$messageId = (int)($sendResult['message_id'] ?? 0);
$createdConversation = !empty($sendResult['created_conversation']);

if ($savedConversationId <= 0 || $messageId <= 0) {
    $respond(false, 500, 'Could not send message.');
    $redirectTo('/messages.php?with=' . rawurlencode($recipientUsername));
}

$message = trux_fetch_direct_message_for_user($messageId, $viewerId);
$conversationSummary = trux_fetch_direct_conversation_summary($savedConversationId, $viewerId);
if (!$message || !$conversationSummary) {
    $respond(false, 500, 'Could not load the new message.');
    $redirectTo('/messages.php?id=' . $savedConversationId);
}

trux_moderation_record_activity_event('direct_message_sent', $viewerId, [
    'subject_type' => 'conversation',
    'subject_id' => $savedConversationId,
    'related_user_id' => (int)$recipientUser['id'],
    'source_url' => '/messages.php?id=' . $savedConversationId,
    'metadata' => [
        'recipient_user_id' => (int)$recipientUser['id'],
        'recipient_username' => $recipientUsername,
        'body_length' => mb_strlen($body),
        'link_count' => trux_moderation_link_count($body),
        'body_hash' => trux_moderation_text_fingerprint($body),
    ],
]);

if ($isJson) {
    $messageHtml = trux_render_direct_message_bubble($message, $viewerId, $savedConversationId);
    $conversationItemHtml = trux_render_direct_conversation_item($conversationSummary, $savedConversationId);
    if ($messageHtml === '' || $conversationItemHtml === '') {
        $respond(false, 500, 'Could not render the new message.');
    }

    $createdAt = (string)($message['created_at'] ?? '');
    $respond(true, 200, 'Message sent.', [
        'conversation_id' => $savedConversationId,
        'conversation_url' => TRUX_BASE_URL . '/messages.php?id=' . $savedConversationId,
        'created_conversation' => $createdConversation,
        'message_html' => $messageHtml,
        'conversation_item_html' => $conversationItemHtml,
        'message' => [
            'id' => $messageId,
            'body' => (string)($message['body'] ?? $body),
            'created_at' => $createdAt,
            'time_ago' => $createdAt !== '' ? trux_time_ago($createdAt) : 'just now',
            'exact_time' => $createdAt !== '' ? trux_format_exact_time($createdAt) : '',
        ],
    ]);
}

$respond(true, 200, 'Message sent.');
$redirectTo('/messages.php?id=' . $savedConversationId);
