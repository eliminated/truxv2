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

trux_toggle_post_bookmark($postId, (int)$me['id']);
$stats = trux_fetch_post_interactions([$postId], (int)$me['id']);
$postStats = $stats[$postId] ?? ['bookmarked' => false, 'bookmarks' => 0];

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'post_id' => $postId,
        'bookmarked' => (bool)$postStats['bookmarked'],
        'bookmarks_count' => (int)$postStats['bookmarks'],
    ]);
    exit;
}

trux_flash_set('success', !empty($postStats['bookmarked']) ? 'Post bookmarked.' : 'Post removed from bookmarks.');

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect('/bookmarks.php');
