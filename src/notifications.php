<?php
declare(strict_types=1);

function trux_notification_defaults(): array {
    return [
        'notify_post_likes' => true,
        'notify_comment_votes' => true,
        'notify_mentions' => true,
        'notify_follows' => true,
        'notify_post_comments' => true,
        'notify_replies' => true,
    ];
}

function trux_notification_pref_labels(): array {
    return [
        'notify_post_likes' => [
            'title' => 'Post likes',
            'description' => 'When someone likes one of your posts.',
        ],
        'notify_comment_votes' => [
            'title' => 'Comment and reply votes',
            'description' => 'When someone upvotes one of your comments or replies.',
        ],
        'notify_mentions' => [
            'title' => 'Mentions',
            'description' => 'When someone mentions you in a post, comment, or reply.',
        ],
        'notify_follows' => [
            'title' => 'Follows',
            'description' => 'When someone starts following you.',
        ],
        'notify_post_comments' => [
            'title' => 'New comments on your posts',
            'description' => 'When someone comments on one of your posts.',
        ],
        'notify_replies' => [
            'title' => 'Replies to your comments',
            'description' => 'When someone replies to one of your comments.',
        ],
    ];
}

function trux_notification_pref_for_type(string $type): ?string {
    return match ($type) {
        'post_like' => 'notify_post_likes',
        'comment_vote' => 'notify_comment_votes',
        'mention_post', 'mention_comment' => 'notify_mentions',
        'follow' => 'notify_follows',
        'post_comment' => 'notify_post_comments',
        'comment_reply' => 'notify_replies',
        default => null,
    };
}

function trux_fetch_notification_preferences(int $userId): array {
    static $cache = [];
    $defaults = trux_notification_defaults();

    if ($userId <= 0) {
        return $defaults;
    }

    if (isset($cache[$userId]) && is_array($cache[$userId])) {
        return $cache[$userId];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT notify_post_likes, notify_comment_votes, notify_mentions, notify_follows, notify_post_comments, notify_replies
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $cache[$userId] = $defaults;
            return $defaults;
        }

        $prefs = $defaults;
        foreach (array_keys($defaults) as $key) {
            $prefs[$key] = !empty($row[$key]);
        }
        $cache[$userId] = $prefs;
        return $prefs;
    } catch (PDOException) {
        $cache[$userId] = $defaults;
        return $defaults;
    }
}

