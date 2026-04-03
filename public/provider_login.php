<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    trux_redirect('/login.php');
}

if (trux_is_logged_in()) {
    trux_redirect('/');
}

$provider = trux_normalize_linked_account_provider((string)($_POST['provider'] ?? ''));
if ($provider === '') {
    trux_flash_set('error', 'Invalid sign-in provider.');
    trux_redirect('/login.php');
}

$startResult = trux_start_linked_account_flow(0, $provider, '/login.php', 'login');
if ($startResult['ok'] ?? false) {
    trux_redirect((string)$startResult['redirect_url']);
}

$providerMeta = trux_linked_account_provider($provider);
$providerLabel = (string)(($providerMeta['label'] ?? '') !== '' ? $providerMeta['label'] : ucfirst($provider));
$errorCode = (string)($startResult['error'] ?? '');

if ($errorCode === 'pending_setup') {
    trux_flash_set('info', $providerLabel . ' sign-in is not configured yet.');
} elseif ($errorCode === 'coming_soon' || $errorCode === 'unsupported_provider') {
    trux_flash_set('info', $providerLabel . ' sign-in is not available right now.');
} else {
    trux_flash_set('error', 'Could not start ' . $providerLabel . ' sign-in right now.');
}

trux_redirect('/login.php');

