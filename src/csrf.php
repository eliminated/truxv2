<?php
declare(strict_types=1);

function trux_csrf_token(): string {
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || strlen($_SESSION['_csrf']) < 20) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function trux_csrf_field(): string {
    $t = trux_csrf_token();
    return '<input type="hidden" name="_csrf" value="' . trux_e($t) . '">';
}

function trux_csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $sent = $_POST['_csrf'] ?? '';
    $sess = $_SESSION['_csrf'] ?? '';

    if (!is_string($sent) || !is_string($sess) || $sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
        http_response_code(403);
        echo "Forbidden (CSRF).";
        exit;
    }
}