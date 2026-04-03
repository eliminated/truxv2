<?php
declare(strict_types=1);

function trux_linked_accounts_schema_supports_v061(): bool {
    static $isSupported = null;
    if (is_bool($isSupported)) {
        return $isSupported;
    }

    try {
        $db = trux_db();
        $stmt = $db->query('SHOW COLUMNS FROM linked_accounts');
        $rows = $stmt->fetchAll();
    } catch (PDOException) {
        $isSupported = false;
        return false;
    }

    $columns = [];
    foreach ($rows as $row) {
        $field = strtolower(trim((string)($row['Field'] ?? '')));
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    $isSupported = isset($columns['status'], $columns['provider_display_name'], $columns['updated_at']);
    return $isSupported;
}

function trux_linked_account_default_redirect_uri(): string {
    return TRUX_BASE_URL . '/settings/link-account-callback.php';
}

function trux_linked_account_oauth_client_id(string $provider): string {
    return match (strtolower(trim($provider))) {
        'discord' => trim(TRUX_DISCORD_CLIENT_ID),
        'google' => trim(TRUX_GOOGLE_CLIENT_ID),
        'facebook' => trim(TRUX_FACEBOOK_CLIENT_ID),
        'x' => trim(TRUX_X_CLIENT_ID),
        default => '',
    };
}

function trux_linked_account_oauth_client_secret(string $provider): string {
    return match (strtolower(trim($provider))) {
        'discord' => trim(TRUX_DISCORD_CLIENT_SECRET),
        'google' => trim(TRUX_GOOGLE_CLIENT_SECRET),
        'facebook' => trim(TRUX_FACEBOOK_CLIENT_SECRET),
        'x' => trim(TRUX_X_CLIENT_SECRET),
        default => '',
    };
}

function trux_linked_account_oauth_redirect_uri(string $provider): string {
    $redirectUri = match (strtolower(trim($provider))) {
        'discord' => trim(TRUX_DISCORD_REDIRECT_URI),
        'google' => trim(TRUX_GOOGLE_REDIRECT_URI),
        'facebook' => trim(TRUX_FACEBOOK_REDIRECT_URI),
        'x' => trim(TRUX_X_REDIRECT_URI),
        default => '',
    };

    return $redirectUri !== '' ? $redirectUri : trux_linked_account_default_redirect_uri();
}

function trux_linked_account_oauth_scopes(string $provider): string {
    $scopes = match (strtolower(trim($provider))) {
        'discord' => trim(TRUX_DISCORD_SCOPES),
        'google' => trim(TRUX_GOOGLE_SCOPES),
        'facebook' => trim(TRUX_FACEBOOK_SCOPES),
        'x' => trim(TRUX_X_SCOPES),
        default => '',
    };

    return match (strtolower(trim($provider))) {
        'discord' => $scopes !== '' ? $scopes : 'identify',
        'google' => $scopes !== '' ? $scopes : 'openid email profile',
        'facebook' => $scopes !== '' ? $scopes : 'public_profile,email',
        'x' => $scopes !== '' ? $scopes : 'users.read tweet.read',
        default => '',
    };
}

function trux_linked_account_provider_is_configured(string $provider): bool {
    $provider = strtolower(trim($provider));
    $clientId = trux_linked_account_oauth_client_id($provider);
    $clientSecret = trux_linked_account_oauth_client_secret($provider);
    $redirectUri = trux_linked_account_oauth_redirect_uri($provider);

    return match ($provider) {
        'discord', 'google', 'facebook' => $clientId !== '' && $clientSecret !== '' && trim($redirectUri) !== '',
        'x' => $clientId !== '' && trim($redirectUri) !== '',
        default => false,
    };
}

function trux_discord_provider_is_configured(): bool {
    return trux_linked_account_provider_is_configured('discord');
}

function trux_linked_account_provider_icon_svg(string $provider): string {
    return match ($provider) {
        'discord' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.54 5.28a16.4 16.4 0 0 0-4.03-1.25.06.06 0 0 0-.06.03c-.17.3-.36.69-.49 1a15.26 15.26 0 0 0-5.92 0 10.3 10.3 0 0 0-.5-1 .06.06 0 0 0-.06-.03 16.34 16.34 0 0 0-4.03 1.25.05.05 0 0 0-.03.02C1.86 9.05 1.18 12.7 1.5 16.31a.07.07 0 0 0 .03.05 16.5 16.5 0 0 0 4.95 2.5.06.06 0 0 0 .07-.02c.38-.53.72-1.09 1.02-1.67a.06.06 0 0 0-.03-.08c-.54-.2-1.05-.44-1.54-.71a.06.06 0 0 1-.01-.1c.1-.08.21-.16.31-.25a.06.06 0 0 1 .06-.01c3.22 1.47 6.7 1.47 9.89 0a.06.06 0 0 1 .06.01c.1.09.2.17.31.25a.06.06 0 0 1-.01.1 10.1 10.1 0 0 1-1.54.71.06.06 0 0 0-.03.08c.3.58.65 1.14 1.02 1.67a.06.06 0 0 0 .07.02 16.44 16.44 0 0 0 4.95-2.5.07.07 0 0 0 .03-.05c.38-4.18-.64-7.8-2.92-11.02a.05.05 0 0 0-.03-.02ZM8.02 14.11c-.97 0-1.77-.89-1.77-1.98 0-1.1.78-1.99 1.77-1.99.99 0 1.78.9 1.77 1.99 0 1.09-.79 1.98-1.77 1.98Zm7.96 0c-.97 0-1.77-.89-1.77-1.98 0-1.1.78-1.99 1.77-1.99.99 0 1.78.9 1.77 1.99 0 1.09-.78 1.98-1.77 1.98Z"/></svg>',
        'nicholic' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 18V6l5.75 7.25V6H18v12l-5.75-7.25V18H6Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M4.5 4.5h15v15h-15z" fill="none" stroke="currentColor" stroke-width="1.4" opacity=".45"/></svg>',
        'google' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v4h5.4a4.7 4.7 0 0 1-2 3.1v2.6h3.2c1.9-1.7 3-4.3 3-7.7Z"/><path fill="currentColor" d="M12 22c2.7 0 4.9-.9 6.5-2.4l-3.2-2.6c-.9.6-2 .9-3.3.9-2.5 0-4.6-1.7-5.4-4H3.3v2.7A10 10 0 0 0 12 22Z"/><path fill="currentColor" d="M6.6 13.9a6 6 0 0 1 0-3.8V7.4H3.3a10 10 0 0 0 0 9.2l3.3-2.7Z"/><path fill="currentColor" d="M12 6a5.5 5.5 0 0 1 3.9 1.5l2.9-2.9A9.8 9.8 0 0 0 12 2 10 10 0 0 0 3.3 7.4l3.3 2.7c.8-2.3 2.9-4.1 5.4-4.1Z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 21v-7h2.8l.5-3.5h-3.3V8.3c0-1 .3-1.8 1.8-1.8H17V3.4c-.3 0-1.3-.2-2.5-.2-2.8 0-4.5 1.7-4.5 4.9v2.4H7v3.5h3V21h3.5Z"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 4h3.7l3 4.3L16.8 4H19l-4.9 5.9L19.6 20h-3.7l-3.3-4.8L8.4 20H6.2l5.3-6.4L6.4 4Z"/></svg>',
    };
}

function trux_linked_account_providers(): array {
    $storageReady = trux_linked_accounts_schema_supports_v061();
    $discordAvailability = trux_linked_account_provider_is_configured('discord') && $storageReady ? 'available' : 'pending_setup';
    $googleAvailability = trux_linked_account_provider_is_configured('google') && $storageReady ? 'available' : 'pending_setup';
    $facebookAvailability = trux_linked_account_provider_is_configured('facebook') && $storageReady ? 'available' : 'pending_setup';
    $xAvailability = trux_linked_account_provider_is_configured('x') && $storageReady ? 'available' : 'pending_setup';

    return [
        'discord' => [
            'label' => 'Discord',
            'brand' => 'Community',
            'availability' => $discordAvailability,
            'supports_oauth' => true,
            'username_prefix' => '@',
            'description' => 'Link Discord for future server notifications, support workflows, and shared identity across TruX and Nicholas Foundation services.',
            'availability_note' => $discordAvailability === 'available'
                ? 'Discord OAuth is ready when the app can reach your configured credentials.'
                : 'Complete the v0.6.1 linked-account migration and set Discord credentials in your environment to enable live linking.',
            'icon_svg' => trux_linked_account_provider_icon_svg('discord'),
        ],
        'nicholic' => [
            'label' => 'Nicholic Account',
            'brand' => 'The Nicholas Foundation',
            'availability' => 'coming_soon',
            'supports_oauth' => false,
            'username_prefix' => '',
            'description' => 'Nicholic will become the first-party identity bridge for Nicholas Foundation support tools, notifications, and future service access.',
            'availability_note' => 'Reserved for first-party Nicholas Foundation identity and ecosystem services.',
            'icon_svg' => trux_linked_account_provider_icon_svg('nicholic'),
        ],
        'google' => [
            'label' => 'Google',
            'brand' => 'Google Identity',
            'availability' => $googleAvailability,
            'supports_oauth' => true,
            'username_prefix' => '',
            'description' => 'Link Google for identity federation, support tooling, and future mail-aware ecosystem integrations.',
            'availability_note' => $googleAvailability === 'available'
                ? 'Google OAuth is ready when the configured callback URL matches your Google app.'
                : 'Set Google OAuth credentials in your environment to enable live linking.',
            'icon_svg' => trux_linked_account_provider_icon_svg('google'),
        ],
        'facebook' => [
            'label' => 'Facebook',
            'brand' => 'Meta Identity',
            'availability' => $facebookAvailability,
            'supports_oauth' => true,
            'username_prefix' => '',
            'description' => 'Link Facebook for future identity federation, user support routing, and ecosystem notification workflows.',
            'availability_note' => $facebookAvailability === 'available'
                ? 'Facebook OAuth is ready when the configured callback URL matches your Meta app.'
                : 'Set Facebook OAuth credentials in your environment to enable live linking.',
            'icon_svg' => trux_linked_account_provider_icon_svg('facebook'),
        ],
        'x' => [
            'label' => 'X',
            'brand' => 'Social Identity',
            'availability' => $xAvailability,
            'supports_oauth' => true,
            'username_prefix' => '@',
            'description' => 'Link X for social identity federation, support touchpoints, and future Nicholas Foundation service integrations.',
            'availability_note' => $xAvailability === 'available'
                ? 'X OAuth is ready when the configured callback URL matches your X app.'
                : 'Set X OAuth credentials in your environment to enable live linking.',
            'icon_svg' => trux_linked_account_provider_icon_svg('x'),
        ],
    ];
}

function trux_linked_account_live_provider_labels(): array {
    $labels = [];
    foreach (trux_linked_account_providers() as $providerMeta) {
        if ((string)($providerMeta['availability'] ?? '') !== 'available') {
            continue;
        }
        $label = trim((string)($providerMeta['label'] ?? ''));
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    return $labels;
}

function trux_normalize_linked_account_provider(string $provider): string {
    $provider = strtolower(trim($provider));
    return array_key_exists($provider, trux_linked_account_providers()) ? $provider : '';
}

function trux_linked_account_provider(string $provider): ?array {
    $provider = trux_normalize_linked_account_provider($provider);
    if ($provider === '') {
        return null;
    }

    $providers = trux_linked_account_providers();
    return $providers[$provider] ?? null;
}

function trux_linked_account_status_presenter(string $availability, ?array $account): array {
    if ($account !== null) {
        $status = strtolower(trim((string)($account['status'] ?? 'connected')));
        return match ($status) {
            'error' => ['label' => 'Error', 'class' => 'is-danger', 'summary' => 'This connection needs attention before it can be trusted again.'],
            'revoked' => ['label' => 'Revoked', 'class' => 'is-danger', 'summary' => 'The provider reports this link as no longer active.'],
            default => ['label' => 'Connected', 'class' => 'is-success', 'summary' => 'This provider identity is linked to your TruX account.'],
        };
    }

    return match ($availability) {
        'available' => ['label' => 'Not connected', 'class' => 'is-muted', 'summary' => 'Ready to link when you choose.'],
        'pending_setup' => ['label' => 'Pending setup', 'class' => 'is-info', 'summary' => 'Provider support is wired, but environment setup still needs to be completed.'],
        default => ['label' => 'Coming soon', 'class' => 'is-info', 'summary' => 'This provider is reserved for a future release.'],
    };
}

function trux_linked_account_metadata_encode(array $metadata): ?string {
    if ($metadata === []) {
        return null;
    }

    $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : null;
}

function trux_linked_account_metadata_decode($value): array {
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function trux_fetch_linked_accounts(int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT id, user_id, provider, provider_user_id, provider_username, provider_email, provider_email_verified, provider_display_name,
                    provider_avatar_url, status, status_reason, metadata_json, linked_at,
                    last_verified_at, last_used_at, last_login_at, created_at, updated_at
             FROM linked_accounts
             WHERE user_id = ?
             ORDER BY provider ASC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException) {
        try {
            $db = trux_db();
            $stmt = $db->prepare(
                'SELECT id, user_id, provider, provider_user_id,
                        NULL AS provider_username,
                        NULL AS provider_email,
                        0 AS provider_email_verified,
                        NULL AS provider_display_name,
                        NULL AS provider_avatar_url,
                        \'connected\' AS status,
                        NULL AS status_reason,
                        NULL AS metadata_json,
                        linked_at,
                        linked_at AS last_verified_at,
                        NULL AS last_used_at,
                        NULL AS last_login_at,
                        linked_at AS created_at,
                        linked_at AS updated_at
                 FROM linked_accounts
                 WHERE user_id = ?
                 ORDER BY provider ASC'
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();
        } catch (PDOException) {
            return [];
        }
    }

    $linked = [];
    foreach ($rows as $row) {
        $provider = trux_normalize_linked_account_provider((string)($row['provider'] ?? ''));
        if ($provider === '') {
            continue;
        }

        $row['metadata'] = trux_linked_account_metadata_decode($row['metadata_json'] ?? null);
        $linked[$provider] = $row;
    }

    return $linked;
}

function trux_fetch_linked_account(int $userId, string $provider): ?array {
    $provider = trux_normalize_linked_account_provider($provider);
    if ($userId <= 0 || $provider === '') {
        return null;
    }

    $linked = trux_fetch_linked_accounts($userId);
    return $linked[$provider] ?? null;
}

function trux_fetch_linked_account_by_external(string $provider, string $providerUserId): ?array {
    $provider = trux_normalize_linked_account_provider($provider);
    $providerUserId = trim($providerUserId);
    if ($provider === '' || $providerUserId === '') {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT id, user_id, provider, provider_user_id, provider_username, provider_email, provider_email_verified, provider_display_name,
                    provider_avatar_url, status, status_reason, metadata_json, linked_at,
                    last_verified_at, last_used_at, last_login_at, created_at, updated_at
             FROM linked_accounts
             WHERE provider = ? AND provider_user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$provider, $providerUserId]);
        $row = $stmt->fetch();
    } catch (PDOException) {
        try {
            $db = trux_db();
            $stmt = $db->prepare(
                'SELECT id, user_id, provider, provider_user_id,
                        NULL AS provider_username,
                        NULL AS provider_email,
                        0 AS provider_email_verified,
                        NULL AS provider_display_name,
                        NULL AS provider_avatar_url,
                        \'connected\' AS status,
                        NULL AS status_reason,
                        NULL AS metadata_json,
                        linked_at,
                        linked_at AS last_verified_at,
                        NULL AS last_used_at,
                        NULL AS last_login_at,
                        linked_at AS created_at,
                        linked_at AS updated_at
                 FROM linked_accounts
                 WHERE provider = ? AND provider_user_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$provider, $providerUserId]);
            $row = $stmt->fetch();
        } catch (PDOException) {
            return null;
        }
    }

    if (!$row) {
        return null;
    }

    $row['metadata'] = trux_linked_account_metadata_decode($row['metadata_json'] ?? null);
    return $row;
}

