<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$isJson = trux_str_param('format', '') === 'json';

if (!trux_is_logged_in()) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to vote.', 'login_url' => '/login.php']);
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
$voteRaw = $_POST['vote'] ?? null;
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

$vote = is_string($voteRaw) ? (int)$voteRaw : 0;
if (!in_array($vote, [-1, 1], true)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid vote value.']);
        exit;
    }
    trux_flash_set('error', 'Invalid vote value.');
    trux_redirect('/');
}

$me = trux_current_user();
if (!$me) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to continue.', 'login_url' => '/login.php']);
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

$viewerVote = trux_set_comment_vote($commentId, (int)$me['id'], $vote);
$stats = trux_fetch_comment_vote_stats([$commentId], (int)$me['id']);
$commentStats = $stats[$commentId] ?? ['score' => 0, 'viewer_vote' => $viewerVote];

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'comment_id' => $commentId,
        'post_id' => (int)$comment['post_id'],
        'score' => (int)$commentStats['score'],
        'viewer_vote' => (int)$commentStats['viewer_vote'],
    ]);
    exit;
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect('/post.php?id=' . (int)$comment['post_id']);
