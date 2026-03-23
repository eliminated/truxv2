<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$rawUserId = $_REQUEST['user_id'] ?? null;
$targetUserId = is_string($rawUserId) && preg_match('/^\d+$/', $rawUserId)
    ? (int)$rawUserId
    : 0;
$path = '/moderation/staff.php' . ($targetUserId > 0 ? '?user_id=' . $targetUserId : '');
trux_redirect($path);
