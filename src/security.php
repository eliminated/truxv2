<?php
declare(strict_types=1);

function trux_security_current_request_ip(): ?string {
    if (function_exists('trux_current_request_ip')) {
        return trux_current_request_ip();
    }
    $value = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return $value !== '' ? $value : null;
}

function trux_security_current_request_user_agent(): ?string {
    if (function_exists('trux_current_user_agent')) {
        return trux_current_user_agent();
    }
    $value = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return $value !== '' ? $value : null;
}

function trux_security_detect_browser_name(?string $userAgent): string {
    $ua = strtolower(trim((string)$userAgent));
    return match (true) {
        str_contains($ua, 'edg/') => 'Edge',
        str_contains($ua, 'chrome/') && !str_contains($ua, 'edg/') => 'Chrome',
        str_contains($ua, 'firefox/') => 'Firefox',
        str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/') => 'Safari',
        str_contains($ua, 'opr/') || str_contains($ua, 'opera') => 'Opera',
        default => 'Unknown browser',
    };
}

function trux_security_detect_platform_name(?string $userAgent): string {
    $ua = strtolower(trim((string)$userAgent));
    return match (true) {
        str_contains($ua, 'windows') => 'Windows',
        str_contains($ua, 'android') => 'Android',
        str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios') => 'iOS',
        str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
        str_contains($ua, 'linux') => 'Linux',
        default => 'Unknown platform',
    };
}

function trux_security_device_context(): array {
    $userAgent = trux_security_current_request_user_agent();
    $browser = trux_security_detect_browser_name($userAgent);
    $platform = trux_security_detect_platform_name($userAgent);
    $ip = trux_security_current_request_ip();

    return [
        'ip_address' => $ip,
        'user_agent' => $userAgent,
        'browser_name' => $browser,
        'platform_name' => $platform,
        'device_label' => trim($browser . ' on ' . $platform),
    ];
}

function trux_security_session_hash(string $sessionId): string {
    return hash('sha256', $sessionId);
}

function trux_security_session_public_id(): ?string {
    $value = $_SESSION['session_public_id'] ?? null;
    return is_string($value) && trim($value) !== '' ? trim($value) : null;
}

function trux_security_set_session_public_id(string $publicId): void {
    $_SESSION['session_public_id'] = $publicId;
}

function trux_security_clear_session_state(): void {
    unset($_SESSION['session_public_id'], $_SESSION['pending_auth'], $_SESSION['security_step_up'], $_SESSION['pending_security_action']);
}

function trux_security_pending_auth(): ?array {
    $payload = $_SESSION['pending_auth'] ?? null;
    return is_array($payload) ? $payload : null;
}

function trux_security_set_pending_auth(int $userId, string $loginIdentifier, array $analysis = [], string $loginMethod = 'password', ?string $provider = null): void {
    $_SESSION['pending_auth'] = [
        'user_id' => $userId,
        'login_identifier' => $loginIdentifier,
        'analysis' => $analysis,
        'login_method' => $loginMethod,
        'provider' => $provider,
        'created_at' => time(),
        'email_challenge_public_id' => null,
    ];
}

function trux_security_update_pending_auth(array $values): void {
    $current = trux_security_pending_auth();
    if ($current === null) {
        return;
    }
    $_SESSION['pending_auth'] = array_merge($current, $values);
}

function trux_security_clear_pending_auth(): void {
    unset($_SESSION['pending_auth']);
}

function trux_security_set_pending_action(array $payload): void {
    $payload['created_at'] = time();
    $_SESSION['pending_security_action'] = $payload;
}

function trux_security_pending_action(): ?array {
    $payload = $_SESSION['pending_security_action'] ?? null;
    if (!is_array($payload)) {
        return null;
    }
    if (((int)($payload['created_at'] ?? 0)) < (time() - 900)) {
        unset($_SESSION['pending_security_action']);
        return null;
    }
    return $payload;
}

function trux_security_clear_pending_action(): void {
    unset($_SESSION['pending_security_action']);
}

function trux_security_mark_step_up(int $userId, string $method, string $purpose = 'sensitive_action'): void {
    $_SESSION['security_step_up'] = [
        'user_id' => $userId,
        'method' => $method,
        'purpose' => $purpose,
        'verified_at' => time(),
    ];
}

function trux_security_has_recent_step_up(int $userId, string $purpose = 'sensitive_action'): bool {
    $payload = $_SESSION['security_step_up'] ?? null;
    if (!is_array($payload)) {
        return false;
    }
    if ((int)($payload['user_id'] ?? 0) !== $userId) {
        return false;
    }
    if ((string)($payload['purpose'] ?? '') !== $purpose) {
        return false;
    }
    return ((int)($payload['verified_at'] ?? 0)) >= (time() - 600);
}

