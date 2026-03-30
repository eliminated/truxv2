<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

$linkedAccountsRedirectPath = '/settings.php?section=linked-accounts';

trux_require_login();
$me = trux_current_user();
if (!$me) {
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

if (empty($me['email_verified'])) {
    trux_linked_accounts_clear_oauth_session();
    trux_flash_set('error', 'Verify control of your email before completing a linked-account connection.');
    trux_redirect($linkedAccountsRedirectPath);
}

$callbackResult = trux_complete_linked_account_callback((int)$me['id'], $_GET);
$redirectPath = trux_safe_local_redirect_path((string)($callbackResult['redirect'] ?? $linkedAccountsRedirectPath), $linkedAccountsRedirectPath);
$provider = trux_normalize_linked_account_provider((string)($callbackResult['provider'] ?? 'discord'));
$providerMeta = trux_linked_account_provider($provider);
$providerLabel = (string)(($providerMeta['label'] ?? '') !== '' ? $providerMeta['label'] : 'Discord');

if ($callbackResult['ok'] ?? false) {
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
    trux_flash_set('error', 'That provider handshake expired. Start the link again from Linked Accounts.');
} elseif ($errorCode === 'user_mismatch') {
    trux_flash_set('error', 'The provider callback no longer matches your active TruX session. Start again from Linked Accounts.');
} elseif ($errorCode === 'invalid_state') {
    trux_flash_set('error', 'We could not validate the provider callback. Start the link again.');
} elseif ($errorCode === 'provider_denied') {
    trux_flash_set('info', $providerLabel . ' did not approve the link request.');
} elseif ($errorCode === 'missing_code') {
    trux_flash_set('error', 'The provider callback did not include an authorization code.');
} elseif ($errorCode === 'token_exchange_failed' || $errorCode === 'identity_fetch_failed' || $errorCode === 'identity_invalid') {
    trux_flash_set('error', 'TruX could not finish the ' . $providerLabel . ' identity handshake. Please try again.');
} elseif ($errorCode === 'already_linked_elsewhere') {
    trux_flash_set('error', 'That ' . $providerLabel . ' account is already linked to another TruX user.');
} elseif ($errorCode === 'schema_not_ready') {
    trux_flash_set('error', 'Apply the v0.6.1 linked-account migration before completing provider connections.');
} else {
    trux_flash_set('error', 'Could not complete the ' . $providerLabel . ' link right now.');
}

trux_redirect($redirectPath);
