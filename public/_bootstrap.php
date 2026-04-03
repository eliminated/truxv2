<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/email_helpers.php';
require_once dirname(__DIR__) . '/src/csrf.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/moderation.php';
require_once dirname(__DIR__) . '/src/guardian.php';
require_once dirname(__DIR__) . '/src/security.php';
require_once dirname(__DIR__) . '/src/linked_accounts.php';
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
require_once dirname(__DIR__) . '/src/algr.php';

date_default_timezone_set(TRUX_TIMEZONE);

$truxRequestStartedAt = microtime(true);
if (PHP_SAPI !== 'cli') {
    register_shutdown_function(static function () use ($truxRequestStartedAt): void {
        $elapsedMs = (int)round((microtime(true) - $truxRequestStartedAt) * 1000);
        if (!headers_sent()) {
            header('X-Response-Time: ' . $elapsedMs . 'ms');
        }

        if ($elapsedMs <= 200) {
            return;
        }

        $logDirectory = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }

        if (!is_dir($logDirectory) || !is_writable($logDirectory)) {
            return;
        }

        $requestUri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($requestUri === '') {
            $requestUri = trim((string)($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $statusCode = http_response_code();
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf("[%s] %s %s %d %dms\n", $timestamp, $method, $requestUri, $statusCode, $elapsedMs);
        @file_put_contents($logDirectory . '/slow_requests.log', $line, FILE_APPEND | LOCK_EX);
    });
}

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

trux_security_enforce_current_session();

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
