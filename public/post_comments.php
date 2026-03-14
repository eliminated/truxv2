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

$beforeRaw = $_GET['before'] ?? null;
$beforeId = null;
if (is_string($beforeRaw) && $beforeRaw !== '') {
    if (!preg_match('/^\d+$/', $beforeRaw)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid before cursor.']);
        exit;
    }
    $beforeId = (int)$beforeRaw;
    if ($beforeId <= 0) {
        $beforeId = null;
    }
}

$limitRaw = $_GET['limit'] ?? null;
$limit = 120;
if (is_string($limitRaw) && $limitRaw !== '') {
    if (!preg_match('/^\d+$/', $limitRaw)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid limit value.']);
        exit;
    }
    $limit = max(1, min(200, (int)$limitRaw));
}

$postId = (int)$id;
if (!trux_post_exists($postId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Post not found.']);
    exit;
}

$me = trux_current_user();
$viewerId = $me ? (int)$me['id'] : 0;
$page = trux_fetch_post_comments_page($postId, $limit, $beforeId);
$comments = is_array($page['comments'] ?? null) ? $page['comments'] : [];
$totalCount = trux_count_post_comments($postId);
$commentIds = trux_collect_comment_ids($comments);
$voteStats = trux_fetch_comment_vote_stats($commentIds, $viewerId);
$bookmarkMap = trux_fetch_comment_bookmark_map($commentIds, $viewerId);
$payload = [];
foreach ($comments as $c) {
    $commentId = (int)$c['id'];
    $vote = $voteStats[$commentId] ?? ['score' => 0, 'viewer_vote' => 0];
    $payload[] = [
        'id' => $commentId,
        'post_id' => (int)$c['post_id'],
        'parent_comment_id' => isset($c['parent_comment_id']) && $c['parent_comment_id'] !== null ? (int)$c['parent_comment_id'] : null,
        'user_id' => (int)$c['user_id'],
        'reply_to_user_id' => isset($c['reply_to_user_id']) && $c['reply_to_user_id'] !== null ? (int)$c['reply_to_user_id'] : null,
        'reply_to_username' => isset($c['reply_to_username']) ? (string)$c['reply_to_username'] : '',
        'username' => (string)$c['username'],
        'body' => (string)$c['body'],
        'body_html' => trux_render_comment_body((string)$c['body']),
        'is_owner' => $viewerId > 0 && $viewerId === (int)$c['user_id'],
        'created_at' => (string)$c['created_at'],
        'time_ago' => trux_time_ago((string)$c['created_at']),
        'exact_time' => trux_format_exact_time((string)$c['created_at']),
        'edited_at' => isset($c['edited_at']) && $c['edited_at'] !== null ? (string)$c['edited_at'] : '',
        'edited_time_ago' => !empty($c['edited_at']) ? trux_time_ago((string)$c['edited_at']) : '',
        'edited_exact_time' => !empty($c['edited_at']) ? trux_format_exact_time((string)$c['edited_at']) : '',
        'score' => (int)$vote['score'],
        'viewer_vote' => (int)$vote['viewer_vote'],
        'bookmarked' => (bool)($bookmarkMap[$commentId] ?? false),
    ];
}

echo json_encode([
    'ok' => true,
    'post_id' => $postId,
    'comments' => $payload,
    'count' => $totalCount,
    'loaded_count' => count($payload),
    'total_count' => $totalCount,
    'next_before' => isset($page['next_before']) && $page['next_before'] !== null ? (int)$page['next_before'] : null,
    'has_more' => !empty($page['has_more']),
]);
