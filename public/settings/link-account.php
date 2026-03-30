<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

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
            <p class="muted">Open this route from the linked-accounts controls inside account settings.</p>
          </div>
          <div class="row">
            <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/settings.php?section=account">Back to account settings</a>
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
    trux_redirect('/settings.php?section=account');
}

if (empty($me['email_verified'])) {
    trux_flash_set('error', 'Please verify your email before linking accounts.');
    trux_redirect('/settings.php?section=account');
}

$linkedAccounts = trux_fetch_linked_accounts((int)$me['id']);
if (isset($linkedAccounts[$provider])) {
    trux_flash_set('info', trux_linked_account_providers()[$provider]['label'] . ' is already linked.');
    trux_redirect('/settings.php?section=account');
}

trux_moderation_record_activity_event('oauth_link_attempted', (int)$me['id'], [
    'subject_type' => 'linked_account',
    'metadata' => [
        'provider' => $provider,
    ],
]);

$pageKey = 'link-account';
$pageLayout = 'app';
$providerLabel = (string)(trux_linked_account_providers()[$provider]['label'] ?? ucfirst($provider));

$providerIcon = static function (string $key): string {
    return match ($key) {
        'google' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v4h5.4a4.7 4.7 0 0 1-2 3.1v2.6h3.2c1.9-1.7 3-4.3 3-7.7Z"/><path fill="currentColor" d="M12 22c2.7 0 4.9-.9 6.5-2.4l-3.2-2.6c-.9.6-2 .9-3.3.9-2.5 0-4.6-1.7-5.4-4H3.3v2.7A10 10 0 0 0 12 22Z"/><path fill="currentColor" d="M6.6 13.9a6 6 0 0 1 0-3.8V7.4H3.3a10 10 0 0 0 0 9.2l3.3-2.7Z"/><path fill="currentColor" d="M12 6a5.5 5.5 0 0 1 3.9 1.5l2.9-2.9A9.8 9.8 0 0 0 12 2 10 10 0 0 0 3.3 7.4l3.3 2.7c.8-2.3 2.9-4.1 5.4-4.1Z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 21v-7h2.8l.5-3.5h-3.3V8.3c0-1 .3-1.8 1.8-1.8H17V3.4c-.3 0-1.3-.2-2.5-.2-2.8 0-4.5 1.7-4.5 4.9v2.4H7v3.5h3V21h3.5Z"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 4h3.7l3 4.3L16.8 4H19l-4.9 5.9L19.6 20h-3.7l-3.3-4.8L8.4 20H6.2l5.3-6.4L6.4 4Z"/></svg>',
    };
};

require_once dirname(__DIR__) . '/_header.php';
?>

<div class="pageFrame pageFrame--settings">
  <section class="inlineHeader inlineHeader--settings">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Linked accounts</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title">OAuth placeholder</h2>
        <p class="inlineHeader__copy"><?= trux_e($providerLabel) ?> linking is scaffolded, but the live OAuth handshake is not enabled yet.</p>
      </div>
    </div>
  </section>

  <section class="settingsSectionCard">
    <div class="settingSection">
      <div class="linkedAccountPanel">
        <span class="linkedAccountPanel__icon" aria-hidden="true"><?= $providerIcon($provider) ?></span>
        <div class="linkedAccountPanel__body">
          <span class="settingSection__eyebrow">Coming soon</span>
          <h3><?= trux_e($providerLabel) ?> OAuth integration</h3>
          <p class="muted">OAuth integration coming soon. <?= trux_e($providerLabel) ?> linking will be available in an upcoming update.</p>
        </div>
      </div>
      <div class="row">
        <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/settings.php?section=account">Back to linked accounts</a>
      </div>
    </div>
  </section>
</div>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
