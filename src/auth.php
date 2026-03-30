<?php
declare(strict_types=1);

function trux_normalize_email_address(string $email): string {
    return strtolower(trim($email));
}

function trux_current_app_datetime(): string {
    return date('Y-m-d H:i:s');
}

function trux_email_verification_ttl_seconds(): int {
    return 5 * 60;
}

function trux_email_verification_resend_cooldown_seconds(): int {
    return 5 * 60;
}

function trux_email_verification_cooldown_remaining_seconds(?string $sentAt): int {
    $value = trim((string)$sentAt);
    if ($value === '') {
        return 0;
    }

    $sent = trux_parse_datetime($value);
    if (!$sent) {
        return 0;
    }

    $availableAt = $sent->getTimestamp() + trux_email_verification_resend_cooldown_seconds();
    return max(0, $availableAt - time());
}

function trux_email_verification_is_expired(?string $sentAt): bool {
    $value = trim((string)$sentAt);
    if ($value === '') {
        return true;
    }

    $sent = trux_parse_datetime($value);
    if (!$sent) {
        return true;
    }

    return ($sent->getTimestamp() + trux_email_verification_ttl_seconds()) < time();
}

function trux_email_verification_cooldown_text(int $remainingSeconds): string {
    $minutes = (int)max(1, ceil($remainingSeconds / 60));
    return 'You can resend another verification email in about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
}

