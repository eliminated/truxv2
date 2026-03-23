<?php
declare(strict_types=1);

function trux_moderation_staff_roles(): array {
    return [
        'user' => 0,
        'developer' => 10,
        'moderator' => 20,
        'admin' => 30,
        'owner' => 40,
    ];
}

function trux_staff_role(string $role): string {
    $normalized = strtolower(trim($role));
    $roles = trux_moderation_staff_roles();
    return array_key_exists($normalized, $roles) ? $normalized : 'user';
}

function trux_staff_role_rank(string $role): int {
    $roles = trux_moderation_staff_roles();
    return (int)($roles[trux_staff_role($role)] ?? 0);
}

function trux_has_staff_role(string $currentRole, string $minimumRole): bool {
    return trux_staff_role_rank($currentRole) >= trux_staff_role_rank($minimumRole);
}

function trux_staff_role_label(string $role): string {
    return match (trux_staff_role($role)) {
        'developer' => 'Developer',
        'moderator' => 'Moderator',
        'admin' => 'Administrator',
        'owner' => 'Owner',
        default => 'User',
    };
}

function trux_is_owner_staff_role(?string $staffRole = null): bool {
    $role = $staffRole === null ? trux_current_staff_role() : trux_staff_role($staffRole);
    return $role === 'owner';
}

function trux_manageable_staff_roles(): array {
    return [
        'user' => trux_staff_role_label('user'),
        'developer' => trux_staff_role_label('developer'),
        'moderator' => trux_staff_role_label('moderator'),
        'admin' => trux_staff_role_label('admin'),
    ];
}

function trux_assignable_staff_roles(): array {
    return trux_manageable_staff_roles();
}

function trux_current_staff_role(): string {
    $user = trux_current_user();
    return trux_staff_role((string)($user['staff_role'] ?? 'user'));
}

function trux_require_staff_role(string $minimumRole): void {
    trux_require_login();

    $user = trux_current_user();
    if ($user && trux_has_staff_role((string)($user['staff_role'] ?? 'user'), $minimumRole)) {
        return;
    }

    trux_flash_set('error', 'You do not have access to the moderation area.');
    trux_redirect('/');
}

function trux_can_moderation_write(?string $staffRole = null): bool {
    $role = $staffRole === null ? trux_current_staff_role() : trux_staff_role($staffRole);
    return trux_has_staff_role($role, 'moderator');
}

function trux_can_moderation_reassign(?string $staffRole = null): bool {
    $role = $staffRole === null ? trux_current_staff_role() : trux_staff_role($staffRole);
    return trux_has_staff_role($role, 'admin');
}

function trux_can_view_full_moderation_audit(?string $staffRole = null): bool {
    $role = $staffRole === null ? trux_current_staff_role() : trux_staff_role($staffRole);
    return trux_has_staff_role($role, 'admin');
}

function trux_can_manage_staff_roles(?string $staffRole = null): bool {
    $role = $staffRole === null ? trux_current_staff_role() : trux_staff_role($staffRole);
    return trux_has_staff_role($role, 'admin');
}

function trux_report_system_username(): string {
    return 'report_system_updates_bot';
}

function trux_report_system_email(): string {
    return 'report-system-updates@system.invalid';
}

function trux_report_system_display_name(): string {
    return 'Report System Updates';
}

function trux_is_report_system_user(string $username): bool {
    return strtolower(trim($username)) === trux_report_system_username();
}

function trux_fetch_report_system_user(): ?array {
    static $cache = null;
    static $loaded = false;

    if ($loaded) {
        return $cache;
    }

    $loaded = true;
    $cache = trux_fetch_user_by_username(trux_report_system_username());
    return $cache;
}

function trux_report_system_user_id(): int {
    $user = trux_fetch_report_system_user();
    return $user ? (int)($user['id'] ?? 0) : 0;
}

function trux_moderation_modules(): array {
    return [
        'dashboard' => [
            'title' => 'Overview',
            'description' => 'Queue health, attention items, and recent staff actions.',
            'path' => '/moderation/',
            'minimum_role' => 'developer',
        ],
        'reports' => [
            'title' => 'Reports',
            'description' => 'Review reported content and assignment state.',
            'path' => '/moderation/reports.php',
            'minimum_role' => 'developer',
        ],
        'user_review' => [
            'title' => 'User Review',
            'description' => 'Watchlist, account notes, and linked user context.',
            'path' => '/moderation/user_review.php',
            'minimum_role' => 'developer',
        ],
        'activity' => [
            'title' => 'Suspicious Activity',
            'description' => 'Open signals generated from app and security events.',
            'path' => '/moderation/activity.php',
            'minimum_role' => 'developer',
        ],
        'audit_logs' => [
            'title' => 'Audit Logs',
            'description' => 'Immutable staff action history.',
            'path' => '/moderation/audit_logs.php',
            'minimum_role' => 'developer',
        ],
        'escalations' => [
            'title' => 'Escalations',
            'description' => 'Hand-off queue for admin and owner review.',
            'path' => '/moderation/escalations.php',
            'minimum_role' => 'admin',
        ],
        'rule_tuning' => [
            'title' => 'Rule Tuning',
            'description' => 'Thresholds, heuristics, and alert calibration.',
            'path' => '/moderation/rule_tuning.php',
            'minimum_role' => 'admin',
        ],
        'appeals' => [
            'title' => 'Appeals',
            'description' => 'Account-action appeals and resolutions.',
            'path' => '/moderation/appeals.php',
            'minimum_role' => 'admin',
        ],
        'staff_access' => [
            'title' => 'Staff Access',
            'description' => 'Search accounts, adjust roles, and review staff history.',
            'path' => '/moderation/staff.php',
            'minimum_role' => 'admin',
        ],
    ];
}

function trux_visible_moderation_modules(?string $staffRole = null): array {
    $role = $staffRole === null ? trux_current_staff_role() : trux_staff_role($staffRole);
    $visible = [];

    foreach (trux_moderation_modules() as $moduleKey => $module) {
        $minimumRole = trux_staff_role((string)($module['minimum_role'] ?? 'developer'));
        if (!trux_has_staff_role($role, $minimumRole)) {
            continue;
        }

        $visible[$moduleKey] = $module;
    }

    return $visible;
}

function trux_moderation_future_modules(): array {
    return [];
}

function trux_moderation_report_statuses(): array {
    return [
        'open' => 'Open',
        'investigating' => 'Investigating',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
    ];
}

function trux_moderation_is_report_archived_status(string $status): bool {
    return in_array(trim(strtolower($status)), ['resolved', 'dismissed'], true);
}

function trux_moderation_report_resolution_actions(): array {
    return [
        'content_removed' => 'Content removed',
        'content_already_unavailable' => 'Content already unavailable',
        'user_case_opened' => 'User case opened',
        'warning_issued' => 'Warning issued',
        'dm_restricted' => 'DM restriction applied',
        'account_suspended' => 'Account suspended',
        'account_locked' => 'Account locked',
    ];
}

function trux_moderation_is_valid_report_resolution_action(?string $actionKey): bool {
    $value = trim(strtolower((string)$actionKey));
    return $value !== '' && array_key_exists($value, trux_moderation_report_resolution_actions());
}

function trux_moderation_archived_report_message(string $status, ?string $resolutionActionKey = null, ?string $targetType = null): string {
    $status = trim(strtolower($status));
    $resolutionActionKey = trim(strtolower((string)$resolutionActionKey));
    $targetType = trim(strtolower((string)$targetType));

    if ($status === 'resolved') {
        if ($resolutionActionKey === 'content_removed') {
            return match ($targetType) {
                'comment' => 'Report reviewed. Violation confirmed and the comment was removed.',
                'message' => 'Report reviewed. Violation confirmed and the message was removed.',
                default => 'Report reviewed. Violation confirmed and the content was removed.',
            };
        }

        if ($resolutionActionKey === 'content_already_unavailable') {
            return 'Report reviewed. Violation confirmed, but the reported content was already unavailable.';
        }

        if ($resolutionActionKey === 'user_case_opened') {
            return 'Report reviewed. A staff user case was opened for follow-up.';
        }

        if ($resolutionActionKey === 'warning_issued') {
            return 'Report reviewed. Violation confirmed and the account received a warning.';
        }

        if ($resolutionActionKey === 'dm_restricted') {
            return 'Report reviewed. Violation confirmed and the account lost DM access.';
        }

        if ($resolutionActionKey === 'account_suspended') {
            return 'Report reviewed. Violation confirmed and the account was suspended.';
        }

        if ($resolutionActionKey === 'account_locked') {
            return 'Report reviewed. Violation confirmed and the account was locked.';
        }

        return 'Report reviewed. Violation confirmed and action taken.';
    }

    return match ($status) {
        'dismissed' => 'Report reviewed. No violation found.',
        default => 'Report review is complete.',
    };
}

function trux_moderation_report_priorities(): array {
    return [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'critical' => 'Critical',
    ];
}

function trux_moderation_report_target_types(): array {
    return [
        'user' => 'User',
        'post' => 'Post',
        'comment' => 'Comment',
        'message' => 'Message',
    ];
}

function trux_moderation_report_reason_options(): array {
    return [
        'spam' => 'Spam',
        'harassment' => 'Harassment',
        'hate' => 'Hate',
        'violence' => 'Violence',
        'sexual_content' => 'Sexual content',
        'self_harm' => 'Self-harm',
        'impersonation' => 'Impersonation',
        'scam' => 'Scam',
        'other' => 'Other',
    ];
}

function trux_moderation_suspicious_statuses(): array {
    return [
        'open' => 'Open',
        'reviewed' => 'Reviewed',
        'false_positive' => 'False positive',
    ];
}

function trux_moderation_severity_options(): array {
    return [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];
}

function trux_moderation_rule_labels(): array {
    return [
        'repeated_failed_login' => 'Repeated failed logins',
        'content_burst' => 'Burst posting or commenting',
        'dm_burst_multiple_recipients' => 'Burst DMs to many recipients',
        'multiple_reports_same_account' => 'Multiple distinct reports against one account',
        'spam_link_burst' => 'Burst content with links',
        'duplicate_content_burst' => 'Repeated duplicate content',
        'follow_burst' => 'Burst follow activity',
        'multiple_blocks_same_account' => 'Multiple distinct blocks against one account',
    ];
}

function trux_moderation_audit_action_labels(): array {
    return [
        'report_status_updated' => 'Report status updated',
        'report_assigned' => 'Head reviewer assigned',
        'report_unassigned' => 'Head reviewer cleared',
        'report_review_note_added' => 'Review discussion added',
        'report_vote_recorded' => 'Review vote recorded',
        'user_case_created' => 'User case created',
        'user_case_updated' => 'User case updated',
        'user_case_watchlist_updated' => 'Watchlist updated',
        'user_case_note_added' => 'User case note added',
        'user_case_assigned' => 'User case assignee updated',
        'user_case_reopened' => 'User case reopened',
        'user_case_closed' => 'User case closed',
        'suspicious_event_assigned' => 'Suspicious event assignee updated',
        'staff_role_updated' => 'Staff role updated',
        'suspicious_event_reviewed' => 'Suspicious event reviewed',
        'suspicious_event_false_positive' => 'Suspicious event marked false positive',
        'suspicious_event_reopened' => 'Suspicious event reopened',
        'escalation_created' => 'Escalation created',
        'escalation_assigned' => 'Escalation assignee updated',
        'escalation_status_updated' => 'Escalation status updated',
        'appeal_submitted' => 'Appeal submitted',
        'appeal_assigned' => 'Appeal assignee updated',
        'appeal_status_updated' => 'Appeal status updated',
        'user_enforcement_created' => 'User enforcement created',
        'user_enforcement_revoked' => 'User enforcement revoked',
        'rule_config_updated' => 'Rule config updated',
    ];
}

function trux_moderation_subject_type_labels(): array {
    return [
        'report' => 'Report',
        'suspicious_event' => 'Suspicious event',
        'user' => 'User',
        'user_case' => 'User case',
        'escalation' => 'Escalation',
        'appeal' => 'Appeal',
        'user_enforcement' => 'User enforcement',
        'rule_config' => 'Rule config',
    ];
}

function trux_moderation_user_case_statuses(): array {
    return [
        'open' => 'Open',
        'investigating' => 'Investigating',
        'escalated' => 'Escalated',
        'closed' => 'Closed',
    ];
}

function trux_moderation_user_case_priorities(): array {
    return trux_moderation_report_priorities();
}

function trux_moderation_user_case_resolution_actions(): array {
    return [
        'monitor_only' => 'Monitor only',
        'no_violation' => 'No violation',
        'warning_issued' => 'Warning issued',
        'dm_restricted' => 'DM restriction applied',
        'account_suspended' => 'Account suspended',
        'account_locked' => 'Account locked',
    ];
}

function trux_moderation_user_enforcement_actions(): array {
    return [
        'warning_issued' => 'Warning issued',
        'dm_restricted' => 'DM restriction applied',
        'account_suspended' => 'Account suspended',
        'account_locked' => 'Account locked',
    ];
}

function trux_moderation_user_enforcement_statuses(): array {
    return [
        'active' => 'Active',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
    ];
}

function trux_moderation_escalation_queue_roles(): array {
    return [
        'admin' => 'Administrator queue',
        'owner' => 'Owner queue',
    ];
}

function trux_moderation_escalation_statuses(): array {
    return [
        'open' => 'Open',
        'in_review' => 'In review',
        'resolved' => 'Resolved',
    ];
}

function trux_moderation_appeal_statuses(): array {
    return [
        'open' => 'Open',
        'investigating' => 'Investigating',
        'upheld' => 'Upheld',
        'denied' => 'Denied',
    ];
}

function trux_moderation_is_valid_report_status(string $status): bool {
    return array_key_exists($status, trux_moderation_report_statuses());
}

function trux_moderation_is_valid_report_priority(string $priority): bool {
    return array_key_exists($priority, trux_moderation_report_priorities());
}

function trux_moderation_is_valid_report_target_type(string $targetType): bool {
    return array_key_exists($targetType, trux_moderation_report_target_types());
}

function trux_moderation_is_valid_report_reason(string $reason): bool {
    return array_key_exists($reason, trux_moderation_report_reason_options());
}

function trux_moderation_report_vote_options(): array {
    return [
        'yay' => 'Yay',
        'nay' => 'Nay',
    ];
}

function trux_moderation_is_valid_report_vote(string $vote): bool {
    return array_key_exists($vote, trux_moderation_report_vote_options());
}

function trux_moderation_is_valid_suspicious_status(string $status): bool {
    return array_key_exists($status, trux_moderation_suspicious_statuses());
}

function trux_moderation_is_valid_severity(string $severity): bool {
    return array_key_exists($severity, trux_moderation_severity_options());
}

function trux_moderation_is_valid_rule_key(string $ruleKey): bool {
    return array_key_exists($ruleKey, trux_moderation_rule_labels());
}

function trux_moderation_is_valid_user_case_status(string $status): bool {
    return array_key_exists($status, trux_moderation_user_case_statuses());
}

function trux_moderation_is_valid_user_case_priority(string $priority): bool {
    return array_key_exists($priority, trux_moderation_user_case_priorities());
}

function trux_moderation_is_valid_user_case_resolution_action(?string $actionKey): bool {
    $value = trim(strtolower((string)$actionKey));
    return $value !== '' && array_key_exists($value, trux_moderation_user_case_resolution_actions());
}

function trux_moderation_is_valid_user_enforcement_action(?string $actionKey): bool {
    $value = trim(strtolower((string)$actionKey));
    return $value !== '' && array_key_exists($value, trux_moderation_user_enforcement_actions());
}

function trux_moderation_is_valid_user_enforcement_status(string $status): bool {
    return array_key_exists($status, trux_moderation_user_enforcement_statuses());
}

function trux_moderation_is_valid_escalation_queue_role(string $queueRole): bool {
    return array_key_exists($queueRole, trux_moderation_escalation_queue_roles());
}

function trux_moderation_is_valid_escalation_status(string $status): bool {
    return array_key_exists($status, trux_moderation_escalation_statuses());
}

function trux_moderation_is_valid_appeal_status(string $status): bool {
    return array_key_exists($status, trux_moderation_appeal_statuses());
}

function trux_moderation_label(array $labels, string $key, string $fallback = ''): string {
    if (isset($labels[$key]) && is_string($labels[$key])) {
        return $labels[$key];
    }

    if ($fallback !== '') {
        return $fallback;
    }

    return ucfirst(str_replace('_', ' ', $key));
}

function trux_moderation_clean_text(?string $value, int $maxLength = 255): ?string {
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }

    return mb_substr($text, 0, max(1, $maxLength));
}

function trux_moderation_clean_path(?string $value): ?string {
    $path = trim((string)$value);
    if ($path === '') {
        return null;
    }

    if (str_starts_with($path, '//') || preg_match('/[\r\n]/', $path)) {
        return null;
    }

    return mb_substr($path, 0, 255);
}

function trux_moderation_clean_map(array $values): array {
    $clean = [];

    foreach ($values as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if (is_array($value)) {
            $nested = trux_moderation_clean_map($value);
            if ($nested !== []) {
                $clean[$key] = $nested;
            }
            continue;
        }

        if ($value === null) {
            continue;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            $clean[$key] = $value;
            continue;
        }

        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            continue;
        }

        $clean[$key] = mb_substr($stringValue, 0, 500);
    }

    return $clean;
}

function trux_moderation_json_encode(array $data): string {
    try {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : '{}';
    } catch (JsonException) {
        return '{}';
    }
}

function trux_moderation_json_decode(?string $json): array {
    $value = trim((string)$json);
    if ($value === '') {
        return [];
    }

    try {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (JsonException) {
        return [];
    }
}

function trux_moderation_normalize_text_for_fingerprint(?string $value): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $text = strtolower($text);
    $text = preg_replace('/https?:\/\/\S+/i', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = preg_replace('/[^a-z0-9\s]+/u', '', $text) ?? $text;
    return trim($text);
}

function trux_moderation_text_fingerprint(?string $value): ?string {
    $normalized = trux_moderation_normalize_text_for_fingerprint($value);
    if ($normalized === '') {
        return null;
    }

    return sha1($normalized);
}

function trux_moderation_link_count(?string $value): int {
    $text = (string)$value;
    if ($text === '') {
        return 0;
    }

    preg_match_all('/https?:\/\/\S+/i', $text, $matches);
    return count($matches[0] ?? []);
}

function trux_current_request_ip(): ?string {
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return null;
    }

    return mb_substr($ip, 0, 45);
}

function trux_current_user_agent(): ?string {
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent === '') {
        return null;
    }

    return mb_substr($userAgent, 0, 255);
}

function trux_moderation_now(): DateTimeImmutable {
    return new DateTimeImmutable('now');
}

function trux_moderation_format_datetime(DateTimeImmutable $value): string {
    return $value->format('Y-m-d H:i:s');
}

function trux_moderation_resolution_action_label(string $actionKey): string {
    return trux_moderation_label(trux_moderation_report_resolution_actions(), $actionKey);
}

function trux_moderation_trimmed_excerpt(?string $value, int $maxLength = 160): string {
    $text = trim(preg_replace('/\s+/', ' ', (string)$value) ?? '');
    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, max(1, $maxLength - 1))) . '…';
}

function trux_moderation_target_label_from_snapshot(string $targetType, int $targetId, array $snapshot = []): string {
    $username = trim((string)($snapshot['author_username'] ?? $snapshot['sender_username'] ?? $snapshot['username'] ?? ''));

    return match ($targetType) {
        'user' => $username !== '' ? 'User @' . $username : trux_moderation_target_label($targetType, $targetId),
        'post' => $username !== '' ? 'Post #' . $targetId . ' by @' . $username : trux_moderation_target_label($targetType, $targetId),
        'comment' => $username !== '' ? 'Comment #' . $targetId . ' by @' . $username : trux_moderation_target_label($targetType, $targetId),
        'message' => $username !== '' ? 'Message #' . $targetId . ' from @' . $username : trux_moderation_target_label($targetType, $targetId),
        default => trux_moderation_target_label($targetType, $targetId),
    };
}

