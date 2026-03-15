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
if (!is_string($action) || ($action !== 'follow' && $action !== 'unfollow')) {
    trux_flash_set('error', 'Invalid follow action.');
    trux_redirect('/');
}

$rawRedirect = $_POST['redirect'] ?? '';
$safeRedirect = '';
if (is_string($rawRedirect)) {
    $candidateRedirect = trim($rawRedirect);
    if (
        $candidateRedirect !== ''
        && str_starts_with($candidateRedirect, '/')
        && !str_starts_with($candidateRedirect, '//')
        && !preg_match('/[\r\n]/', $candidateRedirect)
    ) {
        $safeRedirect = $candidateRedirect;
    }
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
        if (is_string($rawUsername)) {
            $rawUsername = trim($rawUsername);
        } else {
            $rawUsername = '';
        }

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
    $redirectPath = $safeRedirect !== '' ? $safeRedirect : $backToProfile;

    if ((int)$me['id'] === $targetId) {
        trux_flash_set('error', 'This is you.');
        trux_redirect($redirectPath);
    }

    if ($action === 'follow') {
        trux_follow((int)$me['id'], $targetId);
        trux_notify_follow($targetId, (int)$me['id']);
        trux_flash_set('success', 'Now following @' . $targetUsername . '.');
    } else {
        trux_unfollow((int)$me['id'], $targetId);
        trux_remove_follow_notification($targetId, (int)$me['id']);
        trux_flash_set('success', 'Unfollowed @' . $targetUsername . '.');
    }

    trux_redirect($redirectPath);
} catch (PDOException $e) {
    trux_flash_set('error', 'Could not update follow state right now.');
    if ($safeRedirect !== '') {
        trux_redirect($safeRedirect);
    }
    trux_redirect('/');
}
