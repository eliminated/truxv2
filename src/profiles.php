<?php
declare(strict_types=1);

function trux_profile_about_me_limit(): int {
    return 3000;
}

function trux_profile_link_limit(): int {
    return 5;
}

function trux_profile_link_label_limit(): int {
    return 80;
}

function trux_profile_privacy_defaults(): array {
    return [
        'show_likes_public' => true,
        'show_bookmarks_public' => true,
    ];
}

function trux_profile_normalize_http_url(string $rawUrl, int $maxLength = 255): ?string {
    $url = trim($rawUrl);
    if ($url === '') {
        return null;
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    if (mb_strlen($url) > $maxLength) {
        return null;
    }

    $validated = filter_var($url, FILTER_VALIDATE_URL);
    $parts = $validated !== false ? parse_url((string)$validated) : false;
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    $host = is_array($parts) ? trim((string)($parts['host'] ?? '')) : '';

    if ($validated === false || $host === '' || !in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    return (string)$validated;
}

function trux_profile_fill_link_slots(array $links, ?int $slotCount = null): array {
    $maxSlots = $slotCount ?? trux_profile_link_limit();
    $rows = [];

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $rows[] = [
            'label' => trim((string)($link['label'] ?? '')),
            'url' => trim((string)($link['url'] ?? '')),
        ];

        if (count($rows) >= $maxSlots) {
            break;
        }
    }

    while (count($rows) < $maxSlots) {
        $rows[] = ['label' => '', 'url' => ''];
    }

    return $rows;
}

function trux_profile_encode_links(array $links): ?string {
    if ($links === []) {
        return null;
    }

    $payload = [];
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $url = trim((string)($link['url'] ?? ''));
        if ($url === '') {
            continue;
        }

        $label = trim((string)($link['label'] ?? ''));
        $payload[] = [
            'label' => $label !== '' ? $label : null,
            'url' => $url,
        ];
    }

    if ($payload === []) {
        return null;
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : null;
}

function trux_profile_decode_links(?string $json): array {
    $raw = trim((string)$json);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $links = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $url = trux_profile_normalize_http_url((string)($row['url'] ?? ''));
        if ($url === null) {
            continue;
        }

        $label = trim((string)($row['label'] ?? ''));
        if ($label !== '' && mb_strlen($label) > trux_profile_link_label_limit()) {
            $label = mb_substr($label, 0, trux_profile_link_label_limit());
        }

        $links[] = [
            'label' => $label !== '' ? $label : null,
            'url' => $url,
        ];

        if (count($links) >= trux_profile_link_limit()) {
            break;
        }
    }

    return $links;
}

function trux_profile_normalize_links(array $rows): array {
    $errors = [];
    $links = [];
    $maxLinks = trux_profile_link_limit();
    $maxRows = min(max(count($rows), $maxLinks), $maxLinks);

    for ($index = 0; $index < $maxRows; $index++) {
        $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
        $label = trim((string)($row['label'] ?? ''));
        $urlRaw = trim((string)($row['url'] ?? ''));

        if ($label === '' && $urlRaw === '') {
            continue;
        }

        $humanIndex = $index + 1;
        if ($label !== '' && mb_strlen($label) > trux_profile_link_label_limit()) {
            $errors[] = 'Link label #' . $humanIndex . ' must be ' . trux_profile_link_label_limit() . ' characters or less.';
        }

        if ($urlRaw === '') {
            $errors[] = 'Link URL #' . $humanIndex . ' is required when a label is provided.';
            continue;
        }

        $normalizedUrl = trux_profile_normalize_http_url($urlRaw);
        if ($normalizedUrl === null) {
            $errors[] = 'Link URL #' . $humanIndex . ' must be a valid http(s) address under 255 characters.';
            continue;
        }

        $links[] = [
            'label' => $label !== '' ? $label : null,
            'url' => $normalizedUrl,
        ];
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'links' => $links,
        'slots' => trux_profile_fill_link_slots($rows),
    ];
}

