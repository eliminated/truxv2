<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/mailer.php';

$pageKey = 'settings';
$pageLayout = 'app';

trux_require_login();
$me = trux_current_user();
if (!$me) {
  trux_redirect('/login.php');
}

$accountRedirectPath = '/settings.php?section=account';
$emailProviderCatalogJson = json_encode(trux_email_provider_domains(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$emailProviderCatalogJson = is_string($emailProviderCatalogJson) ? $emailProviderCatalogJson : '{}';

$settingsSectionIcon = static function (string $key): string {
  return match ($key) {
    'account' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4.75 7.25h14.5v9.5H4.75z" fill="none" stroke="currentColor" stroke-width="1.7" rx="1.8"/><path d="m5.5 8 6.5 5 6.5-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.5v3.75M9.75 17.5h4.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    'notifications' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4.75a4 4 0 0 0-4 4v1.35c0 1.12-.37 2.21-1.05 3.1L5.8 14.75h12.4l-1.15-1.55A5.4 5.4 0 0 1 16 10.1V8.75a4 4 0 0 0-4-4Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /><path d="M9.75 17.5a2.25 2.25 0 0 0 4.5 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>',
    'privacy' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4.5c3.75 0 6.98 2.24 8.5 5.45-1.52 3.21-4.75 5.45-8.5 5.45S5.02 13.16 3.5 9.95C5.02 6.74 8.25 4.5 12 4.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /><circle cx="12" cy="9.95" r="2.2" fill="none" stroke="currentColor" stroke-width="1.7" /></svg>',
    'muted' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 10.5h3.25L13 6.75v10.5l-4.75-3.75H5v-3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /><path d="m16.5 9.25 4 5.5M20.5 9.25l-4 5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>',
    'blocked' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7.25" fill="none" stroke="currentColor" stroke-width="1.7" /><path d="m7 17 10-10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>',
    'interface' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6.5h11M6 12h6M6 17.5h11" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /><circle cx="9.5" cy="6.5" r="1.75" fill="none" stroke="currentColor" stroke-width="1.7" /><circle cx="14.5" cy="12" r="1.75" fill="none" stroke="currentColor" stroke-width="1.7" /><circle cx="11" cy="17.5" r="1.75" fill="none" stroke="currentColor" stroke-width="1.7" /></svg>',
    default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7.25" fill="none" stroke="currentColor" stroke-width="1.7" /></svg>',
  };
};

$linkedAccountIcon = static function (string $provider): string {
  return match ($provider) {
    'google' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v4h5.4a4.7 4.7 0 0 1-2 3.1v2.6h3.2c1.9-1.7 3-4.3 3-7.7Z"/><path fill="currentColor" d="M12 22c2.7 0 4.9-.9 6.5-2.4l-3.2-2.6c-.9.6-2 .9-3.3.9-2.5 0-4.6-1.7-5.4-4H3.3v2.7A10 10 0 0 0 12 22Z"/><path fill="currentColor" d="M6.6 13.9a6 6 0 0 1 0-3.8V7.4H3.3a10 10 0 0 0 0 9.2l3.3-2.7Z"/><path fill="currentColor" d="M12 6a5.5 5.5 0 0 1 3.9 1.5l2.9-2.9A9.8 9.8 0 0 0 12 2 10 10 0 0 0 3.3 7.4l3.3 2.7c.8-2.3 2.9-4.1 5.4-4.1Z"/></svg>',
    'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 21v-7h2.8l.5-3.5h-3.3V8.3c0-1 .3-1.8 1.8-1.8H17V3.4c-.3 0-1.3-.2-2.5-.2-2.8 0-4.5 1.7-4.5 4.9v2.4H7v3.5h3V21h3.5Z"/></svg>',
    default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 4h3.7l3 4.3L16.8 4H19l-4.9 5.9L19.6 20h-3.7l-3.3-4.8L8.4 20H6.2l5.3-6.4L6.4 4Z"/></svg>',
  };
};

$settingsSections = [
  'account' => [
    'title' => 'Account',
    'nav_description' => 'Email, password, linked accounts',
    'hero_description' => 'Manage account trust, verification, password access, and linked-account scaffolding.',
  ],
  'notifications' => [
    'title' => 'Notifications',
    'nav_description' => 'Feed alerts and activity',
    'hero_description' => 'Choose which activity should appear in your notification feed.',
  ],
  'privacy' => [
    'title' => 'Privacy',
    'nav_description' => 'Profile visibility controls',
    'hero_description' => 'Manage what other people can see on your profile.',
  ],
  'muted' => [
    'title' => 'Muted Users',
    'nav_description' => 'Users you muted',
    'hero_description' => 'Review and manage the people you have muted.',
  ],
  'blocked' => [
    'title'            => 'Blocked Users',
    'nav_description'  => 'Users you blocked',
    'hero_description' => 'Review and manage users you have blocked.',
  ],
  'interface' => [
    'title' => 'Interface',
    'nav_description' => 'Current UI status',
    'hero_description' => 'Check the current state of interface-related options.',
  ],
];

$requestedSection = trim(trux_str_param('section', ''));
$showSettingsOverview = $requestedSection === '';
$activeSection = '';
if (!$showSettingsOverview) {
  $activeSection = isset($settingsSections[$requestedSection]) ? $requestedSection : 'account';
}

$activeSectionMeta = $showSettingsOverview
  ? [
      'title' => 'Sections',
      'hero_description' => 'Choose a section to manage your account settings.',
    ]
  : $settingsSections[$activeSection];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $redirectSection = trim((string)($_POST['section'] ?? $activeSection));
  if (!isset($settingsSections[$redirectSection])) {
    $redirectSection = 'account';
  }

  $action = $_POST['action'] ?? 'save_notifications';
  if ($action === 'save_account_email') {
    $emailResult = trux_update_account_email((int)$me['id'], (string)($_POST['email'] ?? ''));
    if (!($emailResult['ok'] ?? false)) {
      trux_flash_set('error', implode(' ', (array)($emailResult['errors'] ?? ['Could not update your email address.'])));
    } elseif (!($emailResult['changed'] ?? false)) {
      trux_flash_set('info', 'Your email address did not change.');
    } else {
      $sent = trux_send_email_verification_email(
        (string)($emailResult['email'] ?? ''),
        (string)($emailResult['username'] ?? $me['username']),
        (int)$me['id'],
        (string)($emailResult['verification_token'] ?? '')
      );
      trux_flash_set('success', $sent
        ? 'Email updated. Check your inbox to verify the new address.'
        : 'Email updated, but we could not send the verification email yet.');
      if (!$sent) {
        trux_flash_set('error', 'Use the resend action after a short delay to send a fresh verification email.');
      }

      $domainValidation = is_array($emailResult['email_domain'] ?? null) ? $emailResult['email_domain'] : validate_email_domain((string)($emailResult['email'] ?? ''));
      if (!($domainValidation['recognized'] ?? false)) {
        trux_flash_set('info', 'This email domain is not in our recognized-provider list. Account recovery is safer with Gmail, Outlook, Yahoo, iCloud, Proton, or a similar mainstream provider.');
      }
    }
  } elseif ($action === 'change_password') {
    if (empty($me['email_verified'])) {
      trux_flash_set('error', 'Verify your email before changing your password.');
    } else {
      $currentPassword = (string)($_POST['current_password'] ?? '');
      $newPassword = (string)($_POST['new_password'] ?? '');
      $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

      if ($newPassword !== $newPasswordConfirm) {
        trux_flash_set('error', 'Your new passwords do not match.');
      } else {
        $passwordResult = trux_change_password((int)$me['id'], $currentPassword, $newPassword);
        if ($passwordResult['ok'] ?? false) {
          trux_flash_set('success', 'Password updated.');
        } else {
          trux_flash_set('error', implode(' ', (array)($passwordResult['errors'] ?? ['Could not update your password.'])));
        }
      }
    }
  } elseif ($action === 'unmute_user') {
    $rawMutedUserId = $_POST['muted_user_id'] ?? null;
    if (is_string($rawMutedUserId) && preg_match('/^\d+$/', $rawMutedUserId)) {
      trux_unmute_user((int)$me['id'], (int)$rawMutedUserId);
      trux_flash_set('success', 'User unmuted.');
    } else {
      trux_flash_set('error', 'Invalid muted user.');
    }
  } elseif ($action === 'unblock_user') {
    $rawBlockedUserId = $_POST['blocked_user_id'] ?? null;
    if (is_string($rawBlockedUserId) && preg_match('/^\d+$/', $rawBlockedUserId)) {
      trux_unblock_user((int)$me['id'], (int)$rawBlockedUserId);
      trux_flash_set('success', 'User unblocked.');
    } else {
      trux_flash_set('error', 'Invalid user.');
    }
  } elseif ($action === 'save_privacy') {
    $submitted = [];
    foreach (array_keys(trux_profile_privacy_defaults()) as $key) {
      $submitted[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
    }

    if (trux_update_profile_privacy_preferences((int)$me['id'], $submitted)) {
      trux_flash_set('success', 'Privacy settings updated.');
    } else {
      trux_flash_set('error', 'Could not update privacy settings right now.');
    }
  } else {
    $submitted = [];
    foreach (array_keys(trux_notification_defaults()) as $key) {
      $submitted[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
    }

    if (trux_update_notification_preferences((int)$me['id'], $submitted)) {
      trux_flash_set('success', 'Notification preferences updated.');
    } else {
      trux_flash_set('error', 'Could not update notification preferences right now.');
    }
  }

  trux_redirect('/settings.php?section=' . urlencode($redirectSection));
}

$prefs = trux_fetch_notification_preferences((int)$me['id']);
$privacyPrefs = trux_fetch_profile_privacy_preferences((int)$me['id']);
$prefLabels = trux_notification_pref_labels();
$mutedUsers = trux_fetch_muted_users((int)$me['id']);
$blockedUsers = trux_fetch_blocked_users((int)$me['id']);
$accountState = trux_fetch_account_settings_state((int)$me['id']);
if (!$accountState) {
  trux_flash_set('error', 'Account not found.');
  trux_redirect('/login.php');
}
$linkedAccounts = trux_fetch_linked_accounts((int)$me['id']);
$linkedAccountProviders = trux_linked_account_providers();
$verificationCooldownRemaining = (int)($accountState['verification_cooldown_remaining'] ?? 0);
$canResendVerification = !empty($accountState['verification_can_resend']);
$isEmailVerified = !empty($accountState['email_verified']);
$verificationStatusLabel = $isEmailVerified ? 'Verified &#10003;' : 'Unverified &#9888;';
$verificationStatusClass = $isEmailVerified ? 'is-success' : 'is-danger';
$verificationSentAt = trim((string)($accountState['email_verify_sent_at'] ?? ''));

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--settings">
  <section class="inlineHeader inlineHeader--settings">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Workspace center</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title">Settings</h2>
        <p class="inlineHeader__copy"><?= trux_e((string)$activeSectionMeta['hero_description']) ?></p>
      </div>
    </div>
    <div class="inlineHeader__aside">
      <div class="commandReadoutGrid" aria-hidden="true">
        <div class="commandReadout">
          <span>Operator</span>
          <strong>@<?= trux_e((string)$me['username']) ?></strong>
        </div>
        <div class="commandReadout">
          <span>Sections</span>
          <strong><?= count($settingsSections) ?> modules</strong>
        </div>
      </div>
      <div class="inlineHeader__meta">
        <span>@<?= trux_e((string)$me['username']) ?></span>
        <strong><?= trux_e((string)$activeSectionMeta['title']) ?></strong>
      </div>
    </div>
  </section>

  <section class="settingsLayout<?= $showSettingsOverview ? ' is-overview' : ' is-section-view' ?>">
    <aside class="settingsSidebar">
      <div class="settingsNavCard">
        <div class="settingsNavCard__head">
          <span class="settingsNavCard__eyebrow">Sections</span>
          <h3>Account controls</h3>
        </div>
        <div class="settingsNavCard__grid" aria-hidden="true">
          <div class="settingsSignal">
            <span>Matrix</span>
            <strong><?= count($settingsSections) ?> sectors</strong>
          </div>
          <div class="settingsSignal">
            <span>Active</span>
            <strong><?= trux_e((string)$activeSectionMeta['title']) ?></strong>
          </div>
        </div>
        <nav class="settingsNav" aria-label="Settings sections">
          <?php foreach ($settingsSections as $sectionKey => $sectionMeta): ?>
            <a
              class="settingsNav__item<?= (!$showSettingsOverview && $activeSection === $sectionKey) ? ' is-active' : '' ?>"
              href="<?= TRUX_BASE_URL ?>/settings.php?section=<?= urlencode($sectionKey) ?>"
              data-settings-nav="<?= trux_e($sectionKey) ?>">
              <span class="settingsNav__signal" aria-hidden="true"><?= $settingsSectionIcon($sectionKey) ?></span>
              <span class="settingsNav__copy">
                <strong><?= trux_e((string)$sectionMeta['title']) ?></strong>
                <small><?= trux_e((string)$sectionMeta['nav_description']) ?></small>
              </span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
    </aside>

    <div class="settingsContent">
      <?php if ($showSettingsOverview): ?>
        <section class="settingsSectionCard settingsSectionCard--overview" id="settings-overview">
          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Sections</span>
              <h3>Choose where to go</h3>
              <p class="muted">Open one section at a time to manage your settings in a dedicated view.</p>
            </div>

            <nav class="settingsNav settingsNav--overview" aria-label="Settings section shortcuts">
              <?php foreach ($settingsSections as $sectionKey => $sectionMeta): ?>
                <a
                  class="settingsNav__item"
                  href="<?= TRUX_BASE_URL ?>/settings.php?section=<?= urlencode($sectionKey) ?>"
                  data-settings-nav="<?= trux_e($sectionKey) ?>">
                  <span class="settingsNav__signal" aria-hidden="true"><?= $settingsSectionIcon($sectionKey) ?></span>
                  <span class="settingsNav__copy">
                    <strong><?= trux_e((string)$sectionMeta['title']) ?></strong>
                    <small><?= trux_e((string)$sectionMeta['nav_description']) ?></small>
                  </span>
                </a>
              <?php endforeach; ?>
            </nav>
          </div>
        </section>
      <?php elseif ($activeSection === 'account'): ?>
        <section class="settingsSectionCard" id="settings-account" data-settings-section="account">
          <a class="settingsSectionBack shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php">All sections</a>

          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Account</span>
              <h3>Email trust and access</h3>
              <p class="muted">Verification protects recovery mail, password changes, and future linked-account sign-in.</p>
            </div>

            <div class="settingRow settingRow--stacked">
              <span class="settingRow__label">
                <strong>Verification status</strong>
                <small class="muted">
                  <?= $isEmailVerified ? 'This address is trusted for recovery and sensitive account actions.' : 'Sensitive account actions stay locked until you verify this address.' ?>
                </small>
              </span>
              <span class="statusPill <?= trux_e($verificationStatusClass) ?>"><?= $verificationStatusLabel ?></span>
            </div>

            <div class="settingRow settingRow--stacked">
              <span class="settingRow__label">
                <strong>Current email</strong>
                <small class="muted"><?= trux_e((string)$accountState['email']) ?></small>
              </span>
              <?php if (!$isEmailVerified && $verificationSentAt !== ''): ?>
                <small class="muted">
                  Last sent
                  <span data-time-ago="1" data-time-source="<?= trux_e($verificationSentAt) ?>" title="<?= trux_e(trux_format_exact_time($verificationSentAt)) ?>">
                    <?= trux_e(trux_time_ago($verificationSentAt)) ?>
                  </span>
                </small>
              <?php else: ?>
                <small class="muted"><?= $isEmailVerified ? 'Trusted' : 'Awaiting verification' ?></small>
              <?php endif; ?>
            </div>

            <?php if (!empty($accountState['email_domain_unrecognized'])): ?>
              <div class="flash flash--warning accountNotice">
                <div class="accountNotice__body">
                  <strong>Unrecognized email domain</strong>
                  <p>For security and account recovery, we strongly recommend updating to a real email address like Gmail, Outlook, Yahoo, iCloud, or Proton. This is optional for now.</p>
                </div>
                <button class="accountNotice__dismiss" type="button" data-dismiss-parent="1" aria-label="Dismiss warning">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            <?php endif; ?>

            <?php if (!$isEmailVerified): ?>
              <div class="flash flash--info">
                <strong>Email verification required</strong>
                <div>Browse and post as usual, but password changes and linked-account actions stay locked until your email is verified.</div>
              </div>
            <?php endif; ?>
          </div>

          <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/settings.php?section=account">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="save_account_email">
            <input type="hidden" name="section" value="account">

            <div class="settingSection">
              <div class="settingSection__head">
                <span class="settingSection__eyebrow">Email &amp; verification</span>
                <h3>Email address</h3>
                <p class="muted">Changing your email resets verification and sends a fresh verification message.</p>
              </div>

              <label
                class="field"
                data-email-domain-field="1"
                data-email-provider-catalog="<?= trux_e($emailProviderCatalogJson) ?>">
                <span>Email address</span>
                <input
                  type="email"
                  name="email"
                  value="<?= trux_e((string)$accountState['email']) ?>"
                  maxlength="255"
                  required
                  autocomplete="email"
                  data-email-domain-input="1">
                <div class="emailDomainHint" data-email-domain-hint="1" hidden>
                  <span class="emailDomainHint__badge" data-email-domain-badge="1">Domain</span>
                  <small class="emailDomainHint__text muted" data-email-domain-message="1">Provider status appears here.</small>
                </div>
              </label>

              <div class="settingsActions">
                <button class="shellButton shellButton--accent" type="submit">Save email</button>
              </div>
            </div>
          </form>

          <?php if (!$isEmailVerified): ?>
            <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/resend-verification.php">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="redirect" value="<?= trux_e($accountRedirectPath) ?>">

              <div class="settingSection">
                <div class="settingSection__head">
                  <span class="settingSection__eyebrow">Verification delivery</span>
                  <h3>Resend verification email</h3>
                  <p class="muted">Use this when the original message expired or never reached your inbox.</p>
                </div>

                <div class="settingsActions settingsActions--stacked">
                  <button class="shellButton shellButton--accent" type="submit" <?= !$canResendVerification ? 'disabled' : '' ?>>Resend verification email</button>
                  <?php if (!$canResendVerification): ?>
                    <small class="muted"><?= trux_e(trux_email_verification_cooldown_text($verificationCooldownRemaining)) ?></small>
                  <?php elseif (!empty($accountState['verification_expired'])): ?>
                    <small class="muted">Your previous link expired. Sending a new message will issue a fresh token.</small>
                  <?php endif; ?>
                </div>
              </div>
            </form>
          <?php endif; ?>

          <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/settings.php?section=account">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="section" value="account">

            <div class="settingSection">
              <div class="settingSection__head">
                <span class="settingSection__eyebrow">Password</span>
                <h3>Change password</h3>
                <p class="muted">Use your current password to confirm the change.</p>
              </div>

              <?php if (!$isEmailVerified): ?>
                <div class="flash flash--warning">
                  <strong>Password changes are locked</strong>
                  <div>Verify your email first. Password changes are restricted until this account has a trusted recovery address.</div>
                </div>
              <?php else: ?>
                <label class="field">
                  <span>Current password</span>
                  <input type="password" name="current_password" minlength="8" autocomplete="current-password" required>
                </label>

                <label class="field">
                  <span>New password</span>
                  <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                </label>

                <label class="field">
                  <span>Confirm new password</span>
                  <input type="password" name="new_password_confirm" minlength="8" autocomplete="new-password" required>
                </label>

                <div class="settingsActions">
                  <button class="shellButton shellButton--accent" type="submit">Change password</button>
                </div>
              <?php endif; ?>
            </div>
          </form>

          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Linked accounts</span>
              <h3>Connected accounts</h3>
              <p class="muted">Prepare your TruX account for future Google, Facebook, and X sign-in convenience.</p>
            </div>

            <?php if (!$isEmailVerified): ?>
              <div class="flash flash--info">
                <strong>Linked accounts are locked</strong>
                <div>Please verify your email before linking or unlinking OAuth providers.</div>
              </div>
            <?php endif; ?>

            <div class="linkedAccountsGrid">
              <?php foreach ($linkedAccountProviders as $providerKey => $providerMeta): ?>
                <?php
                $linkedAccount = $linkedAccounts[$providerKey] ?? null;
                $providerLabel = (string)($providerMeta['label'] ?? ucfirst($providerKey));
                $linkedAt = trim((string)($linkedAccount['linked_at'] ?? ''));
                $linkedAtExact = $linkedAt !== '' ? trux_format_exact_time($linkedAt) : '';
                ?>
                <article class="linkedAccountCard">
                  <div class="linkedAccountCard__identity">
                    <span class="linkedAccountCard__icon" aria-hidden="true"><?= $linkedAccountIcon($providerKey) ?></span>
                    <span class="linkedAccountCard__copy">
                      <strong><?= trux_e($providerLabel) ?></strong>
                      <small class="muted">
                        <?php if ($linkedAccount): ?>
                          Connected
                          <?php if ($linkedAt !== ''): ?>
                            <span data-time-ago="1" data-time-source="<?= trux_e($linkedAt) ?>" title="<?= trux_e($linkedAtExact) ?>">
                              <?= trux_e(trux_time_ago($linkedAt)) ?>
                            </span>
                            <span> · linked <?= trux_e($linkedAtExact) ?></span>
                          <?php endif; ?>
                        <?php else: ?>
                          Not linked
                        <?php endif; ?>
                      </small>
                    </span>
                  </div>

                  <div class="linkedAccountCard__meta">
                    <span class="statusPill <?= $linkedAccount ? 'is-success' : 'is-muted' ?>"><?= $linkedAccount ? 'Connected' : 'Not linked' ?></span>

                    <?php if ($linkedAccount): ?>
                      <form method="post" action="<?= TRUX_BASE_URL ?>/settings/unlink-account.php?provider=<?= urlencode($providerKey) ?>" data-confirm="Unlink <?= trux_e($providerLabel) ?> from your TruX account?">
                        <?= trux_csrf_field() ?>
                        <button class="shellButton shellButton--ghost" type="submit" <?= !$isEmailVerified ? 'disabled' : '' ?>>Unlink</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="<?= TRUX_BASE_URL ?>/settings/link-account.php?provider=<?= urlencode($providerKey) ?>">
                        <?= trux_csrf_field() ?>
                        <button class="shellButton shellButton--ghost" type="submit" <?= !$isEmailVerified ? 'disabled' : '' ?>>Link account</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php elseif ($activeSection === 'notifications'): ?>
        <section class="settingsSectionCard" id="settings-notifications" data-settings-section="notifications">
          <a class="settingsSectionBack shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php">All sections</a>
          <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/settings.php">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="save_notifications">
            <input type="hidden" name="section" value="notifications">

            <div class="settingSection">
              <div class="settingSection__head">
                <span class="settingSection__eyebrow">Notifications</span>
                <h3>Feed alerts and activity</h3>
                <p class="muted">Choose which activity should appear in your notification feed.</p>
              </div>

              <?php foreach ($prefLabels as $key => $meta): ?>
                <label class="settingRow" for="<?= trux_e($key) ?>">
                  <span class="settingRow__label">
                    <strong><?= trux_e((string)$meta['title']) ?></strong>
                    <small class="muted"><?= trux_e((string)$meta['description']) ?></small>
                  </span>
                  <input id="<?= trux_e($key) ?>" type="checkbox" name="<?= trux_e($key) ?>" value="1" <?= !empty($prefs[$key]) ? 'checked' : '' ?>>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="settingsActions">
              <button class="shellButton shellButton--accent" type="submit">Save notifications</button>
            </div>
          </form>
        </section>
      <?php elseif ($activeSection === 'privacy'): ?>
        <section class="settingsSectionCard" id="settings-privacy" data-settings-section="privacy">
          <a class="settingsSectionBack shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php">All sections</a>
          <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/settings.php">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="save_privacy">
            <input type="hidden" name="section" value="privacy">

            <div class="settingSection">
              <div class="settingSection__head">
                <span class="settingSection__eyebrow">Privacy</span>
                <h3>Profile visibility</h3>
                <p class="muted">Control who can view the Likes and Bookmarks tabs on your profile. Both are public by default.</p>
              </div>

              <label class="settingRow" for="show_likes_public">
                <span class="settingRow__label">
                  <strong>Show likes publicly</strong>
                  <small class="muted">Allow other people to open your Likes tab and see the posts, comments, and replies you liked.</small>
                </span>
                <input id="show_likes_public" type="checkbox" name="show_likes_public" value="1" <?= !empty($privacyPrefs['show_likes_public']) ? 'checked' : '' ?>>
              </label>

              <label class="settingRow" for="show_bookmarks_public">
                <span class="settingRow__label">
                  <strong>Show bookmarks publicly</strong>
                  <small class="muted">Allow other people to open your Bookmarks tab and see the posts, comments, and replies you saved.</small>
                </span>
                <input id="show_bookmarks_public" type="checkbox" name="show_bookmarks_public" value="1" <?= !empty($privacyPrefs['show_bookmarks_public']) ? 'checked' : '' ?>>
              </label>
            </div>

            <div class="settingsActions">
              <button class="shellButton shellButton--accent" type="submit">Save privacy</button>
            </div>
          </form>
        </section>
      <?php elseif ($activeSection === 'muted'): ?>
        <section class="settingsSectionCard" id="settings-muted" data-settings-section="muted">
          <a class="settingsSectionBack shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php">All sections</a>
          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Muted users</span>
              <h3>Muted accounts</h3>
              <p class="muted">You will not receive notifications generated by users you mute.</p>
            </div>

            <?php if (!$mutedUsers): ?>
              <div class="settingRow">
                <span class="settingRow__label">
                  <strong>No muted users</strong>
                  <small class="muted">Mute a user from their profile to hide their likes, replies, follows, mentions, and vote notifications.</small>
                </span>
                <strong class="muted">0</strong>
              </div>
            <?php else: ?>
              <?php foreach ($mutedUsers as $mutedUser): ?>
                <div class="settingRow">
                  <span class="settingRow__label">
                    <strong><a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$mutedUser['username']) ?>">@<?= trux_e((string)$mutedUser['username']) ?></a></strong>
                    <small class="muted">
                      Muted
                      <span data-time-ago="1" data-time-source="<?= trux_e((string)$mutedUser['created_at']) ?>" title="<?= trux_e(trux_format_exact_time((string)$mutedUser['created_at'])) ?>">
                        <?= trux_e(trux_time_ago((string)$mutedUser['created_at'])) ?>
                      </span>
                    </small>
                  </span>
                  <form method="post" action="<?= TRUX_BASE_URL ?>/settings.php" class="inline">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="unmute_user">
                    <input type="hidden" name="section" value="muted">
                    <input type="hidden" name="muted_user_id" value="<?= (int)$mutedUser['id'] ?>">
                    <button class="shellButton shellButton--ghost" type="submit">Unmute</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      <?php elseif ($activeSection === 'blocked'): ?>
        <section class="settingsSectionCard" id="settings-blocked" data-settings-section="blocked">
          <a class="settingsSectionBack shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php">All sections</a>
          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Blocked users</span>
              <h3>Blocked accounts</h3>
              <p class="muted">Blocked users cannot see your profile, send you messages, or appear in your feeds.</p>
            </div>

            <?php if (!$blockedUsers): ?>
              <div class="settingRow">
                <span class="settingRow__label">
                  <strong>No blocked users</strong>
                  <small class="muted">Block a user from their profile to hide them entirely.</small>
                </span>
                <strong class="muted">0</strong>
              </div>
            <?php else: ?>
              <?php foreach ($blockedUsers as $blockedUser): ?>
                <div class="settingRow">
                  <span class="settingRow__label">
                    <strong><a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$blockedUser['username']) ?>">@<?= trux_e((string)$blockedUser['username']) ?></a></strong>
                    <small class="muted">
                      Blocked
                      <span data-time-ago="1" data-time-source="<?= trux_e((string)$blockedUser['created_at']) ?>" title="<?= trux_e(trux_format_exact_time((string)$blockedUser['created_at'])) ?>">
                        <?= trux_e(trux_time_ago((string)$blockedUser['created_at'])) ?>
                      </span>
                    </small>
                  </span>
                  <form method="post" action="<?= TRUX_BASE_URL ?>/settings.php" class="inline">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="unblock_user">
                    <input type="hidden" name="section" value="blocked">
                    <input type="hidden" name="blocked_user_id" value="<?= (int)$blockedUser['id'] ?>">
                    <button class="shellButton shellButton--ghost" type="submit">Unblock</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      <?php else: ?>
        <section class="settingsSectionCard" id="settings-interface" data-settings-section="interface">
          <a class="settingsSectionBack shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php">All sections</a>
          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Interface</span>
              <h3>UI status</h3>
              <p class="muted">A quick status check for UI-related options.</p>
            </div>

            <div class="settingRow">
              <span class="settingRow__label">
                <strong>Visual system</strong>
                <small class="muted">TruX now runs on the command-shell interface foundation across the site.</small>
              </span>
              <strong class="muted">Unified</strong>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
