<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$isJson = trux_str_param('format', '') === 'json';
$forcedTargetType = isset($truxForcedReportTargetType) ? trim(strtolower((string)$truxForcedReportTargetType)) : '';

$respond = static function (bool $ok, int $statusCode, string $message, array $payload = []) use ($isJson): void {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode(array_merge(['ok' => $ok], $payload, [$ok ? 'message' : 'error' => $message]));
        exit;
    }

    trux_flash_set($ok ? 'success' : 'error', $message);
};

$redirectBack = static function (string $fallbackPath = '/') use ($isJson): void {
    if ($isJson) {
        exit;
    }

    $back = $_SERVER['HTTP_REFERER'] ?? '';
    if (is_string($back) && $back !== '' && str_starts_with($back, TRUX_BASE_URL)) {
        trux_redirect(str_replace(TRUX_BASE_URL, '', $back));
    }

    trux_redirect($fallbackPath);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(false, 405, 'Method not allowed.');
    $redirectBack('/');
}

if (!trux_is_logged_in()) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Please log in to submit a report.',
            'login_url' => TRUX_BASE_URL . '/login.php',
        ]);
        exit;
    }

    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$me = trux_current_user();
if (!$me) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Please log in to continue.',
            'login_url' => TRUX_BASE_URL . '/login.php',
        ]);
        exit;
    }

    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

$targetType = $forcedTargetType !== '' ? $forcedTargetType : trim(strtolower((string)($_POST['target_type'] ?? '')));
$targetIdRaw = $_POST['target_id'] ?? ($_POST['id'] ?? null);
$reasonKey = trim((string)($_POST['reason_key'] ?? ''));
$details = trim((string)($_POST['details'] ?? ''));
$wantsReporterDmUpdates = !empty($_POST['wants_reporter_dm_updates']);

if (!is_string($targetIdRaw) || !preg_match('/^\d+$/', $targetIdRaw)) {
    $respond(false, 400, 'Invalid report target.');
    $redirectBack('/');
}

$targetId = (int)$targetIdRaw;
$result = trux_moderation_submit_report([
    'target_type' => $targetType,
    'target_id' => $targetId,
    'reporter_user_id' => (int)$me['id'],
    'reason_key' => $reasonKey,
    'details' => $details,
    'wants_reporter_dm_updates' => $wantsReporterDmUpdates,
]);

if (!empty($result['ok'])) {
    $respond(true, (int)($result['status'] ?? 200), (string)($result['message'] ?? 'Report submitted.'), [
        'report_id' => (int)($result['report_id'] ?? 0),
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);

    $target = trux_moderation_fetch_target_context($targetType, $targetId);
    $fallbackPath = is_array($target) && !empty($target['source_url']) ? (string)$target['source_url'] : '/';
    $redirectBack($fallbackPath);
}

$status = (int)($result['status'] ?? 400);
if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => (string)($result['error'] ?? 'Could not submit the report.'),
    ]);
    exit;
}

trux_flash_set('error', (string)($result['error'] ?? 'Could not submit the report.'));
$target = trux_moderation_fetch_target_context($targetType, $targetId);
$fallbackPath = is_array($target) && !empty($target['source_url']) ? (string)$target['source_url'] : '/';
$redirectBack($fallbackPath);
