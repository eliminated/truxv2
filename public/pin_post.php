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
    http_response_code(405);
    trux_redirect('/');
}

$id = $_POST['id'] ?? null;
if (!is_string($id) || !preg_match('/^\d+$/', $id)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid post id.']);
        exit;
    }
    trux_flash_set('error', 'Invalid post id.');
    trux_redirect('/');
}

$postId = (int)$id;
$me = trux_current_user();
if (!$me) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to continue.', 'login_url' => TRUX_BASE_URL . '/login.php']);
        exit;
    }
    trux_require_login();
}

$userId = (int)$me['id'];

// Check current pin state
$db = trux_db();
$checkStmt = $db->prepare('SELECT is_pinned FROM posts WHERE id = ? AND user_id = ? LIMIT 1');
$checkStmt->execute([$postId, $userId]);
$postRow = $checkStmt->fetch();

if (!$postRow) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Post not found or not owned by you.']);
        exit;
    }
    trux_flash_set('error', 'Post not found.');
    trux_redirect('/');
}

$currentlyPinned = !empty($postRow['is_pinned']);

if ($currentlyPinned) {
    trux_unpin_post($userId, $postId);
    $pinned = false;
} else {
    trux_pin_post($userId, $postId);
    $pinned = true;
}

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'      => true,
        'post_id' => $postId,
        'pinned'  => $pinned,
    ]);
    exit;
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}
trux_redirect('/');