function trux_update_notification_preferences(int $userId, array $submitted): bool {
    if ($userId <= 0) {
        return false;
    }

    $defaults = trux_notification_defaults();
    $values = [];
    foreach (array_keys($defaults) as $key) {
        $values[$key] = !empty($submitted[$key]) ? 1 : 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE users
             SET notify_post_likes = ?,
                 notify_comment_votes = ?,
                 notify_mentions = ?,
                 notify_follows = ?,
                 notify_post_comments = ?,
                 notify_replies = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $values['notify_post_likes'],
            $values['notify_comment_votes'],
            $values['notify_mentions'],
            $values['notify_follows'],
            $values['notify_post_comments'],
            $values['notify_replies'],
            $userId,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_fetch_users_by_lower_usernames(array $usernames): array {
    $names = [];
    foreach ($usernames as $username) {
        if (!is_string($username)) {
            continue;
        }
        $normalized = strtolower(trim($username));
        if ($normalized === '' || !preg_match('/^[a-z0-9_]{3,32}$/', $normalized)) {
            continue;
        }
        $names[] = $normalized;
    }

    $names = array_values(array_unique($names));
    if (!$names) {
        return [];
    }

    try {
        $db = trux_db();
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $db->prepare(
            "SELECT id, username
             FROM users
             WHERE LOWER(username) IN ($placeholders)"
        );
        foreach ($names as $index => $name) {
            $stmt->bindValue($index + 1, $name, PDO::PARAM_STR);
        }
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = strtolower((string)($row['username'] ?? ''));
            if ($key !== '') {
                $out[$key] = $row;
            }
        }
        return $out;
    } catch (PDOException) {
        return [];
    }
}

function trux_notification_event_key(string $type, int $actorUserId, ?int $postId = null, ?int $commentId = null): string {
    return implode(':', [
        $type,
        $actorUserId,
        $postId !== null && $postId > 0 ? $postId : 0,
        $commentId !== null && $commentId > 0 ? $commentId : 0,
    ]);
}

function trux_create_notification(int $recipientUserId, int $actorUserId, string $type, ?int $postId = null, ?int $commentId = null): void {
    if ($recipientUserId <= 0 || $actorUserId <= 0 || $recipientUserId === $actorUserId || $type === '') {
        return;
    }

    if (trux_has_muted_user($recipientUserId, $actorUserId)) {
        return;
    }

    $prefKey = trux_notification_pref_for_type($type);
    if ($prefKey !== null) {
        $prefs = trux_fetch_notification_preferences($recipientUserId);
        if (empty($prefs[$prefKey])) {
            return;
        }
    }

    try {
        $db = trux_db();
        $eventKey = trux_notification_event_key($type, $actorUserId, $postId, $commentId);
        $stmt = $db->prepare(
            'INSERT INTO notifications (recipient_user_id, actor_user_id, type, event_key, post_id, comment_id)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );
        $stmt->execute([
            $recipientUserId,
            $actorUserId,
            $type,
            $eventKey,
            $postId !== null && $postId > 0 ? $postId : null,
            $commentId !== null && $commentId > 0 ? $commentId : null,
        ]);
    } catch (PDOException) {
        // Notifications should not break primary actions if the migration is missing.
    }
}

function trux_remove_notifications_from_actor(int $recipientUserId, int $actorUserId): void {
    if ($recipientUserId <= 0 || $actorUserId <= 0) {
        return;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'DELETE FROM notifications
             WHERE recipient_user_id = ?
               AND actor_user_id = ?'
        );
        $stmt->execute([$recipientUserId, $actorUserId]);
    } catch (PDOException) {
        // Ignore when the table is unavailable.
    }
}

function trux_delete_notification(int $recipientUserId, int $actorUserId, string $type, ?int $postId = null, ?int $commentId = null): void {
    if ($recipientUserId <= 0 || $actorUserId <= 0 || $type === '') {
        return;
    }

    try {
        $db = trux_db();
        $eventKey = trux_notification_event_key($type, $actorUserId, $postId, $commentId);
        $stmt = $db->prepare(
            'DELETE FROM notifications
             WHERE recipient_user_id = ?
               AND event_key = ?'
        );
        $stmt->execute([
            $recipientUserId,
            $eventKey,
        ]);
    } catch (PDOException) {
        // Ignore when the table is unavailable.
    }
}

function trux_notify_post_like(int $recipientUserId, int $actorUserId, int $postId): void {
    trux_create_notification($recipientUserId, $actorUserId, 'post_like', $postId, null);
}

function trux_remove_post_like_notification(int $recipientUserId, int $actorUserId, int $postId): void {
    trux_delete_notification($recipientUserId, $actorUserId, 'post_like', $postId, null);
}

function trux_notify_comment_vote(int $recipientUserId, int $actorUserId, int $postId, int $commentId): void {
    trux_create_notification($recipientUserId, $actorUserId, 'comment_vote', $postId, $commentId);
}

function trux_remove_comment_vote_notification(int $recipientUserId, int $actorUserId, int $postId, int $commentId): void {
    trux_delete_notification($recipientUserId, $actorUserId, 'comment_vote', $postId, $commentId);
}

function trux_notify_follow(int $recipientUserId, int $actorUserId): void {
    trux_create_notification($recipientUserId, $actorUserId, 'follow', null, null);
}

function trux_remove_follow_notification(int $recipientUserId, int $actorUserId): void {
    trux_delete_notification($recipientUserId, $actorUserId, 'follow', null, null);
}

function trux_notify_post_comment(int $recipientUserId, int $actorUserId, int $postId, int $commentId): void {
    trux_create_notification($recipientUserId, $actorUserId, 'post_comment', $postId, $commentId);
}

function trux_notify_comment_reply(int $recipientUserId, int $actorUserId, int $postId, int $commentId): void {
    trux_create_notification($recipientUserId, $actorUserId, 'comment_reply', $postId, $commentId);
}

function trux_notify_mentions_for_post(int $postId, int $actorUserId, string $body): void {
    if ($postId <= 0 || $actorUserId <= 0 || $body === '') {
        return;
    }

    $users = trux_fetch_users_by_lower_usernames(trux_extract_mentions($body));
    foreach ($users as $row) {
        $recipientId = (int)($row['id'] ?? 0);
        trux_create_notification($recipientId, $actorUserId, 'mention_post', $postId, null);
    }
}

function trux_notify_mentions_for_comment(int $postId, int $commentId, int $actorUserId, string $body): void {
    if ($postId <= 0 || $commentId <= 0 || $actorUserId <= 0 || $body === '') {
        return;
    }

    $users = trux_fetch_users_by_lower_usernames(trux_extract_mentions($body));
    foreach ($users as $row) {
        $recipientId = (int)($row['id'] ?? 0);
        trux_create_notification($recipientId, $actorUserId, 'mention_comment', $postId, $commentId);
    }
}

function trux_count_unread_notifications(int $userId): int {
    if ($userId <= 0) {
        return 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT actor_user_id
             FROM notifications
             WHERE recipient_user_id = ?
               AND read_at IS NULL'
        );
        $stmt->execute([$userId]);
        $mutedUserIds = trux_fetch_muted_user_id_map($userId);
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            $actorUserId = (int)($row['actor_user_id'] ?? 0);
            if ($actorUserId <= 0 || !isset($mutedUserIds[$actorUserId])) {
                $count++;
            }
        }
        return $count;
    } catch (PDOException) {
        return 0;
    }
}