function trux_is_logged_in(): bool {
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

function trux_current_user(): ?array {
    if (!trux_is_logged_in()) {
        return null;
    }

    $db = trux_db();
    try {
        $stmt = $db->prepare(
            'SELECT id, username, email, display_name, bio, about_me, location, website_url, profile_links_json,
                    avatar_path, banner_path, show_likes_public, show_bookmarks_public,
                    notify_report_updates_default, staff_role, email_domain_unrecognized,
                    email_verified, email_verify_sent_at, created_at
             FROM users
             WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();
        return $u ?: null;
    } catch (PDOException) {
        $stmt = $db->prepare(
            'SELECT id, username, email, display_name, bio, about_me, location, website_url, profile_links_json,
                    avatar_path, banner_path, show_likes_public, show_bookmarks_public,
                    notify_report_updates_default, staff_role,
                    0 AS email_domain_unrecognized,
                    0 AS email_verified,
                    NULL AS email_verify_sent_at,
                    created_at
             FROM users
             WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();
        return $u ?: null;
    }
}

function trux_require_login(): void {
    if (trux_is_logged_in()) return;
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

function trux_login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function trux_logout_user(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();
}

function trux_fetch_user_by_email(string $email): ?array {
    $email = trux_normalize_email_address($email);
    if ($email === '') {
        return null;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT id, username, email, display_name, email_domain_unrecognized, email_verified, email_verify_sent_at
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        try {
            $db = trux_db();
            $stmt = $db->prepare(
                'SELECT id, username, email, display_name,
                        0 AS email_domain_unrecognized,
                        0 AS email_verified,
                        NULL AS email_verify_sent_at
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException) {
            return null;
        }
    }
}

function trux_fetch_account_user_by_id(int $userId, bool $includePasswordHash = false): ?array {
    if ($userId <= 0) {
        return null;
    }

    $fields = [
        'id',
        'username',
        'email',
        'email_domain_unrecognized',
        'email_verified',
        'email_verify_token',
        'email_verify_sent_at',
    ];
    if ($includePasswordHash) {
        $fields[] = 'password_hash';
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT ' . implode(', ', $fields) . '
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException) {
        $fallbackFields = [
            'id',
            'username',
            'email',
            '0 AS email_domain_unrecognized',
            '0 AS email_verified',
            'NULL AS email_verify_token',
            'NULL AS email_verify_sent_at',
        ];
        if ($includePasswordHash) {
            $fallbackFields[] = 'password_hash';
        }

        try {
            $db = trux_db();
            $stmt = $db->prepare(
                'SELECT ' . implode(', ', $fallbackFields) . '
                 FROM users
                 WHERE id = ?
                 LIMIT 1'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException) {
            return null;
        }
    }
}

function trux_fetch_account_settings_state(int $userId): ?array {
    $user = trux_fetch_account_user_by_id($userId);
    if (!$user) {
        return null;
    }

    $sentAt = (string)($user['email_verify_sent_at'] ?? '');
    $user['email_verified'] = !empty($user['email_verified']);
    $user['email_domain_unrecognized'] = !empty($user['email_domain_unrecognized']);
    $user['verification_expired'] = !$user['email_verified'] && trux_email_verification_is_expired($sentAt);
    $user['verification_cooldown_remaining'] = $user['email_verified']
        ? 0
        : trux_email_verification_cooldown_remaining_seconds($sentAt);
    $user['verification_can_resend'] = $user['email_verified']
        ? false
        : ((int)$user['verification_cooldown_remaining'] === 0);

    return $user;
}

function trux_issue_email_verification_token(int $userId, bool $respectCooldown = false): array {
    $user = trux_fetch_account_user_by_id($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    if (!empty($user['email_verified'])) {
        return ['ok' => false, 'error' => 'already_verified', 'user' => $user];
    }

    $email = trux_normalize_email_address((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email', 'user' => $user];
    }

    $remaining = trux_email_verification_cooldown_remaining_seconds((string)($user['email_verify_sent_at'] ?? ''));
    if ($respectCooldown && $remaining > 0) {
        return ['ok' => false, 'error' => 'cooldown', 'remaining' => $remaining, 'user' => $user];
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'token_generation_failed', 'user' => $user];
    }

    $issuedAt = trux_current_app_datetime();

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE users
             SET email_verify_token = ?, email_verify_sent_at = ?
             WHERE id = ? AND email_verified = 0'
        );
        $stmt->execute([$token, $issuedAt, $userId]);
    } catch (PDOException) {
        return ['ok' => false, 'error' => 'update_failed', 'user' => $user];
    }

    $user['email'] = $email;
    $user['email_verify_token'] = $token;
    $user['email_verify_sent_at'] = $issuedAt;

    return [
        'ok' => true,
        'token' => $token,
        'user' => $user,
    ];
}

function trux_verify_email_token(int $userId, string $token): array {
    $token = trim($token);
    if ($userId <= 0 || $token === '') {
        return ['ok' => false, 'error' => 'invalid'];
    }

    $user = trux_fetch_account_user_by_id($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'invalid'];
    }

    if (!empty($user['email_verified'])) {
        return ['ok' => false, 'error' => 'already_verified', 'user' => $user];
    }

    $storedToken = trim((string)($user['email_verify_token'] ?? ''));
    if ($storedToken === '' || !hash_equals($storedToken, $token)) {
        return ['ok' => false, 'error' => 'invalid', 'user' => $user];
    }

    if (trux_email_verification_is_expired((string)($user['email_verify_sent_at'] ?? ''))) {
        return ['ok' => false, 'error' => 'expired', 'user' => $user];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE users
             SET email_verified = 1,
                 email_verify_token = NULL,
                 email_verify_sent_at = NULL
             WHERE id = ? AND email_verify_token = ?'
        );
        $stmt->execute([$userId, $storedToken]);
    } catch (PDOException) {
        return ['ok' => false, 'error' => 'update_failed', 'user' => $user];
    }

    return ['ok' => true, 'user' => $user];
}

function trux_register_user(string $username, string $email, string $password): array {
    $username = trim($username);
    $email = trux_normalize_email_address($email);

    $errors = [];

    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $errors[] = 'Username must be 3-32 characters, letters/numbers/underscore only.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) return ['ok' => false, 'errors' => ['Could not hash password.']];

    $domainValidation = validate_email_domain($email);
    $emailDomainUnrecognized = !($domainValidation['recognized'] ?? false) ? 1 : 0;

    try {
        $verifyToken = bin2hex(random_bytes(32));
    } catch (Throwable) {
        return ['ok' => false, 'errors' => ['Could not generate verification token.']];
    }

    $issuedAt = trux_current_app_datetime();

    $db = trux_db();
    try {
        $stmt = $db->prepare(
            'INSERT INTO users (
                username,
                email,
                password_hash,
                email_domain_unrecognized,
                email_verified,
                email_verify_token,
                email_verify_sent_at
             ) VALUES (?, ?, ?, ?, 0, ?, ?)'
        );
        $stmt->execute([$username, $email, $hash, $emailDomainUnrecognized, $verifyToken, $issuedAt]);
        $userId = (int)$db->lastInsertId();
        return [
            'ok' => true,
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'email_domain' => $domainValidation,
            'verification_token' => $verifyToken,
        ];
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') {
            return ['ok' => false, 'errors' => ['Username or email is already in use.']];
        }
        return ['ok' => false, 'errors' => ['Database error.']];
    }
}

function trux_update_account_email(int $userId, string $email): array {
    if ($userId <= 0) {
        return ['ok' => false, 'errors' => ['Account not found.']];
    }

    $email = trux_normalize_email_address($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'errors' => ['Please enter a valid email address.']];
    }

    $currentUser = trux_fetch_account_user_by_id($userId);
    if (!$currentUser) {
        return ['ok' => false, 'errors' => ['Account not found.']];
    }

    $currentEmail = trux_normalize_email_address((string)($currentUser['email'] ?? ''));
    $domainValidation = validate_email_domain($email);

    if ($currentEmail === $email) {
        return [
            'ok' => true,
            'changed' => false,
            'email' => $email,
            'email_domain' => $domainValidation,
        ];
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable) {
        return ['ok' => false, 'errors' => ['Could not generate a new verification token right now.']];
    }

    $issuedAt = trux_current_app_datetime();

    $emailDomainUnrecognized = !($domainValidation['recognized'] ?? false) ? 1 : 0;

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE users
             SET email = ?,
                 email_domain_unrecognized = ?,
                 email_verified = 0,
                 email_verify_token = ?,
                 email_verify_sent_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$email, $emailDomainUnrecognized, $token, $issuedAt, $userId]);
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') {
            return ['ok' => false, 'errors' => ['That email address is already in use.']];
        }
        return ['ok' => false, 'errors' => ['Could not update your email address right now.']];
    }

    return [
        'ok' => true,
        'changed' => true,
        'email' => $email,
        'email_domain' => $domainValidation,
        'verification_token' => $token,
        'username' => (string)($currentUser['username'] ?? ''),
    ];
}

