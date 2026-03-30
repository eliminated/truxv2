<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

$linkedAccountsRedirectPath = '/settings.php?section=linked-accounts';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $pageKey = 'unlink-account';
    $pageLayout = 'app';
    require_once dirname(__DIR__) . '/_header.php';
    ?>
    <div class="pageFrame pageFrame--settings">
      <section class="settingsSectionCard">
        <div class="settingSection">
          <div class="settingSection__head">
            <span class="settingSection__eyebrow">Linked accounts</span>
            <h3>Method not allowed</h3>
            <p class="muted">Use the unlink controls from linked-account settings so the action stays confirmed and CSRF-protected.</p>
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

$provider = trux_normalize_linked_account_provider(trux_str_param('provider', ''));
if ($provider === '') {
    trux_flash_set('error', 'Invalid account provider.');
    trux_redirect($linkedAccountsRedirectPath);
}

if (empty($me['email_verified'])) {
    trux_flash_set('error', 'Verify control of your email before managing linked accounts.');
    trux_redirect($linkedAccountsRedirectPath);
}

$providerMeta = trux_linked_account_provider($provider);
$providerLabel = (string)(($providerMeta['label'] ?? '') !== '' ? $providerMeta['label'] : ucfirst($provider));

$unlinkResult = trux_unlink_linked_account((int)$me['id'], $provider);
if ($unlinkResult['ok'] ?? false) {
    trux_linked_account_record_activity('linked_account_unlinked', (int)$me['id'], $provider, [
        'label' => $providerLabel,
    ]);
    trux_flash_set('success', $providerLabel . ' was unlinked from your account.');
} else {
    $errorCode = (string)($unlinkResult['error'] ?? '');
    if ($errorCode === 'last_auth_method') {
        trux_flash_set('error', 'You cannot remove the last authentication method from this account.');
    } elseif ($errorCode === 'not_linked') {
        trux_flash_set('info', $providerLabel . ' is not linked to this account.');
    } else {
        trux_flash_set('error', 'Could not unlink ' . $providerLabel . ' right now.');
    }
}

trux_redirect($linkedAccountsRedirectPath);