function trux_profile_link_provider(string $url): string {
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return 'website';
    }

    $host = strtolower((string)$parts['host']);
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    if ($host === 'x.com' || $host === 'twitter.com' || str_ends_with($host, '.x.com') || str_ends_with($host, '.twitter.com')) {
        return 'x';
    }
    if ($host === 'reddit.com' || str_ends_with($host, '.reddit.com')) {
        return 'reddit';
    }
    if ($host === 'instagram.com' || str_ends_with($host, '.instagram.com')) {
        return 'instagram';
    }
    if ($host === 'facebook.com' || $host === 'fb.com' || str_ends_with($host, '.facebook.com')) {
        return 'facebook';
    }
    if ($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com')) {
        return 'linkedin';
    }
    if ($host === 'github.com' || str_ends_with($host, '.github.com')) {
        return 'github';
    }
    if ($host === 'youtube.com' || $host === 'youtu.be' || str_ends_with($host, '.youtube.com')) {
        return 'youtube';
    }
    if ($host === 'tiktok.com' || str_ends_with($host, '.tiktok.com')) {
        return 'tiktok';
    }
    if ($host === 'twitch.tv' || str_ends_with($host, '.twitch.tv')) {
        return 'twitch';
    }
    if ($host === 'discord.com' || $host === 'discord.gg' || str_ends_with($host, '.discord.com')) {
        return 'discord';
    }

    return 'website';
}