function trux_security_require_step_up_or_redirect(int $userId, string $returnPath, string $action, array $payload = []): bool {
    if (trux_security_has_recent_step_up($userId)) {
        return true;
    }
    $payload['action'] = $action;
    $payload['return_path'] = trux_safe_local_redirect_path($returnPath, '/settings.php?section=security');
    trux_security_set_pending_action($payload);
    trux_flash_set('info', 'Confirm a recent security step before continuing.');
    trux_redirect('/security_confirm.php?return=' . urlencode((string)$payload['return_path']));
}

function trux_security_local_force_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function trux_security_store_session_row(int $userId, string $sessionPublicId, string $loginMethod = 'password', ?string $provider = null, array $analysis = []): void {
    if ($userId <= 0) {
        return;
    }

    $context = trux_security_device_context();
    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'INSERT INTO user_sessions
                (user_id, session_public_id, session_hash, session_name, login_method, provider, ip_address, user_agent, device_label, browser_name, platform_name, is_suspicious, created_at, last_active_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $sessionPublicId,
            trux_security_session_hash(session_id()),
            session_name(),
            $loginMethod,
            $provider,
            $context['ip_address'],
            $context['user_agent'],
            $context['device_label'],
            $context['browser_name'],
            $context['platform_name'],
            !empty($analysis['suspicious']) ? 1 : 0,
        ]);
    } catch (PDOException) {
        // Missing migration should not hard-break authentication.
    }
}

function trux_security_finalize_login(
    int $userId,
    string $loginMethod = 'password',
    ?string $provider = null,
    array $analysis = [],
    ?string $loginIdentifier = null
): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $publicId = bin2hex(random_bytes(16));
    trux_security_set_session_public_id($publicId);
    trux_security_store_session_row($userId, $publicId, $loginMethod, $provider, $analysis);
    trux_security_clear_pending_auth();

    $context = trux_security_device_context();
    if (trux_guardian_enabled()) {
        trux_guardian_record_login_event([
            'user_id' => $userId,
            'login_identifier' => $loginIdentifier,
            'outcome' => 'success',
            'login_method' => $loginMethod,
            'provider' => $provider,
            'session_public_id' => $publicId,
            'ip_address' => $context['ip_address'],
            'user_agent' => $context['user_agent'],
            'device_label' => $context['device_label'],
            'browser_name' => $context['browser_name'],
            'platform_name' => $context['platform_name'],
            'suspicious' => !empty($analysis['suspicious']),
            'reasons' => array_values(array_map('strval', (array)($analysis['reasons'] ?? []))),
        ]);
    }
}

function trux_security_logout_current_session(string $reason = 'logout'): void {
    $userId = isset($_SESSION['user_id']) && is_int($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $publicId = trux_security_session_public_id();

    if ($userId > 0 && $publicId !== null) {
        try {
            $db = trux_db();
            $stmt = $db->prepare(
                'UPDATE user_sessions
                 SET revoked_at = NOW(), revoke_reason = ?, revoked_by_session_public_id = ?
                 WHERE user_id = ? AND session_public_id = ? AND revoked_at IS NULL'
            );
            $stmt->execute([$reason, $publicId, $userId, $publicId]);
        } catch (PDOException) {
            // Ignore if migration is missing.
        }

        if (trux_guardian_enabled()) {
            trux_guardian_revoke_sessions([
                'user_id' => $userId,
                'session_public_id' => $publicId,
                'reason' => $reason,
                'revoked_by_session_public_id' => $publicId,
            ]);
        }
    }

    trux_security_local_force_logout();
}

function trux_security_touch_current_session(): void {
    $userId = isset($_SESSION['user_id']) && is_int($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $publicId = trux_security_session_public_id();
    if ($userId <= 0 || $publicId === null) {
        return;
    }

    $lastTouchAt = (int)($_SESSION['_security_last_touch_at'] ?? 0);
    if ($lastTouchAt > 0 && (time() - $lastTouchAt) < max(30, TRUX_GUARDIAN_LAST_ACTIVE_TOUCH_SECONDS)) {
        return;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE user_sessions
             SET last_active_at = NOW()
             WHERE user_id = ? AND session_public_id = ? AND session_hash = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$userId, $publicId, trux_security_session_hash(session_id())]);
    } catch (PDOException) {
        return;
    }

    $_SESSION['_security_last_touch_at'] = time();
}

function trux_security_bootstrap_existing_session(int $userId): void {
    if ($userId <= 0 || trux_security_session_public_id() !== null) {
        return;
    }

    try {
        $publicId = bin2hex(random_bytes(16));
    } catch (Throwable) {
        return;
    }

    trux_security_set_session_public_id($publicId);
    trux_security_store_session_row($userId, $publicId, 'session_resume');
}

function trux_security_enforce_current_session(): void {
    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        return;
    }

    $userId = (int)$_SESSION['user_id'];
    trux_security_bootstrap_existing_session($userId);
    $publicId = trux_security_session_public_id();
    if ($publicId === null) {
        return;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT revoked_at
             FROM user_sessions
             WHERE user_id = ? AND session_public_id = ? AND session_hash = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $publicId, trux_security_session_hash(session_id())]);
        $row = $stmt->fetch();
    } catch (PDOException) {
        return;
    }

    if (!$row) {
        trux_security_store_session_row($userId, $publicId, 'session_resume');
        return;
    }

    if (!empty($row['revoked_at'])) {
        if (PHP_SAPI !== 'cli') {
            $_SESSION = ['_flash' => []];
            session_regenerate_id(true);
            trux_flash_set('info', 'That session is no longer active. Please sign in again.');
            trux_redirect('/login.php');
        }
        trux_security_local_force_logout();
        return;
    }

    trux_security_touch_current_session();
}

