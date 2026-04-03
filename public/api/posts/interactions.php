<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed.',
    ]);
    exit;
}

$rawPostIds = $_POST['post_ids'] ?? [];
if (!is_array($rawPostIds)) {
    $rawPostIds = [$rawPostIds];
}

$postIds = [];
foreach ($rawPostIds as $rawPostId) {
    if (is_string($rawPostId) && preg_match('/^\d+$/', $rawPostId)) {
        $postIds[] = (int)$rawPostId;
    } elseif (is_int($rawPostId)) {
        $postIds[] = $rawPostId;
    }
}
$postIds = array_values(array_unique(array_filter($postIds, static fn(int $id): bool => $id > 0)));

if (!$postIds) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'No valid post ids were provided.',
    ]);
    exit;
}

if (count($postIds) > 25) {
    $postIds = array_slice($postIds, 0, 25);
}

$me = trux_current_user();
$viewerId = $me ? (int)$me['id'] : null;
$interactionMap = trux_fetch_post_interactions($postIds, $viewerId);

echo json_encode([
    'ok' => true,
    'interactions' => $interactionMap,
]);