function trux_profile_link_icon_svg(string $provider): string {
    return match ($provider) {
        'x' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 4h3.7l3 4.3L16.8 4H19l-4.9 5.9L19.6 20h-3.7l-3.3-4.8L8.4 20H6.2l5.3-6.4L6.4 4Z"/></svg>',
        'reddit' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.4 9.1a1.8 1.8 0 1 0-1.8-3 5.8 5.8 0 0 0-3.5-1l.5-2.2 1.6.4a1.7 1.7 0 1 0 .4-1.4l-2-.5a.8.8 0 0 0-1 .6l-.6 2.8a6.8 6.8 0 0 0-4 1.2 1.8 1.8 0 1 0-1.6 3c-.1.4-.2.9-.2 1.4 0 3.1 2.6 5.7 5.8 5.7 3.2 0 5.8-2.6 5.8-5.7 0-.5-.1-.9-.2-1.3Zm-9.8 3a1.1 1.1 0 1 1 0-2.2 1.1 1.1 0 0 1 0 2.2Zm6.8 1.4c-.8.8-2 1.2-3.4 1.2s-2.6-.4-3.4-1.2a.8.8 0 0 1 1.2-1 3.6 3.6 0 0 0 2.2.7c.9 0 1.7-.2 2.2-.7a.8.8 0 0 1 1.2 1Zm-.1-1.4a1.1 1.1 0 1 1 0-2.2 1.1 1.1 0 0 1 0 2.2Z"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2.1A2.9 2.9 0 0 0 4.1 7v10A2.9 2.9 0 0 0 7 19.9h10a2.9 2.9 0 0 0 2.9-2.9V7A2.9 2.9 0 0 0 17 4.1H7Zm10.4 1.5a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2ZM12 6.3A5.7 5.7 0 1 1 6.3 12 5.7 5.7 0 0 1 12 6.3Zm0 2.1A3.6 3.6 0 1 0 15.6 12 3.6 3.6 0 0 0 12 8.4Z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 21v-7h2.8l.5-3.5h-3.3V8.3c0-1 .3-1.8 1.8-1.8H17V3.4c-.3 0-1.3-.2-2.5-.2-2.8 0-4.5 1.7-4.5 4.9v2.4H7v3.5h3V21h3.5Z"/></svg>',
        'linkedin' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M5.2 8.8A1.9 1.9 0 1 1 5.2 5a1.9 1.9 0 0 1 0 3.8ZM3.6 10h3.2v10H3.6V10Zm5.3 0H12v1.4h.1c.4-.8 1.5-1.8 3.1-1.8 3.3 0 3.9 2.2 3.9 5V20h-3.2v-4.6c0-1.1 0-2.5-1.6-2.5s-1.8 1.2-1.8 2.4V20H8.9V10Z"/></svg>',
        'github' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.2A10 10 0 0 0 8.8 21c.5.1.7-.2.7-.5v-1.7c-3 .7-3.7-1.3-3.7-1.3-.5-1.2-1.1-1.5-1.1-1.5-.9-.6 0-.6 0-.6 1 .1 1.5 1 1.5 1 .9 1.6 2.4 1.1 3 .8.1-.7.4-1.1.7-1.4-2.4-.3-5-1.2-5-5.3 0-1.1.4-2 1-2.8 0-.2-.4-1.3.1-2.7 0 0 .8-.3 2.8 1a9.7 9.7 0 0 1 5 0c2-1.3 2.8-1 2.8-1 .5 1.4.1 2.5 0 2.7.6.8 1 1.7 1 2.8 0 4.1-2.5 5-5 5.3.4.3.8 1 .8 2v3c0 .3.2.6.7.5A10 10 0 0 0 12 2.2Z"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.5 7.2a2.9 2.9 0 0 0-2-2c-1.8-.5-7.5-.5-7.5-.5s-5.7 0-7.5.5a2.9 2.9 0 0 0-2 2A30.5 30.5 0 0 0 2 12a30.5 30.5 0 0 0 .5 4.8 2.9 2.9 0 0 0 2 2c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a2.9 2.9 0 0 0 2-2A30.5 30.5 0 0 0 22 12a30.5 30.5 0 0 0-.5-4.8ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14 3h2.2c.2 1.3 1.1 2.5 2.3 3.1.6.3 1.2.5 1.9.5V9a7.3 7.3 0 0 1-4.2-1.4v6.5a5.1 5.1 0 1 1-5.1-5v2.4a2.7 2.7 0 1 0 2.7 2.7V3Z"/></svg>',
        'twitch' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 3h17v11.3l-3.4 3.4h-3l-2.5 2.5H9.7v-2.5H4V3Zm1.9 1.9v10.9h4.4v2.1l2.1-2.1h3.5l2.2-2.2V4.9H5.9Zm5.4 2.7h1.9v5h-1.9v-5Zm4.4 0h1.9v5h-1.9v-5Z"/></svg>',
        'discord' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.7 5.7a14 14 0 0 0-3.5-1.1l-.2.4a10 10 0 0 1 2.9 1.1A13 13 0 0 0 12 4.6 13 13 0 0 0 5.1 6a10 10 0 0 1 2.9-1.1l-.2-.4c-1.2.2-2.4.6-3.5 1.1C2 9 1.4 12.2 1.6 15.3c1.5 1.1 3 1.8 4.5 2.3l1.1-1.8a9 9 0 0 1-1.8-.9l.4-.3c3.4 1.6 7.1 1.6 10.4 0l.4.3c-.6.4-1.2.7-1.8.9l1.1 1.8c1.5-.5 3-1.2 4.5-2.3.3-3.6-.6-6.8-2.7-9.6ZM9.5 13.3c-.8 0-1.4-.8-1.4-1.7 0-1 .6-1.7 1.4-1.7.8 0 1.4.8 1.4 1.7 0 1-.6 1.7-1.4 1.7Zm5 0c-.8 0-1.4-.8-1.4-1.7 0-1 .6-1.7 1.4-1.7.8 0 1.4.8 1.4 1.7 0 1-.6 1.7-1.4 1.7Z"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm6.8 8h-3.1a14.8 14.8 0 0 0-1.3-5.1A7.1 7.1 0 0 1 18.8 11ZM12 4.9c1 1.2 1.8 3.4 2 6.1H10c.2-2.7 1-4.9 2-6.1ZM9.6 5.9A14.8 14.8 0 0 0 8.3 11H5.2a7.1 7.1 0 0 1 4.4-5.1ZM5.2 13h3.1a14.8 14.8 0 0 0 1.3 5.1A7.1 7.1 0 0 1 5.2 13Zm6.8 6.1c-1-1.2-1.8-3.4-2-6.1h4c-.2 2.7-1 4.9-2 6.1Zm2.4-1a14.8 14.8 0 0 0 1.3-5.1h3.1a7.1 7.1 0 0 1-4.4 5.1Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    };
}

function trux_profile_prepare_links_for_display(array $links): array {
    $displayLinks = [];

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $url = trux_profile_normalize_http_url((string)($link['url'] ?? ''));
        if ($url === null) {
            continue;
        }

        $label = trim((string)($link['label'] ?? ''));
        $displayLinks[] = [
            'url' => $url,
            'label' => $label !== '' ? $label : trux_profile_website_label($url),
            'provider' => trux_profile_link_provider($url),
            'icon_svg' => trux_profile_link_icon_svg(trux_profile_link_provider($url)),
        ];
    }

    return $displayLinks;
}

