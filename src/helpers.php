<?php
declare(strict_types=1);

function trux_e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function trux_public_url(?string $path): string {
    $value = trim((string)$path);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (str_starts_with($value, '/')) {
        return TRUX_BASE_URL . $value;
    }

    return TRUX_BASE_URL . '/' . ltrim($value, '/');
}

function trux_post_viewer_path(int $postId, ?int $commentId = null, array $params = []): string {
    if ($postId <= 0) {
        return '/';
    }

    $query = [
        'id' => $postId,
        'viewer' => '1',
    ];

    if ($commentId !== null && $commentId > 0) {
        $query['comment_id'] = $commentId;
    }

    foreach ($params as $key => $value) {
        if (!is_string($key) || $key === '' || $value === null) {
            continue;
        }

        if (is_bool($value)) {
            $query[$key] = $value ? '1' : '0';
            continue;
        }

        if (is_scalar($value)) {
            $query[$key] = (string)$value;
        }
    }

    return '/post.php?' . http_build_query($query);
}

function trux_post_viewer_url(int $postId, ?int $commentId = null, array $params = []): string {
    return TRUX_BASE_URL . trux_post_viewer_path($postId, $commentId, $params);
}

function trux_redirect(string $path): void {
    $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
        ? $path
        : TRUX_BASE_URL . $path;
    header('Location: ' . $url);
    exit;
}

function trux_flash_set(string $key, string $message): void {
    $_SESSION['_flash'][$key] = $message;
}

function trux_flash_get(string $key): ?string {
    if (!isset($_SESSION['_flash'][$key])) return null;
    $msg = (string)$_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function trux_flash_pull_all(): array {
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash']) || $_SESSION['_flash'] === []) {
        return [];
    }

    $payload = [];
    foreach ($_SESSION['_flash'] as $key => $message) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        $text = trim((string)$message);
        if ($text === '') {
            continue;
        }

        $payload[$key] = [$text];
    }

    $_SESSION['_flash'] = [];

    return $payload;
}

function trux_int_param(string $name, int $default = 0): int {
    $v = $_GET[$name] ?? null;
    if (!is_string($v) || $v === '') return $default;
    if (!preg_match('/^-?\d+$/', $v)) return $default;
    return (int)$v;
}

function trux_str_param(string $name, string $default = ''): string {
    $v = $_GET[$name] ?? null;
    if (!is_string($v)) return $default;
    return $v;
}

function trux_safe_local_redirect_path($candidate, string $default = '/'): string {
    $path = is_string($candidate) ? trim($candidate) : '';
    if (
        $path === ''
        || !str_starts_with($path, '/')
        || str_starts_with($path, '//')
        || preg_match('/[\r\n]/', $path)
    ) {
        return $default;
    }

    return $path;
}

function trux_parse_datetime(string $dbTs): ?DateTimeImmutable {
    try {
        // MySQL TIMESTAMP typically returns "YYYY-MM-DD HH:MM:SS"
        return new DateTimeImmutable($dbTs);
    } catch (Throwable) {
        return null;
    }
}

function trux_format_exact_time(string $dbTs): string {
    $dt = trux_parse_datetime($dbTs);
    if (!$dt) return $dbTs;
    return $dt->format('Y-m-d H:i:s');
}

function trux_time_ago(string $dbTs): string {
    $dt = trux_parse_datetime($dbTs);
    if (!$dt) return $dbTs;

    $now = new DateTimeImmutable('now');
    $diff = $now->getTimestamp() - $dt->getTimestamp();

    if ($diff < 0) $diff = 0;

    if ($diff < 10) return 'just now';
    if ($diff < 60) return $diff . ' seconds ago';

    $mins = intdiv($diff, 60);
    if ($mins < 60) return $mins . ' minute' . ($mins === 1 ? '' : 's') . ' ago';

    $hours = intdiv($diff, 3600);
    if ($hours < 24) return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';

    $days = intdiv($diff, 86400);
    if ($days < 7) return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';

    // Fallback: show date for older items
    return $dt->format('Y-m-d');
}

function trux_like_escape(string $s): string {
    // Escape LIKE wildcards for safe searching
    // We'll use ESCAPE '\' in SQL.
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
}