function trux_count_linked_accounts(int $userId): int {
    if ($userId <= 0) {
        return 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('SELECT COUNT(*) FROM linked_accounts WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_user_has_password_method(int $userId): bool {
    $user = trux_fetch_account_user_by_id($userId, true);
    return $user !== null && trim((string)($user['password_hash'] ?? '')) !== '';
}

function trux_linked_account_provider_identifier(?array $account): string {
    if ($account === null) {
        return '';
    }

    $providerMeta = trux_linked_account_provider((string)($account['provider'] ?? ''));
    $displayName = trim((string)($account['provider_display_name'] ?? ''));
    $username = trim((string)($account['provider_username'] ?? ''));
    $providerUserId = trim((string)($account['provider_user_id'] ?? ''));
    $usernamePrefix = trim((string)($providerMeta['username_prefix'] ?? ''));
    $handle = '';
    if ($username !== '') {
        $normalizedUsername = $usernamePrefix === '@' ? ltrim($username, '@') : $username;
        $handle = $usernamePrefix . $normalizedUsername;
    }

    if ($displayName !== '' && $handle !== '' && strcasecmp($displayName, $username) !== 0) {
        return $displayName . ' (' . $handle . ')';
    }
    if ($displayName !== '') {
        return $displayName;
    }
    if ($handle !== '') {
        return $handle;
    }

    return $providerUserId;
}

function trux_linked_account_cards_for_user(int $userId): array {
    $providers = trux_linked_account_providers();
    $accounts = trux_fetch_linked_accounts($userId);
    $cards = [];

    foreach ($providers as $provider => $providerMeta) {
        $account = $accounts[$provider] ?? null;
        $cards[$provider] = [
            'provider' => $provider,
            'meta' => $providerMeta,
            'account' => $account,
            'presenter' => trux_linked_account_status_presenter((string)($providerMeta['availability'] ?? 'coming_soon'), $account),
            'identifier' => trux_linked_account_provider_identifier($account),
        ];
    }

    return $cards;
}

function trux_linked_account_settings_summary(int $userId): array {
    $accounts = trux_fetch_linked_accounts($userId);
    $summary = [
        'total' => count($accounts),
        'connected' => 0,
        'error' => 0,
        'revoked' => 0,
    ];

    foreach ($accounts as $account) {
        $status = strtolower(trim((string)($account['status'] ?? 'connected')));
        if ($status === 'error') {
            $summary['error']++;
        } elseif ($status === 'revoked') {
            $summary['revoked']++;
        } else {
            $summary['connected']++;
        }
    }

    return $summary;
}

function trux_linked_accounts_oauth_session_key(): string {
    return '_linked_account_oauth';
}

function trux_linked_accounts_oauth_max_age_seconds(): int {
    return 10 * 60;
}

function trux_linked_accounts_clear_oauth_session(): void {
    unset($_SESSION[trux_linked_accounts_oauth_session_key()]);
}

function trux_linked_accounts_store_oauth_session(array $payload): void {
    $_SESSION[trux_linked_accounts_oauth_session_key()] = $payload;
}

function trux_linked_accounts_oauth_session(): ?array {
    $payload = $_SESSION[trux_linked_accounts_oauth_session_key()] ?? null;
    if (!is_array($payload)) {
        return null;
    }

    $createdAt = (int)($payload['created_at'] ?? 0);
    if ($createdAt <= 0 || (time() - $createdAt) > trux_linked_accounts_oauth_max_age_seconds()) {
        trux_linked_accounts_clear_oauth_session();
        return null;
    }

    return $payload;
}

function trux_linked_account_record_activity(string $eventType, int $userId, string $provider, array $metadata = [], ?int $relatedUserId = null): void {
    $cleanMetadata = $metadata;
    $cleanMetadata['provider'] = $provider;

    trux_moderation_record_activity_event($eventType, $userId > 0 ? $userId : null, [
        'subject_type' => 'linked_account',
        'related_user_id' => $relatedUserId,
        'source_url' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'metadata' => $cleanMetadata,
    ]);
}

function trux_linked_account_mark_callback_issue(int $userId, string $provider, string $status, string $reason): void {
    if ($userId <= 0 || trim($reason) === '') {
        return;
    }

    $existing = trux_fetch_linked_account($userId, $provider);
    if ($existing === null) {
        return;
    }

    trux_update_linked_account_status($userId, $provider, $status, $reason);
}

function trux_linked_account_http_request(string $method, string $url, array $headers = [], ?string $body = null): array {
    $method = strtoupper(trim($method));
    $headerLines = [];
    foreach ($headers as $header) {
        if (is_string($header) && trim($header) !== '') {
            $headerLines[] = trim($header);
        }
    }

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        if ($handle === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'transport_init_failed'];
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($handle, CURLOPT_TIMEOUT, 15);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($handle, CURLOPT_HEADER, false);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);
            return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => $error !== '' ? $error : 'transport_failed'];
        }

        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $json = json_decode((string)$responseBody, true);
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string)$responseBody,
            'json' => is_array($json) ? $json : null,
            'error' => null,
        ];
    }

    $headersText = implode("\r\n", $headerLines);
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headersText,
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    $responseHeaders = $http_response_header ?? [];
    foreach ($responseHeaders as $headerLine) {
        if (is_string($headerLine) && preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }

    if ($responseBody === false) {
        return ['ok' => false, 'status' => $status, 'body' => '', 'json' => null, 'error' => 'transport_failed'];
    }

    $json = json_decode($responseBody, true);
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $responseBody,
        'json' => is_array($json) ? $json : null,
        'error' => null,
    ];
}

