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
$body = $_POST['body'] ?? null;
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
$comment = trux_fetch_comment_by_id($commentId);
if (!$comment) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Comment not found.']);
        exit;
    }
    trux_flash_set('error', 'Comment not found.');
    trux_redirect('/');
}

$ok = trux_update_comment_if_owner($commentId, (int)$me['id'], $text);
if (!$ok) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Could not edit comment.']);
        exit;
    }
    trux_flash_set('error', 'Could not edit comment.');
} else {
    if ($isJson) {
        $updatedComment = trux_fetch_comment_by_id($commentId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'comment_id' => $commentId,
            'post_id' => (int)$comment['post_id'],
            'body' => $text,
            'body_html' => trux_render_comment_body($text),
            'edited_at' => isset($updatedComment['edited_at']) && $updatedComment['edited_at'] !== null ? (string)$updatedComment['edited_at'] : '',
            'edited_time_ago' => !empty($updatedComment['edited_at']) ? trux_time_ago((string)$updatedComment['edited_at']) : '',
            'edited_exact_time' => !empty($updatedComment['edited_at']) ? trux_format_exact_time((string)$updatedComment['edited_at']) : '',
        ]);
        exit;
    }
    trux_flash_set('success', 'Comment updated.');
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect(trux_post_viewer_path((int)$comment['post_id'], $commentId));