function trux_extract_hashtags(string $body): array {
    if ($body === '') {
        return [];
    }

    preg_match_all('/(^|[^A-Za-z0-9_])#([A-Za-z0-9_]{1,50})\b/', $body, $matches);
    if (!isset($matches[2]) || !is_array($matches[2])) {
        return [];
    }

    $tags = [];
    foreach ($matches[2] as $tag) {
        if (!is_string($tag) || $tag === '') {
            continue;
        }
        $tags[] = strtolower($tag);
    }

    return array_values(array_unique($tags));
}

function trux_normalize_hashtag(string $term): string {
    $term = trim($term);
    if ($term === '') {
        return '';
    }

    if (str_starts_with($term, '#')) {
        $term = substr($term, 1);
    }

    $term = trim($term);
    if ($term === '') {
        return '';
    }

    if (!preg_match('/^[A-Za-z0-9_]{1,50}$/', $term)) {
        return '';
    }

    return strtolower($term);
}

function trux_normalize_mention_fragment(string $term): string {
    $term = trim($term);
    if ($term === '') {
        return '';
    }

    if (str_starts_with($term, '@')) {
        $term = substr($term, 1);
    }

    $term = trim($term);
    if ($term === '') {
        return '';
    }

    if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $term)) {
        return '';
    }

    return $term;
}

function trux_extract_mentions(string $body): array {
    if ($body === '') {
        return [];
    }

    preg_match_all('/(^|[^A-Za-z0-9_])@([A-Za-z0-9_]{3,32})\b/', $body, $matches);
    if (!isset($matches[2]) || !is_array($matches[2])) {
        return [];
    }

    $mentions = [];
    foreach ($matches[2] as $username) {
        if (!is_string($username) || $username === '') {
            continue;
        }
        $mentions[] = strtolower($username);
    }

    return array_values(array_unique($mentions));
}

function trux_render_non_url_rich_text_segment(string $line): string {
    $pattern = '/(^|[^A-Za-z0-9_])(@[A-Za-z0-9_]{3,32}|#[A-Za-z0-9_]{1,50})\b/';
    $cursor = 0;
    $out = '';

    $matched = preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE);
    if (is_int($matched) && $matched > 0) {
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $fullOffset = (int)$matches[0][$i][1];
            $prefix = (string)$matches[1][$i][0];
            $token = (string)$matches[2][$i][0];
            $prefixLen = strlen($prefix);
            $tokenStart = $fullOffset + $prefixLen;

            if ($tokenStart < $cursor || $token === '') {
                continue;
            }

            $before = substr($line, $cursor, $tokenStart - $cursor);
            if ($before !== false && $before !== '') {
                $out .= trux_e($before);
            }

            $sigil = substr($token, 0, 1);
            $term = substr($token, 1);

            if ($sigil === '@') {
                $href = TRUX_BASE_URL . '/profile.php?u=' . rawurlencode($term);
                $out .= '<a class="mentionLink" href="' . trux_e($href) . '">@' . trux_e($term) . '</a>';
            } elseif ($sigil === '#') {
                $normalized = strtolower($term);
                $href = TRUX_BASE_URL . '/search.php?q=' . rawurlencode('#' . $normalized) . '&filter=hashtags';
                $out .= '<a class="hashtagLink" href="' . trux_e($href) . '">#' . trux_e($term) . '</a>';
            } else {
                $out .= trux_e($token);
            }

            $cursor = $tokenStart + strlen($token);
        }
    }

    $tail = substr($line, $cursor);
    if ($tail !== false && $tail !== '') {
        $out .= trux_e($tail);
    }

    return $out;
}

function trux_split_url_suffix(string $rawUrl): array {
    $url = $rawUrl;
    $suffix = '';

    while ($url !== '') {
        $lastChar = substr($url, -1);
        if ($lastChar === '' || $lastChar === false) {
            break;
        }

        if (in_array($lastChar, ['.', ',', '!', '?', ';', ':', '"', "'"], true)) {
            $suffix = $lastChar . $suffix;
            $url = substr($url, 0, -1);
            continue;
        }

        $pairs = [
            ')' => '(',
            ']' => '[',
            '}' => '{',
        ];
        if (isset($pairs[$lastChar])) {
            $openChar = $pairs[$lastChar];
            if (substr_count($url, $openChar) < substr_count($url, $lastChar)) {
                $suffix = $lastChar . $suffix;
                $url = substr($url, 0, -1);
                continue;
            }
        }

        break;
    }

    return [$url, $suffix];
}

