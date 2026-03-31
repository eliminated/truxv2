<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$isJson = trux_str_param('format', '') === 'json';

if (!trux_is_logged_in()) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to continue.', 'login_url' => TRUX_BASE_URL . '/login.php']);
        exit;
    }
    trux_require_login();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
        exit;
    }
    trux_flash_set('error', 'Method not allowed.');
    trux_redirect('/messages.php');
}

$id = $_POST['id'] ?? null;
if (!is_string($id) || !preg_match('/^\d+$/', $id)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid conversation id.']);
        exit;
    }
    trux_flash_set('error', 'Invalid conversation id.');
    trux_redirect('/messages.php');
}

$me = trux_current_user();
if (!$me) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to continue.', 'login_url' => TRUX_BASE_URL . '/login.php']);
        exit;
    }
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$conversationId = (int)$id;
$viewerId = (int)$me['id'];
$conversation = trux_fetch_direct_conversation_for_user($conversationId, $viewerId);
if (!$conversation) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Conversation not found.']);
        exit;
    }
    trux_flash_set('error', 'Conversation not found.');
    trux_redirect('/messages.php');
}

trux_mark_direct_conversation_read($conversationId, $viewerId);
$conversationSummary = trux_fetch_direct_conversation_summary($conversationId, $viewerId);

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'conversation_id' => $conversationId,
        'conversation_summary' => $conversationSummary,
        'conversation_item_html' => $conversationSummary ? trux_render_direct_conversation_item($conversationSummary, $conversationId) : '',
        'unread_total' => trux_count_unread_direct_messages($viewerId),
    ]);
    exit;
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect('/messages.php?id=' . $conversationId);