function trux_fetch_notifications(int $userId, int $limit = 50): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    try {
        $db = trux_db();
        $queryLimit = min(300, max($limit * 3, $limit));
        $stmt = $db->prepare(
            'SELECT n.id, n.type, n.post_id, n.comment_id, n.read_at, n.created_at, n.actor_user_id, actor.username AS actor_username
             FROM notifications n
             JOIN users actor ON actor.id = n.actor_user_id
             WHERE n.recipient_user_id = ?
             ORDER BY n.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $queryLimit, PDO::PARAM_INT);
        $stmt->execute();
        $mutedUserIds = trux_fetch_muted_user_id_map($userId);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $actorId = (int)($row['actor_user_id'] ?? 0);
            if ($actorId > 0 && isset($mutedUserIds[$actorId])) {
                continue;
            }

            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    } catch (PDOException) {
        return [];
    }
}

function trux_mark_all_notifications_read(int $userId): void {
    if ($userId <= 0) {
        return;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE notifications
             SET read_at = CURRENT_TIMESTAMP
             WHERE recipient_user_id = ?
               AND read_at IS NULL'
        );
        $stmt->execute([$userId]);
    } catch (PDOException) {
        // Ignore when the table is unavailable.
    }
}

function trux_notification_url(array $notification): string {
    $type = (string)($notification['type'] ?? '');
    $actorUsername = (string)($notification['actor_username'] ?? '');
    $postId = isset($notification['post_id']) && $notification['post_id'] !== null ? (int)$notification['post_id'] : 0;

    return match ($type) {
        'follow' => $actorUsername !== '' ? TRUX_BASE_URL . '/profile.php?u=' . urlencode($actorUsername) : TRUX_BASE_URL . '/notifications.php',
        default => $postId > 0 ? TRUX_BASE_URL . '/post.php?id=' . $postId : TRUX_BASE_URL . '/notifications.php',
    };
}

function trux_notification_text(array $notification): string {
    $actor = (string)($notification['actor_username'] ?? '');
    if ($actor === '') {
        $actor = 'Someone';
    } else {
        $actor = '@' . $actor;
    }

    return match ((string)($notification['type'] ?? '')) {
        'post_like' => $actor . ' liked your post.',
        'comment_vote' => $actor . ' upvoted your comment or reply.',
        'mention_post' => $actor . ' mentioned you in a post.',
        'mention_comment' => $actor . ' mentioned you in a comment or reply.',
        'follow' => $actor . ' started following you.',
        'post_comment' => $actor . ' commented on your post.',
        'comment_reply' => $actor . ' replied to your comment.',
        default => $actor . ' sent you a notification.',
    };
}
