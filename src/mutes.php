<?php
declare(strict_types=1);

function trux_has_muted_user(int $userId, int $mutedUserId): bool {
    if ($userId <= 0 || $mutedUserId <= 0 || $userId === $mutedUserId) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT 1
             FROM muted_users
             WHERE user_id = ? AND muted_user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $mutedUserId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException) {
        return false;
    }
}

function trux_mute_user(int $userId, int $mutedUserId): void {
    if ($userId <= 0 || $mutedUserId <= 0 || $userId === $mutedUserId) {
        return;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO muted_users (user_id, muted_user_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );
        $stmt->execute([$userId, $mutedUserId]);
    } catch (PDOException) {
        // Ignore when the migration is unavailable.
    }
}

function trux_unmute_user(int $userId, int $mutedUserId): void {
    if ($userId <= 0 || $mutedUserId <= 0 || $userId === $mutedUserId) {
        return;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('DELETE FROM muted_users WHERE user_id = ? AND muted_user_id = ?');
        $stmt->execute([$userId, $mutedUserId]);
    } catch (PDOException) {
        // Ignore when the migration is unavailable.
    }
}

function trux_fetch_muted_users(int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT u.id, u.username, m.created_at
             FROM muted_users m
             JOIN users u ON u.id = m.muted_user_id
             WHERE m.user_id = ?
             ORDER BY u.username ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}
