<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

trux_require_login();

$me = trux_current_user();
if (!$me) {
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$action = $_POST['action'] ?? '';
if (!is_string($action) || ($action !== 'block' && $action !== 'unblock')) {
    trux_flash_set('error', 'Invalid action.');
    trux_redirect('/');
}

$rawUserId = $_POST['user_id'] ?? null;
if (!is_string($rawUserId) || !preg_match('/^\d+$/', $rawUserId)) {
    trux_flash_set('error', 'User not found.');
    trux_redirect('/');
}

$target = trux_fetch_user_by_id((int)$rawUserId);
if (!$target || (int)$target['id'] === (int)$me['id']) {
    trux_flash_set('error', 'User not found.');
    trux_redirect('/');
}

$targetId       = (int)$target['id'];
$targetUsername = (string)$target['username'];
$backToProfile  = '/profile.php?u=' . urlencode($targetUsername);

if (trux_is_report_system_user($targetUsername)) {
    trux_flash_set('error', 'Action unavailable.');
    trux_redirect('/');
}

if ($action === 'block') {
    trux_block_user((int)$me['id'], $targetId);
    trux_moderation_record_activity_event('user_blocked', (int)$me['id'], [
        'subject_type' => 'user',
        'subject_id' => $targetId,
        'related_user_id' => $targetId,
        'source_url' => $backToProfile,
        'metadata' => [
            'target_user_id' => $targetId,
            'target_username' => $targetUsername,
        ],
    ]);
    trux_flash_set('success', '@' . $targetUsername . ' has been blocked.');
    trux_redirect('/');
} else {
    trux_unblock_user((int)$me['id'], $targetId);
    trux_moderation_record_activity_event('user_unblocked', (int)$me['id'], [
        'subject_type' => 'user',
        'subject_id' => $targetId,
        'related_user_id' => $targetId,
        'source_url' => $backToProfile,
        'metadata' => [
            'target_user_id' => $targetId,
            'target_username' => $targetUsername,
        ],
    ]);
    trux_flash_set('success', '@' . $targetUsername . ' has been unblocked.');
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect($backToProfile);