function trux_moderation_fetch_message_conversation_context(int $conversationId, int $messageId, int $radius = 2): array {
    if ($conversationId <= 0 || $messageId <= 0) {
        return [];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.created_at,
                    u.username AS sender_username, u.display_name AS sender_display_name
             FROM direct_messages m
             JOIN users u ON u.id = m.sender_user_id
             WHERE m.conversation_id = ?
             ORDER BY m.id ASC'
        );
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll();
        if (!$messages) {
            return [];
        }

        $targetIndex = null;
        foreach ($messages as $index => $message) {
            if ((int)($message['id'] ?? 0) === $messageId) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === null) {
            return [];
        }

        $start = max(0, $targetIndex - max(0, $radius));
        $length = max(1, ($radius * 2) + 1);
        $slice = array_slice($messages, $start, $length);
        foreach ($slice as &$message) {
            $message['is_reported_target'] = (int)($message['id'] ?? 0) === $messageId;
        }
        unset($message);

        return $slice;
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_post_target(int $postId): ?array {
    $post = trux_fetch_post_by_id($postId);
    if (!$post) {
        return null;
    }

    $username = trim((string)($post['username'] ?? ''));
    return [
        'target_type' => 'post',
        'target_id' => $postId,
        'owner_user_id' => (int)($post['user_id'] ?? 0),
        'owner_username' => $username,
        'source_url' => trux_post_viewer_path($postId),
        'target_label' => $username !== '' ? 'Post #' . $postId . ' by @' . $username : trux_moderation_target_label('post', $postId),
        'snapshot' => [
            'post_id' => $postId,
            'author_user_id' => (int)($post['user_id'] ?? 0),
            'author_username' => $username,
            'author_avatar_path' => (string)($post['avatar_path'] ?? ''),
            'body' => (string)($post['body'] ?? ''),
            'image_path' => (string)($post['image_path'] ?? ''),
            'created_at' => (string)($post['created_at'] ?? ''),
            'edited_at' => (string)($post['edited_at'] ?? ''),
        ],
        'live_context' => [
            'post' => [
                'id' => $postId,
                'body' => (string)($post['body'] ?? ''),
                'image_path' => (string)($post['image_path'] ?? ''),
                'created_at' => (string)($post['created_at'] ?? ''),
                'edited_at' => (string)($post['edited_at'] ?? ''),
                'author_username' => $username,
                'author_avatar_path' => (string)($post['avatar_path'] ?? ''),
            ],
        ],
    ];
}

function trux_moderation_fetch_comment_target(int $commentId): ?array {
    if ($commentId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at,
                    u.username, u.avatar_path,
                    ru.username AS reply_to_username,
                    p.body AS post_body,
                    pu.username AS post_username
             FROM post_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN users ru ON ru.id = c.reply_to_user_id
             JOIN posts p ON p.id = c.post_id
             JOIN users pu ON pu.id = p.user_id
             WHERE c.id = ?
             LIMIT 1'
        );
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        if (!$comment) {
            return null;
        }

        $username = trim((string)($comment['username'] ?? ''));
        $postId = (int)($comment['post_id'] ?? 0);
        return [
            'target_type' => 'comment',
            'target_id' => $commentId,
            'owner_user_id' => (int)($comment['user_id'] ?? 0),
            'owner_username' => $username,
            'source_url' => trux_post_viewer_path($postId, $commentId),
            'target_label' => $username !== '' ? 'Comment #' . $commentId . ' by @' . $username : trux_moderation_target_label('comment', $commentId),
            'snapshot' => [
                'comment_id' => $commentId,
                'post_id' => $postId,
                'parent_comment_id' => isset($comment['parent_comment_id']) && $comment['parent_comment_id'] !== null ? (int)$comment['parent_comment_id'] : null,
                'reply_to_user_id' => isset($comment['reply_to_user_id']) && $comment['reply_to_user_id'] !== null ? (int)$comment['reply_to_user_id'] : null,
                'reply_to_username' => (string)($comment['reply_to_username'] ?? ''),
                'author_user_id' => (int)($comment['user_id'] ?? 0),
                'author_username' => $username,
                'author_avatar_path' => (string)($comment['avatar_path'] ?? ''),
                'body' => (string)($comment['body'] ?? ''),
                'created_at' => (string)($comment['created_at'] ?? ''),
                'edited_at' => (string)($comment['edited_at'] ?? ''),
                'post_excerpt' => trux_moderation_trimmed_excerpt((string)($comment['post_body'] ?? ''), 180),
                'post_username' => (string)($comment['post_username'] ?? ''),
            ],
            'live_context' => [
                'comment' => [
                    'id' => $commentId,
                    'post_id' => $postId,
                    'parent_comment_id' => isset($comment['parent_comment_id']) && $comment['parent_comment_id'] !== null ? (int)$comment['parent_comment_id'] : null,
                    'reply_to_user_id' => isset($comment['reply_to_user_id']) && $comment['reply_to_user_id'] !== null ? (int)$comment['reply_to_user_id'] : null,
                    'reply_to_username' => (string)($comment['reply_to_username'] ?? ''),
                    'body' => (string)($comment['body'] ?? ''),
                    'created_at' => (string)($comment['created_at'] ?? ''),
                    'edited_at' => (string)($comment['edited_at'] ?? ''),
                    'author_username' => $username,
                    'author_avatar_path' => (string)($comment['avatar_path'] ?? ''),
                ],
                'post' => [
                    'id' => $postId,
                    'excerpt' => trux_moderation_trimmed_excerpt((string)($comment['post_body'] ?? ''), 180),
                    'author_username' => (string)($comment['post_username'] ?? ''),
                ],
            ],
        ];
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_fetch_message_target(int $messageId): ?array {
    if ($messageId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.created_at,
                    c.user_one_id, c.user_two_id,
                    sender.username AS sender_username,
                    sender.display_name AS sender_display_name,
                    recipient.id AS recipient_user_id,
                    recipient.username AS recipient_username,
                    recipient.display_name AS recipient_display_name
             FROM direct_messages m
             JOIN direct_conversations c ON c.id = m.conversation_id
             JOIN users sender ON sender.id = m.sender_user_id
             JOIN users recipient ON recipient.id = CASE
                WHEN c.user_one_id = m.sender_user_id THEN c.user_two_id
                ELSE c.user_one_id
             END
             WHERE m.id = ?
             LIMIT 1'
        );
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        if (!$message) {
            return null;
        }

        $conversationId = (int)($message['conversation_id'] ?? 0);
        $senderUsername = trim((string)($message['sender_username'] ?? ''));
        return [
            'target_type' => 'message',
            'target_id' => $messageId,
            'owner_user_id' => (int)($message['sender_user_id'] ?? 0),
            'owner_username' => $senderUsername,
            'source_url' => '/messages.php?id=' . $conversationId . '#message-' . $messageId,
            'target_label' => $senderUsername !== '' ? 'Message #' . $messageId . ' from @' . $senderUsername : trux_moderation_target_label('message', $messageId),
            'snapshot' => [
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'sender_user_id' => (int)($message['sender_user_id'] ?? 0),
                'sender_username' => $senderUsername,
                'sender_display_name' => (string)($message['sender_display_name'] ?? ''),
                'recipient_user_id' => (int)($message['recipient_user_id'] ?? 0),
                'recipient_username' => (string)($message['recipient_username'] ?? ''),
                'recipient_display_name' => (string)($message['recipient_display_name'] ?? ''),
                'body' => (string)($message['body'] ?? ''),
                'created_at' => (string)($message['created_at'] ?? ''),
            ],
            'live_context' => [
                'message' => [
                    'id' => $messageId,
                    'conversation_id' => $conversationId,
                    'body' => (string)($message['body'] ?? ''),
                    'created_at' => (string)($message['created_at'] ?? ''),
                    'sender_user_id' => (int)($message['sender_user_id'] ?? 0),
                    'sender_username' => $senderUsername,
                    'recipient_user_id' => (int)($message['recipient_user_id'] ?? 0),
                    'recipient_username' => (string)($message['recipient_username'] ?? ''),
                ],
                'conversation_messages' => trux_moderation_fetch_message_conversation_context($conversationId, $messageId),
            ],
        ];
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_fetch_user_target(int $userId): ?array {
    $user = trux_fetch_user_by_id($userId);
    if (!$user) {
        return null;
    }

    $username = trim((string)($user['username'] ?? ''));
    return [
        'target_type' => 'user',
        'target_id' => $userId,
        'owner_user_id' => $userId,
        'owner_username' => $username,
        'source_url' => $username !== '' ? '/profile.php?u=' . rawurlencode($username) : '/profile.php',
        'target_label' => $username !== '' ? 'User @' . $username : trux_moderation_target_label('user', $userId),
        'snapshot' => [
            'user_id' => $userId,
            'username' => $username,
            'display_name' => (string)($user['display_name'] ?? ''),
            'bio' => (string)($user['bio'] ?? ''),
            'location' => (string)($user['location'] ?? ''),
            'website_url' => (string)($user['website_url'] ?? ''),
            'avatar_path' => (string)($user['avatar_path'] ?? ''),
            'banner_path' => (string)($user['banner_path'] ?? ''),
            'created_at' => (string)($user['created_at'] ?? ''),
            'staff_role' => trux_staff_role((string)($user['staff_role'] ?? 'user')),
        ],
        'live_context' => [
            'user' => [
                'id' => $userId,
                'username' => $username,
                'display_name' => (string)($user['display_name'] ?? ''),
                'bio' => (string)($user['bio'] ?? ''),
                'location' => (string)($user['location'] ?? ''),
                'website_url' => (string)($user['website_url'] ?? ''),
                'avatar_path' => (string)($user['avatar_path'] ?? ''),
                'banner_path' => (string)($user['banner_path'] ?? ''),
                'created_at' => (string)($user['created_at'] ?? ''),
                'staff_role' => trux_staff_role((string)($user['staff_role'] ?? 'user')),
            ],
        ],
    ];
}

function trux_moderation_fetch_target_context(string $targetType, int $targetId): ?array {
    $targetType = trim(strtolower($targetType));
    if ($targetId <= 0 || !trux_moderation_is_valid_report_target_type($targetType)) {
        return null;
    }

    static $cache = [];
    $cacheKey = $targetType . ':' . $targetId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $target = match ($targetType) {
        'post' => trux_moderation_fetch_post_target($targetId),
        'comment' => trux_moderation_fetch_comment_target($targetId),
        'message' => trux_moderation_fetch_message_target($targetId),
        'user' => trux_moderation_fetch_user_target($targetId),
        default => null,
    };

    $cache[$cacheKey] = $target;
    return $target;
}

function trux_moderation_hydrate_report_row(array $row): array {
    $targetType = trim((string)($row['target_type'] ?? ''));
    $targetId = (int)($row['target_id'] ?? 0);
    $snapshot = trux_moderation_json_decode((string)($row['target_snapshot_json'] ?? ''));
    $liveTarget = trux_moderation_fetch_target_context($targetType, $targetId);

    if ($snapshot === [] && is_array($liveTarget['snapshot'] ?? null)) {
        $snapshot = $liveTarget['snapshot'];
    }

    $row['snapshot'] = $snapshot;
    $row['live_context'] = is_array($liveTarget['live_context'] ?? null) ? $liveTarget['live_context'] : [];
    $row['target_available'] = $liveTarget !== null;
    $row['owner_user_id'] = (int)($row['target_owner_user_id'] ?? ($liveTarget['owner_user_id'] ?? 0));
    $row['owner_username'] = trim((string)($row['target_owner_username'] ?? ($liveTarget['owner_username'] ?? '')));

    if (trim((string)($row['source_url'] ?? '')) === '' && !empty($liveTarget['source_url'])) {
        $row['source_url'] = (string)$liveTarget['source_url'];
    }

    $row['target_label'] = trim((string)($liveTarget['target_label'] ?? ''));
    if ($row['target_label'] === '') {
        $row['target_label'] = trux_moderation_target_label_from_snapshot($targetType, $targetId, $snapshot);
    }
    if ($row['target_label'] === '') {
        $row['target_label'] = trux_moderation_target_label($targetType, $targetId);
    }

    $resolutionActionKey = trim(strtolower((string)($row['resolution_action_key'] ?? '')));
    $row['resolution_action_key'] = trux_moderation_is_valid_report_resolution_action($resolutionActionKey) ? $resolutionActionKey : null;
    $row['resolution_action_label'] = $row['resolution_action_key'] !== null
        ? trux_moderation_resolution_action_label((string)$row['resolution_action_key'])
        : '';

    return $row;
}

function trux_moderation_can_finalize_report(array $report, ?array $actor = null): bool {
    $user = is_array($actor) ? $actor : trux_current_user();
    if (!$user || !trux_can_moderation_write((string)($user['staff_role'] ?? 'user'))) {
        return false;
    }

    if (trux_moderation_is_report_archived_status((string)($report['status'] ?? ''))) {
        return false;
    }

    $actorUserId = (int)($user['id'] ?? 0);
    if ($actorUserId <= 0) {
        return false;
    }

    $headReviewerUserId = isset($report['assigned_staff_user_id']) && $report['assigned_staff_user_id'] !== null
        ? (int)$report['assigned_staff_user_id']
        : 0;
    if ($headReviewerUserId > 0 && $headReviewerUserId === $actorUserId) {
        return true;
    }

    return trux_can_moderation_reassign((string)($user['staff_role'] ?? 'user'));
}

function trux_fetch_staff_users(string $minimumRole = 'developer'): array {
    $minimumRank = trux_staff_role_rank($minimumRole);

    try {
        $db = trux_db();
        $stmt = $db->query(
            "SELECT id, username, staff_role
             FROM users
             WHERE staff_role <> 'user'
             ORDER BY FIELD(staff_role, 'owner', 'admin', 'moderator', 'developer', 'user'), username ASC"
        );

        $users = [];
        foreach ($stmt->fetchAll() as $row) {
            $role = trux_staff_role((string)($row['staff_role'] ?? 'user'));
            if (trux_staff_role_rank($role) < $minimumRank) {
                continue;
            }
            $row['staff_role'] = $role;
            $users[] = $row;
        }

        return $users;
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_search_staff_access_users(string $query, string $currentRole = '', int $limit = 18): array {
    $query = trim($query);
    $currentRole = trim($currentRole);
    $limit = max(1, min(50, $limit));

    $validFilter = $currentRole !== '' && array_key_exists(trux_staff_role($currentRole), trux_moderation_staff_roles())
        ? trux_staff_role($currentRole)
        : '';

    try {
        $db = trux_db();
        $where = [];
        $params = [];

        if ($query === '') {
            $where[] = "u.staff_role <> 'user'";
        } else {
            $like = '%' . trux_like_escape($query) . '%';
            $where[] = '(u.username LIKE ? ESCAPE \'\\\' OR u.display_name LIKE ? ESCAPE \'\\\' OR u.email LIKE ? ESCAPE \'\\\'' . (preg_match('/^\d+$/', $query) ? ' OR u.id = ?' : '') . ')';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            if (preg_match('/^\d+$/', $query)) {
                $params[] = (int)$query;
            }
        }

        if ($validFilter !== '') {
            $where[] = 'u.staff_role = ?';
            $params[] = $validFilter;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare(
            "SELECT u.id, u.username, u.email, u.display_name, u.created_at, u.staff_role
             FROM users u
             $whereSql
             ORDER BY FIELD(u.staff_role, 'owner', 'admin', 'moderator', 'developer', 'user'), u.username ASC
             LIMIT ?"
        );

        $bindIndex = 1;
        foreach ($params as $value) {
            if (is_int($value)) {
                $stmt->bindValue($bindIndex++, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($bindIndex++, $value, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue($bindIndex, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['staff_role'] = trux_staff_role((string)($row['staff_role'] ?? 'user'));
        }
        unset($row);

        return $rows;
    } catch (PDOException) {
        return [];
    }
}

function trux_fetch_user_staff_role(int $userId): string {
    if ($userId <= 0) {
        return 'user';
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('SELECT staff_role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        return is_string($role) ? trux_staff_role($role) : 'user';
    } catch (PDOException) {
        return 'user';
    }
}

function trux_moderation_is_staff_role_demotion(string $fromRole, string $toRole): bool {
    return trux_staff_role_rank($toRole) < trux_staff_role_rank($fromRole);
}

function trux_moderation_staff_role_target_lock_reason(?array $targetUser, int $actorUserId): ?string {
    if (!$targetUser) {
        return 'target_not_found';
    }

    $targetUserId = (int)($targetUser['id'] ?? 0);
    $targetUsername = trim((string)($targetUser['username'] ?? ''));
    $targetRole = trux_staff_role((string)($targetUser['staff_role'] ?? 'user'));

    if ($targetUserId <= 0) {
        return 'target_not_found';
    }

    if ($actorUserId > 0 && $targetUserId === $actorUserId) {
        return 'self_change_blocked';
    }

    if ($targetRole === 'owner') {
        return 'owner_locked';
    }

    if ($targetUsername !== '' && trux_is_report_system_user($targetUsername)) {
        return 'report_system_locked';
    }

    return null;
}

function trux_moderation_staff_role_error_message(string $errorCode, ?array $targetUser = null, ?string $nextRole = null): string {
    $targetUsername = trim((string)($targetUser['username'] ?? ''));

    return match ($errorCode) {
        'invalid_role' => 'Invalid staff role.',
        'forbidden' => 'Only administrators and owners can manage staff roles.',
        'target_not_found' => 'User not found.',
        'self_change_blocked' => 'You cannot change your own staff role from this page.',
        'owner_locked' => 'Owner accounts must stay outside the staff management UI.',
        'report_system_locked' => 'The internal report system account is locked.',
        'confirmation_required' => $targetUsername !== ''
            ? 'Type the exact username ' . $targetUsername . ' to confirm this demotion.'
            : 'Type the exact username to confirm this demotion.',
        'update_failed' => 'Could not update the staff role right now.',
        default => 'Could not update the staff role right now.',
    };
}

function trux_assign_staff_role_result(int $targetUserId, int $actorUserId, string $nextRole, array $options = []): array {
    $nextRole = trux_staff_role($nextRole);
    $confirmationUsername = trim((string)($options['confirmation_username'] ?? ''));

    if ($targetUserId <= 0 || $actorUserId <= 0) {
        return ['ok' => false, 'changed' => false, 'error' => 'target_not_found'];
    }

    if (!array_key_exists($nextRole, trux_manageable_staff_roles())) {
        return ['ok' => false, 'changed' => false, 'error' => 'invalid_role'];
    }

    if (!trux_can_manage_staff_roles(trux_fetch_user_staff_role($actorUserId))) {
        return ['ok' => false, 'changed' => false, 'error' => 'forbidden'];
    }

    $targetUser = trux_fetch_user_by_id($targetUserId);
    if (!$targetUser) {
        return ['ok' => false, 'changed' => false, 'error' => 'target_not_found'];
    }

    $targetUsername = (string)($targetUser['username'] ?? '');
    $currentRole = trux_staff_role((string)($targetUser['staff_role'] ?? 'user'));
    $lockReason = trux_moderation_staff_role_target_lock_reason($targetUser, $actorUserId);

    if ($lockReason !== null) {
        return [
            'ok' => false,
            'changed' => false,
            'error' => $lockReason,
            'target_user' => $targetUser,
            'from_role' => $currentRole,
            'to_role' => $nextRole,
        ];
    }

    if (trux_moderation_is_staff_role_demotion($currentRole, $nextRole) && $confirmationUsername !== $targetUsername) {
        return [
            'ok' => false,
            'changed' => false,
            'error' => 'confirmation_required',
            'target_user' => $targetUser,
            'from_role' => $currentRole,
            'to_role' => $nextRole,
        ];
    }

    if ($currentRole === $nextRole) {
        return [
            'ok' => true,
            'changed' => false,
            'error' => null,
            'target_user' => $targetUser,
            'from_role' => $currentRole,
            'to_role' => $nextRole,
        ];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('UPDATE users SET staff_role = ? WHERE id = ? LIMIT 1');
        $stmt->execute([$nextRole, $targetUserId]);

        trux_moderation_write_audit_log($actorUserId, 'staff_role_updated', 'user', $targetUserId, [
            'target_username' => $targetUsername,
            'from_role' => $currentRole,
            'to_role' => $nextRole,
        ]);
        return [
            'ok' => true,
            'changed' => true,
            'error' => null,
            'target_user' => $targetUser,
            'from_role' => $currentRole,
            'to_role' => $nextRole,
        ];
    } catch (PDOException) {
        return [
            'ok' => false,
            'changed' => false,
            'error' => 'update_failed',
            'target_user' => $targetUser,
            'from_role' => $currentRole,
            'to_role' => $nextRole,
        ];
    }
}

function trux_assign_staff_role(int $targetUserId, int $actorUserId, string $nextRole): bool {
    $result = trux_assign_staff_role_result($targetUserId, $actorUserId, $nextRole);
    return !empty($result['ok']);
}

function trux_moderation_rule_config_defaults(): array {
    return [
        'repeated_failed_login' => [
            'enabled' => true,
            'settings' => [
                'threshold' => 5,
                'critical_threshold' => 8,
                'window_minutes' => 15,
            ],
        ],
        'content_burst' => [
            'enabled' => true,
            'settings' => [
                'threshold' => 6,
                'high_threshold' => 10,
                'window_minutes' => 10,
            ],
        ],
        'dm_burst_multiple_recipients' => [
            'enabled' => true,
            'settings' => [
                'threshold_messages' => 6,
                'threshold_recipients' => 5,
                'critical_messages' => 10,
                'critical_recipients' => 8,
                'window_minutes' => 15,
            ],
        ],
        'multiple_reports_same_account' => [
            'enabled' => true,
            'settings' => [
                'threshold' => 3,
                'critical_threshold' => 5,
                'window_hours' => 24,
            ],
        ],
        'spam_link_burst' => [
            'enabled' => true,
            'settings' => [
                'threshold' => 3,
                'critical_threshold' => 5,
                'window_minutes' => 10,
            ],
        ],
        'duplicate_content_burst' => [
            'enabled' => true,
            'settings' => [
                'threshold' => 3,
                'critical_threshold' => 5,
                'window_minutes' => 15,
            ],
        ],
        'follow_burst' => [
            'enabled' => true,
            'settings' => [
                'threshold' => 12,
                'critical_threshold' => 20,
                'window_minutes' => 15,
            ],
        ],
        'multiple_blocks_same_account' => [
            'enabled' => true,
            'settings' => [
                'threshold' => 3,
                'critical_threshold' => 5,
                'window_hours' => 24,
            ],
        ],
    ];
}

function trux_moderation_fetch_rule_config_map(bool $forceReload = false): array {
    static $cache = null;

    if (!$forceReload && is_array($cache)) {
        return $cache;
    }

    $defaults = trux_moderation_rule_config_defaults();
    $configs = [];
    foreach ($defaults as $ruleKey => $default) {
        $configs[$ruleKey] = [
            'rule_key' => $ruleKey,
            'enabled' => !empty($default['enabled']),
            'settings' => is_array($default['settings'] ?? null) ? $default['settings'] : [],
            'updated_by_staff_user_id' => null,
            'updated_at' => null,
        ];
    }

    try {
        $db = trux_db();
        $stmt = $db->query(
            'SELECT rule_key, enabled, settings_json, updated_by_staff_user_id, updated_at
             FROM moderation_rule_configs'
        );
        foreach ($stmt->fetchAll() as $row) {
            $ruleKey = trim((string)($row['rule_key'] ?? ''));
            if ($ruleKey === '' || !isset($configs[$ruleKey])) {
                continue;
            }

            $configs[$ruleKey]['enabled'] = !empty($row['enabled']);
            $configs[$ruleKey]['settings'] = array_merge(
                $configs[$ruleKey]['settings'],
                trux_moderation_json_decode((string)($row['settings_json'] ?? ''))
            );
            $configs[$ruleKey]['updated_by_staff_user_id'] = isset($row['updated_by_staff_user_id']) && $row['updated_by_staff_user_id'] !== null
                ? (int)$row['updated_by_staff_user_id']
                : null;
            $configs[$ruleKey]['updated_at'] = (string)($row['updated_at'] ?? '');
        }
    } catch (PDOException) {
        // Keep defaults when the tuning table is unavailable.
    }

    $cache = $configs;
    return $configs;
}

function trux_moderation_fetch_rule_config(string $ruleKey): array {
    $configs = trux_moderation_fetch_rule_config_map();
    return is_array($configs[$ruleKey] ?? null) ? $configs[$ruleKey] : [
        'rule_key' => $ruleKey,
        'enabled' => false,
        'settings' => [],
        'updated_by_staff_user_id' => null,
        'updated_at' => null,
    ];
}

function trux_moderation_rule_setting(array $config, string $key, int $default): int {
    $settings = is_array($config['settings'] ?? null) ? $config['settings'] : [];
    $value = (int)($settings[$key] ?? $default);
    return $value > 0 ? $value : $default;
}

function trux_moderation_update_rule_config(int $actorUserId, string $ruleKey, bool $enabled, array $settings): bool {
    if ($actorUserId <= 0 || !trux_has_staff_role(trux_fetch_user_staff_role($actorUserId), 'admin') || !trux_moderation_is_valid_rule_key($ruleKey)) {
        return false;
    }

    $defaults = trux_moderation_rule_config_defaults();
    if (!isset($defaults[$ruleKey])) {
        return false;
    }

    $defaultSettings = is_array($defaults[$ruleKey]['settings'] ?? null) ? $defaults[$ruleKey]['settings'] : [];
    $normalized = [];
    foreach ($defaultSettings as $key => $defaultValue) {
        $value = (int)($settings[$key] ?? $defaultValue);
        $normalized[$key] = $value > 0 ? $value : (int)$defaultValue;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_rule_configs (rule_key, enabled, settings_json, updated_by_staff_user_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                settings_json = VALUES(settings_json),
                updated_by_staff_user_id = VALUES(updated_by_staff_user_id),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            $ruleKey,
            $enabled ? 1 : 0,
            trux_moderation_json_encode($normalized),
            $actorUserId,
        ]);

        trux_moderation_write_audit_log($actorUserId, 'rule_config_updated', 'rule_config', null, [
            'rule_key' => $ruleKey,
            'enabled' => $enabled,
            'settings' => $normalized,
        ]);
        trux_moderation_fetch_rule_config_map(true);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_record_activity_event(string $eventType, ?int $actorUserId, array $context = []): int {
    $eventType = trim(strtolower($eventType));
    if ($eventType === '') {
        return 0;
    }

    $subjectType = trux_moderation_clean_text((string)($context['subject_type'] ?? ''), 32);
    $subjectId = (int)($context['subject_id'] ?? 0);
    $relatedUserId = (int)($context['related_user_id'] ?? 0);
    $sourceUrl = trux_moderation_clean_path((string)($context['source_url'] ?? ''));
    $metadata = trux_moderation_clean_map(is_array($context['metadata'] ?? null) ? $context['metadata'] : []);

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_activity_events
                (event_type, actor_user_id, subject_type, subject_id, related_user_id, source_url, ip_address, user_agent, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $eventType,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            $subjectType,
            $subjectId > 0 ? $subjectId : null,
            $relatedUserId > 0 ? $relatedUserId : null,
            $sourceUrl,
            trux_current_request_ip(),
            trux_current_user_agent(),
            $metadata === [] ? null : trux_moderation_json_encode($metadata),
        ]);

        $eventId = (int)$db->lastInsertId();
        trux_moderation_evaluate_suspicious_rules($eventType, $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null, $relatedUserId > 0 ? $relatedUserId : null, $metadata);
        return $eventId;
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_evaluate_suspicious_rules(string $eventType, ?int $actorUserId, ?int $relatedUserId, array $metadata): void {
    if ($eventType === 'login_failed') {
        trux_moderation_maybe_flag_repeated_failed_logins($actorUserId);
        return;
    }

    if (in_array($eventType, ['post_created', 'comment_created', 'reply_created'], true)) {
        trux_moderation_maybe_flag_content_burst($actorUserId);
        trux_moderation_maybe_flag_spam_link_burst($actorUserId);
        trux_moderation_maybe_flag_duplicate_content_burst($actorUserId);
        return;
    }

    if ($eventType === 'direct_message_sent') {
        trux_moderation_maybe_flag_dm_burst($actorUserId);
        trux_moderation_maybe_flag_spam_link_burst($actorUserId);
        trux_moderation_maybe_flag_duplicate_content_burst($actorUserId);
        return;
    }

    if ($eventType === 'follow_created') {
        trux_moderation_maybe_flag_follow_burst($actorUserId);
        return;
    }

    if ($eventType === 'user_blocked') {
        $targetUserId = (int)($metadata['target_user_id'] ?? $relatedUserId ?? 0);
        trux_moderation_maybe_flag_multiple_blocks_against_account($targetUserId);
        return;
    }

    if ($eventType === 'report_submitted') {
        $targetOwnerId = (int)($metadata['target_owner_user_id'] ?? $relatedUserId ?? 0);
        $linkedReportId = (int)($metadata['report_id'] ?? 0);
        trux_moderation_maybe_flag_multiple_reports_against_account($targetOwnerId, $linkedReportId > 0 ? $linkedReportId : null);
    }
}

function trux_moderation_count_activity_events_for_actor(int $actorUserId, array $eventTypes, int $windowMinutes): int {
    if ($actorUserId <= 0 || $eventTypes === [] || $windowMinutes <= 0) {
        return 0;
    }

    $eventTypes = array_values(array_filter(array_map(
        static fn ($value) => trim((string)$value),
        $eventTypes
    )));
    if ($eventTypes === []) {
        return 0;
    }

    try {
        $db = trux_db();
        $placeholders = implode(',', array_fill(0, count($eventTypes), '?'));
        $since = trux_moderation_format_datetime(trux_moderation_now()->modify('-' . $windowMinutes . ' minutes'));
        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM moderation_activity_events
             WHERE actor_user_id = ?
               AND event_type IN ($placeholders)
               AND created_at >= ?"
        );
        $bindIndex = 1;
        $stmt->bindValue($bindIndex++, $actorUserId, PDO::PARAM_INT);
        foreach ($eventTypes as $eventType) {
            $stmt->bindValue($bindIndex++, $eventType, PDO::PARAM_STR);
        }
        $stmt->bindValue($bindIndex, $since, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_count_distinct_dm_recipients(int $actorUserId, int $windowMinutes): array {
    if ($actorUserId <= 0 || $windowMinutes <= 0) {
        return ['recipients' => 0, 'messages' => 0];
    }

    try {
        $db = trux_db();
        $since = trux_moderation_format_datetime(trux_moderation_now()->modify('-' . $windowMinutes . ' minutes'));
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS messages_count,
                    COUNT(DISTINCT related_user_id) AS recipient_count
             FROM moderation_activity_events
             WHERE actor_user_id = ?
               AND event_type = ?
               AND created_at >= ?'
        );
        $stmt->execute([$actorUserId, 'direct_message_sent', $since]);
        $row = $stmt->fetch();
        return [
            'recipients' => (int)($row['recipient_count'] ?? 0),
            'messages' => (int)($row['messages_count'] ?? 0),
        ];
    } catch (PDOException) {
        return ['recipients' => 0, 'messages' => 0];
    }
}

function trux_moderation_count_distinct_reporters_against_target(int $targetOwnerUserId, int $windowHours): int {
    if ($targetOwnerUserId <= 0 || $windowHours <= 0) {
        return 0;
    }

    try {
        $db = trux_db();
        $since = trux_moderation_format_datetime(trux_moderation_now()->modify('-' . $windowHours . ' hours'));
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT reporter_user_id)
             FROM moderation_reports
             WHERE target_owner_user_id = ?
               AND created_at >= ?'
        );
        $stmt->execute([$targetOwnerUserId, $since]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_count_distinct_actors_against_related_user(int $relatedUserId, string $eventType, int $windowHours): int {
    if ($relatedUserId <= 0 || $eventType === '' || $windowHours <= 0) {
        return 0;
    }

    try {
        $db = trux_db();
        $since = trux_moderation_format_datetime(trux_moderation_now()->modify('-' . $windowHours . ' hours'));
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT actor_user_id)
             FROM moderation_activity_events
             WHERE related_user_id = ?
               AND event_type = ?
               AND created_at >= ?'
        );
        $stmt->execute([$relatedUserId, $eventType, $since]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_fetch_recent_activity_rows_for_actor(int $actorUserId, array $eventTypes, int $windowMinutes): array {
    if ($actorUserId <= 0 || $eventTypes === [] || $windowMinutes <= 0) {
        return [];
    }

    $eventTypes = array_values(array_filter(array_map(static fn ($value) => trim((string)$value), $eventTypes)));
    if ($eventTypes === []) {
        return [];
    }

    try {
        $db = trux_db();
        $placeholders = implode(',', array_fill(0, count($eventTypes), '?'));
        $since = trux_moderation_format_datetime(trux_moderation_now()->modify('-' . $windowMinutes . ' minutes'));
        $stmt = $db->prepare(
            "SELECT id, event_type, metadata_json, created_at
             FROM moderation_activity_events
             WHERE actor_user_id = ?
               AND event_type IN ($placeholders)
               AND created_at >= ?
             ORDER BY created_at DESC, id DESC
             LIMIT 250"
        );
        $bindIndex = 1;
        $stmt->bindValue($bindIndex++, $actorUserId, PDO::PARAM_INT);
        foreach ($eventTypes as $eventType) {
            $stmt->bindValue($bindIndex++, $eventType, PDO::PARAM_STR);
        }
        $stmt->bindValue($bindIndex, $since, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_count_link_bearing_activity_events(int $actorUserId, int $windowMinutes): int {
    $rows = trux_moderation_fetch_recent_activity_rows_for_actor(
        $actorUserId,
        ['post_created', 'comment_created', 'reply_created', 'direct_message_sent'],
        $windowMinutes
    );

    $count = 0;
    foreach ($rows as $row) {
        $metadata = trux_moderation_json_decode((string)($row['metadata_json'] ?? ''));
        if ((int)($metadata['link_count'] ?? 0) > 0) {
            $count++;
        }
    }

    return $count;
}

function trux_moderation_count_duplicate_content_events(int $actorUserId, int $windowMinutes): int {
    $rows = trux_moderation_fetch_recent_activity_rows_for_actor(
        $actorUserId,
        ['post_created', 'comment_created', 'reply_created', 'direct_message_sent'],
        $windowMinutes
    );

    $buckets = [];
    $max = 0;
    foreach ($rows as $row) {
        $metadata = trux_moderation_json_decode((string)($row['metadata_json'] ?? ''));
        $bodyHash = trim((string)($metadata['body_hash'] ?? ''));
        if ($bodyHash === '') {
            continue;
        }

        $buckets[$bodyHash] = (int)($buckets[$bodyHash] ?? 0) + 1;
        if ($buckets[$bodyHash] > $max) {
            $max = $buckets[$bodyHash];
        }
    }

    return $max;
}

function trux_moderation_upsert_suspicious_event(
    string $ruleKey,
    ?int $actorUserId,
    string $severity,
    int $score,
    string $summary,
    array $metadata = [],
    int $windowMinutes = 0,
    ?int $linkedReportId = null
): void {
    if (!trux_moderation_is_valid_rule_key($ruleKey)) {
        return;
    }

    $severity = trux_moderation_is_valid_severity($severity) ? $severity : 'medium';
    $summary = trim($summary);
    if ($summary === '') {
        return;
    }

    $now = trux_moderation_now();
    $windowStartedAt = $windowMinutes > 0
        ? trux_moderation_format_datetime($now->modify('-' . $windowMinutes . ' minutes'))
        : null;
    $windowExpiresAt = $windowMinutes > 0
        ? trux_moderation_format_datetime($now->modify('+' . $windowMinutes . ' minutes'))
        : null;

    try {
        $db = trux_db();
        $find = $db->prepare(
            'SELECT id
             FROM moderation_suspicious_events
             WHERE rule_key = ?
               AND actor_user_id <=> ?
               AND status = ?
               AND (window_expires_at IS NULL OR window_expires_at >= CURRENT_TIMESTAMP)
             ORDER BY id DESC
             LIMIT 1'
        );
        $find->execute([
            $ruleKey,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'open',
        ]);
        $existingId = (int)$find->fetchColumn();

        if ($existingId > 0) {
            $update = $db->prepare(
                'UPDATE moderation_suspicious_events
                 SET severity = ?,
                     score = ?,
                     summary = ?,
                     linked_report_id = COALESCE(?, linked_report_id),
                     metadata_json = ?,
                     window_started_at = COALESCE(?, window_started_at),
                     window_expires_at = COALESCE(?, window_expires_at),
                     last_detected_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $update->execute([
                $severity,
                max(0, $score),
                mb_substr($summary, 0, 255),
                $linkedReportId !== null && $linkedReportId > 0 ? $linkedReportId : null,
                $metadata === [] ? null : trux_moderation_json_encode(trux_moderation_clean_map($metadata)),
                $windowStartedAt,
                $windowExpiresAt,
                $existingId,
            ]);
            return;
        }

        $insert = $db->prepare(
            'INSERT INTO moderation_suspicious_events
                (rule_key, actor_user_id, severity, score, summary, status, linked_report_id, window_started_at, window_expires_at, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $ruleKey,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            $severity,
            max(0, $score),
            mb_substr($summary, 0, 255),
            'open',
            $linkedReportId !== null && $linkedReportId > 0 ? $linkedReportId : null,
            $windowStartedAt,
            $windowExpiresAt,
            $metadata === [] ? null : trux_moderation_json_encode(trux_moderation_clean_map($metadata)),
        ]);
    } catch (PDOException) {
        // Keep the primary app path unaffected if moderation tables are unavailable.
    }
}

function trux_moderation_maybe_flag_repeated_failed_logins(?int $actorUserId): void {
    if ($actorUserId === null || $actorUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('repeated_failed_login');
    if (empty($config['enabled'])) {
        return;
    }

    $windowMinutes = trux_moderation_rule_setting($config, 'window_minutes', 15);
    $threshold = trux_moderation_rule_setting($config, 'threshold', 5);
    $criticalThreshold = trux_moderation_rule_setting($config, 'critical_threshold', 8);
    $failures = trux_moderation_count_activity_events_for_actor($actorUserId, ['login_failed'], $windowMinutes);
    if ($failures < $threshold) {
        return;
    }

    $severity = $failures >= $criticalThreshold ? 'critical' : 'high';
    $summary = sprintf('Account failed login %d times in %d minutes.', $failures, $windowMinutes);

    trux_moderation_upsert_suspicious_event(
        'repeated_failed_login',
        $actorUserId,
        $severity,
        $failures,
        $summary,
        [
            'window_minutes' => $windowMinutes,
            'failure_count' => $failures,
            'threshold' => $threshold,
            'critical_threshold' => $criticalThreshold,
        ],
        $windowMinutes
    );
}

function trux_moderation_maybe_flag_content_burst(?int $actorUserId): void {
    if ($actorUserId === null || $actorUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('content_burst');
    if (empty($config['enabled'])) {
        return;
    }

    $windowMinutes = trux_moderation_rule_setting($config, 'window_minutes', 10);
    $threshold = trux_moderation_rule_setting($config, 'threshold', 6);
    $highThreshold = trux_moderation_rule_setting($config, 'high_threshold', 10);
    $count = trux_moderation_count_activity_events_for_actor($actorUserId, ['post_created', 'comment_created', 'reply_created'], $windowMinutes);
    if ($count < $threshold) {
        return;
    }

    $severity = $count >= $highThreshold ? 'high' : 'medium';
    $summary = sprintf('Account created %d posts/comments in %d minutes.', $count, $windowMinutes);

    trux_moderation_upsert_suspicious_event(
        'content_burst',
        $actorUserId,
        $severity,
        $count,
        $summary,
        [
            'window_minutes' => $windowMinutes,
            'content_count' => $count,
            'threshold' => $threshold,
            'high_threshold' => $highThreshold,
        ],
        $windowMinutes
    );
}

function trux_moderation_maybe_flag_dm_burst(?int $actorUserId): void {
    if ($actorUserId === null || $actorUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('dm_burst_multiple_recipients');
    if (empty($config['enabled'])) {
        return;
    }

    $windowMinutes = trux_moderation_rule_setting($config, 'window_minutes', 15);
    $thresholdMessages = trux_moderation_rule_setting($config, 'threshold_messages', 6);
    $thresholdRecipients = trux_moderation_rule_setting($config, 'threshold_recipients', 5);
    $criticalMessages = trux_moderation_rule_setting($config, 'critical_messages', 10);
    $criticalRecipients = trux_moderation_rule_setting($config, 'critical_recipients', 8);
    $stats = trux_moderation_count_distinct_dm_recipients($actorUserId, $windowMinutes);
    if ($stats['recipients'] < $thresholdRecipients || $stats['messages'] < $thresholdMessages) {
        return;
    }

    $severity = ($stats['recipients'] >= $criticalRecipients || $stats['messages'] >= $criticalMessages) ? 'critical' : 'high';
    $summary = sprintf(
        'Account sent %d DMs to %d distinct recipients in %d minutes.',
        $stats['messages'],
        $stats['recipients'],
        $windowMinutes
    );

    trux_moderation_upsert_suspicious_event(
        'dm_burst_multiple_recipients',
        $actorUserId,
        $severity,
        $stats['messages'],
        $summary,
        [
            'window_minutes' => $windowMinutes,
            'message_count' => $stats['messages'],
            'recipient_count' => $stats['recipients'],
            'threshold_messages' => $thresholdMessages,
            'threshold_recipients' => $thresholdRecipients,
            'critical_messages' => $criticalMessages,
            'critical_recipients' => $criticalRecipients,
        ],
        $windowMinutes
    );
}

function trux_moderation_maybe_flag_multiple_reports_against_account(int $targetOwnerUserId, ?int $linkedReportId = null): void {
    if ($targetOwnerUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('multiple_reports_same_account');
    if (empty($config['enabled'])) {
        return;
    }

    $windowHours = trux_moderation_rule_setting($config, 'window_hours', 24);
    $threshold = trux_moderation_rule_setting($config, 'threshold', 3);
    $criticalThreshold = trux_moderation_rule_setting($config, 'critical_threshold', 5);
    $distinctReporters = trux_moderation_count_distinct_reporters_against_target($targetOwnerUserId, $windowHours);
    if ($distinctReporters < $threshold) {
        return;
    }

    $severity = $distinctReporters >= $criticalThreshold ? 'critical' : 'high';
    $summary = sprintf(
        'Account received reports from %d distinct reporters in %d hours.',
        $distinctReporters,
        $windowHours
    );

    trux_moderation_upsert_suspicious_event(
        'multiple_reports_same_account',
        $targetOwnerUserId,
        $severity,
        $distinctReporters,
        $summary,
        [
            'window_hours' => $windowHours,
            'distinct_reporters' => $distinctReporters,
            'threshold' => $threshold,
            'critical_threshold' => $criticalThreshold,
        ],
        $windowHours * 60,
        $linkedReportId
    );
}

function trux_moderation_maybe_flag_spam_link_burst(?int $actorUserId): void {
    if ($actorUserId === null || $actorUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('spam_link_burst');
    if (empty($config['enabled'])) {
        return;
    }

    $windowMinutes = trux_moderation_rule_setting($config, 'window_minutes', 10);
    $threshold = trux_moderation_rule_setting($config, 'threshold', 3);
    $criticalThreshold = trux_moderation_rule_setting($config, 'critical_threshold', 5);
    $count = trux_moderation_count_link_bearing_activity_events($actorUserId, $windowMinutes);
    if ($count < $threshold) {
        return;
    }

    $severity = $count >= $criticalThreshold ? 'critical' : 'high';
    $summary = sprintf('Account created %d link-bearing items in %d minutes.', $count, $windowMinutes);
    trux_moderation_upsert_suspicious_event(
        'spam_link_burst',
        $actorUserId,
        $severity,
        $count,
        $summary,
        [
            'window_minutes' => $windowMinutes,
            'link_bearing_count' => $count,
            'threshold' => $threshold,
            'critical_threshold' => $criticalThreshold,
        ],
        $windowMinutes
    );
}

function trux_moderation_maybe_flag_duplicate_content_burst(?int $actorUserId): void {
    if ($actorUserId === null || $actorUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('duplicate_content_burst');
    if (empty($config['enabled'])) {
        return;
    }

    $windowMinutes = trux_moderation_rule_setting($config, 'window_minutes', 15);
    $threshold = trux_moderation_rule_setting($config, 'threshold', 3);
    $criticalThreshold = trux_moderation_rule_setting($config, 'critical_threshold', 5);
    $duplicates = trux_moderation_count_duplicate_content_events($actorUserId, $windowMinutes);
    if ($duplicates < $threshold) {
        return;
    }

    $severity = $duplicates >= $criticalThreshold ? 'critical' : 'high';
    $summary = sprintf('Account repeated the same content %d times in %d minutes.', $duplicates, $windowMinutes);
    trux_moderation_upsert_suspicious_event(
        'duplicate_content_burst',
        $actorUserId,
        $severity,
        $duplicates,
        $summary,
        [
            'window_minutes' => $windowMinutes,
            'duplicate_count' => $duplicates,
            'threshold' => $threshold,
            'critical_threshold' => $criticalThreshold,
        ],
        $windowMinutes
    );
}

function trux_moderation_maybe_flag_follow_burst(?int $actorUserId): void {
    if ($actorUserId === null || $actorUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('follow_burst');
    if (empty($config['enabled'])) {
        return;
    }

    $windowMinutes = trux_moderation_rule_setting($config, 'window_minutes', 15);
    $threshold = trux_moderation_rule_setting($config, 'threshold', 12);
    $criticalThreshold = trux_moderation_rule_setting($config, 'critical_threshold', 20);
    $count = trux_moderation_count_activity_events_for_actor($actorUserId, ['follow_created'], $windowMinutes);
    if ($count < $threshold) {
        return;
    }

    $severity = $count >= $criticalThreshold ? 'critical' : 'high';
    $summary = sprintf('Account followed %d users in %d minutes.', $count, $windowMinutes);
    trux_moderation_upsert_suspicious_event(
        'follow_burst',
        $actorUserId,
        $severity,
        $count,
        $summary,
        [
            'window_minutes' => $windowMinutes,
            'follow_count' => $count,
            'threshold' => $threshold,
            'critical_threshold' => $criticalThreshold,
        ],
        $windowMinutes
    );
}

function trux_moderation_maybe_flag_multiple_blocks_against_account(int $targetUserId): void {
    if ($targetUserId <= 0) {
        return;
    }

    $config = trux_moderation_fetch_rule_config('multiple_blocks_same_account');
    if (empty($config['enabled'])) {
        return;
    }

    $windowHours = trux_moderation_rule_setting($config, 'window_hours', 24);
    $threshold = trux_moderation_rule_setting($config, 'threshold', 3);
    $criticalThreshold = trux_moderation_rule_setting($config, 'critical_threshold', 5);
    $count = trux_moderation_count_distinct_actors_against_related_user($targetUserId, 'user_blocked', $windowHours);
    if ($count < $threshold) {
        return;
    }

    $severity = $count >= $criticalThreshold ? 'critical' : 'high';
    $summary = sprintf('Account was blocked by %d distinct users in %d hours.', $count, $windowHours);
    trux_moderation_upsert_suspicious_event(
        'multiple_blocks_same_account',
        $targetUserId,
        $severity,
        $count,
        $summary,
        [
            'window_hours' => $windowHours,
            'distinct_blockers' => $count,
            'threshold' => $threshold,
            'critical_threshold' => $criticalThreshold,
        ],
        $windowHours * 60
    );
}

function trux_moderation_write_audit_log(int $actorUserId, string $actionType, string $subjectType, ?int $subjectId = null, array $details = []): int {
    if ($actorUserId <= 0) {
        return 0;
    }

    $actionType = trux_moderation_clean_text($actionType, 64);
    $subjectType = trux_moderation_clean_text($subjectType, 32);
    if ($actionType === null || $subjectType === null) {
        return 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_audit_logs
                (actor_user_id, action_type, subject_type, subject_id, details_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actorUserId,
            $actionType,
            $subjectType,
            $subjectId !== null && $subjectId > 0 ? $subjectId : null,
            $details === [] ? null : trux_moderation_json_encode(trux_moderation_clean_map($details)),
        ]);
        return (int)$db->lastInsertId();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_report_target_noun(string $targetType): string {
    return match (trim(strtolower($targetType))) {
        'user' => 'profile',
        'comment' => 'comment',
        'message' => 'message',
        default => 'post',
    };
}

function trux_moderation_submit_report(array $data): array {
    $targetType = trim(strtolower((string)($data['target_type'] ?? '')));
    $targetId = (int)($data['target_id'] ?? 0);
    $reporterUserId = (int)($data['reporter_user_id'] ?? 0);
    $reasonKey = trim((string)($data['reason_key'] ?? ''));
    $details = trim((string)($data['details'] ?? ''));
    $wantsReporterDmUpdates = !empty($data['wants_reporter_dm_updates']);

    if ($reporterUserId <= 0) {
        return ['ok' => false, 'status' => 401, 'error' => 'Please log in to continue.'];
    }

    if (!trux_moderation_is_valid_report_target_type($targetType) || $targetId <= 0) {
        return ['ok' => false, 'status' => 400, 'error' => 'Invalid report target.'];
    }

    if (!trux_moderation_is_valid_report_reason($reasonKey)) {
        return ['ok' => false, 'status' => 400, 'error' => 'Choose a report reason.'];
    }

    if ($details !== '' && mb_strlen($details) > 2000) {
        return ['ok' => false, 'status' => 400, 'error' => 'Message must be 2000 characters or fewer.'];
    }

    $target = trux_moderation_fetch_target_context($targetType, $targetId);
    if (!$target) {
        return [
            'ok' => false,
            'status' => 404,
            'error' => ucfirst(trux_moderation_report_target_noun($targetType)) . ' not found.',
        ];
    }

    $targetOwnerUserId = (int)($target['owner_user_id'] ?? 0);
    $targetOwnerUsername = trim((string)($target['owner_username'] ?? ''));
    if ($targetOwnerUserId > 0 && $targetOwnerUserId === $reporterUserId) {
        return [
            'ok' => false,
            'status' => 400,
            'error' => match ($targetType) {
                'user' => 'You cannot report your own profile.',
                'comment' => 'You cannot report your own comment.',
                'message' => 'You cannot report your own message.',
                default => 'You cannot report your own post.',
            },
        ];
    }

    if ($targetOwnerUsername !== '' && trux_is_report_system_user($targetOwnerUsername)) {
        return [
            'ok' => false,
            'status' => 400,
            'error' => $targetType === 'message'
                ? 'Report System Updates messages cannot be reported.'
                : 'This account cannot be reported.',
        ];
    }

    $reportId = trux_moderation_create_report([
        'target_type' => $targetType,
        'target_id' => $targetId,
        'reporter_user_id' => $reporterUserId,
        'target_owner_user_id' => $targetOwnerUserId > 0 ? $targetOwnerUserId : null,
        'reason_key' => $reasonKey,
        'details' => $details,
        'priority' => 'normal',
        'source_url' => (string)($target['source_url'] ?? ''),
        'target_snapshot' => is_array($target['snapshot'] ?? null) ? $target['snapshot'] : [],
        'wants_reporter_dm_updates' => $wantsReporterDmUpdates,
    ]);

    if ($reportId <= 0) {
        return ['ok' => false, 'status' => 500, 'error' => 'Could not submit the report.'];
    }

    return [
        'ok' => true,
        'status' => 200,
        'report_id' => $reportId,
        'message' => $wantsReporterDmUpdates
            ? 'Report submitted. DM updates are enabled for this report.'
            : 'Report submitted.',
    ];
}

function trux_moderation_create_report(array $data): int {
    $targetType = trim((string)($data['target_type'] ?? ''));
    $targetType = trux_moderation_is_valid_report_target_type($targetType) ? $targetType : '';
    $targetId = (int)($data['target_id'] ?? 0);
    $reporterUserId = (int)($data['reporter_user_id'] ?? 0);
    $targetOwnerUserId = (int)($data['target_owner_user_id'] ?? 0);
    $reasonKey = trim((string)($data['reason_key'] ?? ''));
    $reasonKey = trux_moderation_is_valid_report_reason($reasonKey) ? $reasonKey : '';
    $details = trux_moderation_clean_text((string)($data['details'] ?? ''), 2000);
    $priority = trim((string)($data['priority'] ?? 'normal'));
    $priority = trux_moderation_is_valid_report_priority($priority) ? $priority : 'normal';
    $sourceUrl = trux_moderation_clean_path((string)($data['source_url'] ?? ''));
    $targetSnapshot = trux_moderation_clean_map(is_array($data['target_snapshot'] ?? null) ? $data['target_snapshot'] : []);
    $wantsReporterDmUpdates = !empty($data['wants_reporter_dm_updates']);

    if ($targetType === '' || $targetId <= 0 || $reporterUserId <= 0 || $reasonKey === '') {
        return 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_reports
                (target_type, target_id, reporter_user_id, target_owner_user_id, reason_key, details, status, priority, assigned_staff_user_id, source_url, target_snapshot_json, wants_reporter_dm_updates)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $targetType,
            $targetId,
            $reporterUserId,
            $targetOwnerUserId > 0 ? $targetOwnerUserId : null,
            $reasonKey,
            $details,
            'open',
            $priority,
            null,
            $sourceUrl,
            $targetSnapshot === [] ? null : trux_moderation_json_encode($targetSnapshot),
            $wantsReporterDmUpdates ? 1 : 0,
        ]);

        $reportId = (int)$db->lastInsertId();
        trux_moderation_record_activity_event('report_submitted', $reporterUserId, [
            'subject_type' => 'report',
            'subject_id' => $reportId,
            'related_user_id' => $targetOwnerUserId > 0 ? $targetOwnerUserId : null,
            'source_url' => $sourceUrl,
            'metadata' => [
                'report_id' => $reportId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_owner_user_id' => $targetOwnerUserId > 0 ? $targetOwnerUserId : null,
                'reason_key' => $reasonKey,
                'priority' => $priority,
                'wants_reporter_dm_updates' => $wantsReporterDmUpdates,
            ],
        ]);

        trux_moderation_dispatch_report_submission_update([
            'report_id' => $reportId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_label' => trux_moderation_target_label_from_snapshot($targetType, $targetId, $targetSnapshot),
            'reporter_user_id' => $reporterUserId,
            'wants_reporter_dm_updates' => $wantsReporterDmUpdates,
        ]);

        return $reportId;
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_send_report_system_dm(int $recipientUserId, string $body): bool {
    $recipientUserId = (int)$recipientUserId;
    $senderUserId = trux_report_system_user_id();
    $messageBody = trim($body);

    if ($recipientUserId <= 0 || $senderUserId <= 0 || $messageBody === '') {
        return false;
    }

    return trux_send_direct_message($senderUserId, $recipientUserId, $messageBody) > 0;
}

function trux_moderation_dispatch_report_submission_update(array $report): void {
    if (empty($report['wants_reporter_dm_updates'])) {
        return;
    }

    $reporterUserId = (int)($report['reporter_user_id'] ?? 0);
    $reportId = (int)($report['report_id'] ?? 0);
    $targetType = (string)($report['target_type'] ?? '');
    $targetId = (int)($report['target_id'] ?? 0);

    if ($reporterUserId <= 0 || $reportId <= 0 || $targetType === '' || $targetId <= 0) {
        return;
    }

    $targetLabel = trim((string)($report['target_label'] ?? ''));
    if ($targetLabel === '') {
        $targetLabel = trux_moderation_target_label($targetType, $targetId);
    }
    $body = implode("\n\n", [
        'Thanks for helping keep ' . TRUX_APP_NAME . ' safe.',
        'We received your report for ' . $targetLabel . ' (reference #' . $reportId . ').',
        'Because you asked for private updates, Report System Updates will message you here again after the moderation review is complete.',
    ]);

    trux_moderation_send_report_system_dm($reporterUserId, $body);
}

function trux_moderation_delete_post_if_staff(int $postId): bool {
    if ($postId <= 0) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('SELECT image_path FROM posts WHERE id = ? LIMIT 1');
        $stmt->execute([$postId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $delete = $db->prepare('DELETE FROM posts WHERE id = ?');
        $delete->execute([$postId]);
        if ($delete->rowCount() <= 0) {
            return false;
        }

        $imagePath = (string)($row['image_path'] ?? '');
        if ($imagePath !== '' && str_starts_with($imagePath, '/uploads/')) {
            $absolute = dirname(__DIR__) . '/public' . $imagePath;
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_delete_comment_if_staff(int $commentId): ?int {
    if ($commentId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('SELECT post_id FROM post_comments WHERE id = ? LIMIT 1');
        $stmt->execute([$commentId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $postId = (int)($row['post_id'] ?? 0);
        $delete = $db->prepare('DELETE FROM post_comments WHERE id = ?');
        $delete->execute([$commentId]);
        return $delete->rowCount() > 0 ? $postId : null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_delete_message_if_staff(int $messageId): bool {
    if ($messageId <= 0) {
        return false;
    }

    try {
        $db = trux_db();
        $delete = $db->prepare('DELETE FROM direct_messages WHERE id = ?');
        $delete->execute([$messageId]);
        return $delete->rowCount() > 0;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_remove_reported_content(array $report): string {
    $targetType = trim((string)($report['target_type'] ?? ''));
    $targetId = (int)($report['target_id'] ?? 0);
    if ($targetId <= 0) {
        return 'failed';
    }

    if (!in_array($targetType, ['post', 'comment', 'message'], true)) {
        return 'not_applicable';
    }

    $liveTarget = trux_moderation_fetch_target_context($targetType, $targetId);
    if (!$liveTarget) {
        return 'missing';
    }

    return match ($targetType) {
        'post' => trux_moderation_delete_post_if_staff($targetId) ? 'removed' : 'failed',
        'comment' => trux_moderation_delete_comment_if_staff($targetId) !== null ? 'removed' : 'failed',
        'message' => trux_moderation_delete_message_if_staff($targetId) ? 'removed' : 'failed',
        default => 'not_applicable',
    };
}

function trux_moderation_primary_report_resolution_action(array $context, ?string $fallback = null): ?string {
    $enforcementAction = trim(strtolower((string)($context['enforcement_action'] ?? '')));
    if (trux_moderation_is_valid_report_resolution_action($enforcementAction)) {
        return $enforcementAction;
    }

    $contentAction = trim(strtolower((string)($context['content_action'] ?? '')));
    if (trux_moderation_is_valid_report_resolution_action($contentAction)) {
        return $contentAction;
    }

    if (!empty($context['user_case_opened'])) {
        return 'user_case_opened';
    }

    $fallback = trim(strtolower((string)$fallback));
    return trux_moderation_is_valid_report_resolution_action($fallback) ? $fallback : null;
}

function trux_moderation_notify_target_user_content_action(array $report, string $contentAction): void {
    $contentAction = trim(strtolower($contentAction));
    if ($contentAction !== 'content_removed') {
        return;
    }

    $targetOwnerUserId = (int)($report['target_owner_user_id'] ?? $report['owner_user_id'] ?? 0);
    $targetType = trim((string)($report['target_type'] ?? ''));
    $targetId = (int)($report['target_id'] ?? 0);
    $reportId = (int)($report['id'] ?? 0);
    $actorUserId = trux_report_system_user_id();
    if ($targetOwnerUserId <= 0 || $targetId <= 0 || $actorUserId <= 0) {
        return;
    }

    $notificationType = match ($targetType) {
        'post' => 'moderation_post_removed',
        'comment' => 'moderation_comment_removed',
        'message' => 'moderation_message_removed',
        default => '',
    };
    if ($notificationType === '') {
        return;
    }

    $targetUrl = trux_moderation_clean_path((string)($report['source_url'] ?? '')) ?? '/notifications.php';
    trux_create_custom_notification(
        $targetOwnerUserId,
        $actorUserId,
        $notificationType,
        'moderation:' . $notificationType . ':' . $reportId,
        $targetType === 'post' ? $targetId : null,
        $targetType === 'comment' ? $targetId : null,
        $targetUrl
    );
}

function trux_moderation_notify_target_user_enforcement(array $report, array $enforcement, string $resolutionNotes = ''): void {
    $targetUserId = (int)($enforcement['user_id'] ?? $report['target_owner_user_id'] ?? $report['owner_user_id'] ?? 0);
    $actionKey = trim((string)($enforcement['action_key'] ?? ''));
    $actorUserId = trux_report_system_user_id();
    if ($targetUserId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_user_enforcement_action($actionKey)) {
        return;
    }

    $notificationType = match ($actionKey) {
        'warning_issued' => 'moderation_warning_issued',
        'dm_restricted' => 'moderation_dm_restricted',
        'account_suspended' => 'moderation_account_suspended',
        'account_locked' => 'moderation_account_locked',
        default => '',
    };
    if ($notificationType === '') {
        return;
    }

    $appealPath = trux_moderation_public_appeal_url($enforcement);
    $targetUrl = $appealPath !== null ? $appealPath : '/notifications.php';
    trux_create_custom_notification(
        $targetUserId,
        $actorUserId,
        $notificationType,
        'moderation:' . $notificationType . ':' . (int)($enforcement['id'] ?? 0),
        null,
        null,
        $targetUrl
    );

    $targetLabel = trim((string)($report['target_label'] ?? ''));
    if ($targetLabel === '') {
        $targetLabel = trux_moderation_target_label((string)($report['target_type'] ?? ''), (int)($report['target_id'] ?? 0));
    }

    $reasonText = trim((string)($enforcement['reason_summary'] ?? ''));
    if ($reasonText === '') {
        $reasonText = trim($resolutionNotes);
    }

    $bodyParts = [
        'A moderation review confirmed a violation on ' . $targetLabel . '.',
        'Account action: ' . trux_moderation_resolution_action_label($actionKey) . '.',
    ];
    if ($reasonText !== '') {
        $bodyParts[] = 'Reason: ' . trux_moderation_trimmed_excerpt($reasonText, 500);
    }

    $endsAt = trim((string)($enforcement['ends_at'] ?? ''));
    if ($endsAt !== '') {
        $bodyParts[] = 'This action remains active until ' . $endsAt . '.';
    } elseif ($actionKey === 'account_locked') {
        $bodyParts[] = 'This action remains active until it is revoked after further review.';
    } elseif ($actionKey === 'dm_restricted') {
        $bodyParts[] = 'You can still browse and receive system messages, but you cannot send new direct messages while this restriction is active.';
    }

    if ($appealPath !== null) {
        $bodyParts[] = 'Appeal this action: ' . trux_public_url($appealPath);
    }

    trux_moderation_send_report_system_dm($targetUserId, implode("\n\n", $bodyParts));
}

function trux_moderation_dispatch_report_status_update(array $report, string $status, ?string $resolutionActionKey = null, array $context = []): void {
    $reportId = (int)($report['id'] ?? 0);
    $reporterUserId = (int)($report['reporter_user_id'] ?? 0);
    $targetOwnerUserId = (int)($report['target_owner_user_id'] ?? $report['owner_user_id'] ?? 0);
    $targetType = (string)($report['target_type'] ?? '');
    $targetId = (int)($report['target_id'] ?? 0);
    $wantsReporterDmUpdates = !empty($report['wants_reporter_dm_updates']);
    $targetLabel = trim((string)($report['target_label'] ?? ''));
    if ($targetLabel === '') {
        $targetLabel = trux_moderation_target_label($targetType, $targetId);
    }
    $resolutionActionKey = trux_moderation_primary_report_resolution_action($context, $resolutionActionKey);
    $contentAction = trim(strtolower((string)($context['content_action'] ?? '')));
    $contentAction = trux_moderation_is_valid_report_resolution_action($contentAction) ? $contentAction : '';
    $enforcementAction = trim(strtolower((string)($context['enforcement_action'] ?? '')));
    $enforcementAction = trux_moderation_is_valid_user_enforcement_action($enforcementAction) ? $enforcementAction : '';
    if ($contentAction === '' && in_array($resolutionActionKey, ['content_removed', 'content_already_unavailable'], true)) {
        $contentAction = (string)$resolutionActionKey;
    }
    if ($enforcementAction === '' && $resolutionActionKey !== null && trux_moderation_is_valid_user_enforcement_action($resolutionActionKey)) {
        $enforcementAction = $resolutionActionKey;
    }
    $userCaseOpened = !empty($context['user_case_opened']) || $resolutionActionKey === 'user_case_opened';
    $resolutionNotes = trim((string)($context['resolution_notes'] ?? ''));
    $enforcement = null;
    if ((int)($context['enforcement_id'] ?? 0) > 0) {
        $enforcement = trux_moderation_fetch_user_enforcement_by_id((int)$context['enforcement_id']);
    }

    if ($status === 'resolved') {
        if ($wantsReporterDmUpdates && $reporterUserId > 0) {
            $reviewLines = [];
            if ($contentAction === 'content_removed') {
                $reviewLines[] = 'Our moderators reviewed it, confirmed a violation, and removed the reported content.';
            } elseif ($contentAction === 'content_already_unavailable') {
                $reviewLines[] = 'Our moderators reviewed it, confirmed a violation, but the reported content was already unavailable by the time of review.';
            }
            if ($enforcementAction !== '') {
                $reviewLines[] = 'We also applied account action against the responsible account: ' . trux_moderation_resolution_action_label($enforcementAction) . '.';
            }
            if ($userCaseOpened) {
                $reviewLines[] = $reviewLines === []
                    ? 'Our moderators reviewed it and opened a staff case for follow-up.'
                    : 'We also opened or updated a staff case for follow-up.';
            }
            if ($reviewLines === []) {
                $reviewLines[] = 'Our moderators reviewed it, confirmed a violation, and took moderation action.';
            }
            $body = implode("\n\n", [
                'Thanks again for reporting ' . $targetLabel . ' (reference #' . $reportId . ').',
                implode("\n\n", $reviewLines),
                'We appreciate your effort to report suspicious activity and help protect the community.',
            ]);
            trux_moderation_send_report_system_dm($reporterUserId, $body);
        }

        if ($targetOwnerUserId > 0 && $contentAction !== '') {
            trux_moderation_notify_target_user_content_action($report, $contentAction);
        }
        if ($targetOwnerUserId > 0 && $enforcement !== null) {
            trux_moderation_notify_target_user_enforcement($report, $enforcement, $resolutionNotes);
        }
        return;
    }

    if ($status === 'dismissed' && $wantsReporterDmUpdates && $reporterUserId > 0) {
        $body = implode("\n\n", [
            'Thanks again for reporting ' . $targetLabel . ' (reference #' . $reportId . ').',
            'Our moderators reviewed it and did not find a violation at this time.',
            'We still appreciate the effort you made to help identify suspicious activity on ' . TRUX_APP_NAME . '.',
        ]);
        trux_moderation_send_report_system_dm($reporterUserId, $body);
    }
}

function trux_moderation_fetch_report_by_id(int $reportId): ?array {
    if ($reportId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT r.*,
                    reporter.username AS reporter_username,
                    target_owner.username AS target_owner_username,
                    assignee.username AS assigned_staff_username,
                    assignee.staff_role AS assigned_staff_role
             FROM moderation_reports r
             JOIN users reporter ON reporter.id = r.reporter_user_id
             LEFT JOIN users target_owner ON target_owner.id = r.target_owner_user_id
             LEFT JOIN users assignee ON assignee.id = r.assigned_staff_user_id
             WHERE r.id = ?
             LIMIT 1'
        );
        $stmt->execute([$reportId]);
        $row = $stmt->fetch();
        return $row ? trux_moderation_hydrate_report_row($row) : null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_assign_report(int $reportId, int $actorUserId, ?int $assignedStaffUserId): bool {
    if ($reportId <= 0 || $actorUserId <= 0) {
        return false;
    }

    $report = trux_moderation_fetch_report_by_id($reportId);
    if (!$report) {
        return false;
    }

    if (trux_moderation_is_report_archived_status((string)($report['status'] ?? ''))) {
        return false;
    }

    $nextAssigneeId = $assignedStaffUserId !== null && $assignedStaffUserId > 0 ? $assignedStaffUserId : null;
    if ($nextAssigneeId !== null && !trux_has_staff_role(trux_fetch_user_staff_role($nextAssigneeId), 'developer')) {
        return false;
    }

    $previousAssigneeId = isset($report['assigned_staff_user_id']) && $report['assigned_staff_user_id'] !== null
        ? (int)$report['assigned_staff_user_id']
        : null;
    if ($previousAssigneeId === $nextAssigneeId) {
        return true;
    }

    $db = null;

    try {
        $db = trux_db();
        $db->beginTransaction();

        $update = $db->prepare(
            'UPDATE moderation_reports
             SET assigned_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $update->execute([$nextAssigneeId, $reportId]);

        $newAssignee = $nextAssigneeId !== null ? trux_fetch_user_by_id($nextAssigneeId) : null;
        $oldAssignee = $previousAssigneeId !== null ? trux_fetch_user_by_id($previousAssigneeId) : null;
        $actionType = $nextAssigneeId === null ? 'report_unassigned' : 'report_assigned';

        $audit = $db->prepare(
            'INSERT INTO moderation_audit_logs
                (actor_user_id, action_type, subject_type, subject_id, details_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $audit->execute([
            $actorUserId,
            $actionType,
            'report',
            $reportId,
            trux_moderation_json_encode(trux_moderation_clean_map([
                'from_assignee_user_id' => $previousAssigneeId,
                'from_assignee_username' => (string)($oldAssignee['username'] ?? ''),
                'to_assignee_user_id' => $nextAssigneeId,
                'to_assignee_username' => (string)($newAssignee['username'] ?? ''),
            ])),
        ]);

        $db->commit();
        return true;
    } catch (PDOException) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function trux_moderation_fetch_user_case_by_user_id(int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT c.*,
                    target.username AS target_username,
                    target.display_name AS target_display_name,
                    target.avatar_path AS target_avatar_path,
                    target.banner_path AS target_banner_path,
                    target.bio AS target_bio,
                    target.location AS target_location,
                    target.website_url AS target_website_url,
                    target.created_at AS target_created_at,
                    assignee.username AS assigned_staff_username,
                    creator.username AS created_by_staff_username,
                    updater.username AS updated_by_staff_username,
                    closer.username AS closed_by_staff_username
             FROM moderation_user_cases c
             JOIN users target ON target.id = c.user_id
             JOIN users creator ON creator.id = c.created_by_staff_user_id
             JOIN users updater ON updater.id = c.updated_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = c.assigned_staff_user_id
             LEFT JOIN users closer ON closer.id = c.closed_by_staff_user_id
             WHERE c.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_ensure_user_case(int $userId, int $actorUserId, ?int $linkedReportId = null): ?array {
    if ($userId <= 0 || $actorUserId <= 0 || !trux_can_moderation_write(trux_fetch_user_staff_role($actorUserId))) {
        return null;
    }

    $existing = trux_moderation_fetch_user_case_by_user_id($userId);
    if ($existing) {
        return $existing;
    }

    $targetUser = trux_fetch_user_by_id($userId);
    if (!$targetUser) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_user_cases
                (user_id, watchlisted, watch_reason, summary, assigned_staff_user_id, created_by_staff_user_id, updated_by_staff_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, 0, null, null, null, $actorUserId, $actorUserId]);
        $caseId = (int)$db->lastInsertId();

        trux_moderation_write_audit_log($actorUserId, 'user_case_created', 'user_case', $caseId, [
            'user_id' => $userId,
            'target_username' => (string)($targetUser['username'] ?? ''),
            'linked_report_id' => $linkedReportId,
        ]);

        return trux_moderation_fetch_user_case_by_user_id($userId);
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_update_user_case_summary(int $userId, int $actorUserId, string $summary): bool {
    $summary = trim($summary);
    if ($summary !== '' && mb_strlen($summary) > 4000) {
        return false;
    }

    $case = trux_moderation_ensure_user_case($userId, $actorUserId);
    if (!$case) {
        return false;
    }

    $caseId = (int)($case['id'] ?? 0);
    $currentSummary = trim((string)($case['summary'] ?? ''));
    if ($caseId <= 0 || $currentSummary === $summary) {
        return $caseId > 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_user_cases
             SET summary = ?, updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$summary !== '' ? $summary : null, $actorUserId, $caseId]);

        trux_moderation_write_audit_log($actorUserId, 'user_case_updated', 'user_case', $caseId, [
            'user_id' => $userId,
            'summary_excerpt' => trux_moderation_trimmed_excerpt($summary, 160),
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_update_user_case_watchlist(int $userId, int $actorUserId, bool $watchlisted, string $watchReason = ''): bool {
    $watchReason = trim($watchReason);
    if ($watchReason !== '' && mb_strlen($watchReason) > 280) {
        return false;
    }

    $case = trux_moderation_ensure_user_case($userId, $actorUserId);
    if (!$case) {
        return false;
    }

    $caseId = (int)($case['id'] ?? 0);
    $currentWatchlisted = !empty($case['watchlisted']);
    $currentReason = trim((string)($case['watch_reason'] ?? ''));
    if ($caseId <= 0) {
        return false;
    }
    if ($currentWatchlisted === $watchlisted && $currentReason === $watchReason) {
        return true;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_user_cases
             SET watchlisted = ?, watch_reason = ?, updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$watchlisted ? 1 : 0, $watchReason !== '' ? $watchReason : null, $actorUserId, $caseId]);

        trux_moderation_write_audit_log($actorUserId, 'user_case_watchlist_updated', 'user_case', $caseId, [
            'user_id' => $userId,
            'watchlisted' => $watchlisted,
            'watch_reason' => $watchReason,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_assign_user_case(int $userId, int $actorUserId, ?int $assignedStaffUserId): bool {
    $case = trux_moderation_ensure_user_case($userId, $actorUserId);
    if (!$case) {
        return false;
    }

    $caseId = (int)($case['id'] ?? 0);
    if ($caseId <= 0) {
        return false;
    }

    $nextAssigneeId = $assignedStaffUserId !== null && $assignedStaffUserId > 0 ? $assignedStaffUserId : null;
    if ($nextAssigneeId !== null && !trux_has_staff_role(trux_fetch_user_staff_role($nextAssigneeId), 'developer')) {
        return false;
    }

    $previousAssigneeId = isset($case['assigned_staff_user_id']) && $case['assigned_staff_user_id'] !== null
        ? (int)$case['assigned_staff_user_id']
        : null;
    if ($previousAssigneeId === $nextAssigneeId) {
        return true;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_user_cases
             SET assigned_staff_user_id = ?, updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$nextAssigneeId, $actorUserId, $caseId]);

        $newAssignee = $nextAssigneeId !== null ? trux_fetch_user_by_id($nextAssigneeId) : null;
        $oldAssignee = $previousAssigneeId !== null ? trux_fetch_user_by_id($previousAssigneeId) : null;
        trux_moderation_write_audit_log($actorUserId, 'user_case_assigned', 'user_case', $caseId, [
            'user_id' => $userId,
            'from_assignee_user_id' => $previousAssigneeId,
            'from_assignee_username' => (string)($oldAssignee['username'] ?? ''),
            'to_assignee_user_id' => $nextAssigneeId,
            'to_assignee_username' => (string)($newAssignee['username'] ?? ''),
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_add_user_case_note(int $userId, int $actorUserId, string $body, ?int $linkedReportId = null): bool {
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 1000) {
        return false;
    }

    $case = trux_moderation_ensure_user_case($userId, $actorUserId, $linkedReportId);
    if (!$case) {
        return false;
    }

    $caseId = (int)($case['id'] ?? 0);
    if ($caseId <= 0) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_user_case_notes
                (user_case_id, author_user_id, linked_report_id, body)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$caseId, $actorUserId, $linkedReportId !== null && $linkedReportId > 0 ? $linkedReportId : null, $body]);

        $touch = $db->prepare(
            'UPDATE moderation_user_cases
             SET updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $touch->execute([$actorUserId, $caseId]);

        trux_moderation_write_audit_log($actorUserId, 'user_case_note_added', 'user_case', $caseId, [
            'user_id' => $userId,
            'linked_report_id' => $linkedReportId,
            'note_excerpt' => trux_moderation_trimmed_excerpt($body, 120),
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_fetch_user_case_notes(int $caseId): array {
    if ($caseId <= 0) {
        return [];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT n.*, u.username AS author_username, u.staff_role AS author_staff_role
             FROM moderation_user_case_notes n
             JOIN users u ON u.id = n.author_user_id
             WHERE n.user_case_id = ?
             ORDER BY n.created_at DESC, n.id DESC'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_user_case_related_reports(int $userId, int $limit = 20): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            "SELECT r.*,
                    reporter.username AS reporter_username,
                    target_owner.username AS target_owner_username,
                    assignee.username AS assigned_staff_username,
                    assignee.staff_role AS assigned_staff_role
             FROM moderation_reports r
             JOIN users reporter ON reporter.id = r.reporter_user_id
             LEFT JOIN users target_owner ON target_owner.id = r.target_owner_user_id
             LEFT JOIN users assignee ON assignee.id = r.assigned_staff_user_id
             WHERE r.target_owner_user_id = ?
                OR (r.target_type = 'user' AND r.target_id = ?)
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = trux_moderation_hydrate_report_row($row);
        }
        return $items;
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_user_case_related_suspicious_events(int $userId, int $limit = 20): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT s.*, actor.username AS actor_username, reviewer.username AS reviewer_username,
                    assignee.username AS assigned_staff_username
             FROM moderation_suspicious_events s
             LEFT JOIN users actor ON actor.id = s.actor_user_id
             LEFT JOIN users reviewer ON reviewer.id = s.reviewed_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = s.assigned_staff_user_id
             WHERE s.actor_user_id = ?
             ORDER BY s.last_detected_at DESC, s.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_watchlisted_user_cases(int $limit = 20): array {
    $limit = max(1, min(100, $limit));

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT c.*,
                    target.username AS target_username,
                    target.display_name AS target_display_name,
                    assignee.username AS assigned_staff_username
             FROM moderation_user_cases c
             JOIN users target ON target.id = c.user_id
             LEFT JOIN users assignee ON assignee.id = c.assigned_staff_user_id
             WHERE c.watchlisted = 1
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_search_case_users(string $query, int $limit = 15): array {
    $query = trim($query);
    $limit = max(1, min(50, $limit));

    try {
        $db = trux_db();
        if ($query === '') {
            $stmt = $db->prepare(
                'SELECT u.id, u.username, u.display_name, u.avatar_path,
                        c.id AS user_case_id, c.watchlisted, c.updated_at AS case_updated_at
                 FROM users u
                 LEFT JOIN moderation_user_cases c ON c.user_id = u.id
                 WHERE c.id IS NOT NULL
                 ORDER BY c.watchlisted DESC, c.updated_at DESC, u.username ASC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $like = '%' . trux_like_escape($query) . '%';
        $stmt = $db->prepare(
            'SELECT u.id, u.username, u.display_name, u.avatar_path,
                    c.id AS user_case_id, c.watchlisted, c.updated_at AS case_updated_at
             FROM users u
             LEFT JOIN moderation_user_cases c ON c.user_id = u.id
             WHERE u.username LIKE ? ESCAPE \'\\\'
                OR u.display_name LIKE ? ESCAPE \'\\\'
                OR u.location LIKE ? ESCAPE \'\\\'
             ORDER BY c.watchlisted DESC, c.updated_at DESC, u.username ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $like, PDO::PARAM_STR);
        $stmt->bindValue(2, $like, PDO::PARAM_STR);
        $stmt->bindValue(3, $like, PDO::PARAM_STR);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_open_user_case_from_report(int $reportId, int $actorUserId): bool {
    if ($reportId <= 0 || $actorUserId <= 0) {
        return false;
    }

    $report = trux_moderation_fetch_report_by_id($reportId);
    if (!$report) {
        return false;
    }

    $userId = (string)($report['target_type'] ?? '') === 'user'
        ? (int)($report['target_id'] ?? 0)
        : (int)($report['target_owner_user_id'] ?? $report['owner_user_id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $case = trux_moderation_ensure_user_case($userId, $actorUserId, $reportId);
    if (!$case) {
        return false;
    }
    trux_moderation_reopen_user_case_if_closed($userId, $actorUserId, 'Linked report activity.');

    $reasonLabel = trux_moderation_reason_label((string)($report['reason_key'] ?? ''));
    $targetLabel = trim((string)($report['target_label'] ?? ''));
    if ($targetLabel === '') {
        $targetLabel = trux_moderation_target_label((string)($report['target_type'] ?? ''), (int)($report['target_id'] ?? 0));
    }
    $note = 'Linked report #' . $reportId . ': ' . $targetLabel . '. Reason: ' . $reasonLabel . '.';
    $reportDetails = trim((string)($report['details'] ?? ''));
    if ($reportDetails !== '') {
        $note .= ' Reporter note: ' . trux_moderation_trimmed_excerpt($reportDetails, 500);
    }

    return trux_moderation_add_user_case_note($userId, $actorUserId, $note, $reportId);
}

function trux_moderation_report_supports_content_removal(array $report): bool {
    return in_array((string)($report['target_type'] ?? ''), ['post', 'comment', 'message'], true);
}

function trux_moderation_report_supports_user_case(array $report): bool {
    $targetType = trim((string)($report['target_type'] ?? ''));
    if ($targetType === 'user' && (int)($report['target_id'] ?? 0) > 0) {
        return true;
    }

    return (int)($report['target_owner_user_id'] ?? $report['owner_user_id'] ?? 0) > 0;
}

function trux_moderation_resolve_report_with_action(int $reportId, int $actorUserId, string $resolutionActionKey): bool {
    $resolutionActionKey = trim(strtolower($resolutionActionKey));
    if ($reportId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_report_resolution_action($resolutionActionKey)) {
        return false;
    }

    return trux_moderation_finalize_report_decision($reportId, $actorUserId, match ($resolutionActionKey) {
        'content_removed', 'content_already_unavailable' => [
            'content_action' => $resolutionActionKey,
            'open_case' => false,
            'enforcement_action' => '',
            'resolution_notes' => '',
        ],
        'user_case_opened' => [
            'content_action' => 'none',
            'open_case' => true,
            'enforcement_action' => '',
            'resolution_notes' => '',
        ],
        'warning_issued', 'dm_restricted', 'account_suspended', 'account_locked' => [
            'content_action' => 'none',
            'open_case' => true,
            'enforcement_action' => $resolutionActionKey,
            'resolution_notes' => '',
            'suspension_ends_at' => null,
        ],
        default => [],
    });
}

function trux_moderation_finalize_report_decision(int $reportId, int $actorUserId, array $decision): bool {
    $report = trux_moderation_fetch_report_by_id($reportId);
    if (!$report || !trux_moderation_can_finalize_report($report, trux_fetch_user_by_id($actorUserId))) {
        return false;
    }

    $contentAction = trim(strtolower((string)($decision['content_action'] ?? 'none')));
    $contentAction = $contentAction === 'none' ? '' : $contentAction;
    $openCase = !empty($decision['open_case']);
    $enforcementAction = trim(strtolower((string)($decision['enforcement_action'] ?? '')));
    $enforcementAction = $enforcementAction === 'none' ? '' : $enforcementAction;
    $resolutionNotes = trim((string)($decision['resolution_notes'] ?? ''));
    $suspensionEndsAt = isset($decision['suspension_ends_at']) ? trux_moderation_parse_local_datetime_input((string)$decision['suspension_ends_at']) : null;

    if ($contentAction !== '' && !trux_moderation_is_valid_report_resolution_action($contentAction)) {
        return false;
    }
    if ($contentAction !== '' && !trux_moderation_report_supports_content_removal($report)) {
        return false;
    }
    if ($enforcementAction !== '' && !trux_moderation_is_valid_user_enforcement_action($enforcementAction)) {
        return false;
    }
    if ($resolutionNotes !== '' && mb_strlen($resolutionNotes) > 4000) {
        return false;
    }

    $targetUserId = (string)($report['target_type'] ?? '') === 'user'
        ? (int)($report['target_id'] ?? 0)
        : (int)($report['target_owner_user_id'] ?? $report['owner_user_id'] ?? 0);
    if (($openCase || $enforcementAction !== '') && $targetUserId <= 0) {
        return false;
    }
    if ($contentAction === '' && !$openCase && $enforcementAction === '') {
        return false;
    }
    if ($enforcementAction === 'account_suspended' && $suspensionEndsAt === null) {
        return false;
    }

    if ($contentAction === 'content_removed') {
        $removalResult = trux_moderation_remove_reported_content($report);
        if ($removalResult !== 'removed') {
            return false;
        }
    } elseif ($contentAction === 'content_already_unavailable') {
        if (trux_moderation_fetch_target_context((string)($report['target_type'] ?? ''), (int)($report['target_id'] ?? 0))) {
            return false;
        }
    }

    $caseId = null;
    if ($openCase || $enforcementAction !== '') {
        if (!trux_moderation_open_user_case_from_report($reportId, $actorUserId)) {
            return false;
        }
        $case = trux_moderation_fetch_user_case_by_user_id($targetUserId);
        $caseId = $case ? (int)($case['id'] ?? 0) : null;
    }

    $enforcement = null;
    if ($enforcementAction !== '') {
        $reasonSummary = 'Report #' . $reportId . ' for ' . trux_moderation_reason_label((string)($report['reason_key'] ?? ''));
        $enforcement = trux_moderation_create_user_enforcement(
            $targetUserId,
            $actorUserId,
            $enforcementAction,
            $reportId,
            $caseId,
            $reasonSummary,
            $resolutionNotes,
            $suspensionEndsAt
        );
        if ($enforcement === null) {
            return false;
        }
    }

    $context = [
        'content_action' => $contentAction,
        'user_case_opened' => $openCase || $caseId !== null,
        'user_case_id' => $caseId,
        'enforcement_action' => $enforcementAction,
        'enforcement_id' => $enforcement !== null ? (int)($enforcement['id'] ?? 0) : null,
        'resolution_notes' => $resolutionNotes,
        'suspension_ends_at' => $suspensionEndsAt,
    ];
    $primaryAction = trux_moderation_primary_report_resolution_action($context, null);
    return trux_moderation_update_report_status($reportId, $actorUserId, 'resolved', $primaryAction, $context);
}

function trux_moderation_update_report_status(int $reportId, int $actorUserId, string $status, ?string $resolutionActionKey = null, array $context = []): bool {
    if ($reportId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_report_status($status)) {
        return false;
    }

    $report = trux_moderation_fetch_report_by_id($reportId);
    if (!$report) {
        return false;
    }

    $previousStatus = (string)($report['status'] ?? 'open');
    if ($previousStatus === $status) {
        return true;
    }

    if (trux_moderation_is_report_archived_status($previousStatus)) {
        $actorRole = trux_fetch_user_staff_role($actorUserId);
        if ($status !== 'open' || !trux_is_owner_staff_role($actorRole)) {
            return false;
        }
    }

    $resolutionActionKey = trux_moderation_is_valid_report_resolution_action($resolutionActionKey)
        ? trim(strtolower((string)$resolutionActionKey))
        : null;
    if ($status === 'resolved' && $resolutionActionKey === null) {
        $resolutionActionKey = trim(strtolower((string)($report['resolution_action_key'] ?? '')));
        $resolutionActionKey = trux_moderation_is_valid_report_resolution_action($resolutionActionKey) ? $resolutionActionKey : null;
    }
    if ($status !== 'resolved') {
        $resolutionActionKey = null;
    }

    $resolvedAt = in_array($status, ['resolved', 'dismissed'], true)
        ? trux_moderation_format_datetime(trux_moderation_now())
        : null;

    $db = null;

    try {
        $db = trux_db();
        $db->beginTransaction();

        $update = $db->prepare(
            'UPDATE moderation_reports
             SET status = ?, resolved_at = ?, resolution_action_key = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $update->execute([$status, $resolvedAt, $resolutionActionKey, $reportId]);

        $audit = $db->prepare(
            'INSERT INTO moderation_audit_logs
                (actor_user_id, action_type, subject_type, subject_id, details_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $auditDetails = [
            'from_status' => $previousStatus,
            'to_status' => $status,
            'resolution_action_key' => $resolutionActionKey,
        ];
        if (!empty($context)) {
            $auditDetails = array_merge($auditDetails, trux_moderation_clean_map($context));
        }

        $audit->execute([
            $actorUserId,
            'report_status_updated',
            'report',
            $reportId,
            trux_moderation_json_encode(trux_moderation_clean_map($auditDetails)),
        ]);

        $db->commit();

        $report['status'] = $status;
        $report['resolved_at'] = $resolvedAt;
        $report['resolution_action_key'] = $resolutionActionKey;
        trux_moderation_dispatch_report_status_update($report, $status, $resolutionActionKey, $context);
        return true;
    } catch (PDOException) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function trux_moderation_mark_suspicious_event_reviewed(int $eventId, int $actorUserId): bool {
    return trux_moderation_update_suspicious_event_status($eventId, $actorUserId, 'reviewed');
}

function trux_moderation_fetch_suspicious_event_by_id(int $eventId): ?array {
    if ($eventId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT s.*, actor.username AS actor_username, reviewer.username AS reviewer_username,
                    assignee.username AS assigned_staff_username
             FROM moderation_suspicious_events s
             LEFT JOIN users actor ON actor.id = s.actor_user_id
             LEFT JOIN users reviewer ON reviewer.id = s.reviewed_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = s.assigned_staff_user_id
             WHERE s.id = ?
             LIMIT 1'
        );
        $stmt->execute([$eventId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_assign_suspicious_event(int $eventId, int $actorUserId, ?int $assignedStaffUserId): bool {
    $event = trux_moderation_fetch_suspicious_event_by_id($eventId);
    if (!$event || $actorUserId <= 0) {
        return false;
    }

    $nextAssigneeId = $assignedStaffUserId !== null && $assignedStaffUserId > 0 ? $assignedStaffUserId : null;
    if ($nextAssigneeId !== null && !trux_has_staff_role(trux_fetch_user_staff_role($nextAssigneeId), 'developer')) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_suspicious_events
             SET assigned_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$nextAssigneeId, $eventId]);
        trux_moderation_write_audit_log($actorUserId, 'suspicious_event_assigned', 'suspicious_event', $eventId, [
            'to_assignee_user_id' => $nextAssigneeId,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_update_suspicious_event_status(int $eventId, int $actorUserId, string $status): bool {
    $status = trim(strtolower($status));
    if ($eventId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_suspicious_status($status)) {
        return false;
    }

    $event = trux_moderation_fetch_suspicious_event_by_id($eventId);
    if (!$event) {
        return false;
    }
    if ((string)($event['status'] ?? '') === $status) {
        return true;
    }

    $reviewerId = in_array($status, ['reviewed', 'false_positive'], true) ? $actorUserId : null;
    $reviewedAt = in_array($status, ['reviewed', 'false_positive'], true) ? trux_moderation_format_datetime(trux_moderation_now()) : null;
    $auditType = match ($status) {
        'reviewed' => 'suspicious_event_reviewed',
        'false_positive' => 'suspicious_event_false_positive',
        'open' => 'suspicious_event_reopened',
        default => 'suspicious_event_reviewed',
    };

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_suspicious_events
             SET status = ?, reviewed_by_staff_user_id = ?, reviewed_at = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$status, $reviewerId, $reviewedAt, $eventId]);
        trux_moderation_write_audit_log($actorUserId, $auditType, 'suspicious_event', $eventId, [
            'from_status' => (string)($event['status'] ?? ''),
            'to_status' => $status,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_fetch_reports(array $filters, int $page = 1, int $perPage = 25): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all' && trux_moderation_is_valid_report_status($status)) {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }

    $priority = trim((string)($filters['priority'] ?? ''));
    if ($priority !== '' && $priority !== 'all' && trux_moderation_is_valid_report_priority($priority)) {
        $where[] = 'r.priority = ?';
        $params[] = $priority;
    }

    $reasonKey = trim((string)($filters['reason_key'] ?? ''));
    if ($reasonKey !== '' && $reasonKey !== 'all' && trux_moderation_is_valid_report_reason($reasonKey)) {
        $where[] = 'r.reason_key = ?';
        $params[] = $reasonKey;
    }

    $targetType = trim((string)($filters['target_type'] ?? ''));
    if ($targetType !== '' && $targetType !== 'all' && trux_moderation_is_valid_report_target_type($targetType)) {
        $where[] = 'r.target_type = ?';
        $params[] = $targetType;
    }

    $assignee = trim((string)($filters['assignee'] ?? ''));
    if ($assignee === 'unassigned') {
        $where[] = 'r.assigned_staff_user_id IS NULL';
    } elseif (preg_match('/^\d+$/', $assignee)) {
        $where[] = 'r.assigned_staff_user_id = ?';
        $params[] = (int)$assignee;
    }

    $search = trim((string)($filters['q'] ?? ''));
    if ($search !== '') {
        $like = '%' . trux_like_escape($search) . '%';
        $where[] = '(reporter.username LIKE ? ESCAPE \'\\\' OR target_owner.username LIKE ? ESCAPE \'\\\' OR assignee.username LIKE ? ESCAPE \'\\\' OR r.details LIKE ? ESCAPE \'\\\' OR r.source_url LIKE ? ESCAPE \'\\\' OR CAST(r.target_id AS CHAR) LIKE ? ESCAPE \'\\\')';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $countStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM moderation_reports r
             JOIN users reporter ON reporter.id = r.reporter_user_id
             LEFT JOIN users target_owner ON target_owner.id = r.target_owner_user_id
             LEFT JOIN users assignee ON assignee.id = r.assigned_staff_user_id
             $whereSql"
        );
        foreach ($params as $index => $value) {
            if (is_int($value)) {
                $countStmt->bindValue($index + 1, $value, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue($index + 1, $value, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT r.*,
                    reporter.username AS reporter_username,
                    target_owner.username AS target_owner_username,
                    assignee.username AS assigned_staff_username,
                    assignee.staff_role AS assigned_staff_role
             FROM moderation_reports r
             JOIN users reporter ON reporter.id = r.reporter_user_id
             LEFT JOIN users target_owner ON target_owner.id = r.target_owner_user_id
             LEFT JOIN users assignee ON assignee.id = r.assigned_staff_user_id
             $whereSql
             ORDER BY
                CASE r.status
                    WHEN 'open' THEN 0
                    WHEN 'investigating' THEN 1
                    WHEN 'resolved' THEN 2
                    WHEN 'dismissed' THEN 3
                    ELSE 9
                END,
                CASE r.priority
                    WHEN 'critical' THEN 0
                    WHEN 'high' THEN 1
                    WHEN 'normal' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 9
                END,
                r.created_at DESC,
                r.id DESC
             LIMIT ? OFFSET ?"
        );

        $bindIndex = 1;
        foreach ($params as $value) {
            if (is_int($value)) {
                $dataStmt->bindValue($bindIndex++, $value, PDO::PARAM_INT);
            } else {
                $dataStmt->bindValue($bindIndex++, $value, PDO::PARAM_STR);
            }
        }
        $dataStmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $items = [];
        foreach ($dataStmt->fetchAll() as $row) {
            $items[] = trux_moderation_hydrate_report_row($row);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    } catch (PDOException) {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
        ];
    }
}

function trux_moderation_normalize_ids(array $values): array {
    $ids = [];
    foreach ($values as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function trux_moderation_fetch_report_review_state(array $reportIds, ?int $viewerUserId = null): array {
    $reportIds = trux_moderation_normalize_ids($reportIds);
    if ($reportIds === []) {
        return [];
    }

    $state = [];
    foreach ($reportIds as $reportId) {
        $state[$reportId] = [
            'discussion' => [],
            'votes' => [],
            'totals' => [
                'yay' => 0,
                'nay' => 0,
            ],
            'viewer_vote' => '',
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($reportIds), '?'));

    try {
        $db = trux_db();
        $discussionStmt = $db->prepare(
            "SELECT d.id, d.report_id, d.author_user_id, d.body, d.created_at,
                    u.username AS author_username, u.staff_role AS author_staff_role
             FROM moderation_report_discussions d
             JOIN users u ON u.id = d.author_user_id
             WHERE d.report_id IN ($placeholders)
             ORDER BY d.created_at ASC, d.id ASC"
        );
        foreach ($reportIds as $index => $reportId) {
            $discussionStmt->bindValue($index + 1, $reportId, PDO::PARAM_INT);
        }
        $discussionStmt->execute();

        foreach ($discussionStmt->fetchAll() as $row) {
            $reportId = (int)($row['report_id'] ?? 0);
            if (!isset($state[$reportId])) {
                continue;
            }
            $state[$reportId]['discussion'][] = $row;
        }
    } catch (PDOException) {
        // Keep the review UI usable until the discussion migration exists.
    }

    try {
        $db = trux_db();
        $voteStmt = $db->prepare(
            "SELECT v.id, v.report_id, v.staff_user_id, v.vote_value, v.created_at, v.updated_at,
                    u.username AS staff_username, u.staff_role AS staff_role
             FROM moderation_report_votes v
             JOIN users u ON u.id = v.staff_user_id
             WHERE v.report_id IN ($placeholders)
             ORDER BY v.updated_at ASC, v.id ASC"
        );
        foreach ($reportIds as $index => $reportId) {
            $voteStmt->bindValue($index + 1, $reportId, PDO::PARAM_INT);
        }
        $voteStmt->execute();

        foreach ($voteStmt->fetchAll() as $row) {
            $reportId = (int)($row['report_id'] ?? 0);
            if (!isset($state[$reportId])) {
                continue;
            }

            $voteValue = trim((string)($row['vote_value'] ?? ''));
            if (trux_moderation_is_valid_report_vote($voteValue)) {
                $state[$reportId]['totals'][$voteValue] = (int)($state[$reportId]['totals'][$voteValue] ?? 0) + 1;
            }
            if ($viewerUserId !== null && $viewerUserId > 0 && (int)($row['staff_user_id'] ?? 0) === $viewerUserId) {
                $state[$reportId]['viewer_vote'] = $voteValue;
            }

            $state[$reportId]['votes'][] = $row;
        }
    } catch (PDOException) {
        // Keep the review UI usable until the vote migration exists.
    }

    return $state;
}

function trux_moderation_add_report_discussion_message(int $reportId, int $actorUserId, string $body): bool {
    $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');
    if ($reportId <= 0 || $actorUserId <= 0 || $body === '' || mb_strlen($body) > 280) {
        return false;
    }

    if (!trux_has_staff_role(trux_fetch_user_staff_role($actorUserId), 'moderator')) {
        return false;
    }

    $report = trux_moderation_fetch_report_by_id($reportId);
    if (!$report || trux_moderation_is_report_archived_status((string)($report['status'] ?? ''))) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_report_discussions
                (report_id, author_user_id, body)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$reportId, $actorUserId, $body]);

        trux_moderation_write_audit_log($actorUserId, 'report_review_note_added', 'report', $reportId, [
            'note_excerpt' => mb_substr($body, 0, 120),
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_set_report_vote(int $reportId, int $actorUserId, string $voteValue): bool {
    $voteValue = trim(strtolower($voteValue));
    if ($reportId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_report_vote($voteValue)) {
        return false;
    }

    if (!trux_has_staff_role(trux_fetch_user_staff_role($actorUserId), 'moderator')) {
        return false;
    }

    $report = trux_moderation_fetch_report_by_id($reportId);
    if (!$report || trux_moderation_is_report_archived_status((string)($report['status'] ?? ''))) {
        return false;
    }

    try {
        $db = trux_db();

        $existingStmt = $db->prepare(
            'SELECT vote_value
             FROM moderation_report_votes
             WHERE report_id = ? AND staff_user_id = ?
             LIMIT 1'
        );
        $existingStmt->execute([$reportId, $actorUserId]);
        $existingVote = $existingStmt->fetchColumn();
        if (is_string($existingVote) && trim(strtolower($existingVote)) === $voteValue) {
            return true;
        }

        $stmt = $db->prepare(
            'INSERT INTO moderation_report_votes
                (report_id, staff_user_id, vote_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                vote_value = VALUES(vote_value),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$reportId, $actorUserId, $voteValue]);

        trux_moderation_write_audit_log($actorUserId, 'report_vote_recorded', 'report', $reportId, [
            'vote' => $voteValue,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_fetch_suspicious_events(array $filters, int $page = 1, int $perPage = 25): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all' && trux_moderation_is_valid_suspicious_status($status)) {
        $where[] = 's.status = ?';
        $params[] = $status;
    }

    $severity = trim((string)($filters['severity'] ?? ''));
    if ($severity !== '' && $severity !== 'all' && trux_moderation_is_valid_severity($severity)) {
        $where[] = 's.severity = ?';
        $params[] = $severity;
    }

    $ruleKey = trim((string)($filters['rule_key'] ?? ''));
    if ($ruleKey !== '' && $ruleKey !== 'all' && trux_moderation_is_valid_rule_key($ruleKey)) {
        $where[] = 's.rule_key = ?';
        $params[] = $ruleKey;
    }

    $search = trim((string)($filters['q'] ?? ''));
    if ($search !== '') {
        $like = '%' . trux_like_escape($search) . '%';
        $where[] = '(actor.username LIKE ? ESCAPE \'\\\' OR reviewer.username LIKE ? ESCAPE \'\\\' OR assignee.username LIKE ? ESCAPE \'\\\' OR s.summary LIKE ? ESCAPE \'\\\' OR CAST(s.id AS CHAR) LIKE ? ESCAPE \'\\\')';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $countStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM moderation_suspicious_events s
             LEFT JOIN users actor ON actor.id = s.actor_user_id
             LEFT JOIN users reviewer ON reviewer.id = s.reviewed_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = s.assigned_staff_user_id
             $whereSql"
        );
        foreach ($params as $index => $value) {
            $countStmt->bindValue($index + 1, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT s.*,
                    actor.username AS actor_username,
                    reviewer.username AS reviewer_username,
                    assignee.username AS assigned_staff_username
             FROM moderation_suspicious_events s
             LEFT JOIN users actor ON actor.id = s.actor_user_id
             LEFT JOIN users reviewer ON reviewer.id = s.reviewed_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = s.assigned_staff_user_id
             $whereSql
             ORDER BY
                 CASE s.status
                    WHEN 'open' THEN 0
                    WHEN 'reviewed' THEN 1
                    ELSE 1
                END,
                CASE s.severity
                    WHEN 'critical' THEN 0
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 9
                END,
                s.last_detected_at DESC,
                s.id DESC
             LIMIT ? OFFSET ?"
        );

        $bindIndex = 1;
        foreach ($params as $value) {
            $dataStmt->bindValue($bindIndex++, $value);
        }
        $dataStmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    } catch (PDOException) {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
        ];
    }
}

function trux_moderation_fetch_audit_logs(array $filters, int $page = 1, int $perPage = 30): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    $actionType = trim((string)($filters['action_type'] ?? ''));
    if ($actionType !== '' && $actionType !== 'all') {
        $where[] = 'l.action_type = ?';
        $params[] = $actionType;
    }

    $subjectType = trim((string)($filters['subject_type'] ?? ''));
    if ($subjectType !== '' && $subjectType !== 'all') {
        $where[] = 'l.subject_type = ?';
        $params[] = $subjectType;
    }

    $actor = trim((string)($filters['actor'] ?? ''));
    if (preg_match('/^\d+$/', $actor)) {
        $where[] = 'l.actor_user_id = ?';
        $params[] = (int)$actor;
    }

    $search = trim((string)($filters['q'] ?? ''));
    if ($search !== '') {
        $like = '%' . trux_like_escape($search) . '%';
        $where[] = '(actor.username LIKE ? ESCAPE \'\\\' OR l.action_type LIKE ? ESCAPE \'\\\' OR l.subject_type LIKE ? ESCAPE \'\\\' OR CAST(l.subject_id AS CHAR) LIKE ? ESCAPE \'\\\')';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $countStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM moderation_audit_logs l
             JOIN users actor ON actor.id = l.actor_user_id
             $whereSql"
        );
        foreach ($params as $index => $value) {
            if (is_int($value)) {
                $countStmt->bindValue($index + 1, $value, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue($index + 1, $value, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT l.*, actor.username AS actor_username, actor.staff_role AS actor_staff_role
             FROM moderation_audit_logs l
             JOIN users actor ON actor.id = l.actor_user_id
             $whereSql
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ? OFFSET ?"
        );

        $bindIndex = 1;
        foreach ($params as $value) {
            if (is_int($value)) {
                $dataStmt->bindValue($bindIndex++, $value, PDO::PARAM_INT);
            } else {
                $dataStmt->bindValue($bindIndex++, $value, PDO::PARAM_STR);
            }
        }
        $dataStmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    } catch (PDOException) {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
        ];
    }
}

function trux_moderation_fetch_user_staff_role_history(int $userId, int $limit = 12): array {
    $userId = max(0, $userId);
    $limit = max(1, min(50, $limit));
    if ($userId <= 0) {
        return [];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            "SELECT l.*, actor.username AS actor_username, actor.staff_role AS actor_staff_role
             FROM moderation_audit_logs l
             JOIN users actor ON actor.id = l.actor_user_id
             WHERE l.subject_type = 'user'
               AND l.subject_id = ?
               AND l.action_type = 'staff_role_updated'
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_user_audit_excerpt(int $userId, int $limit = 10): array {
    $userId = max(0, $userId);
    $limit = max(1, min(50, $limit));
    if ($userId <= 0) {
        return [];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            "SELECT l.*, actor.username AS actor_username, actor.staff_role AS actor_staff_role
             FROM moderation_audit_logs l
             JOIN users actor ON actor.id = l.actor_user_id
             WHERE l.subject_type = 'user'
               AND l.subject_id = ?
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_count_reports_by_status(?string $status = null, ?string $priority = null): int {
    $where = [];
    $params = [];

    if ($status !== null && $status !== '' && trux_moderation_is_valid_report_status($status)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    if ($priority !== null && $priority !== '' && trux_moderation_is_valid_report_priority($priority)) {
        $where[] = 'priority = ?';
        $params[] = $priority;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $stmt = $db->prepare("SELECT COUNT(*) FROM moderation_reports $whereSql");
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_count_suspicious_by_status(?string $status = null): int {
    $where = [];
    $params = [];

    if ($status !== null && $status !== '' && trux_moderation_is_valid_suspicious_status($status)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $stmt = $db->prepare("SELECT COUNT(*) FROM moderation_suspicious_events $whereSql");
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_count_user_cases_by_status(?string $status = null): int {
    $where = [];
    $params = [];

    if ($status !== null && $status !== '' && trux_moderation_is_valid_user_case_status($status)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $stmt = $db->prepare("SELECT COUNT(*) FROM moderation_user_cases $whereSql");
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_count_activity_events_since(DateTimeImmutable $since): int {
    try {
        $db = trux_db();
        $stmt = $db->prepare('SELECT COUNT(*) FROM moderation_activity_events WHERE created_at >= ?');
        $stmt->execute([trux_moderation_format_datetime($since)]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_moderation_fetch_staff_badge_counts(?int $staffUserId = null, ?string $staffRole = null): array {
    $userId = $staffUserId !== null ? (int)$staffUserId : (int)(trux_current_user()['id'] ?? 0);
    $role = $staffRole !== null ? trux_staff_role($staffRole) : trux_current_staff_role();
    $counts = [
        'reports' => 0,
        'user_review' => 0,
        'activity' => 0,
        'escalations' => 0,
        'appeals' => 0,
        'total' => 0,
    ];
    if ($userId <= 0 || !trux_has_staff_role($role, 'developer')) {
        return $counts;
    }

    try {
        $db = trux_db();
        $activeStatuses = ['open', 'investigating'];
        $caseStatuses = ['open', 'investigating', 'escalated'];
        $appealStatuses = ['open', 'investigating'];

        $countForAssignment = static function (PDO $db, string $table, string $statusColumn, array $statuses, int $userId): int {
            $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
            $sql = "SELECT COUNT(*)
                    FROM $table
                    WHERE $statusColumn IN ($placeholders)
                      AND (assigned_staff_user_id IS NULL OR assigned_staff_user_id = ?)";
            $stmt = $db->prepare($sql);
            $bindIndex = 1;
            foreach ($statuses as $status) {
                $stmt->bindValue($bindIndex++, $status, PDO::PARAM_STR);
            }
            $stmt->bindValue($bindIndex, $userId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        };

        $counts['reports'] = $countForAssignment($db, 'moderation_reports', 'status', $activeStatuses, $userId);
        $counts['user_review'] = $countForAssignment($db, 'moderation_user_cases', 'status', $caseStatuses, $userId);
        $counts['activity'] = $countForAssignment($db, 'moderation_suspicious_events', 'status', ['open'], $userId);

        if (trux_has_staff_role($role, 'admin')) {
            $counts['escalations'] = $countForAssignment($db, 'moderation_escalations', 'status', ['open', 'in_review'], $userId);
            $counts['appeals'] = $countForAssignment($db, 'moderation_appeals', 'status', $appealStatuses, $userId);
        }
    } catch (PDOException) {
        return $counts;
    }

    $counts['total'] = (int)array_sum($counts);
    return $counts;
}

function trux_moderation_fetch_dashboard_data(): array {
    $staffRole = trux_current_staff_role();
    $openReports = trux_moderation_count_reports_by_status('open');
    $investigatingReports = trux_moderation_count_reports_by_status('investigating');
    $criticalReports = (int)(trux_moderation_fetch_reports([
        'status' => 'open',
        'priority' => 'critical',
    ], 1, 1)['total'] ?? 0);
    $activeCases = trux_moderation_count_user_cases_by_status('open')
        + trux_moderation_count_user_cases_by_status('investigating')
        + trux_moderation_count_user_cases_by_status('escalated');
    $openSuspicious = trux_moderation_count_suspicious_by_status('open');
    $activityLast24h = trux_moderation_count_activity_events_since(trux_moderation_now()->modify('-24 hours'));
    $openEscalations = trux_has_staff_role($staffRole, 'admin')
        ? (int)(trux_moderation_fetch_escalations(['status' => 'open'], 1, 1)['total'] ?? 0)
        : 0;
    $openAppeals = trux_has_staff_role($staffRole, 'admin')
        ? (int)(trux_moderation_fetch_appeals(['status' => 'open'], 1, 1)['total'] ?? 0)
        : 0;
    $badgeCounts = trux_moderation_fetch_staff_badge_counts();

    $reports = trux_moderation_fetch_reports([
        'status' => 'open',
    ], 1, 5)['items'];

    $suspicious = trux_moderation_fetch_suspicious_events([
        'status' => 'open',
    ], 1, 5)['items'];

    $escalations = trux_has_staff_role($staffRole, 'admin')
        ? trux_moderation_fetch_escalations(['status' => 'open'], 1, 5)['items']
        : [];
    $appeals = trux_has_staff_role($staffRole, 'admin')
        ? trux_moderation_fetch_appeals(['status' => 'open'], 1, 5)['items']
        : [];
    $auditLogs = trux_moderation_fetch_audit_logs([], 1, 8)['items'];

    return [
        'metrics' => [
            ['label' => 'Open reports', 'value' => $openReports],
            ['label' => 'Investigating', 'value' => $investigatingReports],
            ['label' => 'Critical reports', 'value' => $criticalReports],
            ['label' => 'Active user cases', 'value' => $activeCases],
            ['label' => 'Open suspicious', 'value' => $openSuspicious],
            ['label' => 'Open escalations', 'value' => $openEscalations],
            ['label' => 'Open appeals', 'value' => $openAppeals],
            ['label' => 'Activity events (24h)', 'value' => $activityLast24h],
        ],
        'open_reports' => $reports,
        'open_suspicious' => $suspicious,
        'open_escalations' => $escalations,
        'open_appeals' => $appeals,
        'recent_audit_logs' => $auditLogs,
        'badge_counts' => $badgeCounts,
        'future_modules' => [],
    ];
}

function trux_moderation_target_label(string $targetType, int $targetId): string {
    $label = trux_moderation_label(trux_moderation_report_target_types(), $targetType, ucfirst($targetType));
    return $targetId > 0 ? $label . ' #' . $targetId : $label;
}

function trux_moderation_reason_label(string $reasonKey): string {
    return trux_moderation_label(trux_moderation_report_reason_options(), $reasonKey);
}

function trux_moderation_rule_label(string $ruleKey): string {
    return trux_moderation_label(trux_moderation_rule_labels(), $ruleKey);
}

function trux_moderation_action_label(string $actionType): string {
    return trux_moderation_label(trux_moderation_audit_action_labels(), $actionType);
}

function trux_moderation_subject_label(string $subjectType): string {
    return trux_moderation_label(trux_moderation_subject_type_labels(), $subjectType);
}

function trux_moderation_status_badge_class(string $status): string {
    return match ($status) {
        'resolved', 'reviewed', 'active', 'upheld' => 'is-success',
        'dismissed', 'false_positive', 'closed', 'denied', 'expired', 'revoked' => 'is-muted',
        'investigating', 'in_review' => 'is-info',
        'escalated' => 'is-warning',
        default => 'is-open',
    };
}

function trux_moderation_priority_badge_class(string $priority): string {
    return match ($priority) {
        'critical' => 'is-danger',
        'high' => 'is-warning',
        'low' => 'is-muted',
        default => 'is-info',
    };
}

function trux_moderation_severity_badge_class(string $severity): string {
    return match ($severity) {
        'critical' => 'is-danger',
        'high' => 'is-warning',
        'medium' => 'is-info',
        default => 'is-muted',
    };
}

function trux_moderation_audit_log_summary(array $row): string {
    $actionType = (string)($row['action_type'] ?? '');
    $details = trux_moderation_json_decode((string)($row['details_json'] ?? ''));

    if ($actionType === 'report_status_updated') {
        $from = (string)($details['from_status'] ?? '');
        $to = (string)($details['to_status'] ?? '');
        if ($from !== '' && $to !== '') {
            $summary = trux_moderation_label(trux_moderation_report_statuses(), $from) . ' -> ' . trux_moderation_label(trux_moderation_report_statuses(), $to);
            $resolutionActionKey = trim((string)($details['resolution_action_key'] ?? ''));
            if ($resolutionActionKey !== '' && trux_moderation_is_valid_report_resolution_action($resolutionActionKey)) {
                $summary .= ' (' . trux_moderation_resolution_action_label($resolutionActionKey) . ')';
            }
            return $summary;
        }
    }

    if (in_array($actionType, ['report_assigned', 'report_unassigned'], true)) {
        $toUser = trim((string)($details['to_assignee_username'] ?? ''));
        if ($toUser !== '') {
            return 'Head reviewer: @' . $toUser;
        }
        return 'Head reviewer cleared';
    }

    if ($actionType === 'report_review_note_added') {
        return 'Added a discussion line';
    }

    if ($actionType === 'report_vote_recorded') {
        $vote = trim((string)($details['vote'] ?? ''));
        if ($vote !== '' && trux_moderation_is_valid_report_vote($vote)) {
            return 'Vote: ' . trux_moderation_label(trux_moderation_report_vote_options(), $vote);
        }
        return 'Vote recorded';
    }

    if ($actionType === 'staff_role_updated') {
        $fromRole = trim((string)($details['from_role'] ?? ''));
        $toRole = trim((string)($details['to_role'] ?? ''));
        if ($fromRole !== '' && $toRole !== '') {
            return trux_staff_role_label($fromRole) . ' -> ' . trux_staff_role_label($toRole);
        }
        return 'Staff role changed';
    }

    if ($actionType === 'suspicious_event_reviewed') {
        return 'Marked as reviewed';
    }

    if ($actionType === 'suspicious_event_false_positive') {
        return 'Marked as false positive';
    }

    if ($actionType === 'suspicious_event_reopened') {
        return 'Reopened for triage';
    }

    if ($actionType === 'suspicious_event_assigned') {
        $toUserId = (int)($details['to_assignee_user_id'] ?? 0);
        return $toUserId > 0 ? 'Assigned to staff #' . $toUserId : 'Assignment cleared';
    }

    if ($actionType === 'user_case_created') {
        $username = trim((string)($details['target_username'] ?? ''));
        return $username !== '' ? 'Case opened for @' . $username : 'User case opened';
    }

    if ($actionType === 'user_case_updated') {
        $status = trim((string)($details['status'] ?? ''));
        $priority = trim((string)($details['priority'] ?? ''));
        if ($status !== '' || $priority !== '') {
            $parts = [];
            if ($status !== '' && trux_moderation_is_valid_user_case_status($status)) {
                $parts[] = 'Status: ' . trux_moderation_label(trux_moderation_user_case_statuses(), $status);
            }
            if ($priority !== '' && trux_moderation_is_valid_user_case_priority($priority)) {
                $parts[] = 'Priority: ' . trux_moderation_label(trux_moderation_user_case_priorities(), $priority);
            }
            if ($parts !== []) {
                return implode(' | ', $parts);
            }
        }
        $summaryExcerpt = trim((string)($details['summary_excerpt'] ?? ''));
        return $summaryExcerpt !== '' ? 'Summary: ' . $summaryExcerpt : 'User case updated';
    }

    if ($actionType === 'user_case_watchlist_updated') {
        return !empty($details['watchlisted']) ? 'Added to watchlist' : 'Removed from watchlist';
    }

    if ($actionType === 'user_case_note_added') {
        return 'Added a case note';
    }

    if ($actionType === 'user_case_assigned') {
        $toUser = trim((string)($details['to_assignee_username'] ?? ''));
        return $toUser !== '' ? 'Case assignee: @' . $toUser : 'Case assignee cleared';
    }

    if ($actionType === 'user_case_closed') {
        $resolutionActionKey = trim((string)($details['resolution_action_key'] ?? ''));
        return $resolutionActionKey !== '' ? 'Closed with ' . trux_moderation_resolution_action_label($resolutionActionKey) : 'Case closed';
    }

    if ($actionType === 'user_case_reopened') {
        return 'Case reopened';
    }

    if ($actionType === 'escalation_created') {
        return 'Escalation opened';
    }

    if ($actionType === 'escalation_assigned') {
        $toUserId = (int)($details['to_assignee_user_id'] ?? 0);
        return $toUserId > 0 ? 'Escalation assigned to staff #' . $toUserId : 'Escalation assignment cleared';
    }

    if ($actionType === 'escalation_status_updated') {
        $status = trim((string)($details['status'] ?? ''));
        return $status !== '' ? 'Escalation: ' . trux_moderation_label(trux_moderation_escalation_statuses(), $status) : 'Escalation updated';
    }

    if ($actionType === 'appeal_submitted') {
        return 'Appeal submitted';
    }

    if ($actionType === 'appeal_assigned') {
        $toUserId = (int)($details['to_assignee_user_id'] ?? 0);
        return $toUserId > 0 ? 'Appeal assigned to staff #' . $toUserId : 'Appeal assignment cleared';
    }

    if ($actionType === 'appeal_status_updated') {
        $status = trim((string)($details['status'] ?? ''));
        return $status !== '' ? 'Appeal: ' . trux_moderation_label(trux_moderation_appeal_statuses(), $status) : 'Appeal updated';
    }

    if ($actionType === 'user_enforcement_created') {
        $actionKey = trim((string)($details['action_key'] ?? ''));
        return $actionKey !== '' ? 'Enforcement: ' . trux_moderation_resolution_action_label($actionKey) : 'User enforcement created';
    }

    if ($actionType === 'user_enforcement_revoked') {
        $actionKey = trim((string)($details['action_key'] ?? ''));
        return $actionKey !== '' ? 'Revoked ' . trux_moderation_resolution_action_label($actionKey) : 'User enforcement revoked';
    }

    if ($actionType === 'rule_config_updated') {
        $ruleKey = trim((string)($details['rule_key'] ?? ''));
        return $ruleKey !== '' ? 'Rule: ' . trux_moderation_rule_label($ruleKey) : 'Rule config updated';
    }

    return 'Staff action recorded';
}

function trux_moderation_metadata_preview(array $metadata, int $limit = 4): array {
    $preview = [];
    foreach ($metadata as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if (is_array($value)) {
            $value = implode(', ', array_map('strval', array_slice($value, 0, 5)));
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } else {
            $value = (string)$value;
        }

        $value = trim($value);
        if ($value === '') {
            continue;
        }

        $preview[] = [
            'key' => ucfirst(str_replace('_', ' ', $key)),
            'value' => mb_substr($value, 0, 120),
        ];

        if (count($preview) >= $limit) {
            break;
        }
    }

    return $preview;
}

function trux_moderation_parse_local_datetime_input(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'];
    foreach ($formats as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $value);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('Y-m-d H:i:s');
        }
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Exception) {
        return null;
    }
}

function trux_moderation_fetch_user_case_by_id(int $caseId): ?array {
    if ($caseId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT c.*,
                    target.username AS target_username,
                    assignee.username AS assigned_staff_username,
                    creator.username AS created_by_staff_username,
                    updater.username AS updated_by_staff_username,
                    closer.username AS closed_by_staff_username
             FROM moderation_user_cases c
             JOIN users target ON target.id = c.user_id
             JOIN users creator ON creator.id = c.created_by_staff_user_id
             JOIN users updater ON updater.id = c.updated_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = c.assigned_staff_user_id
             LEFT JOIN users closer ON closer.id = c.closed_by_staff_user_id
             WHERE c.id = ?
             LIMIT 1'
        );
        $stmt->execute([$caseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_reopen_user_case_if_closed(int $userId, int $actorUserId, string $reason = ''): bool {
    $case = trux_moderation_fetch_user_case_by_user_id($userId);
    if (!$case) {
        return false;
    }

    if ((string)($case['status'] ?? '') !== 'closed') {
        return true;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_user_cases
             SET status = ?, closed_at = NULL, closed_by_staff_user_id = NULL, updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute(['open', $actorUserId, (int)$case['id']]);
        trux_moderation_write_audit_log($actorUserId, 'user_case_reopened', 'user_case', (int)$case['id'], [
            'user_id' => $userId,
            'reason' => $reason,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_update_user_case_workflow(
    int $userId,
    int $actorUserId,
    string $status,
    string $priority,
    ?string $resolutionActionKey = null,
    string $resolutionNotes = ''
): bool {
    $status = trim(strtolower($status));
    $priority = trim(strtolower($priority));
    $resolutionActionKey = trim(strtolower((string)$resolutionActionKey));
    $resolutionNotes = trim($resolutionNotes);

    if (!trux_moderation_is_valid_user_case_status($status) || !trux_moderation_is_valid_user_case_priority($priority)) {
        return false;
    }
    if ($resolutionActionKey !== '' && !trux_moderation_is_valid_user_case_resolution_action($resolutionActionKey)) {
        return false;
    }
    if ($resolutionNotes !== '' && mb_strlen($resolutionNotes) > 4000) {
        return false;
    }

    $case = trux_moderation_ensure_user_case($userId, $actorUserId);
    if (!$case) {
        return false;
    }

    $caseId = (int)($case['id'] ?? 0);
    if ($caseId <= 0) {
        return false;
    }

    $resolutionValue = $resolutionActionKey !== '' ? $resolutionActionKey : null;

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_user_cases
             SET status = ?, priority = ?, resolution_action_key = ?, resolution_notes = ?,
                 closed_at = CASE WHEN ? = ? THEN COALESCE(closed_at, CURRENT_TIMESTAMP) ELSE NULL END,
                 closed_by_staff_user_id = CASE WHEN ? = ? THEN COALESCE(closed_by_staff_user_id, ?) ELSE NULL END,
                 updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            $status,
            $priority,
            $resolutionValue,
            $resolutionNotes !== '' ? $resolutionNotes : null,
            $status,
            'closed',
            $status,
            'closed',
            $actorUserId,
            $actorUserId,
            $caseId,
        ]);

        trux_moderation_write_audit_log(
            $actorUserId,
            $status === 'closed' ? 'user_case_closed' : 'user_case_updated',
            'user_case',
            $caseId,
            [
                'user_id' => $userId,
                'status' => $status,
                'priority' => $priority,
                'resolution_action_key' => $resolutionValue,
                'resolution_notes_excerpt' => trux_moderation_trimmed_excerpt($resolutionNotes, 160),
            ]
        );
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_fetch_user_case_enforcements(int $caseId, int $limit = 20): array {
    if ($caseId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    trux_moderation_expire_due_enforcements();

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            "SELECT e.*, creator.username AS created_by_staff_username, revoker.username AS revoked_by_staff_username
             FROM moderation_user_enforcements e
             JOIN users creator ON creator.id = e.created_by_staff_user_id
             LEFT JOIN users revoker ON revoker.id = e.revoked_by_staff_user_id
             WHERE e.user_case_id = ?
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $caseId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_user_case_escalations(int $caseId, int $limit = 20): array {
    if ($caseId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            "SELECT e.*, assignee.username AS assigned_staff_username, creator.username AS created_by_staff_username
             FROM moderation_escalations e
             JOIN users creator ON creator.id = e.created_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = e.assigned_staff_user_id
             WHERE e.subject_type = ? AND e.subject_id = ?
             ORDER BY
                CASE e.status WHEN 'open' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END,
                e.updated_at DESC, e.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, 'user_case', PDO::PARAM_STR);
        $stmt->bindValue(2, $caseId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_user_case_appeals(int $caseId, int $limit = 20): array {
    if ($caseId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            "SELECT a.*, e.action_key, e.status AS enforcement_status, e.ends_at,
                    assignee.username AS assigned_staff_username
             FROM moderation_appeals a
             JOIN moderation_user_enforcements e ON e.id = a.enforcement_id
             LEFT JOIN users assignee ON assignee.id = a.assigned_staff_user_id
             WHERE e.user_case_id = ?
             ORDER BY
                CASE a.status WHEN 'open' THEN 0 WHEN 'investigating' THEN 1 ELSE 2 END,
                a.updated_at DESC, a.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $caseId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_generate_appeal_token(): string {
    return bin2hex(random_bytes(16));
}

function trux_moderation_expire_due_enforcements(?int $userId = null): void {
    try {
        $db = trux_db();
        $sql = 'UPDATE moderation_user_enforcements
                SET status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE status = ?
                  AND ends_at IS NOT NULL
                  AND ends_at <= CURRENT_TIMESTAMP';
        $params = ['expired', 'active'];
        if ($userId !== null && $userId > 0) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException) {
        // Keep app flows working when the enforcement table is unavailable.
    }
}

function trux_moderation_fetch_active_user_enforcements(int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    trux_moderation_expire_due_enforcements($userId);

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT *
             FROM moderation_user_enforcements
             WHERE user_id = ?
               AND status = ?
               AND starts_at <= CURRENT_TIMESTAMP
               AND (ends_at IS NULL OR ends_at > CURRENT_TIMESTAMP)
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId, 'active']);
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_moderation_fetch_active_access_block(int $userId): ?array {
    foreach (trux_moderation_fetch_active_user_enforcements($userId) as $enforcement) {
        $actionKey = trim((string)($enforcement['action_key'] ?? ''));
        if (in_array($actionKey, ['account_suspended', 'account_locked'], true)) {
            return $enforcement;
        }
    }

    return null;
}

function trux_moderation_is_user_dm_restricted(int $userId): bool {
    foreach (trux_moderation_fetch_active_user_enforcements($userId) as $enforcement) {
        if ((string)($enforcement['action_key'] ?? '') === 'dm_restricted') {
            return true;
        }
    }

    return false;
}

function trux_moderation_access_block_message(array $enforcement): string {
    $actionKey = trim((string)($enforcement['action_key'] ?? ''));
    $endsAt = trim((string)($enforcement['ends_at'] ?? ''));

    if ($actionKey === 'account_locked') {
        return 'Your account is locked pending moderation review.';
    }

    if ($endsAt !== '') {
        return 'Your account is suspended until ' . $endsAt . '.';
    }

    return 'Your account is suspended pending moderation review.';
}

function trux_moderation_fetch_user_enforcement_by_id(int $enforcementId): ?array {
    if ($enforcementId <= 0) {
        return null;
    }

    trux_moderation_expire_due_enforcements();

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT e.*, creator.username AS created_by_staff_username, revoker.username AS revoked_by_staff_username
             FROM moderation_user_enforcements e
             JOIN users creator ON creator.id = e.created_by_staff_user_id
             LEFT JOIN users revoker ON revoker.id = e.revoked_by_staff_user_id
             WHERE e.id = ?
             LIMIT 1'
        );
        $stmt->execute([$enforcementId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_fetch_user_enforcement_by_token(string $token): ?array {
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    trux_moderation_expire_due_enforcements();

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT *
             FROM moderation_user_enforcements
             WHERE appeal_token = ?
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_public_appeal_url(array $enforcement): ?string {
    $token = trim((string)($enforcement['appeal_token'] ?? ''));
    return $token !== '' ? '/appeal.php?token=' . urlencode($token) : null;
}

function trux_moderation_create_user_enforcement(
    int $userId,
    int $actorUserId,
    string $actionKey,
    ?int $sourceReportId = null,
    ?int $userCaseId = null,
    string $reasonSummary = '',
    string $details = '',
    ?string $endsAt = null
): ?array {
    $actionKey = trim(strtolower($actionKey));
    $reasonSummary = trim($reasonSummary);
    $details = trim($details);
    $endsAt = $endsAt !== null ? trux_moderation_parse_local_datetime_input($endsAt) : null;

    if ($userId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_user_enforcement_action($actionKey)) {
        return null;
    }

    if ($actionKey === 'account_suspended' && $endsAt === null) {
        return null;
    }

    try {
        $db = trux_db();
        $appealToken = trux_moderation_generate_appeal_token();
        $stmt = $db->prepare(
            'INSERT INTO moderation_user_enforcements
                (user_id, source_report_id, user_case_id, action_key, status, appeal_token, reason_summary, details,
                 created_by_staff_user_id, starts_at, ends_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)'
        );
        $stmt->execute([
            $userId,
            $sourceReportId !== null && $sourceReportId > 0 ? $sourceReportId : null,
            $userCaseId !== null && $userCaseId > 0 ? $userCaseId : null,
            $actionKey,
            'active',
            $appealToken,
            $reasonSummary !== '' ? $reasonSummary : null,
            $details !== '' ? $details : null,
            $actorUserId,
            $endsAt,
        ]);

        $enforcementId = (int)$db->lastInsertId();
        trux_moderation_write_audit_log($actorUserId, 'user_enforcement_created', 'user_enforcement', $enforcementId, [
            'user_id' => $userId,
            'action_key' => $actionKey,
            'source_report_id' => $sourceReportId,
            'user_case_id' => $userCaseId,
            'ends_at' => $endsAt,
        ]);
        return trux_moderation_fetch_user_enforcement_by_id($enforcementId);
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_revoke_user_enforcement(int $enforcementId, int $actorUserId, string $reason = ''): bool {
    if ($enforcementId <= 0 || $actorUserId <= 0) {
        return false;
    }

    $enforcement = trux_moderation_fetch_user_enforcement_by_id($enforcementId);
    if (!$enforcement) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_user_enforcements
             SET status = ?, revoked_by_staff_user_id = ?, revoked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute(['revoked', $actorUserId, $enforcementId]);
        trux_moderation_write_audit_log($actorUserId, 'user_enforcement_revoked', 'user_enforcement', $enforcementId, [
            'user_id' => (int)($enforcement['user_id'] ?? 0),
            'action_key' => (string)($enforcement['action_key'] ?? ''),
            'reason' => $reason,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_subject_url(string $subjectType, int $subjectId): ?string {
    $subjectType = trim(strtolower($subjectType));
    if ($subjectId <= 0) {
        return null;
    }

    return match ($subjectType) {
        'report' => '/moderation/reports.php?review=' . $subjectId,
        'user_case' => ($case = trux_moderation_fetch_user_case_by_id($subjectId)) && (int)($case['user_id'] ?? 0) > 0
            ? '/moderation/user_review.php?user_id=' . (int)$case['user_id']
            : '/moderation/user_review.php',
        'suspicious_event' => '/moderation/activity.php?q=' . urlencode((string)$subjectId),
        'appeal' => '/moderation/appeals.php?appeal=' . $subjectId,
        'escalation' => '/moderation/escalations.php?escalation=' . $subjectId,
        default => null,
    };
}

function trux_moderation_fetch_escalation_by_id(int $escalationId): ?array {
    if ($escalationId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT e.*, assignee.username AS assigned_staff_username, creator.username AS created_by_staff_username,
                    resolver.username AS resolved_by_staff_username
             FROM moderation_escalations e
             JOIN users creator ON creator.id = e.created_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = e.assigned_staff_user_id
             LEFT JOIN users resolver ON resolver.id = e.resolved_by_staff_user_id
             WHERE e.id = ?
             LIMIT 1'
        );
        $stmt->execute([$escalationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_fetch_open_escalation(string $subjectType, int $subjectId, string $queueRole = ''): ?array {
    $subjectType = trim(strtolower($subjectType));
    if ($subjectType === '' || $subjectId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $sql = 'SELECT id
                FROM moderation_escalations
                WHERE subject_type = ?
                  AND subject_id = ?
                  AND status <> ?';
        $params = [$subjectType, $subjectId, 'resolved'];
        if ($queueRole !== '' && trux_moderation_is_valid_escalation_queue_role($queueRole)) {
            $sql .= ' AND queue_role = ?';
            $params[] = $queueRole;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $escalationId = (int)$stmt->fetchColumn();
        return $escalationId > 0 ? trux_moderation_fetch_escalation_by_id($escalationId) : null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_create_or_get_escalation(
    string $subjectType,
    int $subjectId,
    int $actorUserId,
    string $summary,
    string $queueRole = 'admin',
    string $priority = 'high'
): ?array {
    $subjectType = trim(strtolower($subjectType));
    $summary = trim($summary);
    $queueRole = trux_moderation_is_valid_escalation_queue_role($queueRole) ? $queueRole : 'admin';
    $priority = trux_moderation_is_valid_report_priority($priority) ? $priority : 'high';
    if ($subjectType === '' || $subjectId <= 0 || $actorUserId <= 0 || $summary === '') {
        return null;
    }

    $existing = trux_moderation_fetch_open_escalation($subjectType, $subjectId, $queueRole);
    if ($existing) {
        return $existing;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_escalations
                (subject_type, subject_id, queue_role, status, priority, summary, created_by_staff_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$subjectType, $subjectId, $queueRole, 'open', $priority, mb_substr($summary, 0, 280), $actorUserId]);
        $escalationId = (int)$db->lastInsertId();

        if ($subjectType === 'user_case') {
            $case = trux_moderation_fetch_user_case_by_id($subjectId);
            if ($case) {
                $update = $db->prepare(
                    'UPDATE moderation_user_cases
                     SET status = ?, current_escalation_id = ?, updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $update->execute(['escalated', $escalationId, $actorUserId, $subjectId]);
            }
        }

        trux_moderation_write_audit_log($actorUserId, 'escalation_created', 'escalation', $escalationId, [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'queue_role' => $queueRole,
            'priority' => $priority,
        ]);
        return trux_moderation_fetch_escalation_by_id($escalationId);
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_fetch_escalations(array $filters, int $page = 1, int $perPage = 25): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;
    $where = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all' && trux_moderation_is_valid_escalation_status($status)) {
        $where[] = 'e.status = ?';
        $params[] = $status;
    }

    $queueRole = trim((string)($filters['queue_role'] ?? ''));
    if ($queueRole !== '' && $queueRole !== 'all' && trux_moderation_is_valid_escalation_queue_role($queueRole)) {
        $where[] = 'e.queue_role = ?';
        $params[] = $queueRole;
    }

    $assignee = trim((string)($filters['assignee'] ?? ''));
    if ($assignee === 'unassigned') {
        $where[] = 'e.assigned_staff_user_id IS NULL';
    } elseif (preg_match('/^\d+$/', $assignee)) {
        $where[] = 'e.assigned_staff_user_id = ?';
        $params[] = (int)$assignee;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM moderation_escalations e $whereSql");
        foreach ($params as $index => $value) {
            $countStmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT e.*, assignee.username AS assigned_staff_username, creator.username AS created_by_staff_username
             FROM moderation_escalations e
             JOIN users creator ON creator.id = e.created_by_staff_user_id
             LEFT JOIN users assignee ON assignee.id = e.assigned_staff_user_id
             $whereSql
             ORDER BY
                CASE e.status WHEN 'open' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END,
                CASE e.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,
                e.updated_at DESC, e.id DESC
             LIMIT ? OFFSET ?"
        );
        $bindIndex = 1;
        foreach ($params as $value) {
            $dataStmt->bindValue($bindIndex++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    } catch (PDOException) {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
        ];
    }
}

function trux_moderation_assign_escalation(int $escalationId, int $actorUserId, ?int $assignedStaffUserId): bool {
    $escalation = trux_moderation_fetch_escalation_by_id($escalationId);
    if (!$escalation || $actorUserId <= 0) {
        return false;
    }

    $nextAssigneeId = $assignedStaffUserId !== null && $assignedStaffUserId > 0 ? $assignedStaffUserId : null;
    if ($nextAssigneeId !== null && !trux_has_staff_role(trux_fetch_user_staff_role($nextAssigneeId), 'admin')) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_escalations
             SET assigned_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$nextAssigneeId, $escalationId]);
        trux_moderation_write_audit_log($actorUserId, 'escalation_assigned', 'escalation', $escalationId, [
            'to_assignee_user_id' => $nextAssigneeId,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_update_escalation_status(int $escalationId, int $actorUserId, string $status, string $resolutionNotes = ''): bool {
    $status = trim(strtolower($status));
    $resolutionNotes = trim($resolutionNotes);
    if ($escalationId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_escalation_status($status)) {
        return false;
    }

    $escalation = trux_moderation_fetch_escalation_by_id($escalationId);
    if (!$escalation) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_escalations
             SET status = ?, resolution_notes = ?, resolved_by_staff_user_id = ?, resolved_at = CASE WHEN ? = ? THEN CURRENT_TIMESTAMP ELSE NULL END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            $status,
            $resolutionNotes !== '' ? $resolutionNotes : null,
            $status === 'resolved' ? $actorUserId : null,
            $status,
            'resolved',
            $escalationId,
        ]);

        if ((string)($escalation['subject_type'] ?? '') === 'user_case' && (int)($escalation['subject_id'] ?? 0) > 0 && $status === 'resolved') {
            $updateCase = $db->prepare(
                'UPDATE moderation_user_cases
                 SET current_escalation_id = CASE WHEN current_escalation_id = ? THEN NULL ELSE current_escalation_id END,
                     status = CASE WHEN status = ? THEN ? ELSE status END,
                     updated_by_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $updateCase->execute([$escalationId, 'escalated', 'investigating', $actorUserId, (int)$escalation['subject_id']]);
        }

        trux_moderation_write_audit_log($actorUserId, 'escalation_status_updated', 'escalation', $escalationId, [
            'status' => $status,
            'resolution_notes_excerpt' => trux_moderation_trimmed_excerpt($resolutionNotes, 160),
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_fetch_appeal_by_id(int $appealId): ?array {
    if ($appealId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT a.*, e.user_id, e.user_case_id, e.action_key, e.status AS enforcement_status, e.ends_at, e.appeal_token,
                    assignee.username AS assigned_staff_username
             FROM moderation_appeals a
             JOIN moderation_user_enforcements e ON e.id = a.enforcement_id
             LEFT JOIN users assignee ON assignee.id = a.assigned_staff_user_id
             WHERE a.id = ?
             LIMIT 1'
        );
        $stmt->execute([$appealId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_fetch_appeal_by_enforcement_id(int $enforcementId): ?array {
    if ($enforcementId <= 0) {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('SELECT id FROM moderation_appeals WHERE enforcement_id = ? LIMIT 1');
        $stmt->execute([$enforcementId]);
        $appealId = (int)$stmt->fetchColumn();
        return $appealId > 0 ? trux_moderation_fetch_appeal_by_id($appealId) : null;
    } catch (PDOException) {
        return null;
    }
}

function trux_moderation_submit_appeal(string $token, string $reason, ?int $actorUserId = null): array {
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason) > 4000) {
        return ['ok' => false, 'error' => 'Appeal reason must be between 1 and 4000 characters.'];
    }

    $enforcement = trux_moderation_fetch_user_enforcement_by_token($token);
    if (!$enforcement) {
        return ['ok' => false, 'error' => 'Appeal link is invalid or expired.'];
    }
    if (!in_array((string)($enforcement['action_key'] ?? ''), ['warning_issued', 'dm_restricted', 'account_suspended', 'account_locked'], true)) {
        return ['ok' => false, 'error' => 'This moderation action cannot be appealed.'];
    }
    if ((string)($enforcement['status'] ?? '') !== 'active') {
        return ['ok' => false, 'error' => 'This moderation action is no longer active.'];
    }
    if (trux_moderation_fetch_appeal_by_enforcement_id((int)$enforcement['id'])) {
        return ['ok' => false, 'error' => 'An appeal has already been submitted for this action.'];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO moderation_appeals
                (enforcement_id, status, submitter_reason, created_by_staff_user_id)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$enforcement['id'],
            'open',
            $reason,
            $actorUserId !== null && $actorUserId > 0 && $actorUserId === (int)$enforcement['user_id'] ? $actorUserId : null,
        ]);
        $appealId = (int)$db->lastInsertId();
        $auditActorId = $actorUserId !== null && $actorUserId > 0 ? $actorUserId : trux_report_system_user_id();
        if ($auditActorId > 0) {
            trux_moderation_write_audit_log($auditActorId, 'appeal_submitted', 'appeal', $appealId, [
                'enforcement_id' => (int)$enforcement['id'],
                'action_key' => (string)($enforcement['action_key'] ?? ''),
            ]);
        }
        return ['ok' => true, 'appeal_id' => $appealId];
    } catch (PDOException) {
        return ['ok' => false, 'error' => 'Could not submit the appeal right now.'];
    }
}

function trux_moderation_fetch_appeals(array $filters, int $page = 1, int $perPage = 25): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;
    $where = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '' && $status !== 'all' && trux_moderation_is_valid_appeal_status($status)) {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }

    $assignee = trim((string)($filters['assignee'] ?? ''));
    if ($assignee === 'unassigned') {
        $where[] = 'a.assigned_staff_user_id IS NULL';
    } elseif (preg_match('/^\d+$/', $assignee)) {
        $where[] = 'a.assigned_staff_user_id = ?';
        $params[] = (int)$assignee;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $db = trux_db();
        $countStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM moderation_appeals a
             JOIN moderation_user_enforcements e ON e.id = a.enforcement_id
             $whereSql"
        );
        foreach ($params as $index => $value) {
            $countStmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT a.*, e.user_id, e.user_case_id, e.action_key, e.status AS enforcement_status, e.ends_at,
                    assignee.username AS assigned_staff_username
             FROM moderation_appeals a
             JOIN moderation_user_enforcements e ON e.id = a.enforcement_id
             LEFT JOIN users assignee ON assignee.id = a.assigned_staff_user_id
             $whereSql
             ORDER BY
                CASE a.status WHEN 'open' THEN 0 WHEN 'investigating' THEN 1 ELSE 2 END,
                a.updated_at DESC, a.id DESC
             LIMIT ? OFFSET ?"
        );
        $bindIndex = 1;
        foreach ($params as $value) {
            $dataStmt->bindValue($bindIndex++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    } catch (PDOException) {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 1,
        ];
    }
}

function trux_moderation_assign_appeal(int $appealId, int $actorUserId, ?int $assignedStaffUserId): bool {
    $appeal = trux_moderation_fetch_appeal_by_id($appealId);
    if (!$appeal || $actorUserId <= 0) {
        return false;
    }

    $nextAssigneeId = $assignedStaffUserId !== null && $assignedStaffUserId > 0 ? $assignedStaffUserId : null;
    if ($nextAssigneeId !== null && !trux_has_staff_role(trux_fetch_user_staff_role($nextAssigneeId), 'admin')) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE moderation_appeals
             SET assigned_staff_user_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$nextAssigneeId, $appealId]);
        trux_moderation_write_audit_log($actorUserId, 'appeal_assigned', 'appeal', $appealId, [
            'to_assignee_user_id' => $nextAssigneeId,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_moderation_update_appeal_status(int $appealId, int $actorUserId, string $status, string $resolutionNotes = ''): bool {
    $status = trim(strtolower($status));
    $resolutionNotes = trim($resolutionNotes);
    if ($appealId <= 0 || $actorUserId <= 0 || !trux_moderation_is_valid_appeal_status($status)) {
        return false;
    }

    $appeal = trux_moderation_fetch_appeal_by_id($appealId);
    if (!$appeal) {
        return false;
    }

    try {
        $db = trux_db();
        $db->beginTransaction();

        $stmt = $db->prepare(
            'UPDATE moderation_appeals
             SET status = ?, resolution_notes = ?, resolved_by_staff_user_id = ?, resolved_at = CASE WHEN ? IN (?, ?) THEN CURRENT_TIMESTAMP ELSE NULL END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([
            $status,
            $resolutionNotes !== '' ? $resolutionNotes : null,
            in_array($status, ['upheld', 'denied'], true) ? $actorUserId : null,
            $status,
            'upheld',
            'denied',
            $appealId,
        ]);

        if ($status === 'upheld') {
            trux_moderation_revoke_user_enforcement((int)$appeal['enforcement_id'], $actorUserId, 'Appeal upheld.');
            if ((int)($appeal['user_case_id'] ?? 0) > 0) {
                $case = trux_moderation_fetch_user_case_by_id((int)$appeal['user_case_id']);
                if ($case) {
                    trux_moderation_reopen_user_case_if_closed((int)$case['user_id'], $actorUserId, 'Appeal upheld.');
                }
            }
        }

        $db->commit();
        trux_moderation_write_audit_log($actorUserId, 'appeal_status_updated', 'appeal', $appealId, [
            'status' => $status,
            'resolution_notes_excerpt' => trux_moderation_trimmed_excerpt($resolutionNotes, 160),
        ]);
        return true;
    } catch (PDOException) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}
