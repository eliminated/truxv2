<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$me = trux_current_user();
if (!$me) {
    echo json_encode([
        'notifications' => 0,
        'messages' => 0,
    ]);
    exit;
}

echo json_encode([
    'notifications' => trux_count_unread_notifications((int)$me['id']),
    'messages' => trux_count_unread_direct_messages((int)$me['id']),
]);


