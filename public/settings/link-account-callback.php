<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

$linkedAccountsRedirectPath = '/settings.php?section=linked-accounts';
$me = trux_current_user();
$callbackResult = trux_complete_linked_account_callback($me ? (int)$me['id'] : null, $_GET);
$mode = (string)($callbackResult['mode'] ?? 'link') === 'login' ? 'login' : 'link';
$redirectDefault = $mode === 'login' ? '/login.php' : $linkedAccountsRedirectPath;
$redirectPath = trux_safe_local_redirect_path((string)($callbackResult['redirect'] ?? $redirectDefault), $redirectDefault);
$provider = trux_normalize_linked_account_provider((string)($callbackResult['provider'] ?? 'discord'));
$providerMeta = trux_linked_account_provider($provider);
$providerLabel = (string)(($providerMeta['label'] ?? '') !== '' ? $providerMeta['label'] : 'Discord');

if ($mode === 'link' && (!$me || empty($me['email_verified']))) {
    trux_linked_accounts_clear_oauth_session();
    trux_flash_set('error', 'Verify control of your email before completing a linked-account connection.');
    trux_redirect($linkedAccountsRedirectPath);
}

if ($callbackResult['ok'] ?? false) {
    if ($mode === 'login') {
        $loginUserId = (int)($callbackResult['login_user_id'] ?? 0);
        if ($loginUserId <= 0) {
            trux_flash_set('error', 'Could not complete the provider sign-in right now.');
            trux_redirect('/login.php');
        }

        $context = trux_security_device_context();
        $analysis = trux_guardian_enabled()
            ? trux_guardian_analyze_login($loginUserId, $provider, $context['ip_address'], $context['user_agent'])
            : trux_security_fetch_2fa_state($loginUserId);
        $requiresChallenge = !empty($analysis['totp_enabled']) || !empty($analysis['email_otp_enabled']);
        if ($requiresChallenge) {
            trux_security_set_pending_auth($loginUserId, $provider, is_array($analysis) ? $analysis : [], 'provider', $provider);
            trux_security_update_pending_auth(['redirect_path' => '/']);
            if ((string)($analysis['primary_method'] ?? 'none') === 'email' || (empty($analysis['totp_enabled']) && !empty($analysis['email_otp_enabled']))) {
                $sendResult = trux_guardian_send_email_otp($loginUserId, 'login');
                if (!empty($sendResult['challenge_public_id'])) {
                    trux_security_update_pending_auth(['email_challenge_public_id' => (string)$sendResult['challenge_public_id']]);
                }
            }
            trux_redirect('/login_challenge.php');
        }

        trux_login_user($loginUserId, 'provider', $provider, is_array($analysis) ? $analysis : [], $provider);
        trux_linked_account_touch_provider_login($loginUserId, $provider);
        trux_flash_set('success', 'Signed in with ' . $providerLabel . '.');
        trux_redirect('/');
    }

    $action = (string)($callbackResult['action'] ?? 'linked');
    if ($action === 'relinked') {
        trux_flash_set('success', $providerLabel . ' was reconnected and refreshed on your TruX account.');
    } else {
        trux_flash_set('success', $providerLabel . ' was linked to your TruX account.');
    }
    trux_redirect($redirectPath);
}

$errorCode = (string)($callbackResult['error'] ?? '');
if ($errorCode === 'session_missing') {
    trux_flash_set('error', $mode === 'login'
        ? 'That provider sign-in session expired. Start again from the login page.'
        : 'That provider handshake expired. Start the link again from Linked Accounts.');
} elseif ($errorCode === 'user_mismatch') {
    trux_flash_set('error', 'The provider callback no longer matches the session that started it.');
} elseif ($errorCode === 'invalid_state') {
    trux_flash_set('error', 'We could not validate the provider callback. Start the flow again.');
} elseif ($errorCode === 'provider_denied') {
    trux_flash_set('info', $providerLabel . ' did not approve the request.');
} elseif ($errorCode === 'missing_code') {
    trux_flash_set('error', 'The provider callback did not include an authorization code.');
} elseif ($errorCode === 'token_exchange_failed' || $errorCode === 'identity_fetch_failed' || $errorCode === 'identity_invalid') {
    trux_flash_set('error', 'TruX could not finish the ' . $providerLabel . ' identity handshake. Please try again.');
} elseif ($errorCode === 'already_linked_elsewhere') {
    trux_flash_set('error', 'That ' . $providerLabel . ' account is already linked to another TruX user.');
} elseif ($errorCode === 'not_linked_for_login') {
    trux_flash_set('info', 'That ' . $providerLabel . ' identity is not linked to a TruX account yet. Sign in with your existing credentials, then link it from Settings.');
} elseif ($errorCode === 'provider_email_conflict') {
    trux_flash_set('error', 'That ' . $providerLabel . ' email already belongs to a TruX account, but the provider identity is not linked. Sign in with your existing credentials and link the provider from Settings.');
} elseif ($errorCode === 'schema_not_ready') {
    trux_flash_set('error', 'Apply the linked-account migration before completing provider connections.');
} else {
    trux_flash_set('error', 'Could not complete the ' . $providerLabel . ' flow right now.');
}

trux_redirect($mode === 'login' ? '/login.php' : $redirectPath);
