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
$messageId = (int)($_POST['message_id'] ?? 0);
$reaction = trim((string)($_POST['reaction'] ?? 'heart'));

if ($messageId <= 0) {
    $respond(false, 400, 'Invalid message.');
    $redirectTo('/messages.php');
}

$result = trux_toggle_direct_message_reaction($messageId, $viewerId, $reaction);
if (!($result['ok'] ?? false)) {
    $respond(false, 400, (string)($result['error'] ?? 'Could not update message reaction.'));
    $redirectTo('/messages.php');
}

$message = $result['message'] ?? null;
if (!$message) {
    $respond(false, 500, 'Could not load the updated message reaction.');
    $redirectTo('/messages.php');
}

$conversationId = (int)($message['conversation_id'] ?? 0);

if ($isJson) {
    $respond(true, 200, 'Reaction updated.', [
        'message_id' => $messageId,
        'conversation_id' => $conversationId,
        'reaction' => (string)($result['reaction'] ?? ''),
        'user_reaction' => isset($result['user_reaction']) && is_string($result['user_reaction'])
            ? $result['user_reaction']
            : null,
        'picker_reactions' => array_values(array_filter(
            is_array($result['picker_reactions'] ?? null) ? $result['picker_reactions'] : [],
            static fn ($item): bool => is_string($item) && trim($item) !== ''
        )),
        'total_count' => (int)($result['total_count'] ?? 0),
        'message' => $message,
        'message_html' => trux_render_direct_message_bubble($message, $viewerId, $conversationId),
    ]);
}

$respond(true, 200, 'Reaction updated.');
$redirectTo($conversationId > 0 ? '/messages.php?id=' . $conversationId : '/messages.php');