function trux_discord_avatar_url(string $userId, string $avatarHash): string {
    $extension = str_starts_with($avatarHash, 'a_') ? 'gif' : 'png';
    return 'https://cdn.discordapp.com/avatars/' . rawurlencode($userId) . '/' . rawurlencode($avatarHash) . '.' . $extension . '?size=128';
}

function trux_discord_normalize_identity(array $payload): ?array {
    $providerUserId = trim((string)($payload['id'] ?? ''));
    $username = trim((string)($payload['username'] ?? ''));
    if ($providerUserId === '' || $username === '') {
        return null;
    }

    $displayName = trim((string)($payload['global_name'] ?? ''));
    $avatarHash = trim((string)($payload['avatar'] ?? ''));
    $discriminator = trim((string)($payload['discriminator'] ?? ''));
    $metadata = [];

    if ($displayName !== '') {
        $metadata['global_name'] = $displayName;
    }
    if ($avatarHash !== '') {
        $metadata['avatar_hash'] = $avatarHash;
    }
    if ($discriminator !== '' && $discriminator !== '0') {
        $metadata['discriminator'] = $discriminator;
    }
    if (isset($payload['locale']) && is_string($payload['locale']) && trim($payload['locale']) !== '') {
        $metadata['locale'] = trim($payload['locale']);
    }

    return [
        'provider_user_id' => $providerUserId,
        'provider_username' => $username,
        'provider_email' => trim((string)($payload['email'] ?? '')),
        'provider_email_verified' => !empty($payload['verified']),
        'provider_display_name' => $displayName !== '' ? $displayName : $username,
        'provider_avatar_url' => $avatarHash !== '' ? trux_discord_avatar_url($providerUserId, $avatarHash) : null,
        'metadata' => $metadata,
    ];
}

