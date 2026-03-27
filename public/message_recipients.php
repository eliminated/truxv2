<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!trux_is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Please log in to continue.',
        'login_url' => TRUX_BASE_URL . '/login.php',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$me = trux_current_user();
if (!$me) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Please log in to continue.',
        'login_url' => TRUX_BASE_URL . '/login.php',
    ]);
    exit;
}

$query = $_GET['q'] ?? null;
$term = is_string($query) ? trim($query) : '';
$results = trux_search_direct_message_recipients((int)$me['id'], $term, 8);

$payload = [];
foreach ($results as $result) {
    $username = trim((string)($result['username'] ?? ''));
    if ($username === '') {
        continue;
    }

    $conversationId = (int)($result['conversation_id'] ?? 0);
    $targetPath = $conversationId > 0
        ? '/messages.php?id=' . $conversationId
        : '/messages.php?with=' . rawurlencode($username);

    $payload[] = [
        'id' => (int)($result['id'] ?? 0),
        'username' => $username,
        'display_name' => (string)($result['display_name'] ?? ''),
        'avatar_url' => trux_public_url((string)($result['avatar_path'] ?? '')),
        'conversation_id' => $conversationId,
        'target_url' => TRUX_BASE_URL . $targetPath,
    ];
}

echo json_encode([
    'ok' => true,
    'query' => trux_normalize_mention_fragment($term),
    'recipients' => $payload,
]);
