<?php
declare(strict_types=1);

function trux_sync_post_hashtags(int $postId, string $body): void {
    if ($postId <= 0) {
        return;
    }

    try {
        $db = trux_db();
        $tags = trux_extract_hashtags($body);

        $deleteStmt = $db->prepare('DELETE FROM post_hashtags WHERE post_id = ?');
        $deleteStmt->execute([$postId]);

        if (!$tags) {
            return;
        }

        $insertStmt = $db->prepare('INSERT INTO post_hashtags (post_id, hashtag) VALUES (?, ?)');
        foreach ($tags as $tag) {
            $insertStmt->execute([$postId, $tag]);
        }
    } catch (PDOException) {
        // The app can keep working until the hashtag migration is applied.
    }
}

function trux_create_post(int $userId, string $body, ?string $imagePath): int {
    $body = trim($body);

    $db = trux_db();
    $stmt = $db->prepare('INSERT INTO posts (user_id, body, image_path) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $body, $imagePath]);
    $postId = (int)$db->lastInsertId();
    trux_sync_post_hashtags($postId, $body);
    trux_notify_mentions_for_post($postId, $userId, $body);
    return $postId;
}

function trux_fetch_feed(int $limit = 20, ?int $beforeId = null): array {
    $limit = max(1, min(50, $limit));
    $db = trux_db();

    if ($beforeId !== null && $beforeId > 0) {
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
                    u.username, u.avatar_path, u.display_name
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
        'SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
                u.username, u.avatar_path, u.display_name
         FROM posts p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function trux_fetch_following_feed(int $viewerId, int $limit = 20, ?int $beforeId = null): array {
    if ($viewerId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $db = trux_db();

    $sql = '
        SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
               u.username, u.avatar_path, u.display_name
        FROM posts p
        JOIN users u ON u.id = p.user_id
        WHERE (
            p.user_id = :viewer_id
            OR EXISTS (
                SELECT 1
                FROM follows f
                WHERE f.follower_id = :viewer_follow_id
                  AND f.following_id = p.user_id
            )
        )
    ';

    if ($beforeId !== null && $beforeId > 0) {
        $sql .= ' AND p.id < :before_id';
    }

    $sql .= ' ORDER BY p.id DESC LIMIT :limit_rows';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':viewer_follow_id', $viewerId, PDO::PARAM_INT);
    if ($beforeId !== null && $beforeId > 0) {
        $stmt->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function trux_fetch_post_by_id(int $postId): ?array {
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
                u.username, u.avatar_path, u.display_name
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

function trux_update_post_if_owner(int $postId, int $ownerUserId, string $body): bool {
    $body = trim($body);
    if ($postId <= 0 || $ownerUserId <= 0 || $body === '' || mb_strlen($body) > 2000) {
        return false;
    }

    $db = trux_db();
    $stmt = $db->prepare('UPDATE posts SET body = ?, edited_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
    $stmt->execute([$body, $postId, $ownerUserId]);
    $updated = $stmt->rowCount() > 0;
    if ($updated) {
        trux_sync_post_hashtags($postId, $body);
        trux_notify_mentions_for_post($postId, $ownerUserId, $body);
    }
    return $updated;
}

function trux_fetch_user_by_username(string $username): ?array {
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT id, username, email, display_name, bio, about_me, location, website_url, profile_links_json,
                avatar_path, banner_path, show_likes_public, show_bookmarks_public, created_at, staff_role
         FROM users
         WHERE username = ?
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function trux_fetch_posts_by_user(int $userId, int $limit = 30, ?int $beforeId = null): array {
    $limit = max(1, min(50, $limit));
    $db = trux_db();

    if ($beforeId !== null && $beforeId > 0) {
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
                    u.username, u.avatar_path, u.display_name
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
        'SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
                u.username, u.avatar_path, u.display_name
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

function trux_add_post_comment(int $postId, int $userId, string $body, ?int $parentCommentId = null, ?int $replyToUserId = null): int {
    $body = trim($body);
    if ($postId <= 0 || $userId <= 0 || $body === '' || mb_strlen($body) > 1000) return 0;
    if (!trux_post_exists($postId)) return 0;

    $db = trux_db();

    try {
        $parentId = (int)($parentCommentId ?? 0);
        $replyUserId = (int)($replyToUserId ?? 0);
        $parentOwnerId = 0;

        if ($parentId > 0) {
            $parentStmt = $db->prepare('SELECT id, post_id, user_id FROM post_comments WHERE id = ? LIMIT 1');
            $parentStmt->execute([$parentId]);
            $parent = $parentStmt->fetch();
            if (!$parent || (int)$parent['post_id'] !== $postId) {
                return 0;
            }
            $parentOwnerId = (int)$parent['user_id'];

            if ($replyUserId <= 0) {
                $replyUserId = $parentOwnerId;
            }
        } else {
            $parentId = 0;
            $replyUserId = 0;
        }

        $stmt = $db->prepare(
            'INSERT INTO post_comments (post_id, parent_comment_id, user_id, reply_to_user_id, body)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $postId,
            $parentId > 0 ? $parentId : null,
            $userId,
            $replyUserId > 0 ? $replyUserId : null,
            $body
        ]);
        if ($stmt->rowCount() <= 0) {
            return 0;
        }

        $commentId = (int)$db->lastInsertId();
        $post = trux_fetch_post_by_id($postId);
        $postOwnerId = (int)($post['user_id'] ?? 0);

        if ($parentId > 0 && $parentOwnerId > 0) {
            trux_notify_comment_reply($parentOwnerId, $userId, $postId, $commentId);
        } elseif ($postOwnerId > 0) {
            trux_notify_post_comment($postOwnerId, $userId, $postId, $commentId);
        }

        trux_notify_mentions_for_comment($postId, $commentId, $userId, $body);
        return $commentId;
    } catch (PDOException) {
        return 0;
    }
}

function trux_fetch_comment_by_id(int $commentId): ?array {
    if ($commentId <= 0) return null;

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at, u.username, u.avatar_path
         FROM post_comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.id = ?
         LIMIT 1'
    );
    $stmt->execute([$commentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function trux_update_comment_if_owner(int $commentId, int $ownerUserId, string $body): bool {
    $body = trim($body);
    if ($commentId <= 0 || $ownerUserId <= 0 || $body === '' || mb_strlen($body) > 1000) {
        return false;
    }

    $db = trux_db();
    $stmt = $db->prepare('UPDATE post_comments SET body = ?, edited_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
    $stmt->execute([$body, $commentId, $ownerUserId]);
    $updated = $stmt->rowCount() > 0;
    if ($updated) {
        $comment = trux_fetch_comment_by_id($commentId);
        if ($comment) {
            trux_notify_mentions_for_comment((int)$comment['post_id'], $commentId, $ownerUserId, $body);
        }
    }
    return $updated;
}

function trux_delete_comment_if_owner(int $commentId, int $ownerUserId): ?int {
    if ($commentId <= 0 || $ownerUserId <= 0) return null;

    $db = trux_db();
    $stmt = $db->prepare('SELECT post_id FROM post_comments WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$commentId, $ownerUserId]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $postId = (int)$row['post_id'];
    $del = $db->prepare('DELETE FROM post_comments WHERE id = ? AND user_id = ?');
    $del->execute([$commentId, $ownerUserId]);
    if ($del->rowCount() <= 0) return null;

    return $postId;
}

function trux_set_comment_vote(int $commentId, int $userId, int $vote): int {
    if ($commentId <= 0 || $userId <= 0 || !in_array($vote, [-1, 1], true)) {
        return 0;
    }

    $db = trux_db();

    try {
        $checkComment = $db->prepare('SELECT 1 FROM post_comments WHERE id = ? LIMIT 1');
        $checkComment->execute([$commentId]);
        if (!(bool)$checkComment->fetchColumn()) {
            return 0;
        }

        $checkVote = $db->prepare('SELECT vote FROM post_comment_votes WHERE comment_id = ? AND user_id = ? LIMIT 1');
        $checkVote->execute([$commentId, $userId]);
        $existing = $checkVote->fetchColumn();

        if ($existing !== false) {
            $existingVote = (int)$existing;
            if ($existingVote === $vote) {
                $del = $db->prepare('DELETE FROM post_comment_votes WHERE comment_id = ? AND user_id = ?');
                $del->execute([$commentId, $userId]);
                return 0;
            }

            $upd = $db->prepare('UPDATE post_comment_votes SET vote = ? WHERE comment_id = ? AND user_id = ?');
            $upd->execute([$vote, $commentId, $userId]);
            return $vote;
        }

        $ins = $db->prepare('INSERT INTO post_comment_votes (comment_id, user_id, vote) VALUES (?, ?, ?)');
        $ins->execute([$commentId, $userId, $vote]);
        return $vote;
    } catch (PDOException) {
        return 0;
    }
}

function trux_fetch_comment_vote_stats(array $commentIds, ?int $viewerId): array {
    $ids = [];
    foreach ($commentIds as $id) {
        $n = (int)$id;
        if ($n > 0) $ids[] = $n;
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) return [];

    $out = [];
    foreach ($ids as $id) {
        $out[$id] = [
            'score' => 0,
            'viewer_vote' => 0,
        ];
    }

    $db = trux_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $scoreStmt = $db->prepare("SELECT comment_id, COALESCE(SUM(vote), 0) AS score FROM post_comment_votes WHERE comment_id IN ($placeholders) GROUP BY comment_id");
        foreach ($ids as $i => $id) {
            $scoreStmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $scoreStmt->execute();
        foreach ($scoreStmt->fetchAll() as $row) {
            $cid = (int)$row['comment_id'];
            if (isset($out[$cid])) {
                $out[$cid]['score'] = (int)$row['score'];
            }
        }

        $viewer = (int)($viewerId ?? 0);
        if ($viewer > 0) {
            $voteStmt = $db->prepare("SELECT comment_id, vote FROM post_comment_votes WHERE user_id = ? AND comment_id IN ($placeholders)");
            $voteStmt->bindValue(1, $viewer, PDO::PARAM_INT);
            foreach ($ids as $i => $id) {
                $voteStmt->bindValue($i + 2, $id, PDO::PARAM_INT);
            }
            $voteStmt->execute();
            foreach ($voteStmt->fetchAll() as $row) {
                $cid = (int)$row['comment_id'];
                if (isset($out[$cid])) {
                    $out[$cid]['viewer_vote'] = (int)$row['vote'];
                }
            }
        }
    } catch (PDOException) {
        return $out;
    }

    return $out;
}

function trux_count_post_comments(int $postId): int {
    if ($postId <= 0) {
        return 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('SELECT COUNT(*) FROM post_comments WHERE post_id = ?');
        $stmt->execute([$postId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_fetch_post_comment_page_ids(int $postId, int $limit = 80, ?int $beforeId = null): array {
    if ($postId <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $db = trux_db();

    try {
        if ($beforeId !== null && $beforeId > 0) {
            $stmt = $db->prepare(
                'SELECT c.id
                 FROM post_comments c
                 WHERE c.post_id = ? AND c.id < ?
                 ORDER BY c.id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $postId, PDO::PARAM_INT);
            $stmt->bindValue(2, $beforeId, PDO::PARAM_INT);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $db->prepare(
                'SELECT c.id
                 FROM post_comments c
                 WHERE c.post_id = ?
                 ORDER BY c.id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $postId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    } catch (PDOException) {
        return [];
    }
}

function trux_expand_comment_ids_with_ancestors(array $commentIds): array {
    $known = [];
    $pending = [];
    foreach ($commentIds as $id) {
        $cid = (int)$id;
        if ($cid <= 0 || isset($known[$cid])) {
            continue;
        }
        $known[$cid] = true;
        $pending[] = $cid;
    }

    if (!$pending) {
        return [];
    }

    try {
        $db = trux_db();
        while ($pending) {
            $placeholders = implode(',', array_fill(0, count($pending), '?'));
            $stmt = $db->prepare(
                "SELECT id, parent_comment_id
                 FROM post_comments
                 WHERE id IN ($placeholders)"
            );
            foreach ($pending as $i => $id) {
                $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();

            $next = [];
            foreach ($stmt->fetchAll() as $row) {
                $parentId = isset($row['parent_comment_id']) && $row['parent_comment_id'] !== null
                    ? (int)$row['parent_comment_id']
                    : 0;
                if ($parentId <= 0 || isset($known[$parentId])) {
                    continue;
                }
                $known[$parentId] = true;
                $next[] = $parentId;
            }

            $pending = $next;
        }
    } catch (PDOException) {
        // Return currently known ids if expansion fails.
    }

    return array_map('intval', array_keys($known));
}

function trux_fetch_comment_rows_by_ids(array $commentIds): array {
    $ids = [];
    foreach ($commentIds as $id) {
        $cid = (int)$id;
        if ($cid > 0) {
            $ids[] = $cid;
        }
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        return [];
    }

    $db = trux_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $stmt = $db->prepare(
            "SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at,
                    u.username, u.avatar_path, ru.username AS reply_to_username
             FROM post_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN users ru ON ru.id = c.reply_to_user_id
             WHERE c.id IN ($placeholders)
             ORDER BY c.id ASC"
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_fetch_post_comments_page(int $postId, int $limit = 80, ?int $beforeId = null): array {
    if ($postId <= 0) {
        return [
            'comments' => [],
            'next_before' => null,
            'has_more' => false,
        ];
    }

    $pageIds = trux_fetch_post_comment_page_ids($postId, $limit, $beforeId);
    if (!$pageIds) {
        return [
            'comments' => [],
            'next_before' => null,
            'has_more' => false,
        ];
    }

    $expandedIds = trux_expand_comment_ids_with_ancestors($pageIds);
    $comments = trux_fetch_comment_rows_by_ids($expandedIds);

    $oldestPageId = min($pageIds);
    $hasMore = false;
    try {
        $db = trux_db();
        $hasMoreStmt = $db->prepare(
            'SELECT 1
             FROM post_comments
             WHERE post_id = ? AND id < ?
             LIMIT 1'
        );
        $hasMoreStmt->execute([$postId, $oldestPageId]);
        $hasMore = (bool)$hasMoreStmt->fetchColumn();
    } catch (PDOException) {
        $hasMore = false;
    }

    return [
        'comments' => $comments,
        'next_before' => $hasMore ? $oldestPageId : null,
        'has_more' => $hasMore,
    ];
}

function trux_fetch_post_comments(int $postId, int $limit = 80, ?int $beforeId = null): array {
    $page = trux_fetch_post_comments_page($postId, $limit, $beforeId);
    return is_array($page['comments'] ?? null) ? $page['comments'] : [];
}

function trux_collect_post_ids(array $posts): array {
    $ids = [];
    foreach ($posts as $p) {
        $id = (int)($p['id'] ?? 0);
        if ($id > 0) $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

function trux_collect_comment_ids(array $comments): array {
    $ids = [];
    foreach ($comments as $comment) {
        $id = (int)($comment['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
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
            'bookmarks' => 0,
            'liked' => false,
            'shared' => false,
            'bookmarked' => false,
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

        $bookmarksStmt = $db->prepare("SELECT post_id, COUNT(*) AS c FROM post_bookmarks WHERE post_id IN ($placeholders) GROUP BY post_id");
        foreach ($ids as $i => $id) {
            $bookmarksStmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $bookmarksStmt->execute();
        foreach ($bookmarksStmt->fetchAll() as $row) {
            $pid = (int)$row['post_id'];
            if (isset($out[$pid])) $out[$pid]['bookmarks'] = (int)$row['c'];
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

            $bookmarksMineSql = "SELECT post_id FROM post_bookmarks WHERE user_id = ? AND post_id IN ($placeholders)";
            $bookmarksMineStmt = $db->prepare($bookmarksMineSql);
            $bookmarksMineStmt->bindValue(1, $viewer, PDO::PARAM_INT);
            foreach ($ids as $i => $id) {
                $bookmarksMineStmt->bindValue($i + 2, $id, PDO::PARAM_INT);
            }
            $bookmarksMineStmt->execute();
            foreach ($bookmarksMineStmt->fetchAll() as $row) {
                $pid = (int)$row['post_id'];
                if (isset($out[$pid])) $out[$pid]['bookmarked'] = true;
            }
        }
    } catch (PDOException) {
        return $out;
    }

    return $out;
}

function trux_fetch_comments_by_user(int $userId, int $limit = 100, int $offset = 0): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at,
                    u.username, u.avatar_path, ru.username AS reply_to_username,
                    p.body AS post_body, pu.username AS post_username
             FROM post_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN users ru ON ru.id = c.reply_to_user_id
             JOIN posts p ON p.id = c.post_id
             JOIN users pu ON pu.id = p.user_id
             WHERE c.user_id = ?
             ORDER BY c.id DESC
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

function trux_fetch_user_liked_posts(int $userId, int $limit = 100, int $offset = 0): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
                    u.username, u.avatar_path, u.display_name,
                    l.created_at AS liked_at
             FROM post_likes l
             JOIN posts p ON p.id = l.post_id
             JOIN users u ON u.id = p.user_id
             WHERE l.user_id = ?
             ORDER BY l.created_at DESC, p.id DESC
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

// ─── Pinned Posts ─────────────────────────────────────────────────────────────

function trux_pin_post(int $userId, int $postId): bool {
    if ($userId <= 0 || $postId <= 0) return false;
    $db = trux_db();
    try {
        $db->beginTransaction();
        $check = $db->prepare('SELECT id FROM posts WHERE id = ? AND user_id = ? LIMIT 1');
        $check->execute([$postId, $userId]);
        if (!$check->fetch()) {
            $db->rollBack();
            return false;
        }
        $db->prepare('UPDATE posts SET is_pinned = 0 WHERE user_id = ?')->execute([$userId]);
        $pin = $db->prepare('UPDATE posts SET is_pinned = 1 WHERE id = ? AND user_id = ?');
        $pin->execute([$postId, $userId]);
        $db->commit();
        return $pin->rowCount() > 0;
    } catch (PDOException) {
        if ($db->inTransaction()) $db->rollBack();
        return false;
    }
}

function trux_unpin_post(int $userId, int $postId): bool {
    if ($userId <= 0 || $postId <= 0) return false;
    try {
        $db = trux_db();
        $stmt = $db->prepare('UPDATE posts SET is_pinned = 0 WHERE id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException) {
        return false;
    }
}

function trux_fetch_pinned_post(int $userId, ?int $viewerId): ?array {
    if ($userId <= 0) return null;
    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.quoted_post_id, p.created_at, p.edited_at, p.is_pinned,
                    u.username, u.avatar_path, u.display_name
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.user_id = ? AND p.is_pinned = 1
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $stats = trux_fetch_post_interactions([(int)$row['id']], $viewerId);
        $row['_interactions'] = $stats[(int)$row['id']] ?? [];
        return $row;
    } catch (PDOException) {
        return null;
    }
}

// ─── Quote Posts ───────────────────────────────────────────────────────────────

function trux_create_quote_post(int $userId, int $originalPostId, string $quoteText): int {
    $quoteText = trim($quoteText);
    if ($userId <= 0 || $originalPostId <= 0) return 0;
    if ($quoteText === '' || mb_strlen($quoteText) > 2000) return 0;
    if (!trux_post_exists($originalPostId)) return 0;

    try {
        $db = trux_db();
        $stmt = $db->prepare('INSERT INTO posts (user_id, body, image_path, quoted_post_id) VALUES (?, ?, NULL, ?)');
        $stmt->execute([$userId, $quoteText, $originalPostId]);
        $postId = (int)$db->lastInsertId();
        if ($postId <= 0) return 0;
        trux_sync_post_hashtags($postId, $quoteText);
        trux_notify_mentions_for_post($postId, $userId, $quoteText);
        return $postId;
    } catch (PDOException) {
        return 0;
    }
}

function trux_fetch_quoted_posts_batch(array $postIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $postIds))));
    if (!$ids) return [];
    try {
        $db = trux_db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.is_pinned,
                    u.username, u.avatar_path, u.display_name
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id IN ($placeholders)"
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['id']] = $row;
        }
        return $out;
    } catch (PDOException) {
        return [];
    }
}

// ─── Post Polls ────────────────────────────────────────────────────────────────

function trux_create_poll(int $postId, array $options, ?string $expiresAt): int {
    if ($postId <= 0) return 0;
    $options = array_values(array_filter(array_map('trim', $options), fn($o) => $o !== ''));
    if (count($options) < 2 || count($options) > 4) return 0;
    foreach ($options as $opt) {
        if (mb_strlen($opt) > 120) return 0;
    }

    try {
        $db = trux_db();
        $db->beginTransaction();
        $pollStmt = $db->prepare('INSERT INTO polls (post_id, expires_at) VALUES (?, ?)');
        $pollStmt->execute([$postId, $expiresAt]);
        $pollId = (int)$db->lastInsertId();
        if ($pollId <= 0) {
            $db->rollBack();
            return 0;
        }
        $optStmt = $db->prepare('INSERT INTO poll_options (poll_id, body, sort_order) VALUES (?, ?, ?)');
        foreach ($options as $i => $opt) {
            $optStmt->execute([$pollId, $opt, $i + 1]);
        }
        $db->commit();
        return $pollId;
    } catch (PDOException) {
        if ($db->inTransaction()) $db->rollBack();
        return 0;
    }
}

function trux_fetch_polls_for_posts(array $postIds, ?int $viewerId): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $postIds))));
    if (!$ids) return [];

    try {
        $db = trux_db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $pollStmt = $db->prepare("SELECT id AS poll_id, post_id, expires_at FROM polls WHERE post_id IN ($placeholders)");
        foreach ($ids as $i => $id) { $pollStmt->bindValue($i + 1, $id, PDO::PARAM_INT); }
        $pollStmt->execute();

        $polls = [];
        $pollIdToPostId = [];
        foreach ($pollStmt->fetchAll() as $row) {
            $pid = (int)$row['post_id'];
            $pollId = (int)$row['poll_id'];
            $expired = $row['expires_at'] !== null && strtotime((string)$row['expires_at']) < time();
            $polls[$pid] = [
                'poll_id'          => $pollId,
                'expires_at'       => $row['expires_at'],
                'expired'          => $expired,
                'options'          => [],
                'total_votes'      => 0,
                'viewer_option_id' => null,
            ];
            $pollIdToPostId[$pollId] = $pid;
        }
        if (!$polls) return [];

        $pollIds = array_keys($pollIdToPostId);
        $pollPlaceholders = implode(',', array_fill(0, count($pollIds), '?'));

        $optStmt = $db->prepare(
            "SELECT po.id AS option_id, po.poll_id, po.body, po.sort_order,
                    COUNT(pv.id) AS vote_count
             FROM poll_options po
             LEFT JOIN poll_votes pv ON pv.poll_option_id = po.id
             WHERE po.poll_id IN ($pollPlaceholders)
             GROUP BY po.id
             ORDER BY po.sort_order ASC"
        );
        foreach ($pollIds as $i => $id) { $optStmt->bindValue($i + 1, $id, PDO::PARAM_INT); }
        $optStmt->execute();
        foreach ($optStmt->fetchAll() as $row) {
            $postId = $pollIdToPostId[(int)$row['poll_id']] ?? 0;
            if (!$postId || !isset($polls[$postId])) continue;
            $polls[$postId]['options'][] = [
                'id'         => (int)$row['option_id'],
                'body'       => (string)$row['body'],
                'vote_count' => (int)$row['vote_count'],
            ];
            $polls[$postId]['total_votes'] += (int)$row['vote_count'];
        }

        $viewer = (int)($viewerId ?? 0);
        if ($viewer > 0) {
            $voteStmt = $db->prepare(
                "SELECT pv.poll_id, pv.poll_option_id FROM poll_votes pv
                 WHERE pv.poll_id IN ($pollPlaceholders) AND pv.user_id = ?"
            );
            foreach ($pollIds as $i => $id) { $voteStmt->bindValue($i + 1, $id, PDO::PARAM_INT); }
            $voteStmt->bindValue(count($pollIds) + 1, $viewer, PDO::PARAM_INT);
            $voteStmt->execute();
            foreach ($voteStmt->fetchAll() as $row) {
                $postId = $pollIdToPostId[(int)$row['poll_id']] ?? 0;
                if ($postId && isset($polls[$postId])) {
                    $polls[$postId]['viewer_option_id'] = (int)$row['poll_option_id'];
                }
            }
        }

        return $polls;
    } catch (PDOException) {
        return [];
    }
}

function trux_vote_on_poll(int $userId, int $pollId, int $optionId): array {
    $err = static fn(string $msg) => ['ok' => false, 'error' => $msg];
    if ($userId <= 0 || $pollId <= 0 || $optionId <= 0) return $err('Invalid parameters.');

    try {
        $db = trux_db();
        $checkStmt = $db->prepare(
            'SELECT pl.id AS poll_id, pl.expires_at, po.id AS option_id, pl.post_id
             FROM polls pl
             JOIN poll_options po ON po.poll_id = pl.id AND po.id = ?
             WHERE pl.id = ?
             LIMIT 1'
        );
        $checkStmt->execute([$optionId, $pollId]);
        $row = $checkStmt->fetch();
        if (!$row) return $err('Invalid poll or option.');
        if ($row['expires_at'] !== null && strtotime((string)$row['expires_at']) < time()) {
            return $err('This poll has closed.');
        }

        $upsert = $db->prepare(
            'INSERT INTO poll_votes (poll_id, poll_option_id, user_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE poll_option_id = VALUES(poll_option_id), created_at = CURRENT_TIMESTAMP'
        );
        $upsert->execute([$pollId, $optionId, $userId]);

        $postId = (int)$row['post_id'];
        $updated = $postId > 0 ? trux_fetch_polls_for_posts([$postId], $userId) : [];
        return ['ok' => true, 'poll' => $updated[$postId] ?? null];
    } catch (PDOException) {
        return ['ok' => false, 'error' => 'Could not record vote.'];
    }
}

// ─── Existing helpers ──────────────────────────────────────────────────────────

function trux_fetch_user_liked_comments(int $userId, int $limit = 100, int $offset = 0): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at,
                    u.username, u.avatar_path, ru.username AS reply_to_username,
                    p.body AS post_body, pu.username AS post_username,
                    v.created_at AS liked_at
             FROM post_comment_votes v
             JOIN post_comments c ON c.id = v.comment_id
             JOIN users u ON u.id = c.user_id
             LEFT JOIN users ru ON ru.id = c.reply_to_user_id
             JOIN posts p ON p.id = c.post_id
             JOIN users pu ON pu.id = p.user_id
             WHERE v.user_id = ? AND v.vote = 1
             ORDER BY v.created_at DESC, c.id DESC
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

