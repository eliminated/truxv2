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

function trux_post_exists(int $postId): bool {
    if ($postId <= 0) return false;

    $db = trux_db();
    $stmt = $db->prepare('SELECT 1 FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$postId]);
    return (bool)$stmt->fetchColumn();
}

function trux_toggle_post_like(int $postId, int $userId): bool {
    if ($postId <= 0 || $userId <= 0) return false;
    if (!trux_post_exists($postId)) return false;

    $db = trux_db();

    try {
        $check = $db->prepare('SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1');
        $check->execute([$postId, $userId]);
        $exists = (bool)$check->fetchColumn();

        if ($exists) {
            $del = $db->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?');
            $del->execute([$postId, $userId]);
            return false;
        }

        $ins = $db->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)');
        $ins->execute([$postId, $userId]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_toggle_post_share(int $postId, int $userId): bool {
    if ($postId <= 0 || $userId <= 0) return false;
    if (!trux_post_exists($postId)) return false;

    $db = trux_db();

    try {
        $check = $db->prepare('SELECT 1 FROM post_shares WHERE post_id = ? AND user_id = ? LIMIT 1');
        $check->execute([$postId, $userId]);
        $exists = (bool)$check->fetchColumn();

        if ($exists) {
            $del = $db->prepare('DELETE FROM post_shares WHERE post_id = ? AND user_id = ?');
            $del->execute([$postId, $userId]);
            return false;
        }

        $ins = $db->prepare('INSERT INTO post_shares (post_id, user_id) VALUES (?, ?)');
        $ins->execute([$postId, $userId]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_add_post_comment(int $postId, int $userId, string $body): bool {
    $body = trim($body);
    if ($postId <= 0 || $userId <= 0 || $body === '' || mb_strlen($body) > 1000) return false;
    if (!trux_post_exists($postId)) return false;

    $db = trux_db();

    try {
        $stmt = $db->prepare('INSERT INTO post_comments (post_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$postId, $userId, $body]);
        return $stmt->rowCount() > 0;
    } catch (PDOException) {
        return false;
    }
}

function trux_fetch_post_comments(int $postId, int $limit = 80): array {
    if ($postId <= 0) return [];
    $limit = max(1, min(200, $limit));

    $db = trux_db();

    try {
        $sql = '
            SELECT t.id, t.post_id, t.user_id, t.body, t.created_at, u.username
            FROM (
                SELECT c.id, c.post_id, c.user_id, c.body, c.created_at
                FROM post_comments c
                WHERE c.post_id = ?
                ORDER BY c.id DESC
                LIMIT ?
            ) t
            JOIN users u ON u.id = t.user_id
            ORDER BY t.id ASC
        ';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $postId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_collect_post_ids(array $posts): array {
    $ids = [];
    foreach ($posts as $p) {
        $id = (int)($p['id'] ?? 0);
        if ($id > 0) $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

function trux_fetch_post_interactions(array $postIds, ?int $viewerId): array {
    $ids = [];
    foreach ($postIds as $id) {
        $n = (int)$id;
        if ($n > 0) $ids[] = $n;
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) return [];

    $out = [];
    foreach ($ids as $id) {
        $out[$id] = [
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'liked' => false,
            'shared' => false,
        ];
    }

    $db = trux_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $likesStmt = $db->prepare("SELECT post_id, COUNT(*) AS c FROM post_likes WHERE post_id IN ($placeholders) GROUP BY post_id");
        foreach ($ids as $i => $id) {
            $likesStmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $likesStmt->execute();
        foreach ($likesStmt->fetchAll() as $row) {
            $pid = (int)$row['post_id'];
            if (isset($out[$pid])) $out[$pid]['likes'] = (int)$row['c'];
        }

        $commentsStmt = $db->prepare("SELECT post_id, COUNT(*) AS c FROM post_comments WHERE post_id IN ($placeholders) GROUP BY post_id");
        foreach ($ids as $i => $id) {
            $commentsStmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $commentsStmt->execute();
        foreach ($commentsStmt->fetchAll() as $row) {
            $pid = (int)$row['post_id'];
            if (isset($out[$pid])) $out[$pid]['comments'] = (int)$row['c'];
        }

        $sharesStmt = $db->prepare("SELECT post_id, COUNT(*) AS c FROM post_shares WHERE post_id IN ($placeholders) GROUP BY post_id");
        foreach ($ids as $i => $id) {
            $sharesStmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $sharesStmt->execute();
        foreach ($sharesStmt->fetchAll() as $row) {
            $pid = (int)$row['post_id'];
            if (isset($out[$pid])) $out[$pid]['shares'] = (int)$row['c'];
        }

        $viewer = (int)($viewerId ?? 0);
        if ($viewer > 0) {
            $likesMineSql = "SELECT post_id FROM post_likes WHERE user_id = ? AND post_id IN ($placeholders)";
            $likesMineStmt = $db->prepare($likesMineSql);
            $likesMineStmt->bindValue(1, $viewer, PDO::PARAM_INT);
            foreach ($ids as $i => $id) {
                $likesMineStmt->bindValue($i + 2, $id, PDO::PARAM_INT);
            }
            $likesMineStmt->execute();
            foreach ($likesMineStmt->fetchAll() as $row) {
                $pid = (int)$row['post_id'];
                if (isset($out[$pid])) $out[$pid]['liked'] = true;
            }

            $sharesMineSql = "SELECT post_id FROM post_shares WHERE user_id = ? AND post_id IN ($placeholders)";
            $sharesMineStmt = $db->prepare($sharesMineSql);
            $sharesMineStmt->bindValue(1, $viewer, PDO::PARAM_INT);
            foreach ($ids as $i => $id) {
                $sharesMineStmt->bindValue($i + 2, $id, PDO::PARAM_INT);
            }
            $sharesMineStmt->execute();
            foreach ($sharesMineStmt->fetchAll() as $row) {
                $pid = (int)$row['post_id'];
                if (isset($out[$pid])) $out[$pid]['shared'] = true;
            }
        }
    } catch (PDOException) {
        return $out;
    }

    return $out;
}