function trux_google_normalize_identity(array $payload): ?array {
    $providerUserId = trim((string)($payload['sub'] ?? ''));
    if ($providerUserId === '') {
        return null;
    }

    $email = trim((string)($payload['email'] ?? ''));
    $displayName = trim((string)($payload['name'] ?? ''));
    $avatarUrl = trim((string)($payload['picture'] ?? ''));
    $metadata = [];

    if ($email !== '') {
        $metadata['email'] = $email;
    }
    if (array_key_exists('email_verified', $payload)) {
        $metadata['email_verified'] = (bool)$payload['email_verified'];
    }
    if (isset($payload['locale']) && is_string($payload['locale']) && trim($payload['locale']) !== '') {
        $metadata['locale'] = trim($payload['locale']);
    }

    return [
        'provider_user_id' => $providerUserId,
        'provider_username' => $email !== '' ? $email : trim((string)($payload['preferred_username'] ?? '')),
        'provider_email' => $email,
        'provider_email_verified' => !empty($payload['email_verified']),
        'provider_display_name' => $displayName !== '' ? $displayName : ($email !== '' ? $email : $providerUserId),
        'provider_avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        'metadata' => $metadata,
    ];
}

function trux_facebook_normalize_identity(array $payload): ?array {
    $providerUserId = trim((string)($payload['id'] ?? ''));
    if ($providerUserId === '') {
        return null;
    }

    $email = trim((string)($payload['email'] ?? ''));
    $displayName = trim((string)($payload['name'] ?? ''));
    $avatarUrl = '';
    if (isset($payload['picture']['data']['url']) && is_string($payload['picture']['data']['url'])) {
        $avatarUrl = trim($payload['picture']['data']['url']);
    }

    $metadata = [];
    if ($email !== '') {
        $metadata['email'] = $email;
    }

    return [
        'provider_user_id' => $providerUserId,
        'provider_username' => $email,
        'provider_email' => $email,
        'provider_email_verified' => $email !== '',
        'provider_display_name' => $displayName !== '' ? $displayName : ($email !== '' ? $email : $providerUserId),
        'provider_avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        'metadata' => $metadata,
    ];
}

