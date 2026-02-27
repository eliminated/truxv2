<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/csrf.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/posts.php';
require_once dirname(__DIR__) . '/src/search.php';
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