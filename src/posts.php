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
            'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username
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
        'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username
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
        SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username
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
        'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username
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
            'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username
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
        'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username
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
        'SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at, u.username
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

function trux_fetch_post_comments(int $postId, int $limit = 80): array {
    if ($postId <= 0) return [];
    $limit = max(1, min(200, $limit));

    $db = trux_db();

    try {
        $sql = '
            SELECT t.id, t.post_id, t.parent_comment_id, t.user_id, t.reply_to_user_id, t.body, t.created_at, t.edited_at, u.username, ru.username AS reply_to_username
            FROM (
                SELECT c.id, c.post_id, c.parent_comment_id, c.user_id, c.reply_to_user_id, c.body, c.created_at, c.edited_at
                FROM post_comments c
                WHERE c.post_id = ?
                ORDER BY c.id DESC
                LIMIT ?
            ) t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN users ru ON ru.id = t.reply_to_user_id
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