function trux_x_avatar_url(string $avatarUrl): string {
    $trimmed = trim($avatarUrl);
    if ($trimmed === '') {
        return '';
    }

    return preg_replace('/_normal(\.[a-z0-9]+)(\?.*)?$/i', '$1$2', $trimmed) ?? $trimmed;
}

function trux_x_normalize_identity(array $payload): ?array {
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
    $providerUserId = trim((string)($data['id'] ?? ''));
    $username = trim((string)($data['username'] ?? ''));
    if ($providerUserId === '' || $username === '') {
        return null;
    }

    $displayName = trim((string)($data['name'] ?? ''));
    $avatarUrl = trux_x_avatar_url(trim((string)($data['profile_image_url'] ?? '')));

    return [
        'provider_user_id' => $providerUserId,
        'provider_username' => $username,
        'provider_email' => '',
        'provider_email_verified' => false,
        'provider_display_name' => $displayName !== '' ? $displayName : $username,
        'provider_avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        'metadata' => [],
    ];
}

function trux_linked_account_base64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function trux_linked_account_generate_pkce_verifier(): string {
    return trux_linked_account_base64url_encode(random_bytes(64));
}

function trux_linked_account_pkce_challenge(string $verifier): string {
    return trux_linked_account_base64url_encode(hash('sha256', $verifier, true));
}

function trux_linked_account_oauth_authorize_url(string $provider, string $state, ?string $pkceVerifier = null): string {
    $provider = trux_normalize_linked_account_provider($provider);
    $query = [
        'client_id' => trux_linked_account_oauth_client_id($provider),
        'redirect_uri' => trux_linked_account_oauth_redirect_uri($provider),
        'response_type' => 'code',
        'state' => $state,
    ];

    return match ($provider) {
        'discord' => 'https://discord.com/oauth2/authorize?' . http_build_query($query + [
            'scope' => trux_linked_account_oauth_scopes($provider),
            'prompt' => 'consent',
        ]),
        'google' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query + [
            'scope' => trux_linked_account_oauth_scopes($provider),
            'prompt' => 'select_account',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
        ]),
        'facebook' => 'https://www.facebook.com/dialog/oauth?' . http_build_query($query + [
            'scope' => trux_linked_account_oauth_scopes($provider),
        ]),
        'x' => 'https://twitter.com/i/oauth2/authorize?' . http_build_query($query + [
            'scope' => trux_linked_account_oauth_scopes($provider),
            'code_challenge' => $pkceVerifier !== null && trim($pkceVerifier) !== '' ? trux_linked_account_pkce_challenge($pkceVerifier) : '',
            'code_challenge_method' => 'S256',
        ]),
        default => '',
    };
}

function trux_linked_account_oauth_exchange_code(string $provider, string $code, ?string $pkceVerifier = null): array {
    $provider = trux_normalize_linked_account_provider($provider);
    $clientId = trux_linked_account_oauth_client_id($provider);
    $clientSecret = trux_linked_account_oauth_client_secret($provider);
    $redirectUri = trux_linked_account_oauth_redirect_uri($provider);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ];

    $payload = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ];

    $tokenUrl = match ($provider) {
        'discord' => 'https://discord.com/api/oauth2/token',
        'google' => 'https://oauth2.googleapis.com/token',
        'facebook' => 'https://graph.facebook.com/oauth/access_token',
        'x' => 'https://api.twitter.com/2/oauth2/token',
        default => '',
    };

    if ($provider === 'discord') {
        $payload['client_id'] = $clientId;
        $payload['client_secret'] = $clientSecret;
        $payload['scope'] = trux_linked_account_oauth_scopes($provider);
    } elseif ($provider === 'google' || $provider === 'facebook') {
        $payload['client_id'] = $clientId;
        $payload['client_secret'] = $clientSecret;
    } elseif ($provider === 'x') {
        $payload['code_verifier'] = trim((string)$pkceVerifier);
        if ($clientSecret !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
        } else {
            $payload['client_id'] = $clientId;
        }
    }

    if ($tokenUrl === '') {
        return ['ok' => false, 'error' => 'token_exchange_failed', 'response' => null];
    }

    $response = trux_linked_account_http_request(
        'POST',
        $tokenUrl,
        $headers,
        http_build_query($payload)
    );

    if (!($response['ok'] ?? false)) {
        return ['ok' => false, 'error' => 'token_exchange_failed', 'response' => $response];
    }

    $json = is_array($response['json'] ?? null) ? $response['json'] : [];
    $accessToken = trim((string)($json['access_token'] ?? ''));
    if ($accessToken === '') {
        return ['ok' => false, 'error' => 'token_missing', 'response' => $response];
    }

    return ['ok' => true, 'access_token' => $accessToken, 'response' => $response];
}

