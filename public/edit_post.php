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
        echo json_encode(['ok' => false, 'error' => 'Invalid post id.']);
        exit;
    }
    trux_flash_set('error', 'Invalid post id.');
    trux_redirect('/');
}

$text = is_string($body) ? trim($body) : '';
if ($text === '' || mb_strlen($text) > 2000) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Post must be 1-2000 characters.']);
        exit;
    }
    trux_flash_set('error', 'Post must be 1-2000 characters.');
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

$postId = (int)$id;
$ok = trux_update_post_if_owner($postId, (int)$me['id'], $text);
if (!$ok) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Could not edit post.']);
        exit;
    }
    trux_flash_set('error', 'Could not edit post.');
} else {
    if ($isJson) {
        $post = trux_fetch_post_by_id($postId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'post_id' => $postId,
            'body' => $text,
            'body_html' => trux_render_post_body($text),
            'edited_at' => isset($post['edited_at']) && $post['edited_at'] !== null ? (string)$post['edited_at'] : '',
            'edited_time_ago' => !empty($post['edited_at']) ? trux_time_ago((string)$post['edited_at']) : '',
            'edited_exact_time' => !empty($post['edited_at']) ? trux_format_exact_time((string)$post['edited_at']) : '',
        ]);
        exit;
    }
    trux_flash_set('success', 'Post updated.');
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect(trux_post_viewer_path($postId));
