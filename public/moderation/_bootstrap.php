<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

trux_require_staff_role('developer');

$moderationMe = trux_current_user();
if (!$moderationMe) {
    trux_redirect('/login.php');
}

$moderationStaffRole = trux_staff_role((string)($moderationMe['staff_role'] ?? 'user'));
$pageLayout = isset($pageLayout) && is_string($pageLayout) && trim($pageLayout) !== '' ? $pageLayout : 'moderation';