function trux_linked_account_fetch_identity(string $provider, string $accessToken): array {
    $provider = trux_normalize_linked_account_provider($provider);
    $headers = ['Accept: application/json'];
    $identityUrl = '';

    if ($provider === 'discord') {
        $identityUrl = 'https://discord.com/api/users/@me';
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    } elseif ($provider === 'google') {
        $identityUrl = 'https://openidconnect.googleapis.com/v1/userinfo';
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    } elseif ($provider === 'facebook') {
        $identityUrl = 'https://graph.facebook.com/me?fields=id,name,email,picture.type(large)&access_token=' . rawurlencode($accessToken);
    } elseif ($provider === 'x') {
        $identityUrl = 'https://api.twitter.com/2/users/me?user.fields=id,name,username,profile_image_url';
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    if ($identityUrl === '') {
        return ['ok' => false, 'error' => 'identity_fetch_failed', 'response' => null];
    }

    $response = trux_linked_account_http_request('GET', $identityUrl, $headers);
    if (!($response['ok'] ?? false)) {
        return ['ok' => false, 'error' => 'identity_fetch_failed', 'response' => $response];
    }

    $json = is_array($response['json'] ?? null) ? $response['json'] : [];
    return ['ok' => true, 'identity' => $json, 'response' => $response];
}

function trux_linked_account_normalize_identity(string $provider, array $payload): ?array {
    $provider = trux_normalize_linked_account_provider($provider);

    return match ($provider) {
        'discord' => trux_discord_normalize_identity($payload),
        'google' => trux_google_normalize_identity($payload),
        'facebook' => trux_facebook_normalize_identity($payload),
        'x' => trux_x_normalize_identity($payload),
        default => null,
    };
}

function trux_linked_account_save_connection(int $userId, string $provider, array $identity): array {
    $provider = trux_normalize_linked_account_provider($provider);
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if ($provider === '') {
        return ['ok' => false, 'error' => 'invalid_provider'];
    }
    if (!trux_linked_accounts_schema_supports_v061()) {
        return ['ok' => false, 'error' => 'schema_not_ready'];
    }

    $providerUserId = trim((string)($identity['provider_user_id'] ?? ''));
    if ($providerUserId === '') {
        return ['ok' => false, 'error' => 'identity_missing'];
    }

    $existingExternal = trux_fetch_linked_account_by_external($provider, $providerUserId);
    if ($existingExternal && (int)($existingExternal['user_id'] ?? 0) !== $userId) {
        return [
            'ok' => false,
            'error' => 'already_linked_elsewhere',
            'existing_user_id' => (int)($existingExternal['user_id'] ?? 0),
        ];
    }

    $providerUsername = trim((string)($identity['provider_username'] ?? ''));
    $providerEmail = trim((string)($identity['provider_email'] ?? ''));
    $providerEmailVerified = !empty($identity['provider_email_verified']) ? 1 : 0;
    $providerDisplayName = trim((string)($identity['provider_display_name'] ?? ''));
    $providerAvatarUrl = trim((string)($identity['provider_avatar_url'] ?? ''));
    $metadataJson = trux_linked_account_metadata_encode(is_array($identity['metadata'] ?? null) ? $identity['metadata'] : []);
    $existing = trux_fetch_linked_account($userId, $provider);

    try {
        $db = trux_db();
        if ($existing) {
            $stmt = $db->prepare(
                'UPDATE linked_accounts
                 SET provider_user_id = ?,
                     provider_username = ?,
                     provider_email = ?,
                     provider_email_verified = ?,
                     provider_display_name = ?,
                     provider_avatar_url = ?,
                     status = ?,
                     status_reason = NULL,
                     metadata_json = ?,
                     linked_at = NOW(),
                     last_verified_at = NOW(),
                     last_used_at = NOW(),
                     updated_at = NOW()
                 WHERE user_id = ? AND provider = ?'
            );
            $stmt->execute([
                $providerUserId,
                $providerUsername !== '' ? $providerUsername : null,
                $providerEmail !== '' ? $providerEmail : null,
                $providerEmailVerified,
                $providerDisplayName !== '' ? $providerDisplayName : null,
                $providerAvatarUrl !== '' ? $providerAvatarUrl : null,
                'connected',
                $metadataJson,
                $userId,
                $provider,
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO linked_accounts (
                    user_id, provider, provider_user_id, provider_username, provider_email, provider_email_verified, provider_display_name,
                    provider_avatar_url, status, status_reason, metadata_json, linked_at,
                    last_verified_at, last_used_at, created_at, updated_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, NOW(), NOW(), NOW(), NOW(), NOW())'
            );
            $stmt->execute([
                $userId,
                $provider,
                $providerUserId,
                $providerUsername !== '' ? $providerUsername : null,
                $providerEmail !== '' ? $providerEmail : null,
                $providerEmailVerified,
                $providerDisplayName !== '' ? $providerDisplayName : null,
                $providerAvatarUrl !== '' ? $providerAvatarUrl : null,
                'connected',
                $metadataJson,
            ]);
        }
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() === '23000') {
            return ['ok' => false, 'error' => 'already_linked_elsewhere'];
        }

        return ['ok' => false, 'error' => 'save_failed'];
    }

    return [
        'ok' => true,
        'account' => trux_fetch_linked_account($userId, $provider),
        'action' => $existing ? 'relinked' : 'linked',
    ];
}

function trux_update_linked_account_status(int $userId, string $provider, string $status, ?string $reason = null): bool {
    $provider = trux_normalize_linked_account_provider($provider);
    $status = strtolower(trim($status));
    if ($userId <= 0 || $provider === '' || !in_array($status, ['connected', 'error', 'revoked'], true)) {
        return false;
    }
    if (!trux_linked_accounts_schema_supports_v061()) {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE linked_accounts
             SET status = ?, status_reason = ?, updated_at = NOW()
             WHERE user_id = ? AND provider = ?'
        );
        $stmt->execute([
            $status,
            $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            $userId,
            $provider,
        ]);
        return $stmt->rowCount() > 0;
    } catch (PDOException) {
        return false;
    }
}

