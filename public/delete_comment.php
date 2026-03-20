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
    trux_flash_set('error', 'Method not allowed.');
    trux_redirect('/');
}

$id = $_POST['id'] ?? null;
if (!is_string($id) || !preg_match('/^\d+$/', $id)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid comment id.']);
        exit;
    }
    trux_flash_set('error', 'Invalid comment id.');
    trux_redirect('/');
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

$commentId = (int)$id;
$postId = trux_delete_comment_if_owner($commentId, (int)$me['id']);
if ($postId === null) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Could not delete comment.']);
        exit;
    }
    trux_flash_set('error', 'Could not delete comment.');
} else {
    if ($isJson) {
        $stats = trux_fetch_post_interactions([$postId], (int)$me['id']);
        $postStats = $stats[$postId] ?? ['comments' => 0];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'comment_id' => $commentId,
            'post_id' => $postId,
            'comments_count' => (int)$postStats['comments'],
        ]);
        exit;
    }
    trux_flash_set('success', 'Comment deleted.');
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect(trux_post_viewer_path((int)$postId));
