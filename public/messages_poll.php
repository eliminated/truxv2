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
$afterMessageId = (int)trux_int_param('after_message_id', trux_int_param('after', 0));

if ($conversationId <= 0 || $afterMessageId < 0) {
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

$messages = trux_fetch_direct_messages_after($conversationId, $viewerId, $afterMessageId, 50);
$messagesHtml = [];
$latestMessageId = $afterMessageId;
foreach ($messages as $message) {
    $messagesHtml[] = trux_render_direct_message_bubble($message, $viewerId, $conversationId);
    $messageId = (int)($message['id'] ?? 0);
    if ($messageId > $latestMessageId) {
        $latestMessageId = $messageId;
    }
}

$conversationSummary = trux_fetch_direct_conversation_summary($conversationId, $viewerId);
$conversationSummaryHtml = $conversationSummary
    ? trux_render_direct_conversation_item($conversationSummary, $conversationId)
    : '';

$sentReadStatuses = trux_fetch_sent_message_read_statuses($conversationId, $viewerId, 100);

echo json_encode([
    'ok' => true,
    'messages' => $messages,
    'messages_html' => $messagesHtml,
    'latest_message_id' => $latestMessageId,
    'conversation_summary' => $conversationSummary,
    'conversation_summary_html' => $conversationSummaryHtml,
    'unread_total' => trux_count_unread_direct_messages($viewerId),
    'sent_read_statuses' => $sentReadStatuses,
]);
