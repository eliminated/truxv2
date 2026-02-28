<?php
declare(strict_types=1);

function trux_is_following(int $viewerId, int $profileUserId): bool {
    if ($viewerId <= 0 || $profileUserId <= 0 || $viewerId === $profileUserId) {
        return false;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT 1
         FROM follows
         WHERE follower_id = ? AND following_id = ?
         LIMIT 1'
    );
    $stmt->execute([$viewerId, $profileUserId]);
    return (bool)$stmt->fetchColumn();
}

function trux_follow(int $viewerId, int $profileUserId): void {
    if ($viewerId <= 0 || $profileUserId <= 0 || $viewerId === $profileUserId) {
        return;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'INSERT INTO follows (follower_id, following_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE created_at = created_at'
    );
    $stmt->execute([$viewerId, $profileUserId]);
}

function trux_unfollow(int $viewerId, int $profileUserId): void {
    if ($viewerId <= 0 || $profileUserId <= 0 || $viewerId === $profileUserId) {
        return;
    }

    $db = trux_db();
    $stmt = $db->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ?');
    $stmt->execute([$viewerId, $profileUserId]);
}

function trux_follow_counts(int $profileUserId): array {
    if ($profileUserId <= 0) {
        return ['followers' => 0, 'following' => 0];
    }

    $db = trux_db();

    $followersStmt = $db->prepare('SELECT COUNT(*) FROM follows WHERE following_id = ?');
    $followersStmt->execute([$profileUserId]);
    $followers = (int)$followersStmt->fetchColumn();

    $followingStmt = $db->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
    $followingStmt->execute([$profileUserId]);
    $following = (int)$followingStmt->fetchColumn();

    return [
        'followers' => $followers,
        'following' => $following,
    ];
}
