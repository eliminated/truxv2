<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/email_helpers.php';
require_once dirname(__DIR__) . '/src/csrf.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/moderation.php';
require_once dirname(__DIR__) . '/src/posts.php';
require_once dirname(__DIR__) . '/src/bookmarks.php';
require_once dirname(__DIR__) . '/src/messages.php';
require_once dirname(__DIR__) . '/src/profiles.php';
require_once dirname(__DIR__) . '/src/follows.php';
require_once dirname(__DIR__) . '/src/mutes.php';
require_once dirname(__DIR__) . '/src/blocks.php';
require_once dirname(__DIR__) . '/src/discovery.php';
require_once dirname(__DIR__) . '/src/search.php';
require_once dirname(__DIR__) . '/src/notifications.php';
require_once dirname(__DIR__) . '/src/upload.php';

date_default_timezone_set(TRUX_TIMEZONE);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
    $_SESSION['_flash'] = [];
}

trux_csrf_verify();

$bootstrapUser = trux_current_user();
if ($bootstrapUser) {
    $activeAccessBlock = trux_moderation_fetch_active_access_block((int)$bootstrapUser['id']);
    if ($activeAccessBlock) {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        trux_flash_set('error', trux_moderation_access_block_message($activeAccessBlock));

        $currentScript = strtolower(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        if ($currentScript !== 'appeal.php') {
            trux_redirect('/login.php');
        }
    }
}
