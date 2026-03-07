<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$id = $_GET['id'] ?? null;
if (!is_string($id) || !preg_match('/^\d+$/', $id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid post id.']);
    exit;
}

$postId = (int)$id;
if (!trux_post_exists($postId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Post not found.']);
    exit;
}

$comments = trux_fetch_post_comments($postId, 120);
$payload = [];
foreach ($comments as $c) {
    $payload[] = [
        'id' => (int)$c['id'],
        'post_id' => (int)$c['post_id'],
        'parent_comment_id' => isset($c['parent_comment_id']) && $c['parent_comment_id'] !== null ? (int)$c['parent_comment_id'] : null,
        'user_id' => (int)$c['user_id'],
        'reply_to_user_id' => isset($c['reply_to_user_id']) && $c['reply_to_user_id'] !== null ? (int)$c['reply_to_user_id'] : null,
        'reply_to_username' => isset($c['reply_to_username']) ? (string)$c['reply_to_username'] : '',
        'username' => (string)$c['username'],
        'body' => (string)$c['body'],
        'created_at' => (string)$c['created_at'],
        'time_ago' => trux_time_ago((string)$c['created_at']),
        'exact_time' => trux_format_exact_time((string)$c['created_at']),
    ];
}

echo json_encode([
    'ok' => true,
    'post_id' => $postId,
    'comments' => $payload,
    'count' => count($payload),
]);