function trux_profile_normalize_payload(array $input): array {
    $errors = [];

    $displayName = trim((string)($input['display_name'] ?? ''));
    $bio = trim(str_replace(["\r\n", "\r"], "\n", (string)($input['bio'] ?? '')));
    $aboutMe = trim(str_replace(["\r\n", "\r"], "\n", (string)($input['about_me'] ?? '')));
    $location = trim((string)($input['location'] ?? ''));
    $websiteRaw = trim((string)($input['website_url'] ?? ''));
    $profileLinks = is_array($input['profile_links'] ?? null) ? $input['profile_links'] : [];

    if ($displayName !== '' && mb_strlen($displayName) > 80) {
        $errors[] = 'Display name must be 80 characters or less.';
    }

    if ($bio !== '' && mb_strlen($bio) > 280) {
        $errors[] = 'Bio must be 280 characters or less.';
    }

    if ($aboutMe !== '' && mb_strlen($aboutMe) > trux_profile_about_me_limit()) {
        $errors[] = 'About Me must be ' . trux_profile_about_me_limit() . ' characters or less.';
    }

    if ($location !== '' && mb_strlen($location) > 100) {
        $errors[] = 'Location must be 100 characters or less.';
    }

    $websiteUrl = null;
    if ($websiteRaw !== '') {
        $websiteUrl = trux_profile_normalize_http_url($websiteRaw);
        if ($websiteUrl === null) {
            $errors[] = 'Website URL must be a valid http(s) address under 255 characters.';
        }
    }

    $normalizedLinks = trux_profile_normalize_links($profileLinks);
    $errors = array_merge($errors, is_array($normalizedLinks['errors'] ?? null) ? $normalizedLinks['errors'] : []);
    $links = is_array($normalizedLinks['links'] ?? null) ? $normalizedLinks['links'] : [];

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'data' => [
            'display_name' => $displayName !== '' ? $displayName : null,
            'bio' => $bio !== '' ? $bio : null,
            'about_me' => $aboutMe !== '' ? $aboutMe : null,
            'location' => $location !== '' ? $location : null,
            'website_url' => $websiteUrl,
            'profile_links_json' => trux_profile_encode_links($links),
        ],
    ];
}

function trux_update_user_profile(int $userId, array $profileData): bool {
    if ($userId <= 0) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE users
             SET display_name = ?,
                 bio = ?,
                 about_me = ?,
                 location = ?,
                 website_url = ?,
                 profile_links_json = ?,
                 avatar_path = ?,
                 banner_path = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $profileData['display_name'] ?? null,
            $profileData['bio'] ?? null,
            $profileData['about_me'] ?? null,
            $profileData['location'] ?? null,
            $profileData['website_url'] ?? null,
            $profileData['profile_links_json'] ?? null,
            $profileData['avatar_path'] ?? null,
            $profileData['banner_path'] ?? null,
            $userId,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_fetch_profile_privacy_preferences(int $userId): array {
    $defaults = trux_profile_privacy_defaults();
    if ($userId <= 0) {
        return $defaults;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT show_likes_public, show_bookmarks_public
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return $defaults;
        }

        return [
            'show_likes_public' => !empty($row['show_likes_public']),
            'show_bookmarks_public' => !empty($row['show_bookmarks_public']),
        ];
    } catch (PDOException) {
        return $defaults;
    }
}

function trux_update_profile_privacy_preferences(int $userId, array $submitted): bool {
    if ($userId <= 0) {
        return false;
    }

    $defaults = trux_profile_privacy_defaults();
    $values = [];
    foreach (array_keys($defaults) as $key) {
        $values[$key] = !empty($submitted[$key]) ? 1 : 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE users
             SET show_likes_public = ?,
                 show_bookmarks_public = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $values['show_likes_public'],
            $values['show_bookmarks_public'],
            $userId,
        ]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function trux_profile_user_has_premium(int $userId): bool {
    return false;
}

function trux_profile_upload_is_animated_gif(array $file): bool {
    if (!isset($file['error']) || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'image/gif') {
        return false;
    }

    return trux_profile_is_animated_gif_file($file['tmp_name']);
}

function trux_profile_is_animated_gif_file(string $tmpPath): bool {
    $fh = @fopen($tmpPath, 'rb');
    if ($fh === false) {
        return false;
    }

    $frames = 0;
    $buffer = '';

    while (!feof($fh) && $frames < 2) {
        $chunk = fread($fh, 8192);
        if (!is_string($chunk) || $chunk === '') {
            break;
        }

        $buffer .= $chunk;
        $matches = [];
        $frames += preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $buffer, $matches);

        if (strlen($buffer) > 8192) {
            $buffer = substr($buffer, -8192);
        }
    }

    fclose($fh);
    return $frames > 1;
}

function trux_profile_delete_uploaded_file(?string $publicPath): void {
    trux_delete_uploaded_file($publicPath);
}

function trux_profile_website_label(?string $websiteUrl): string {
    $url = trim((string)$websiteUrl);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return $url;
    }

    $host = strtolower((string)$parts['host']);
    $path = (string)($parts['path'] ?? '');
    if ($path === '/') {
        $path = '';
    }
    if ($path !== '' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }

    $label = $host . $path;
    if (mb_strlen($label) > 52) {
        $label = mb_substr($label, 0, 49) . '...';
    }

    return $label;
}
