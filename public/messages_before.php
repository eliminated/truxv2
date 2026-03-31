<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$me = trux_current_user();
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

$viewerId = (int)$me['id'];
$conversationId = (int)trux_int_param('conversation_id', 0);
$beforeMessageId = (int)trux_int_param('before_message_id', trux_int_param('before', 0));

if ($conversationId <= 0 || $beforeMessageId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$conversation = trux_fetch_direct_conversation_for_user($conversationId, $viewerId);
if (!$conversation) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

$result = trux_fetch_direct_messages_before($conversationId, $viewerId, $beforeMessageId, 30);
$messages = is_array($result['messages'] ?? null) ? $result['messages'] : [];
$hasMore = !empty($result['has_more']);

$messagesHtml = [];
$oldestMessageId = $beforeMessageId;
foreach ($messages as $message) {
    $messagesHtml[] = trux_render_direct_message_bubble($message, $viewerId, $conversationId);
    $messageId = (int)($message['id'] ?? 0);
    if ($messageId > 0 && $messageId < $oldestMessageId) {
        $oldestMessageId = $messageId;
    }
}

echo json_encode([
    'ok' => true,
    'messages' => $messages,
    'messages_html' => $messagesHtml,
    'oldest_message_id' => $oldestMessageId,
    'has_more' => $hasMore,
    'count' => count($messages),
]);
