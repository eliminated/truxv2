<?php
declare(strict_types=1);

function trux_has_blocked_user(int $userId, int $blockedUserId): bool {
    if ($userId <= 0 || $blockedUserId <= 0 || $userId === $blockedUserId) {
        return false;
    }

    try {
        $stmt = trux_db()->prepare(
            'SELECT 1 FROM blocked_users
             WHERE user_id = ? AND blocked_user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $blockedUserId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException) {
        return false;
    }
}

function trux_block_exists_between(int $a, int $b): bool {
    if ($a <= 0 || $b <= 0 || $a === $b) {
        return false;
    }

    try {
        $stmt = trux_db()->prepare(
            'SELECT 1 FROM blocked_users
             WHERE (user_id = ? AND blocked_user_id = ?)
                OR (user_id = ? AND blocked_user_id = ?)
             LIMIT 1'
        );
        $stmt->execute([$a, $b, $b, $a]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException) {
        return false;
    }
}

function trux_fetch_blocked_user_id_map(int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    try {
        $stmt = trux_db()->prepare(
            'SELECT blocked_user_id FROM blocked_users WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int)$row['blocked_user_id']] = true;
        }
        return $map;
    } catch (PDOException) {
        return [];
    }
}

function trux_block_user(int $userId, int $blockedUserId): void {
    if ($userId <= 0 || $blockedUserId <= 0 || $userId === $blockedUserId) {
        return;
    }

    try {
        $db = trux_db();

        $db->prepare(
            'INSERT INTO blocked_users (user_id, blocked_user_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE created_at = created_at'
        )->execute([$userId, $blockedUserId]);

        $db->prepare(
            'DELETE FROM follows
             WHERE (follower_id = ? AND following_id = ?)
                OR (follower_id = ? AND following_id = ?)'
        )->execute([$userId, $blockedUserId, $blockedUserId, $userId]);

        trux_remove_notifications_from_actor($userId, $blockedUserId);
        trux_mute_user($userId, $blockedUserId);
    } catch (PDOException) {
        // Ignore when migration is unavailable.
    }
}

function trux_unblock_user(int $userId, int $blockedUserId): void {
    if ($userId <= 0 || $blockedUserId <= 0) {
        return;
    }

    try {
        trux_db()->prepare(
            'DELETE FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?'
        )->execute([$userId, $blockedUserId]);

        trux_unmute_user($userId, $blockedUserId);
    } catch (PDOException) {
        // Ignore when migration is unavailable.
    }
}

function trux_fetch_blocked_users(int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    try {
        $stmt = trux_db()->prepare(
            'SELECT u.id, u.username, b.created_at
             FROM blocked_users b
             JOIN users u ON u.id = b.blocked_user_id
             WHERE b.user_id = ?
             ORDER BY u.username ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}