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
if (!is_string($action) || ($action !== 'mute' && $action !== 'unmute')) {
    trux_flash_set('error', 'Invalid mute action.');
    trux_redirect('/');
}

try {
    $targetUser = null;

    $rawUserId = $_POST['user_id'] ?? null;
    if (is_string($rawUserId) && preg_match('/^\d+$/', $rawUserId)) {
        $targetUserId = (int)$rawUserId;
        $stmt = trux_db()->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch() ?: null;
    }

    if (!$targetUser) {
        $rawUsername = $_POST['user'] ?? '';
        $rawUsername = is_string($rawUsername) ? trim($rawUsername) : '';
        if ($rawUsername !== '') {
            $targetUser = trux_fetch_user_by_username($rawUsername);
        }
    }

    if (!$targetUser) {
        trux_flash_set('error', 'User not found.');
        trux_redirect('/');
    }

    $targetId = (int)$targetUser['id'];
    $targetUsername = (string)$targetUser['username'];
    $backToProfile = '/profile.php?u=' . urlencode($targetUsername);

    if ((int)$me['id'] === $targetId) {
        trux_flash_set('error', 'You cannot mute yourself.');
        trux_redirect($backToProfile);
    }

    if ($action === 'mute') {
        trux_mute_user((int)$me['id'], $targetId);
        trux_remove_notifications_from_actor((int)$me['id'], $targetId);
        trux_flash_set('success', 'Muted @' . $targetUsername . '. You will no longer receive notifications from this user.');
    } else {
        trux_unmute_user((int)$me['id'], $targetId);
        trux_flash_set('success', 'Unmuted @' . $targetUsername . '.');
    }

    $back = $_SERVER['HTTP_REFERER'] ?? '';
    if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
        trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
    }

    trux_redirect($backToProfile);
} catch (PDOException) {
    trux_flash_set('error', 'Could not update mute state right now.');
    trux_redirect('/');
}
