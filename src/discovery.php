<?php
declare(strict_types=1);

function trux_discovery_cutoff_datetime(int $hours): string {
    $hours = max(1, min(24 * 365, $hours));

    try {
        $now = new DateTimeImmutable('now');
        return $now->sub(new DateInterval('PT' . $hours . 'H'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return date('Y-m-d H:i:s', time() - ($hours * 3600));
    }
}

function trux_fetch_discovery_feed(?int $viewerId, int $limit = 20, ?int $beforeId = null): array {
    $limit = max(1, min(50, $limit));
    $viewer = (int)($viewerId ?? 0);
    $freshCutoff = trux_discovery_cutoff_datetime(36);

    $db = trux_db();

    $scoreSql = '
        (
            (COALESCE(l.likes_count, 0) * 1.00)
            + (COALESCE(c.comments_count, 0) * 1.75)
            + (COALESCE(s.shares_count, 0) * 2.25)
            + (CASE WHEN p.created_at >= :fresh_cutoff THEN 2.00 ELSE 0 END)
            - (TIMESTAMPDIFF(HOUR, p.created_at, CURRENT_TIMESTAMP) * 0.04)
        )
    ';

    $viewerJoinSql = '';
    $whereParts = [];

    if ($viewer > 0) {
        $scoreSql = '
            (
                (COALESCE(l.likes_count, 0) * 1.00)
                + (COALESCE(c.comments_count, 0) * 1.75)
                + (COALESCE(s.shares_count, 0) * 2.25)
                + (CASE WHEN p.created_at >= :fresh_cutoff THEN 2.00 ELSE 0 END)
                + (CASE WHEN vf.following_id IS NULL THEN 0 ELSE 2.50 END)
                + (CASE WHEN vpi.post_id IS NULL THEN 0 ELSE 1.50 END)
                - (TIMESTAMPDIFF(HOUR, p.created_at, CURRENT_TIMESTAMP) * 0.04)
            )
        ';

        $viewerJoinSql = '
            LEFT JOIN follows vf
                ON vf.follower_id = :viewer_follow
               AND vf.following_id = p.user_id
            LEFT JOIN (
                SELECT DISTINCT vp.post_id
                FROM (
                    SELECT post_id FROM post_likes WHERE user_id = :viewer_like
                    UNION ALL
                    SELECT post_id FROM post_shares WHERE user_id = :viewer_share
                    UNION ALL
                    SELECT post_id FROM post_bookmarks WHERE user_id = :viewer_bookmark
                    UNION ALL
                    SELECT post_id FROM post_comments WHERE user_id = :viewer_comment
                ) vp
            ) vpi
                ON vpi.post_id = p.id
            LEFT JOIN muted_users mu
                ON mu.user_id = :viewer_muted
               AND mu.muted_user_id = p.user_id
        ';

        $whereParts[] = 'mu.muted_user_id IS NULL';
    }

    if ($beforeId !== null && $beforeId > 0) {
        $whereParts[] = 'p.id < :before_id';
    }

    $whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    $sql = "
        SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username,
               $scoreSql AS discovery_score
        FROM posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS likes_count
            FROM post_likes
            GROUP BY post_id
        ) l ON l.post_id = p.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS comments_count
            FROM post_comments
            GROUP BY post_id
        ) c ON c.post_id = p.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS shares_count
            FROM post_shares
            GROUP BY post_id
        ) s ON s.post_id = p.id
        $viewerJoinSql
        $whereSql
        ORDER BY discovery_score DESC, p.id DESC
        LIMIT :limit_rows
    ";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':fresh_cutoff', $freshCutoff, PDO::PARAM_STR);

        if ($viewer > 0) {
            $stmt->bindValue(':viewer_follow', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_like', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_share', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_bookmark', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_comment', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_muted', $viewer, PDO::PARAM_INT);
        }

        if ($beforeId !== null && $beforeId > 0) {
            $stmt->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return trux_fetch_feed($limit, $beforeId);
    }
}

