<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$isJson = trux_str_param('format', '') === 'json';

if (!trux_is_logged_in()) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to continue.', 'login_url' => TRUX_BASE_URL . '/login.php']);
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
    trux_redirect('/');
}

$theme = trim((string)($_POST['theme'] ?? 'system'));
if (!in_array($theme, ['light', 'dark', 'system'], true)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid theme value.']);
        exit;
    }
    trux_flash_set('error', 'Invalid theme value.');
    trux_redirect('/settings.php?section=interface');
}

$me = trux_current_user();
if (!$me) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Please log in to continue.', 'login_url' => TRUX_BASE_URL . '/login.php']);
        exit;
    }
    trux_require_login();
}

$db = trux_db();
$stmt = $db->prepare('UPDATE users SET theme_preference = ? WHERE id = ?');
$stmt->execute([$theme, (int)$me['id']]);

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'theme' => $theme]);
    exit;
}

trux_flash_set('success', 'Theme preference saved.');
trux_redirect('/settings.php?section=interface');
