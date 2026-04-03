<?php
declare(strict_types=1);

function trux_guardian_enabled(): bool {
    return trim(TRUX_GUARDIAN_BASE_URL) !== '' && trim(TRUX_GUARDIAN_SHARED_SECRET) !== '';
}

function trux_guardian_log(string $message, array $context = []): void {
    $directory = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
    if (!is_dir($directory) || !is_writable($directory)) {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($json) && $json !== '') {
            $line .= ' ' . $json;
        }
    }
    $line .= PHP_EOL;
    @file_put_contents($directory . '/guardian.log', $line, FILE_APPEND | LOCK_EX);
}

function trux_guardian_request(string $path, array $payload): array {
    if (!trux_guardian_enabled()) {
        return ['ok' => false, 'error' => 'guardian_unavailable'];
    }

    $url = rtrim(TRUX_GUARDIAN_BASE_URL, '/') . $path;
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($body)) {
        return ['ok' => false, 'error' => 'encode_failed'];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . TRUX_GUARDIAN_SHARED_SECRET,
    ];

    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 12,
        ]);
        $responseBody = (string)curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($responseBody === '' && $curlError !== '') {
            trux_guardian_log('guardian_request_failed', ['path' => $path, 'curl_error' => $curlError]);
            return ['ok' => false, 'error' => 'request_failed'];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 12,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = (string)@file_get_contents($url, false, $context);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $matches)) {
            $statusCode = (int)$matches[1];
        }
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        trux_guardian_log('guardian_request_http_error', [
            'path' => $path,
            'status' => $statusCode,
            'response' => $decoded,
        ]);
        return [
            'ok' => false,
            'error' => (string)($decoded['detail'] ?? $decoded['error'] ?? 'guardian_http_error'),
            'status' => $statusCode,
            'response' => $decoded,
        ];
    }

    return [
        'ok' => (bool)($decoded['ok'] ?? true),
        'status' => $statusCode,
        'response' => $decoded,
    ];
}

function trux_guardian_start_totp_setup(int $userId): array {
    return trux_guardian_request('/internal/2fa/setup/start', ['user_id' => $userId])['response'] ?? ['ok' => false];
}

function trux_guardian_verify_totp_setup(int $userId, string $challengePublicId, string $code, string $primaryMethod = 'totp'): array {
    return trux_guardian_request('/internal/2fa/setup/verify', [
        'user_id' => $userId,
        'challenge_public_id' => $challengePublicId,
        'code' => $code,
        'primary_method' => $primaryMethod,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_verify_totp_challenge(int $userId, string $code, string $purpose = 'login'): array {
    return trux_guardian_request('/internal/2fa/challenge/verify', [
        'user_id' => $userId,
        'code' => $code,
        'purpose' => $purpose,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_verify_recovery_code(int $userId, string $code, string $purpose = 'login'): array {
    return trux_guardian_request('/internal/2fa/recovery/verify', [
        'user_id' => $userId,
        'code' => $code,
        'purpose' => $purpose,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_regenerate_recovery_codes(int $userId): array {
    return trux_guardian_request('/internal/2fa/recovery/regenerate', ['user_id' => $userId])['response'] ?? ['ok' => false];
}

function trux_guardian_disable_2fa(int $userId): array {
    return trux_guardian_request('/internal/2fa/disable', ['user_id' => $userId])['response'] ?? ['ok' => false];
}

function trux_guardian_send_email_otp(int $userId, string $purpose = 'login', bool $activateEmail2fa = false): array {
    return trux_guardian_request('/internal/email-otp/send', [
        'user_id' => $userId,
        'purpose' => $purpose,
        'activate_email_2fa' => $activateEmail2fa,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_verify_email_otp(
    int $userId,
    string $challengePublicId,
    string $code,
    string $purpose = 'login',
    bool $activateEmail2fa = false,
    bool $makePrimary = false
): array {
    return trux_guardian_request('/internal/email-otp/verify', [
        'user_id' => $userId,
        'challenge_public_id' => $challengePublicId,
        'code' => $code,
        'purpose' => $purpose,
        'activate_email_2fa' => $activateEmail2fa,
        'make_primary' => $makePrimary,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_analyze_login(?int $userId, ?string $loginIdentifier, ?string $ipAddress, ?string $userAgent): array {
    return trux_guardian_request('/internal/security/analyze-login', [
        'user_id' => $userId,
        'login_identifier' => $loginIdentifier,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_record_login_event(array $payload): array {
    return trux_guardian_request('/internal/security/record-login-event', $payload)['response'] ?? ['ok' => false];
}

function trux_guardian_revoke_sessions(array $payload): array {
    return trux_guardian_request('/internal/security/revoke-session', $payload)['response'] ?? ['ok' => false];
}

function trux_guardian_issue_password_reset(string $email, ?string $ipAddress, ?string $userAgent): array {
    return trux_guardian_request('/internal/password-reset/issue', [
        'email' => $email,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_preview_password_reset(string $selector, string $validator): array {
    return trux_guardian_request('/internal/password-reset/consume', [
        'selector' => $selector,
        'validator' => $validator,
        'preview' => true,
    ])['response'] ?? ['ok' => false];
}

function trux_guardian_consume_password_reset(
    string $selector,
    string $validator,
    string $passwordHash,
    ?string $stepUpChallengePublicId = null,
    ?string $stepUpCode = null,
    ?string $ipAddress = null,
    ?string $userAgent = null
): array {
    return trux_guardian_request('/internal/password-reset/consume', [
        'selector' => $selector,
        'validator' => $validator,
        'password_hash' => $passwordHash,
        'step_up_challenge_public_id' => $stepUpChallengePublicId,
        'step_up_code' => $stepUpCode,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
    ])['response'] ?? ['ok' => false];
}
