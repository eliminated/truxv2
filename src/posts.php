<?php
declare(strict_types=1);

function trux_create_post(int $userId, string $body, ?string $imagePath): int {
    $body = trim($body);

    $db = trux_db();
    $stmt = $db->prepare('INSERT INTO posts (user_id, body, image_path) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $body, $imagePath]);
    return (int)$db->lastInsertId();
}

function trux_fetch_feed(int $limit = 20, ?int $beforeId = null): array {
    $limit = max(1, min(50, $limit));
    $db = trux_db();

    if ($beforeId !== null && $beforeId > 0) {
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, u.username
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id < ?
             ORDER BY p.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $stmt = $db->prepare(
        'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, u.username
         FROM posts p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function trux_fetch_post_by_id(int $postId): ?array {
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, u.username
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.id = ?
         LIMIT 1'
    );
    $stmt->execute([$postId]);
    $p = $stmt->fetch();
    return $p ?: null;
}

function trux_delete_post_if_owner(int $postId, int $ownerUserId): bool {
    $db = trux_db();

    // Grab image path (for optional deletion), only if owner matches
    $stmt = $db->prepare('SELECT image_path FROM posts WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$postId, $ownerUserId]);
    $row = $stmt->fetch();

    if (!$row) return false;

    $del = $db->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
    $del->execute([$postId, $ownerUserId]);

    // Best-effort delete file from disk if it’s under /uploads
    $img = $row['image_path'] ?? null;
    if (is_string($img) && $img !== '' && str_starts_with($img, '/uploads/')) {
        $abs = dirname(__DIR__) . '/public' . $img; // /public/uploads/...
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    return $del->rowCount() > 0;
}

function trux_fetch_user_by_username(string $username): ?array {
    $db = trux_db();
    $stmt = $db->prepare('SELECT id, username, email, created_at FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function trux_fetch_posts_by_user(int $userId, int $limit = 30, ?int $beforeId = null): array {
    $limit = max(1, min(50, $limit));
    $db = trux_db();

    if ($beforeId !== null && $beforeId > 0) {
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, u.username
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.user_id = ? AND p.id < ?
             ORDER BY p.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $stmt = $db->prepare(
        'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, u.username
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.user_id = ?
         ORDER BY p.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}