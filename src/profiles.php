<?php
declare(strict_types=1);

function trux_profile_normalize_payload(array $input): array {
    $errors = [];

    $displayName = trim((string)($input['display_name'] ?? ''));
    $bio = trim(str_replace(["\r\n", "\r"], "\n", (string)($input['bio'] ?? '')));
    $location = trim((string)($input['location'] ?? ''));
    $websiteRaw = trim((string)($input['website_url'] ?? ''));

    if ($displayName !== '' && mb_strlen($displayName) > 80) {
        $errors[] = 'Display name must be 80 characters or less.';
    }

    if ($bio !== '' && mb_strlen($bio) > 280) {
        $errors[] = 'Bio must be 280 characters or less.';
    }

    if ($location !== '' && mb_strlen($location) > 100) {
        $errors[] = 'Location must be 100 characters or less.';
    }

    $websiteUrl = null;
    if ($websiteRaw !== '') {
        if (!preg_match('#^https?://#i', $websiteRaw)) {
            $websiteRaw = 'https://' . $websiteRaw;
        }

        if (mb_strlen($websiteRaw) > 255) {
            $errors[] = 'Website URL must be 255 characters or less.';
        } else {
            $validated = filter_var($websiteRaw, FILTER_VALIDATE_URL);
            $parts = $validated !== false ? parse_url((string)$validated) : false;
            $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
            $host = is_array($parts) ? (string)($parts['host'] ?? '') : '';

            if ($validated === false || $host === '' || !in_array($scheme, ['http', 'https'], true)) {
                $errors[] = 'Website URL must be a valid http(s) address.';
            } else {
                $websiteUrl = (string)$validated;
            }
        }
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'data' => [
            'display_name' => $displayName !== '' ? $displayName : null,
            'bio' => $bio !== '' ? $bio : null,
            'location' => $location !== '' ? $location : null,
            'website_url' => $websiteUrl,
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
                 location = ?,
                 website_url = ?,
                 avatar_path = ?,
                 banner_path = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $profileData['display_name'] ?? null,
            $profileData['bio'] ?? null,
            $profileData['location'] ?? null,
            $profileData['website_url'] ?? null,
            $profileData['avatar_path'] ?? null,
            $profileData['banner_path'] ?? null,
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
    if (!is_string($publicPath) || $publicPath === '') {
        return;
    }

    if (!preg_match('#^/uploads/[A-Za-z0-9._-]+$#', $publicPath)) {
        return;
    }

    $abs = dirname(__DIR__) . '/public' . $publicPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
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