function trux_security_fetch_sessions(int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT session_public_id, login_method, provider, ip_address, user_agent, device_label, browser_name, platform_name, is_suspicious, created_at, last_active_at, revoked_at, revoke_reason
             FROM user_sessions
             WHERE user_id = ?
             ORDER BY (revoked_at IS NULL) DESC, last_active_at DESC, created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }

    $currentPublicId = trux_security_session_public_id();
    foreach ($rows as &$row) {
        $row['is_current'] = $currentPublicId !== null && $currentPublicId === (string)($row['session_public_id'] ?? '');
    }
    unset($row);

    return $rows;
}

function trux_security_fetch_login_history(int $userId, int $limit = 20): array {
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT login_method, provider, ip_address, user_agent, device_label, browser_name, platform_name, risk_reasons_json, created_at
             FROM login_history
             WHERE user_id = ?
             ORDER BY id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }

    foreach ($rows as &$row) {
        $decoded = json_decode((string)($row['risk_reasons_json'] ?? '[]'), true);
        $row['suspicious_reasons'] = is_array($decoded) ? $decoded : [];
    }
    unset($row);
    return $rows;
}

function trux_security_fetch_2fa_state(int $userId): array {
    $state = [
        'primary_method' => 'none',
        'totp_enabled' => false,
        'email_otp_enabled' => false,
        'recovery_codes_available' => 0,
        'challenge_on_sensitive' => true,
    ];
    if ($userId <= 0) {
        return $state;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'SELECT primary_method, totp_enabled, email_otp_enabled, recovery_codes_generated_at, challenge_on_sensitive, totp_confirmed_at, email_confirmed_at
             FROM user_2fa_settings
             WHERE user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) {
            $state['primary_method'] = (string)($row['primary_method'] ?? 'none');
            $state['totp_enabled'] = !empty($row['totp_enabled']);
            $state['email_otp_enabled'] = !empty($row['email_otp_enabled']);
            $state['challenge_on_sensitive'] = !array_key_exists('challenge_on_sensitive', $row) || !empty($row['challenge_on_sensitive']);
            $state['totp_confirmed_at'] = $row['totp_confirmed_at'] ?? null;
            $state['email_confirmed_at'] = $row['email_confirmed_at'] ?? null;
            $state['recovery_codes_generated_at'] = $row['recovery_codes_generated_at'] ?? null;
        }

        $recoveryStmt = $db->prepare(
            'SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL AND replaced_at IS NULL'
        );
        $recoveryStmt->execute([$userId]);
        $state['recovery_codes_available'] = (int)$recoveryStmt->fetchColumn();
    } catch (PDOException) {
        return $state;
    }

    return $state;
}

function trux_security_revoke_session(int $userId, string $sessionPublicId, string $reason = 'manual_revoke'): bool {
    if ($userId <= 0 || trim($sessionPublicId) === '') {
        return false;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE user_sessions
             SET revoked_at = NOW(), revoke_reason = ?, revoked_by_session_public_id = ?
             WHERE user_id = ? AND session_public_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$reason, trux_security_session_public_id(), $userId, $sessionPublicId]);
        $changed = $stmt->rowCount() > 0;
    } catch (PDOException) {
        return false;
    }

    if ($changed && trux_guardian_enabled()) {
        trux_guardian_revoke_sessions([
            'user_id' => $userId,
            'session_public_id' => $sessionPublicId,
            'reason' => $reason,
            'revoked_by_session_public_id' => trux_security_session_public_id(),
        ]);
    }

    return $changed;
}

function trux_security_revoke_other_sessions(int $userId, string $reason = 'log_out_other_sessions'): int {
    if ($userId <= 0) {
        return 0;
    }
    $currentPublicId = trux_security_session_public_id();
    if ($currentPublicId === null) {
        return 0;
    }

    try {
        $db = trux_db();
        $stmt = $db->prepare(
            'UPDATE user_sessions
             SET revoked_at = NOW(), revoke_reason = ?, revoked_by_session_public_id = ?
             WHERE user_id = ? AND session_public_id <> ? AND revoked_at IS NULL'
        );
        $stmt->execute([$reason, $currentPublicId, $userId, $currentPublicId]);
        $count = $stmt->rowCount();
    } catch (PDOException) {
        return 0;
    }

    if ($count > 0 && trux_guardian_enabled()) {
        trux_guardian_revoke_sessions([
            'user_id' => $userId,
            'exclude_session_public_id' => $currentPublicId,
            'reason' => $reason,
            'revoked_by_session_public_id' => $currentPublicId,
        ]);
    }

    return $count;
}
