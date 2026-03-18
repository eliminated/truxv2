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
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$ok = trux_delete_post_if_owner($postId, (int)$me['id']);
if (!$ok) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Could not delete post (not found or not yours).']);
        exit;
    }
    trux_flash_set('error', 'Could not delete post (not found or not yours).');
} else {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'post_id' => $postId,
        ]);
        exit;
    }
    trux_flash_set('success', 'Post deleted.');
}

// Redirect back
$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect('/');