function trux_change_password(int $userId, string $currentPassword, string $newPassword): array {
    $currentPassword = (string)$currentPassword;
    $newPassword = (string)$newPassword;

    if ($userId <= 0) {
        return ['ok' => false, 'errors' => ['Account not found.']];
    }

    $user = trux_fetch_account_user_by_id($userId, true);
    if (!$user) {
        return ['ok' => false, 'errors' => ['Account not found.']];
    }

    if (empty($user['email_verified'])) {
        return ['ok' => false, 'errors' => ['Verify control of your email address by opening the email link before changing your password.']];
    }

    $hash = (string)($user['password_hash'] ?? '');
    if ($hash === '') {
        return ['ok' => false, 'errors' => ['Password changes are unavailable for this account.']];
    }

    $errors = [];
    if ($currentPassword === '' || !password_verify($currentPassword, $hash)) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (mb_strlen($newPassword) < 8) {
        $errors[] = 'Your new password must be at least 8 characters.';
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $nextHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($nextHash === false) {
        return ['ok' => false, 'errors' => ['Could not secure your new password right now.']];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$nextHash, $userId]);
    } catch (PDOException) {
        return ['ok' => false, 'errors' => ['Could not update your password right now.']];
    }

    return ['ok' => true];
}

function trux_attempt_login(string $login, string $password): array {
    $login = trim($login);

    if ($login === '' || $password === '') {
        return ['ok' => false, 'error' => 'Please enter your username/email and password.'];
    }

    $db = trux_db();
    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $u = $stmt->fetch();

    if (!$u || !isset($u['password_hash'])) {
        trux_moderation_record_activity_event('login_failed', null, [
            'metadata' => [
                'login_identifier' => $login,
            ],
        ]);
        return ['ok' => false, 'error' => 'Invalid credentials.'];
    }
    if (!password_verify($password, (string)$u['password_hash'])) {
        trux_moderation_record_activity_event('login_failed', (int)$u['id'], [
            'metadata' => [
                'login_identifier' => $login,
            ],
        ]);
        return ['ok' => false, 'error' => 'Invalid credentials.'];
    }

    $activeRestriction = trux_moderation_fetch_active_access_block((int)$u['id']);
    if ($activeRestriction) {
        trux_moderation_record_activity_event('login_blocked_by_enforcement', (int)$u['id'], [
            'subject_type' => 'user_enforcement',
            'subject_id' => (int)($activeRestriction['id'] ?? 0),
            'metadata' => [
                'login_identifier' => $login,
                'action_key' => (string)($activeRestriction['action_key'] ?? ''),
            ],
        ]);
        return ['ok' => false, 'error' => trux_moderation_access_block_message($activeRestriction)];
    }

    trux_login_user((int)$u['id']);
    trux_moderation_record_activity_event('login_success', (int)$u['id'], [
        'metadata' => [
            'login_identifier' => $login,
        ],
    ]);
    return ['ok' => true];
}