function trux_normalize_rich_text_url(string $url): string {
    $trimmed = trim($url);
    if ($trimmed === '' || !preg_match('#^https?://#i', $trimmed)) {
        return $trimmed;
    }

    $parsedUrl = parse_url($trimmed);
    $parsedBase = parse_url(TRUX_BASE_URL);
    if (!is_array($parsedUrl) || !is_array($parsedBase)) {
        return $trimmed;
    }

    $candidateHost = strtolower((string)($parsedUrl['host'] ?? ''));
    $baseHost = strtolower((string)($parsedBase['host'] ?? ''));
    if ($candidateHost === '' || $baseHost === '' || $candidateHost !== $baseHost) {
        return $trimmed;
    }

    $candidatePort = isset($parsedUrl['port']) ? (int)$parsedUrl['port'] : null;
    $basePort = isset($parsedBase['port']) ? (int)$parsedBase['port'] : null;
    if ($candidatePort !== $basePort) {
        if (!(($candidatePort === null || $candidatePort === 80) && ($basePort === null || $basePort === 80))) {
            if (!(($candidatePort === null || $candidatePort === 443) && ($basePort === null || $basePort === 443))) {
                return $trimmed;
            }
        }
    }

    $basePath = rtrim((string)($parsedBase['path'] ?? ''), '/');
    $candidatePath = (string)($parsedUrl['path'] ?? '');
    if ($basePath === '' || $candidatePath !== $basePath . '/post.php') {
        return $trimmed;
    }

    parse_str((string)($parsedUrl['query'] ?? ''), $query);
    $postId = isset($query['id']) && preg_match('/^\d+$/', (string)$query['id']) ? (int)$query['id'] : 0;
    if ($postId <= 0) {
        return $trimmed;
    }

    $commentId = isset($query['comment_id']) && preg_match('/^\d+$/', (string)$query['comment_id'])
        ? (int)$query['comment_id']
        : null;

    return trux_post_viewer_url($postId, $commentId !== null && $commentId > 0 ? $commentId : null);
}

function trux_render_link_token(string $url): string {
    $normalizedHref = trux_normalize_rich_text_url($url);
    if ($normalizedHref === '') {
        return trux_e($url);
    }

    return '<a class="richLink" href="' . trux_e($normalizedHref) . '">' . trux_e($url) . '</a>';
}

function trux_render_rich_text_line(string $line): string {
    $pattern = '#https?://[^\s<]+#i';
    $cursor = 0;
    $out = '';

    $matched = preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE);
    if (!is_int($matched) || $matched <= 0) {
        return trux_render_non_url_rich_text_segment($line);
    }

    foreach ($matches[0] as $match) {
        $rawUrl = (string)($match[0] ?? '');
        $fullOffset = (int)($match[1] ?? 0);
        if ($rawUrl === '' || $fullOffset < $cursor) {
            continue;
        }

        $before = substr($line, $cursor, $fullOffset - $cursor);
        if ($before !== false && $before !== '') {
            $out .= trux_render_non_url_rich_text_segment($before);
        }

        [$cleanUrl, $suffix] = trux_split_url_suffix($rawUrl);
        if ($cleanUrl !== '') {
            $out .= trux_render_link_token($cleanUrl);
        } else {
            $out .= trux_e($rawUrl);
        }
        if ($suffix !== '') {
            $out .= trux_e($suffix);
        }

        $cursor = $fullOffset + strlen($rawUrl);
    }

    $tail = substr($line, $cursor);
    if ($tail !== false && $tail !== '') {
        $out .= trux_render_non_url_rich_text_segment($tail);
    }

    return $out;
}

function trux_render_rich_text(string $body): string {
    $lines = preg_split("/\R/", $body);
    if (!is_array($lines) || $lines === []) {
        $lines = [$body];
    }

    $renderedLines = [];
    foreach ($lines as $line) {
        $renderedLines[] = trux_render_rich_text_line((string)$line);
    }

    return implode("<br>\n", $renderedLines);
}

function trux_render_post_body(string $body): string {
    return trux_render_rich_text($body);
}

function trux_render_comment_body(string $body): string {
    return trux_render_rich_text($body);
}