function trux_unlink_linked_account(int $userId, string $provider): array {
    $provider = trux_normalize_linked_account_provider($provider);
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if ($provider === '') {
        return ['ok' => false, 'error' => 'invalid_provider'];
    }

    if (!trux_user_has_password_method($userId) && trux_count_linked_accounts($userId) <= 1) {
        return ['ok' => false, 'error' => 'last_auth_method'];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'DELETE FROM linked_accounts
             WHERE user_id = ? AND provider = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $provider]);
        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'error' => 'not_linked'];
        }
    } catch (PDOException) {
        return ['ok' => false, 'error' => 'delete_failed'];
    }

    return ['ok' => true];
}

function trux_linked_account_touch_provider_login(int $userId, string $provider): void {
    $provider = trux_normalize_linked_account_provider($provider);
    if ($userId <= 0 || $provider === '') {
        return;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE linked_accounts
             SET last_login_at = NOW(), last_used_at = NOW(), updated_at = NOW()
             WHERE user_id = ? AND provider = ?'
        );
        $stmt->execute([$userId, $provider]);
    } catch (PDOException) {
        // Ignore when the migration is missing.
    }
}

function trux_start_linked_account_flow(?int $userId, string $provider, string $redirectPath = '/settings.php?section=linked-accounts', string $mode = 'link'): array {
    $provider = trux_normalize_linked_account_provider($provider);
    $mode = $mode === 'login' ? 'login' : 'link';
    if ($mode === 'link' && (int)$userId <= 0) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    $providerMeta = trux_linked_account_provider($provider);
    if ($providerMeta === null) {
        return ['ok' => false, 'error' => 'invalid_provider'];
    }
    if (empty($providerMeta['supports_oauth'])) {
        return ['ok' => false, 'error' => 'unsupported_provider'];
    }

    $availability = (string)($providerMeta['availability'] ?? 'coming_soon');
    if ($availability !== 'available') {
        return ['ok' => false, 'error' => $availability === 'pending_setup' ? 'pending_setup' : 'coming_soon'];
    }

    try {
        $state = bin2hex(random_bytes(24));
        $pkceVerifier = $provider === 'x' ? trux_linked_account_generate_pkce_verifier() : null;
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'state_generation_failed'];
    }

    $existing = $mode === 'link' ? trux_fetch_linked_account((int)$userId, $provider) : null;
    $sessionPayload = [
        'provider' => $provider,
        'user_id' => (int)$userId,
        'mode' => $mode,
        'state' => $state,
        'created_at' => time(),
        'redirect_path' => trux_safe_local_redirect_path($redirectPath, $mode === 'login' ? '/login.php' : '/settings.php?section=linked-accounts'),
    ];
    if ($pkceVerifier !== null && $pkceVerifier !== '') {
        $sessionPayload['pkce_verifier'] = $pkceVerifier;
    }
    trux_linked_accounts_store_oauth_session($sessionPayload);

    if ($mode === 'link' && (int)$userId > 0) {
        trux_linked_account_record_activity(
            'linked_account_oauth_started',
            (int)$userId,
            $provider,
            ['mode' => $existing ? 'relink' : 'link']
        );
    }

    return ['ok' => true, 'redirect_url' => trux_linked_account_oauth_authorize_url($provider, $state, $pkceVerifier ?? null)];
}

