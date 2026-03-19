<?php
declare(strict_types=1);

function trux_bookmark_normalize_ids(array $ids): array {
    $out = [];
    foreach ($ids as $id) {
        $n = (int)$id;
        if ($n > 0) {
            $out[] = $n;
        }
    }

    return array_values(array_unique($out));
}

function trux_toggle_post_bookmark(int $postId, int $userId): bool {
    if ($postId <= 0 || $userId <= 0) {
        return false;
    }

    if (!trux_post_exists($postId)) {
        return false;
    }

    $db = trux_db();

    try {
        $check = $db->prepare('SELECT 1 FROM post_bookmarks WHERE post_id = ? AND user_id = ? LIMIT 1');
        $check->execute([$postId, $userId]);
        $exists = (bool)$check->fetchColumn();

        if ($exists) {
            $del = $db->prepare('DELETE FROM post_bookmarks WHERE post_id = ? AND user_id = ?');
            $del->execute([$postId, $userId]);
            return false;
        }

        $ins = $db->prepare('INSERT INTO post_bookmarks (post_id, user_id) VALUES (?, ?)');
        $ins->execute([$postId, $userId]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_toggle_comment_bookmark(int $commentId, int $userId): bool {
    if ($commentId <= 0 || $userId <= 0) {
        return false;
    }

    if (!trux_fetch_comment_by_id($commentId)) {
        return false;
    }

    $db = trux_db();

    try {
        $check = $db->prepare('SELECT 1 FROM comment_bookmarks WHERE comment_id = ? AND user_id = ? LIMIT 1');
        $check->execute([$commentId, $userId]);
        $exists = (bool)$check->fetchColumn();

        if ($exists) {
            $del = $db->prepare('DELETE FROM comment_bookmarks WHERE comment_id = ? AND user_id = ?');
            $del->execute([$commentId, $userId]);
            return false;
        }

        $ins = $db->prepare('INSERT INTO comment_bookmarks (comment_id, user_id) VALUES (?, ?)');
        $ins->execute([$commentId, $userId]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_fetch_post_bookmark_map(array $postIds, ?int $viewerId): array {
    $ids = trux_bookmark_normalize_ids($postIds);
    if (!$ids) {
        return [];
    }

    $viewer = (int)($viewerId ?? 0);
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = false;
    }

    if ($viewer <= 0) {
        return $out;
    }

    $db = trux_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $stmt = $db->prepare("SELECT post_id FROM post_bookmarks WHERE user_id = ? AND post_id IN ($placeholders)");
        $stmt->bindValue(1, $viewer, PDO::PARAM_INT);
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 2, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            $postId = (int)$row['post_id'];
            $out[$postId] = true;
        }
    } catch (PDOException) {
        return $out;
    }

    return $out;
}

function trux_fetch_comment_bookmark_map(array $commentIds, ?int $viewerId): array {
    $ids = trux_bookmark_normalize_ids($commentIds);
    if (!$ids) {
        return [];
    }

    $viewer = (int)($viewerId ?? 0);
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = false;
    }

    if ($viewer <= 0) {
        return $out;
    }

    $db = trux_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $stmt = $db->prepare("SELECT comment_id FROM comment_bookmarks WHERE user_id = ? AND comment_id IN ($placeholders)");
        $stmt->bindValue(1, $viewer, PDO::PARAM_INT);
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 2, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            $commentId = (int)$row['comment_id'];
            $out[$commentId] = true;
        }
    } catch (PDOException) {
        return $out;
    }

    return $out;
}

function trux_fetch_user_bookmarked_posts(int $userId, int $limit = 100, int $offset = 0): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path,
                    b.created_at AS bookmarked_at
             FROM post_bookmarks b
             JOIN posts p ON p.id = b.post_id
             JOIN users u ON u.id = p.user_id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC, p.id DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_fetch_user_bookmarked_comments(int $userId, int $limit = 100, int $offset = 0): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at,
                    u.username,
                    ru.username AS reply_to_username,
                    p.body AS post_body,
                    pu.username AS post_username,
                    b.created_at AS bookmarked_at
             FROM comment_bookmarks b
             JOIN post_comments c ON c.id = b.comment_id
             JOIN users u ON u.id = c.user_id
             LEFT JOIN users ru ON ru.id = c.reply_to_user_id
             JOIN posts p ON p.id = c.post_id
             JOIN users pu ON pu.id = p.user_id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC, c.id DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}
