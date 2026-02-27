<?php
declare(strict_types=1);

function trux_search_users(string $term, int $limit = 10): array {
    $term = trim($term);
    if ($term === '') return [];

    $limit = max(1, min(25, $limit));
    $db = trux_db();

    $escaped = trux_like_escape($term);
    $like = '%' . $escaped . '%';

    $stmt = $db->prepare(
        "SELECT id, username, created_at
         FROM users
         WHERE username LIKE ? ESCAPE '\\\\'
         ORDER BY username ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $like, PDO::PARAM_STR);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
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
            "SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, u.username
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
        "SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, u.username
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