function trux_fetch_trending_hashtags(int $limit = 8, int $windowHours = 168): array {
    $limit = max(1, min(20, $limit));
    $windowCutoff = trux_discovery_cutoff_datetime($windowHours);
    $recentCutoff = trux_discovery_cutoff_datetime(24);

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT ph.hashtag,
                    COUNT(*) AS usage_count,
                    SUM(CASE WHEN p.created_at >= :recent_cutoff THEN 1 ELSE 0 END) AS recent_hits,
                    MAX(p.id) AS latest_post_id
             FROM post_hashtags ph
             JOIN posts p ON p.id = ph.post_id
             WHERE p.created_at >= :window_cutoff
             GROUP BY ph.hashtag
             ORDER BY recent_hits DESC, usage_count DESC, latest_post_id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':recent_cutoff', $recentCutoff, PDO::PARAM_STR);
        $stmt->bindValue(':window_cutoff', $windowCutoff, PDO::PARAM_STR);
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

function trux_fetch_discovery_suggestions(?int $viewerId, int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    $viewer = (int)($viewerId ?? 0);
    $recentCutoff = trux_discovery_cutoff_datetime(24 * 14);

    try {
        $db = trux_db();

        if ($viewer > 0) {
            $stmt = $db->prepare(
                'SELECT u.id, u.username, u.display_name, u.avatar_path,
                        COALESCE(mutuals.mutual_count, 0) AS mutual_count,
                        COALESCE(fc.follower_count, 0) AS follower_count,
                        COALESCE(rp.recent_posts, 0) AS recent_posts,
                        (
                            (COALESCE(mutuals.mutual_count, 0) * 6.00)
                            + (LEAST(COALESCE(fc.follower_count, 0), 250) * 0.05)
                            + (COALESCE(rp.recent_posts, 0) * 1.50)
                        ) AS discovery_score
                 FROM users u
                 LEFT JOIN (
                    SELECT cf.following_id AS user_id, COUNT(*) AS mutual_count
                    FROM follows vf
                    JOIN follows cf ON cf.follower_id = vf.following_id
                    WHERE vf.follower_id = :viewer_mutual
                    GROUP BY cf.following_id
                 ) mutuals ON mutuals.user_id = u.id
                 LEFT JOIN (
                    SELECT following_id AS user_id, COUNT(*) AS follower_count
                    FROM follows
                    GROUP BY following_id
                 ) fc ON fc.user_id = u.id
                 LEFT JOIN (
                    SELECT user_id, COUNT(*) AS recent_posts
                    FROM posts
                    WHERE created_at >= :recent_cutoff
                    GROUP BY user_id
                 ) rp ON rp.user_id = u.id
                 LEFT JOIN follows existing
                    ON existing.follower_id = :viewer_existing
                   AND existing.following_id = u.id
                 LEFT JOIN muted_users mu
                    ON mu.user_id = :viewer_muted
                   AND mu.muted_user_id = u.id
                 WHERE u.id <> :viewer_self
                   AND existing.following_id IS NULL
                   AND mu.muted_user_id IS NULL
                 ORDER BY discovery_score DESC, recent_posts DESC, u.id DESC
                 LIMIT :limit_rows'
            );
            $stmt->bindValue(':viewer_mutual', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_existing', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_muted', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_self', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':recent_cutoff', $recentCutoff, PDO::PARAM_STR);
            $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare(
            'SELECT u.id, u.username, u.display_name, u.avatar_path,
                    0 AS mutual_count,
                    COALESCE(fc.follower_count, 0) AS follower_count,
                    COALESCE(rp.recent_posts, 0) AS recent_posts,
                    (
                        (LEAST(COALESCE(fc.follower_count, 0), 250) * 0.05)
                        + (COALESCE(rp.recent_posts, 0) * 1.75)
                    ) AS discovery_score
             FROM users u
             LEFT JOIN (
                SELECT following_id AS user_id, COUNT(*) AS follower_count
                FROM follows
                GROUP BY following_id
             ) fc ON fc.user_id = u.id
             LEFT JOIN (
                SELECT user_id, COUNT(*) AS recent_posts
                FROM posts
                WHERE created_at >= :recent_cutoff
                GROUP BY user_id
             ) rp ON rp.user_id = u.id
             WHERE COALESCE(fc.follower_count, 0) > 0
                OR COALESCE(rp.recent_posts, 0) > 0
             ORDER BY discovery_score DESC, recent_posts DESC, u.id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':recent_cutoff', $recentCutoff, PDO::PARAM_STR);
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}
