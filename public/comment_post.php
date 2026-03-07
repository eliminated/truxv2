<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$isJson = trux_str_param('format', '') === 'json';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
        exit;
    }
    trux_flash_set('error', 'Method not allowed.');
    trux_redirect('/');
}

if (!trux_is_logged_in()) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to comment.', 'login_url' => '/login.php']);
        exit;
    }
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$id = $_POST['id'] ?? null;
$body = $_POST['body'] ?? null;
$parentIdRaw = $_POST['parent_id'] ?? null;
$replyToUserRaw = $_POST['reply_to_user_id'] ?? null;
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

$text = is_string($body) ? trim($body) : '';
if ($text === '' || mb_strlen($text) > 1000) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Comment must be 1-1000 characters.']);
        exit;
    }
    trux_flash_set('error', 'Comment must be 1-1000 characters.');
    trux_redirect('/');
}

$postId = (int)$id;
$parentId = null;
if (is_string($parentIdRaw) && $parentIdRaw !== '') {
    if (!preg_match('/^\d+$/', $parentIdRaw)) {
        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid parent comment id.']);
            exit;
        }
        trux_flash_set('error', 'Invalid parent comment id.');
        trux_redirect('/');
    }
    $parentId = (int)$parentIdRaw;
}

$replyToUserId = null;
if (is_string($replyToUserRaw) && $replyToUserRaw !== '') {
    if (!preg_match('/^\d+$/', $replyToUserRaw)) {
        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid reply user id.']);
            exit;
        }
        trux_flash_set('error', 'Invalid reply user id.');
        trux_redirect('/');
    }
    $replyToUserId = (int)$replyToUserRaw;
}

if (!trux_post_exists($postId)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Post not found.']);
        exit;
    }
    trux_flash_set('error', 'Post not found.');
    trux_redirect('/');
}

$me = trux_current_user();
$ok = $me ? trux_add_post_comment($postId, (int)$me['id'], $text, $parentId, $replyToUserId) : false;
if (!$ok) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not add comment.']);
        exit;
    }
    trux_flash_set('error', 'Could not add comment.');
} else {
    if ($isJson) {
        $stats = trux_fetch_post_interactions([$postId], $me ? (int)$me['id'] : null);
        $s = $stats[$postId] ?? ['comments' => 0];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'post_id' => $postId,
            'comments_count' => (int)$s['comments'],
        ]);
        exit;
    }
    trux_flash_set('success', 'Comment added.');
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect('/post.php?id=' . $postId);
