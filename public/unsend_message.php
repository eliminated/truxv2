<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$isJson = trux_str_param('format', '') === 'json';

$respond = static function (bool $ok, int $statusCode, string $message, array $payload = []) use ($isJson): void {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode(array_merge(['ok' => $ok], $payload, $ok ? ['notice' => $message] : ['error' => $message]));
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
        echo json_encode(['ok' => false, 'error' => 'Please log in to continue.']);
        exit;
    }

    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$viewerId = (int)$me['id'];
$messageId = (int)($_POST['message_id'] ?? 0);

if ($messageId <= 0) {
    $respond(false, 400, 'Invalid message.');
    $redirectTo('/messages.php');
}

$messageBefore = trux_fetch_direct_message_for_user($messageId, $viewerId);
$conversationId = (int)($messageBefore['conversation_id'] ?? 0);

$result = trux_unsend_direct_message($messageId, $viewerId);
if (!($result['ok'] ?? false)) {
    $respond(false, 400, (string)($result['error'] ?? 'Could not delete message.'));
    $redirectTo($conversationId > 0 ? '/messages.php?id=' . $conversationId : '/messages.php');
}

$message = $result['message'] ?? null;
if (!$message) {
    $respond(false, 500, 'Could not load the deleted message.');
    $redirectTo($conversationId > 0 ? '/messages.php?id=' . $conversationId : '/messages.php');
}

$conversationId = (int)($message['conversation_id'] ?? $conversationId);
$conversationSummary = trux_fetch_direct_conversation_summary($conversationId, $viewerId);
$conversationItemHtml = $conversationSummary
    ? trux_render_direct_conversation_item($conversationSummary, $conversationId)
    : '';

trux_moderation_record_activity_event('direct_message_unsent', $viewerId, [
    'subject_type' => 'message',
    'subject_id' => $messageId,
    'source_url' => '/messages.php?id=' . $conversationId,
]);

if ($isJson) {
    $respond(true, 200, 'Message deleted.', [
        'message_id' => $messageId,
        'message' => $message,
        'message_html' => trux_render_direct_message_bubble($message, $viewerId, $conversationId),
        'conversation_summary' => $conversationSummary,
        'conversation_item_html' => $conversationItemHtml,
        'unread_total' => trux_count_unread_direct_messages($viewerId),
    ]);
}

$respond(true, 200, 'Message deleted.');
$redirectTo('/messages.php?id=' . $conversationId);
