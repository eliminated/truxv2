<?php
declare(strict_types=1);

function trux_search_users(string $term, int $limit = 10): array {
    $term = trim($term);
    if ($term === '') return [];

    $limit = max(1, min(25, $limit));
    $db = trux_db();

    $escaped = trux_like_escape($term);
    $like = '%' . $escaped . '%';
    $hiddenUsername = trux_report_system_username();

    $stmt = $db->prepare(
        "SELECT id, username, created_at
         FROM users
         WHERE username LIKE ? ESCAPE '\\\\'
           AND username <> ?
         ORDER BY username ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $like, PDO::PARAM_STR);
    $stmt->bindValue(2, $hiddenUsername, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function trux_search_users_by_prefix(string $term, int $limit = 6): array {
    $prefix = trux_normalize_mention_fragment($term);
    if ($prefix === '') {
        return [];
    }

    $limit = max(1, min(10, $limit));
    $db = trux_db();
    $like = trux_like_escape($prefix) . '%';
    $hiddenUsername = trux_report_system_username();

    $stmt = $db->prepare(
        "SELECT id, username
         FROM users
         WHERE username LIKE ? ESCAPE '\\\\'
           AND username <> ?
         ORDER BY username ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $like, PDO::PARAM_STR);
    $stmt->bindValue(2, $hiddenUsername, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function trux_search_posts_by_hashtag(string $term, int $limit = 20, ?int $beforeId = null): array {
    $tag = trux_normalize_hashtag($term);
    if ($tag === '') return [];

    $limit = max(1, min(50, $limit));
    $db = trux_db();
    $regex = '(^|[^A-Za-z0-9_])#' . preg_quote($tag, '/') . '([^A-Za-z0-9_]|$)';

    try {
        if ($beforeId !== null && $beforeId > 0) {
            $stmt = $db->prepare(
                "SELECT DISTINCT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path
                 FROM posts p
                 JOIN users u ON u.id = p.user_id
                 LEFT JOIN post_hashtags ph ON ph.post_id = p.id
                 WHERE p.id < ?
                   AND (ph.hashtag = ? OR LOWER(p.body) REGEXP ?)
                 ORDER BY p.id DESC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $beforeId, PDO::PARAM_INT);
            $stmt->bindValue(2, $tag, PDO::PARAM_STR);
            $stmt->bindValue(3, $regex, PDO::PARAM_STR);
            $stmt->bindValue(4, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare(
            "SELECT DISTINCT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path
             FROM posts p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN post_hashtags ph ON ph.post_id = p.id
             WHERE ph.hashtag = ? OR LOWER(p.body) REGEXP ?
             ORDER BY p.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $tag, PDO::PARAM_STR);
        $stmt->bindValue(2, $regex, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException) {
        if ($beforeId !== null && $beforeId > 0) {
            $stmt = $db->prepare(
                "SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path
                 FROM posts p
                 JOIN users u ON u.id = p.user_id
                 WHERE p.id < ? AND LOWER(p.body) REGEXP ?
                 ORDER BY p.id DESC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $beforeId, PDO::PARAM_INT);
            $stmt->bindValue(2, $regex, PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare(
            "SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE LOWER(p.body) REGEXP ?
             ORDER BY p.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $regex, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

function trux_search_posts(string $term, int $limit = 20, ?int $beforeId = null): array {
    $term = trim($term);
    if ($term === '') return [];

    $limit = max(1, min(50, $limit));
    $db = trux_db();

    $escaped = trux_like_escape($term);
    $like = '%' . $escaped . '%';

    if ($beforeId !== null && $beforeId > 0) {
        $stmt = $db->prepare(
            "SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id < ? AND (p.body LIKE ? ESCAPE '\\\\' OR u.username LIKE ? ESCAPE '\\\\')
             ORDER BY p.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $like, PDO::PARAM_STR);
        $stmt->bindValue(3, $like, PDO::PARAM_STR);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $stmt = $db->prepare(
        "SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.body LIKE ? ESCAPE '\\\\' OR u.username LIKE ? ESCAPE '\\\\'
         ORDER BY p.id DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $like, PDO::PARAM_STR);
    $stmt->bindValue(2, $like, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