function trux_complete_linked_account_callback(?int $currentUserId, array $query): array {
    $session = trux_linked_accounts_oauth_session();
    if ($session === null) {
        return ['ok' => false, 'error' => 'session_missing', 'redirect' => '/login.php'];
    }

    $provider = trux_normalize_linked_account_provider((string)($session['provider'] ?? ''));
    $mode = (string)($session['mode'] ?? 'link') === 'login' ? 'login' : 'link';
    $redirectDefault = $mode === 'login' ? '/login.php' : '/settings.php?section=linked-accounts';
    $redirectPath = trux_safe_local_redirect_path((string)($session['redirect_path'] ?? $redirectDefault), $redirectDefault);
    if ($provider === '') {
        trux_linked_accounts_clear_oauth_session();
        return ['ok' => false, 'error' => 'invalid_provider', 'redirect' => $redirectPath, 'mode' => $mode];
    }

    $expectedUserId = (int)($session['user_id'] ?? 0);
    if ($mode === 'link' && ((int)$currentUserId <= 0 || (int)$currentUserId !== $expectedUserId)) {
        trux_linked_accounts_clear_oauth_session();
        return ['ok' => false, 'error' => 'user_mismatch', 'redirect' => $redirectPath, 'provider' => $provider, 'mode' => $mode];
    }

    $expectedState = trim((string)($session['state'] ?? ''));
    $receivedState = trim((string)($query['state'] ?? ''));
    if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)) {
        trux_linked_accounts_clear_oauth_session();
        if ($mode === 'link' && (int)$currentUserId > 0) {
            trux_linked_account_record_activity('linked_account_oauth_failed', (int)$currentUserId, $provider, ['reason' => 'invalid_state']);
        }
        return ['ok' => false, 'error' => 'invalid_state', 'redirect' => $redirectPath, 'provider' => $provider, 'mode' => $mode];
    }

    $providerError = trim((string)($query['error'] ?? ''));
    if ($providerError !== '') {
        trux_linked_accounts_clear_oauth_session();
        if ($mode === 'link' && (int)$currentUserId > 0) {
            trux_linked_account_mark_callback_issue((int)$currentUserId, $provider, 'revoked', 'Provider access was denied during the relink attempt.');
            trux_linked_account_record_activity('linked_account_oauth_failed', (int)$currentUserId, $provider, ['reason' => $providerError]);
        }
        return ['ok' => false, 'error' => 'provider_denied', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => $mode];
    }

    $code = trim((string)($query['code'] ?? ''));
    if ($code === '') {
        trux_linked_accounts_clear_oauth_session();
        if ($mode === 'link' && (int)$currentUserId > 0) {
            trux_linked_account_mark_callback_issue((int)$currentUserId, $provider, 'error', 'The provider callback did not include an authorization code.');
            trux_linked_account_record_activity('linked_account_oauth_failed', (int)$currentUserId, $provider, ['reason' => 'missing_code']);
        }
        return ['ok' => false, 'error' => 'missing_code', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => $mode];
    }

    $pkceVerifier = trim((string)($session['pkce_verifier'] ?? ''));
    $tokenResult = trux_linked_account_oauth_exchange_code($provider, $code, $pkceVerifier !== '' ? $pkceVerifier : null);
    if (!($tokenResult['ok'] ?? false)) {
        trux_linked_accounts_clear_oauth_session();
        if ($mode === 'link' && (int)$currentUserId > 0) {
            trux_linked_account_mark_callback_issue((int)$currentUserId, $provider, 'error', 'Token exchange with the provider failed during relink.');
            trux_linked_account_record_activity('linked_account_oauth_failed', (int)$currentUserId, $provider, ['reason' => (string)($tokenResult['error'] ?? 'token_exchange_failed')]);
        }
        return ['ok' => false, 'error' => 'token_exchange_failed', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => $mode];
    }

    $identityResult = trux_linked_account_fetch_identity($provider, (string)$tokenResult['access_token']);
    if (!($identityResult['ok'] ?? false)) {
        trux_linked_accounts_clear_oauth_session();
        if ($mode === 'link' && (int)$currentUserId > 0) {
            trux_linked_account_mark_callback_issue((int)$currentUserId, $provider, 'error', 'Provider identity lookup failed during relink.');
            trux_linked_account_record_activity('linked_account_oauth_failed', (int)$currentUserId, $provider, ['reason' => (string)($identityResult['error'] ?? 'identity_fetch_failed')]);
        }
        return ['ok' => false, 'error' => 'identity_fetch_failed', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => $mode];
    }

    $normalizedIdentity = trux_linked_account_normalize_identity($provider, (array)($identityResult['identity'] ?? []));
    if ($normalizedIdentity === null) {
        trux_linked_accounts_clear_oauth_session();
        if ($mode === 'link' && (int)$currentUserId > 0) {
            trux_linked_account_mark_callback_issue((int)$currentUserId, $provider, 'error', 'Provider returned incomplete identity data during relink.');
            trux_linked_account_record_activity('linked_account_oauth_failed', (int)$currentUserId, $provider, ['reason' => 'identity_invalid']);
        }
        return ['ok' => false, 'error' => 'identity_invalid', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => $mode];
    }

    if ($mode === 'login') {
        $linkedAccount = trux_fetch_linked_account_by_external($provider, (string)($normalizedIdentity['provider_user_id'] ?? ''));
        trux_linked_accounts_clear_oauth_session();
        if ($linkedAccount) {
            if (strtolower(trim((string)($linkedAccount['status'] ?? 'connected'))) !== 'connected') {
                return ['ok' => false, 'error' => 'link_inactive', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => 'login'];
            }
            return [
                'ok' => true,
                'mode' => 'login',
                'provider' => $provider,
                'redirect' => $redirectPath,
                'login_user_id' => (int)($linkedAccount['user_id'] ?? 0),
                'identity' => $normalizedIdentity,
                'account' => $linkedAccount,
            ];
        }

        $providerEmail = trux_normalize_email_address((string)($normalizedIdentity['provider_email'] ?? ''));
        if ($providerEmail !== '' && trux_fetch_user_by_email($providerEmail)) {
            return ['ok' => false, 'error' => 'provider_email_conflict', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => 'login'];
        }

        return ['ok' => false, 'error' => 'not_linked_for_login', 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => 'login'];
    }

    $saveResult = trux_linked_account_save_connection((int)$currentUserId, $provider, $normalizedIdentity);
    trux_linked_accounts_clear_oauth_session();

    if (!($saveResult['ok'] ?? false)) {
        $error = (string)($saveResult['error'] ?? 'save_failed');
        $relatedUserId = $error === 'already_linked_elsewhere' ? (int)($saveResult['existing_user_id'] ?? 0) : null;
        trux_linked_account_record_activity('linked_account_conflict', (int)$currentUserId, $provider, ['reason' => $error], $relatedUserId);
        return ['ok' => false, 'error' => $error, 'provider' => $provider, 'redirect' => $redirectPath, 'mode' => $mode];
    }

    trux_linked_account_record_activity(
        'linked_account_linked',
        (int)$currentUserId,
        $provider,
        [
            'mode' => (string)($saveResult['action'] ?? 'linked'),
            'provider_user_id' => (string)($normalizedIdentity['provider_user_id'] ?? ''),
            'provider_username' => (string)($normalizedIdentity['provider_username'] ?? ''),
        ]
    );

    return [
        'ok' => true,
        'mode' => $mode,
        'provider' => $provider,
        'redirect' => $redirectPath,
        'account' => $saveResult['account'] ?? null,
        'action' => (string)($saveResult['action'] ?? 'linked'),
    ];
}
