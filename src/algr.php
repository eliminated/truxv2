<?php
declare(strict_types=1);

function trux_algr_bool_env(string $key, bool $default = false): bool
{
    $raw = strtolower(trim((string) trux_env($key, $default ? 'true' : 'false')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function trux_algr_enabled(): bool
{
    return trux_algr_bool_env('TRUX_ALGR_ENABLED', false);
}

function trux_algr_base_url(): string
{
    return rtrim((string) trux_env('TRUX_ALGR_BASE_URL', 'http://127.0.0.1:8000'), '/');
}

function trux_algr_timeout_seconds(): float
{
    $raw = (float) trux_env('TRUX_ALGR_TIMEOUT_SECONDS', '2.0');
    return max(0.25, min(10.0, $raw));
}

function trux_algr_candidate_limit(): int
{
    $raw = (int) trux_env('TRUX_ALGR_CANDIDATE_LIMIT', '100');
    return max(20, min(250, $raw));
}

function trux_algr_datetime_to_iso(string $dbTimestamp): string
{
    $dbTimestamp = trim($dbTimestamp);
    if ($dbTimestamp === '') {
        return gmdate('c');
    }

    try {
        $sourceTz = new DateTimeZone(TRUX_TIMEZONE !== '' ? TRUX_TIMEZONE : 'UTC');
        $utcTz = new DateTimeZone('UTC');
        $dt = new DateTimeImmutable($dbTimestamp, $sourceTz);
        return $dt->setTimezone($utcTz)->format(DateTimeInterface::ATOM);
    } catch (Throwable) {
        $ts = strtotime($dbTimestamp);
        if ($ts === false) {
            return gmdate('c');
        }
        return gmdate('c', $ts);
    }
}

/**
 * Fetches recent discovery candidates for Algr.
 *
 * Returns full post rows needed by the UI, plus like_count/comment_count
 * needed to build the Algr payload.
 */
function trux_fetch_discovery_candidates_for_algr(?int $viewerId, int $limit = 100): array
{
    $limit = max(20, min(250, $limit));
    $viewer = (int)($viewerId ?? 0);
    $db = trux_db();

    $extraJoins = '';
    $whereSql = '';

    if ($viewer > 0) {
        $extraJoins = "
            LEFT JOIN muted_users mu
                ON  mu.user_id       = :viewer_mut
                AND mu.muted_user_id = p.user_id
            LEFT JOIN blocked_users bl_out
                ON  bl_out.user_id         = :viewer_blk_out
                AND bl_out.blocked_user_id = p.user_id
            LEFT JOIN blocked_users bl_in
                ON  bl_in.user_id          = p.user_id
                AND bl_in.blocked_user_id  = :viewer_blk_in
        ";
        $whereSql = "
            WHERE mu.muted_user_id       IS NULL
              AND bl_out.blocked_user_id IS NULL
              AND bl_in.user_id          IS NULL
        ";
    }

    $sql = "
        SELECT
            p.id,
            p.user_id,
            p.body,
            p.image_path,
            p.quoted_post_id,
            p.created_at,
            p.edited_at,
            p.is_pinned,
            u.username,
            u.avatar_path,
            u.display_name,
            COALESCE(l.likes_count, 0)      AS like_count,
            COALESCE(c.comments_count, 0)   AS comment_count
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
        {$extraJoins}
        {$whereSql}
        ORDER BY p.id DESC
        LIMIT :limit_rows
    ";

    try {
        $stmt = $db->prepare($sql);

        if ($viewer > 0) {
            $stmt->bindValue(':viewer_mut', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_blk_out', $viewer, PDO::PARAM_INT);
            $stmt->bindValue(':viewer_blk_in', $viewer, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('TruX Algr candidate fetch failed: ' . $e->getMessage());
        return [];
    }
}

function trux_algr_build_payload_posts(array $candidateRows): array
{
    $payloadPosts = [];

    foreach ($candidateRows as $row) {
        $postId = (int)($row['id'] ?? 0);
        $authorId = (int)($row['user_id'] ?? 0);
        if ($postId <= 0 || $authorId <= 0) {
            continue;
        }

        $payloadPosts[] = [
            'id' => $postId,
            'author_id' => $authorId,
            'created_at' => trux_algr_datetime_to_iso((string)($row['created_at'] ?? '')),
            'like_count' => (int)($row['like_count'] ?? 0),
            'comment_count' => (int)($row['comment_count'] ?? 0),
        ];
    }

    return $payloadPosts;
}

function trux_algr_post_json(string $path, array $payload): ?array
{
    if (!function_exists('curl_init')) {
        error_log('TruX Algr request skipped: cURL extension not available.');
        return null;
    }

    $url = trux_algr_base_url() . $path;
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if (!is_string($json)) {
        error_log('TruX Algr request skipped: failed to encode JSON payload.');
        return null;
    }

    $timeoutSeconds = trux_algr_timeout_seconds();
    $timeoutMs = (int)round($timeoutSeconds * 1000);

    $ch = curl_init($url);
    if ($ch === false) {
        error_log('TruX Algr request skipped: failed to initialize cURL.');
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_CONNECTTIMEOUT_MS => min(1000, $timeoutMs),
        CURLOPT_TIMEOUT_MS => $timeoutMs,
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        error_log('TruX Algr request failed: [' . $errno . '] ' . $error);
        return null;
    }

    if (!is_string($body) || $body === '') {
        error_log('TruX Algr request failed: empty response body.');
        return null;
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        error_log('TruX Algr request failed: HTTP ' . $statusCode . ' body=' . $body);
        return null;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        error_log('TruX Algr request failed: invalid JSON response.');
        return null;
    }

    return $decoded;
}

function trux_algr_sort_candidate_rows_by_ranked_posts(array $candidateRows, array $rankedPosts): array
{
    $positionMap = [];
    foreach ($rankedPosts as $index => $rankedRow) {
        $postId = (int)($rankedRow['id'] ?? 0);
        if ($postId > 0 && !isset($positionMap[$postId])) {
            $positionMap[$postId] = (int)$index;
        }
    }

    if (!$positionMap) {
        return [];
    }

    $rankedRows = [];
    foreach ($candidateRows as $row) {
        $postId = (int)($row['id'] ?? 0);
        if ($postId > 0 && isset($positionMap[$postId])) {
            $rankedRows[] = $row;
        }
    }

    usort($rankedRows, static function (array $a, array $b) use ($positionMap): int {
        $aId = (int)($a['id'] ?? 0);
        $bId = (int)($b['id'] ?? 0);

        $aPos = $positionMap[$aId] ?? PHP_INT_MAX;
        $bPos = $positionMap[$bId] ?? PHP_INT_MAX;

        if ($aPos === $bPos) {
            return $bId <=> $aId;
        }

        return $aPos <=> $bPos;
    });

    return $rankedRows;
}

/**
 * Returns a ranked discovery page using Algr, or null on failure.
 *
 * Shape:
 * [
 *   'posts' => array,
 *   'has_more' => bool,
 *   'source' => 'algr',
 *   'total_ranked' => int,
 * ]
 */
function trux_fetch_discovery_feed_via_algr(?int $viewerId, int $limit = 20, int $page = 1): ?array
{
    if (!trux_algr_enabled()) {
        return null;
    }

    $limit = max(1, min(50, $limit));
    $page = max(1, $page);
    $offset = ($page - 1) * $limit;

    $candidateLimit = trux_algr_candidate_limit();
    $candidateRows = trux_fetch_discovery_candidates_for_algr($viewerId, $candidateLimit);

    if (!$candidateRows) {
        return [
            'posts' => [],
            'has_more' => false,
            'source' => 'algr',
            'total_ranked' => 0,
        ];
    }

    $payloadPosts = trux_algr_build_payload_posts($candidateRows);
    if (!$payloadPosts) {
        return [
            'posts' => [],
            'has_more' => false,
            'source' => 'algr',
            'total_ranked' => 0,
        ];
    }

    $payload = [
        'request_id' => 'trux-discovery-' . bin2hex(random_bytes(6)),
        'generated_at' => gmdate('c'),
        'context' => [
            'viewer_id' => $viewerId !== null ? (int)$viewerId : null,
            'feed_type' => 'global',
            'limit' => count($payloadPosts),
        ],
        'posts' => $payloadPosts,
    ];

    $response = trux_algr_post_json('/api/v1/rank-feed', $payload);
    if (!is_array($response)) {
        return null;
    }

    $rankedPosts = $response['ranked_posts'] ?? null;
    if (!is_array($rankedPosts)) {
        error_log('TruX Algr request failed: ranked_posts missing from response.');
        return null;
    }

    $rankedRows = trux_algr_sort_candidate_rows_by_ranked_posts($candidateRows, $rankedPosts);

    $pagedRows = array_slice($rankedRows, $offset, $limit);
    $hasMore = count($rankedRows) > ($offset + $limit);

    return [
        'posts' => $pagedRows,
        'has_more' => $hasMore,
        'source' => 'algr',
        'total_ranked' => count($rankedRows),
    ];
}