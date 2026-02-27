<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    trux_flash_set('error', 'Method not allowed.');
    trux_redirect('/');
}

$id = $_POST['id'] ?? null;
if (!is_string($id) || !preg_match('/^\d+$/', $id)) {
    trux_flash_set('error', 'Invalid post id.');
    trux_redirect('/');
}

$postId = (int)$id;
$me = trux_current_user();

$ok = trux_delete_post_if_owner($postId, (int)$me['id']);
if (!$ok) {
    trux_flash_set('error', 'Could not delete post (not found or not yours).');
} else {
    trux_flash_set('success', 'Post deleted.');
}

// Redirect back
$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect('/');
