<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw = $_GET['q'] ?? null;
$query = is_string($raw) ? trim($raw) : '';
$normalized = trux_normalize_mention_fragment($query);
if ($normalized === '') {
    echo json_encode([
        'ok' => true,
        'query' => '',
        'users' => [],
    ]);
    exit;
}

$users = trux_search_users_by_prefix($normalized, 6);
$payload = [];
foreach ($users as $user) {
    $payload[] = [
        'id' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'profile_url' => '/profile.php?u=' . rawurlencode((string)($user['username'] ?? '')),
    ];
}

echo json_encode([
    'ok' => true,
    'query' => $normalized,
    'users' => $payload,
]);
