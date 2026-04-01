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

$rawPollId = $_POST['poll_id'] ?? null;
$rawOptionId = $_POST['option_id'] ?? null;

if (!is_string($rawPollId) || !preg_match('/^\d+$/', $rawPollId)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid poll id.']);
        exit;
    }
    trux_flash_set('error', 'Invalid poll id.');
    trux_redirect('/');
}

if (!is_string($rawOptionId) || !preg_match('/^\d+$/', $rawOptionId)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid option id.']);
        exit;
    }
    trux_flash_set('error', 'Invalid option id.');
    trux_redirect('/');
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

$pollId = (int)$rawPollId;
$optionId = (int)$rawOptionId;
$userId = (int)$me['id'];

$result = trux_vote_on_poll($userId, $pollId, $optionId);

if (!$result['ok']) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Could not record vote.']);
        exit;
    }
    trux_flash_set('error', $result['error'] ?? 'Could not record vote.');
    trux_redirect('/');
}

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'   => true,
        'poll' => $result['poll'],
    ]);
    exit;
}

$back = $_SERVER['HTTP_REFERER'] ?? '';
if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
    trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
}

trux_redirect('/');
