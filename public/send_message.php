<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    trux_flash_set('error', 'Method not allowed.');
    trux_redirect('/messages.php');
}

$me = trux_current_user();
if (!$me) {
    trux_redirect('/login.php');
}

$viewerId = (int)$me['id'];
$body = trim((string)($_POST['body'] ?? ''));
$conversationIdRaw = $_POST['conversation_id'] ?? null;
$recipientIdRaw = $_POST['recipient_id'] ?? null;

if ($body === '' || mb_strlen($body) > 2000) {
    trux_flash_set('error', 'Message must be 1-2000 characters.');
    trux_redirect('/messages.php');
}

$conversationId = 0;
$recipientUser = null;

if (is_string($conversationIdRaw) && preg_match('/^\d+$/', $conversationIdRaw)) {
    $conversationId = (int)$conversationIdRaw;
    $conversation = trux_fetch_direct_conversation_for_user($conversationId, $viewerId);
    if (!$conversation) {
        trux_flash_set('error', 'Conversation not found.');
        trux_redirect('/messages.php');
    }

    $recipientUser = trux_fetch_user_by_id((int)$conversation['other_user_id']);
} elseif (is_string($recipientIdRaw) && preg_match('/^\d+$/', $recipientIdRaw)) {
    $recipientUser = trux_fetch_user_by_id((int)$recipientIdRaw);
}

if (!$recipientUser || (int)$recipientUser['id'] === $viewerId) {
    trux_flash_set('error', 'Recipient not found.');
    trux_redirect('/messages.php');
}

$savedConversationId = trux_send_direct_message($viewerId, (int)$recipientUser['id'], $body);
if ($savedConversationId <= 0) {
    trux_flash_set('error', 'Could not send message.');
    trux_redirect('/messages.php?with=' . rawurlencode((string)$recipientUser['username']));
}

trux_flash_set('success', 'Message sent.');
trux_redirect('/messages.php?id=' . $savedConversationId);
