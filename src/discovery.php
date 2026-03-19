<?php
declare(strict_types=1);

function trux_discovery_cutoff_datetime(int $hours): string
{
    $hours = max(1, min(24 * 365, $hours));
    try {
        $now = new DateTimeImmutable('now');
        return $now->sub(new DateInterval('PT' . $hours . 'H'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return date('Y-m-d H:i:s', time() - ($hours * 3600));
    }
}

/**
 * Discovery 1.0 ranked feed — scored, diversified, page-based.
 *
 * Signals
 * ───────
 *   + likes      × 1.00
 *   + comments   × 1.75
 *   + shares     × 2.25
 *   + freshness bonus  +2.00  (post < 36 h old)
 *   + social-graph     +2.50  (viewer follows the author)
 *   + author-affinity  +1.50  (viewer previously liked/shared that author's posts)
 *   - log time decay   LOG(1 + hours_old) × 0.80
 *
 * Fixes vs original codex build
 * ──────────────────────────────
 *   • Pagination is now page-based (offset) so score ordering is stable
 *     across pages.  The old id < :before_id cursor was incompatible with
 *     ORDER BY discovery_score and silently dropped high-scoring posts.
 *   • "Prior interaction" boost (viewer already engaged with THIS post)
 *     has been replaced with author-affinity (viewer engaged with OTHER
 *     posts by the same author).  Boosting already-seen posts pushed
 *     stale content back to the top.
 *   • Linear decay (-hours × 0.04) replaced with log decay
 *     (LOG(1 + hours) × 0.80) which is aggressive in the first few hours
 *     and flattens for older content.
 *   • Per-author diversity cap: at most 3 posts from one author per page.
 */
function trux_fetch_discovery_feed(?int $viewerId, int $limit = 20, int $page = 1): array
{
    $limit  = max(1, min(50, $limit));
    $page   = max(1, $page);
    $offset = ($page - 1) * $limit;
    $viewer = (int)($viewerId ?? 0);

    $freshCutoff = trux_discovery_cutoff_datetime(36);
    $db = trux_db();

    $maxPerAuthor = 3;
    // Fetch a pool large enough that after the diversity cap we still have
    // enough rows to satisfy $offset + $limit.
    $fetchLimit = min(($offset + $limit + 1) * $maxPerAuthor + $maxPerAuthor, 500);

    // Log decay: aggressive early, flattens for old posts.
    // Examples:  1h → −0.55,  6h → −1.56,  24h → −2.57,  72h → −3.43
    $decaySql = 'LOG(1 + TIMESTAMPDIFF(HOUR, p.created_at, CURRENT_TIMESTAMP)) * 0.80';

    if ($viewer > 0) {
        $scoreSql = "
            (
              (COALESCE(l.likes_count, 0)    * 1.00)
            + (COALESCE(c.comments_count, 0) * 1.75)
            + (COALESCE(s.shares_count, 0)   * 2.25)
            + (CASE WHEN p.created_at >= :fresh_cutoff THEN 2.00 ELSE 0 END)
            + (CASE WHEN vf.following_id IS NULL THEN 0 ELSE 2.50 END)
            + (CASE WHEN va.author_id    IS NULL THEN 0 ELSE 1.50 END)
            - {$decaySql}
            )
        ";

        // Author-affinity sub-query: has the viewer engaged with OTHER posts
        // by this author?  (liked or shared any of their posts)
        $extraJoins = "
            LEFT JOIN follows vf
                ON  vf.follower_id  = :viewer_fol
                AND vf.following_id = p.user_id
            LEFT JOIN (
                SELECT DISTINCT p2.user_id AS author_id
                FROM   post_likes pl2
                JOIN   posts      p2 ON p2.id = pl2.post_id
                WHERE  pl2.user_id = :viewer_aff_l
                UNION
                SELECT DISTINCT p3.user_id AS author_id
                FROM   post_shares ps3
                JOIN   posts       p3 ON p3.id = ps3.post_id
                WHERE  ps3.user_id = :viewer_aff_s
            ) va ON va.author_id = p.user_id
            LEFT JOIN muted_users mu
                ON  mu.user_id       = :viewer_mut
                AND mu.muted_user_id = p.user_id
        ";
        $whereSql = 'WHERE mu.muted_user_id IS NULL';
    } else {
        $scoreSql = "
            (
              (COALESCE(l.likes_count, 0)    * 1.00)
            + (COALESCE(c.comments_count, 0) * 1.75)
            + (COALESCE(s.shares_count, 0)   * 2.25)
            + (CASE WHEN p.created_at >= :fresh_cutoff THEN 2.00 ELSE 0 END)
            - {$decaySql}
            )
        ";
        $extraJoins = '';
        $whereSql   = '';
    }

    $sql = "
        SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at,
               u.username, u.avatar_path,
               {$scoreSql} AS discovery_score
        FROM   posts p
        JOIN   users u ON u.id = p.user_id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS likes_count
            FROM   post_likes
            GROUP  BY post_id
        ) l ON l.post_id = p.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS comments_count
            FROM   post_comments
            GROUP  BY post_id
        ) c ON c.post_id = p.id
        LEFT JOIN (
            SELECT post_id, COUNT(*) AS shares_count
            FROM   post_shares
            GROUP  BY post_id
        ) s ON s.post_id = p.id
        {$extraJoins}
        {$whereSql}
        ORDER  BY discovery_score DESC, p.id DESC
        LIMIT  :fetch_limit
    ";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':fresh_cutoff', $freshCutoff, PDO::PARAM_STR);

        if ($viewer > 0) {
            $stmt->bindValue(':viewer_fol',   $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_aff_l', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_aff_s', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_mut',   $viewer, PDO::PARAM_INT);
        }

        $stmt->bindValue(':fetch_limit', $fetchLimit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Per-author diversity cap — at most $maxPerAuthor posts per author
        // across the entire fetched pool before pagination.
        $authorCounts = [];
        $diversified  = [];
        foreach ($rows as $row) {
            $uid = (int)($row['user_id'] ?? 0);
            $authorCounts[$uid] = ($authorCounts[$uid] ?? 0) + 1;
            if ($authorCounts[$uid] <= $maxPerAuthor) {
                $diversified[] = $row;
            }
        }

        return array_slice($diversified, $offset, $limit);
    } catch (PDOException) {
        // Graceful fallback to plain reverse-chronological feed
        return trux_fetch_discovery_fallback($limit, $offset);
    }
}

/**
 * Plain reverse-chronological fallback used when the scored query fails
 * (e.g. missing tables during initial setup).
 */
function trux_fetch_discovery_fallback(int $limit, int $offset): array
{
    $limit  = max(1, min(50, $limit));
    $offset = max(0, $offset);

    try {
        $db   = trux_db();
        $stmt = $db->prepare(
            'SELECT p.id, p.user_id, p.body, p.image_path, p.created_at, p.edited_at, u.username, u.avatar_path
             FROM   posts p
             JOIN   users u ON u.id = p.user_id
             ORDER  BY p.id DESC
             LIMIT  ? OFFSET ?'
        );
        $stmt->bindValue(1, $limit,  PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Trending hashtags
// Window tightened to 72 h (was 240 h / 10 days) so "trending" reflects
// what is actually active right now.
// ─────────────────────────────────────────────────────────────────────────────
function trux_fetch_trending_hashtags(int $limit = 8, int $windowHours = 72): array
{
    $limit       = max(1, min(20, $limit));
    $windowHours = max(1, min(24 * 30, $windowHours)); // accept caller override, cap 30 days

    $windowCutoff = trux_discovery_cutoff_datetime($windowHours);
    $recentCutoff = trux_discovery_cutoff_datetime(24); // "recent" = last 24 h

    try {
        $db   = trux_db();
        $stmt = $db->prepare(
            'SELECT ph.hashtag,
                    COUNT(*)                                                              AS usage_count,
                    SUM(CASE WHEN p.created_at >= :recent_cutoff THEN 1 ELSE 0 END)     AS recent_hits,
                    MAX(p.id)                                                             AS latest_post_id
             FROM   post_hashtags ph
             JOIN   posts p ON p.id = ph.post_id
             WHERE  p.created_at >= :window_cutoff
             GROUP  BY ph.hashtag
             ORDER  BY recent_hits DESC, usage_count DESC, latest_post_id DESC
             LIMIT  :limit_rows'
        );
        $stmt->bindValue(':recent_cutoff', $recentCutoff, PDO::PARAM_STR);
        $stmt->bindValue(':window_cutoff', $windowCutoff, PDO::PARAM_STR);
        $stmt->bindValue(':limit_rows',    $limit,        PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Suggested users
//
// Weight changes vs original:
//   • Mutual connections: 6.00 → 8.00  (stronger social signal)
//   • Follower cap: 250 → 500, weight 0.05 → 0.04
//     (allows more range without dominating; on small networks most users
//      have 0 mutuals so follower weight was the only signal firing)
//   • Recent posts: 1.50 → 2.00  (rewards active creators more)
// ─────────────────────────────────────────────────────────────────────────────
function trux_fetch_discovery_suggestions(?int $viewerId, int $limit = 6): array
{
    $limit  = max(1, min(20, $limit));
    $viewer = (int)($viewerId ?? 0);
    $recentCutoff = trux_discovery_cutoff_datetime(24 * 14); // 2-week activity window

    try {
        $db = trux_db();

        if ($viewer > 0) {
            $stmt = $db->prepare(
                'SELECT u.id, u.username, u.display_name, u.avatar_path,
                        COALESCE(mutuals.mutual_count, 0)  AS mutual_count,
                        COALESCE(fc.follower_count, 0)     AS follower_count,
                        COALESCE(rp.recent_posts, 0)       AS recent_posts,
                        (
                            (COALESCE(mutuals.mutual_count, 0)            *  8.00)
                          + (LEAST(COALESCE(fc.follower_count, 0), 500)   *  0.04)
                          + (COALESCE(rp.recent_posts, 0)                 *  2.00)
                        ) AS discovery_score
                 FROM   users u
                 LEFT JOIN (
                     SELECT cf.following_id AS user_id, COUNT(*) AS mutual_count
                     FROM   follows vf
                     JOIN   follows cf ON cf.follower_id = vf.following_id
                     WHERE  vf.follower_id = :viewer_mutual
                     GROUP  BY cf.following_id
                 ) mutuals ON mutuals.user_id = u.id
                 LEFT JOIN (
                     SELECT following_id AS user_id, COUNT(*) AS follower_count
                     FROM   follows
                     GROUP  BY following_id
                 ) fc ON fc.user_id = u.id
                 LEFT JOIN (
                     SELECT user_id, COUNT(*) AS recent_posts
                     FROM   posts
                     WHERE  created_at >= :recent_cutoff
                     GROUP  BY user_id
                 ) rp ON rp.user_id = u.id
                 LEFT JOIN follows existing
                     ON  existing.follower_id  = :viewer_existing
                     AND existing.following_id = u.id
                 LEFT JOIN muted_users mu
                     ON  mu.user_id       = :viewer_muted
                     AND mu.muted_user_id = u.id
                 WHERE  u.id <> :viewer_self
                   AND  existing.following_id IS NULL
                   AND  mu.muted_user_id      IS NULL
                 ORDER  BY discovery_score DESC, recent_posts DESC, u.id DESC
                 LIMIT  :limit_rows'
            );
            $stmt->bindValue(':viewer_mutual',   $viewer,       PDO::PARAM_INT);
            $stmt->bindValue(':viewer_existing',  $viewer,       PDO::PARAM_INT);
            $stmt->bindValue(':viewer_muted',     $viewer,       PDO::PARAM_INT);
            $stmt->bindValue(':viewer_self',      $viewer,       PDO::PARAM_INT);
            $stmt->bindValue(':recent_cutoff',    $recentCutoff, PDO::PARAM_STR);
            $stmt->bindValue(':limit_rows',       $limit,        PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        // Anonymous visitor — rank by follower count + recent activity only
        $stmt = $db->prepare(
            'SELECT u.id, u.username, u.display_name, u.avatar_path,
                    0                                                   AS mutual_count,
                    COALESCE(fc.follower_count, 0)                     AS follower_count,
                    COALESCE(rp.recent_posts, 0)                       AS recent_posts,
                    (
                        (LEAST(COALESCE(fc.follower_count, 0), 500)   * 0.04)
                      + (COALESCE(rp.recent_posts, 0)                 * 2.00)
                    ) AS discovery_score
             FROM   users u
             LEFT JOIN (
                 SELECT following_id AS user_id, COUNT(*) AS follower_count
                 FROM   follows
                 GROUP  BY following_id
             ) fc ON fc.user_id = u.id
             LEFT JOIN (
                 SELECT user_id, COUNT(*) AS recent_posts
                 FROM   posts
                 WHERE  created_at >= :recent_cutoff
                 GROUP  BY user_id
             ) rp ON rp.user_id = u.id
             WHERE  COALESCE(fc.follower_count, 0) > 0
                OR  COALESCE(rp.recent_posts, 0)   > 0
             ORDER  BY discovery_score DESC, recent_posts DESC, u.id DESC
             LIMIT  :limit_rows'
        );
        $stmt->bindValue(':recent_cutoff', $recentCutoff, PDO::PARAM_STR);
        $stmt->bindValue(':limit_rows',    $limit,        PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}
