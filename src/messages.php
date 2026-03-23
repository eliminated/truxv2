<?php
declare(strict_types=1);

function trux_fetch_user_by_id(int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT id, username, email, display_name, bio, location, website_url, avatar_path, banner_path, created_at, staff_role
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function trux_direct_message_pair(int $userA, int $userB): ?array {
    if ($userA <= 0 || $userB <= 0 || $userA === $userB) {
        return null;
    }

    return $userA < $userB
        ? [$userA, $userB]
        : [$userB, $userA];
}

function trux_fetch_direct_conversation_between(int $viewerId, int $otherUserId): ?array {
    $pair = trux_direct_message_pair($viewerId, $otherUserId);
    if (!$pair) {
        return null;
    }

    [$userOneId, $userTwoId] = $pair;
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT c.id, c.user_one_id, c.user_two_id, c.created_at, c.updated_at,
                u.id AS other_user_id, u.username AS other_username, u.display_name AS other_display_name
         FROM direct_conversations c
         JOIN users u ON u.id = ?
         WHERE c.user_one_id = ? AND c.user_two_id = ?
         LIMIT 1'
    );
    $stmt->execute([$otherUserId, $userOneId, $userTwoId]);
    $conversation = $stmt->fetch();

    return $conversation ?: null;
}

function trux_fetch_direct_conversation_for_user(int $conversationId, int $viewerId): ?array {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return null;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT c.id, c.user_one_id, c.user_two_id, c.created_at, c.updated_at,
                u.id AS other_user_id, u.username AS other_username, u.display_name AS other_display_name
         FROM direct_conversations c
         JOIN users u ON u.id = CASE
            WHEN c.user_one_id = :viewer_id THEN c.user_two_id
            ELSE c.user_one_id
         END
         WHERE c.id = :conversation_id
           AND (c.user_one_id = :viewer_one OR c.user_two_id = :viewer_two)
         LIMIT 1'
    );
    $stmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(':viewer_one', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':viewer_two', $viewerId, PDO::PARAM_INT);
    $stmt->execute();
    $conversation = $stmt->fetch();

    return $conversation ?: null;
}

function trux_get_or_create_direct_conversation(int $viewerId, int $otherUserId): int {
    $pair = trux_direct_message_pair($viewerId, $otherUserId);
    if (!$pair) {
        return 0;
    }

    [$userOneId, $userTwoId] = $pair;
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'INSERT INTO direct_conversations (user_one_id, user_two_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([$userOneId, $userTwoId]);

        return (int)$db->lastInsertId();
    } catch (PDOException) {
        return 0;
    }
}

function trux_send_direct_message(int $senderUserId, int $recipientUserId, string $body): int {
    $body = trim($body);
    if ($senderUserId <= 0 || $recipientUserId <= 0 || $senderUserId === $recipientUserId || $body === '' || mb_strlen($body) > 2000) {
        return 0;
    }

    $conversationId = trux_get_or_create_direct_conversation($senderUserId, $recipientUserId);
    if ($conversationId <= 0) {
        return 0;
    }

    $db = trux_db();

    try {
        $db->beginTransaction();

        $insert = $db->prepare(
            'INSERT INTO direct_messages (conversation_id, sender_user_id, body)
             VALUES (?, ?, ?)'
        );
        $insert->execute([$conversationId, $senderUserId, $body]);

        $update = $db->prepare(
            'UPDATE direct_conversations
             SET updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $update->execute([$conversationId]);

        $db->commit();

        return $conversationId;
    } catch (PDOException) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return 0;
    }
}

function trux_fetch_direct_messages(int $conversationId, int $viewerId, int $limit = 100): array {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return [];
    }

    if (!trux_fetch_direct_conversation_for_user($conversationId, $viewerId)) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.created_at, m.read_at, u.username AS sender_username, u.display_name AS sender_display_name
         FROM direct_messages m
         JOIN users u ON u.id = m.sender_user_id
         WHERE m.conversation_id = ?
         ORDER BY m.id ASC
         LIMIT ?'
    );
    $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function trux_mark_direct_conversation_read(int $conversationId, int $viewerId): void {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return;
    }

    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'UPDATE direct_messages
             SET read_at = CURRENT_TIMESTAMP
             WHERE conversation_id = ?
               AND sender_user_id <> ?
               AND read_at IS NULL'
        );
        $stmt->execute([$conversationId, $viewerId]);
    } catch (PDOException) {
        // Ignore missing migrations and keep the rest of the app functional.
    }
}

function trux_count_unread_direct_messages(int $viewerId): int {
    if ($viewerId <= 0) {
        return 0;
    }

    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM direct_messages m
             JOIN direct_conversations c ON c.id = m.conversation_id
             WHERE m.sender_user_id <> ?
               AND m.read_at IS NULL
               AND (c.user_one_id = ? OR c.user_two_id = ?)'
        );
        $stmt->execute([$viewerId, $viewerId, $viewerId]);

        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_fetch_direct_conversations(int $viewerId, int $limit = 50): array {
    if ($viewerId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT c.id, c.user_one_id, c.user_two_id, c.created_at, c.updated_at,
                    u.id AS other_user_id, u.username AS other_username, u.display_name AS other_display_name,
                    (
                        SELECT dm.body
                        FROM direct_messages dm
                        WHERE dm.conversation_id = c.id
                        ORDER BY dm.id DESC
                        LIMIT 1
                    ) AS last_message_body,
                    (
                        SELECT dm.created_at
                        FROM direct_messages dm
                        WHERE dm.conversation_id = c.id
                        ORDER BY dm.id DESC
                        LIMIT 1
                    ) AS last_message_created_at,
                    (
                        SELECT COUNT(*)
                        FROM direct_messages dm
                        WHERE dm.conversation_id = c.id
                          AND dm.sender_user_id <> :viewer_unread
                          AND dm.read_at IS NULL
                    ) AS unread_count
             FROM direct_conversations c
             JOIN users u ON u.id = CASE
                 WHEN c.user_one_id = :viewer_other THEN c.user_two_id
                 ELSE c.user_one_id
             END
             WHERE c.user_one_id = :viewer_one OR c.user_two_id = :viewer_two
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':viewer_unread', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_other', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_one', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_two', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_direct_message_actor_label(?string $username, ?string $displayName = null): string {
    $name = trim((string)$username);
    $display = trim((string)$displayName);

    if ($name === '') {
        return $display !== '' ? $display : 'Unknown';
    }

    if (trux_is_report_system_user($name)) {
        return $display !== '' ? $display : trux_report_system_display_name();
    }

    return '@' . $name;
}

function trux_direct_message_preview(?string $body, int $limit = 90): string {
    $body = trim((string)$body);
    if ($body === '') {
        return 'No messages yet.';
    }

    if (mb_strlen($body) <= $limit) {
        return $body;
    }

    return mb_substr($body, 0, max(1, $limit - 3)) . '...';
}
