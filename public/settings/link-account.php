<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

$linkedAccountsRedirectPath = '/settings.php?section=linked-accounts';
$provider = trux_normalize_linked_account_provider(trux_str_param('provider', ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $pageKey = 'link-account';
    $pageLayout = 'app';
    require_once dirname(__DIR__) . '/_header.php';
    ?>
    <div class="pageFrame pageFrame--settings">
      <section class="settingsSectionCard">
        <div class="settingSection">
          <div class="settingSection__head">
            <span class="settingSection__eyebrow">Linked accounts</span>
            <h3>Method not allowed</h3>
            <p class="muted">Open this route from the linked-account controls inside settings so the request stays authenticated and CSRF-protected.</p>
          </div>
          <div class="row">
            <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL . $linkedAccountsRedirectPath ?>">Back to linked accounts</a>
          </div>
        </div>
      </section>
    </div>
    <?php
    require_once dirname(__DIR__) . '/_footer.php';
    exit;
}

trux_require_login();
$me = trux_current_user();
if (!$me) {
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

if ($provider === '') {
    trux_flash_set('error', 'Invalid account provider.');
    trux_redirect($linkedAccountsRedirectPath);
}

if (empty($me['email_verified'])) {
    trux_flash_set('error', 'Verify control of your email before linking accounts.');
    trux_redirect($linkedAccountsRedirectPath);
}

trux_security_require_step_up_or_redirect((int)$me['id'], $linkedAccountsRedirectPath, 'link_provider', [
    'provider' => $provider,
]);

$providerMeta = trux_linked_account_provider($provider);
if ($providerMeta === null) {
    trux_flash_set('error', 'Invalid account provider.');
    trux_redirect($linkedAccountsRedirectPath);
}

$startResult = trux_start_linked_account_flow((int)$me['id'], $provider, $linkedAccountsRedirectPath);
if ($startResult['ok'] ?? false) {
    trux_redirect((string)$startResult['redirect_url']);
}

$providerLabel = (string)($providerMeta['label'] ?? ucfirst($provider));
$errorCode = (string)($startResult['error'] ?? '');

if ($errorCode === 'pending_setup') {
    trux_flash_set('info', $providerLabel . ' linking is wired in TruX, but the provider credentials or the v0.6.1 linked-account migration are not ready yet.');
} elseif ($errorCode === 'coming_soon') {
    trux_flash_set('info', $providerLabel . ' is listed in your linked-account registry, but live linking is not available yet.');
} elseif ($errorCode === 'unsupported_provider') {
    trux_flash_set('info', $providerLabel . ' is in the linked-account registry, but it does not expose a live OAuth flow in this release.');
} else {
    trux_flash_set('error', 'Could not start the ' . $providerLabel . ' link flow right now.');
}

trux_redirect($linkedAccountsRedirectPath);
