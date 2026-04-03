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
$linkedAccountsRedirectPath = '/settings.php?section=linked-accounts';
$emailProviderCatalogJson = json_encode(trux_email_provider_domains(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$emailProviderCatalogJson = is_string($emailProviderCatalogJson) ? $emailProviderCatalogJson : '{}';

$settingsSectionIcon = static function (string $key): string {
  return match ($key) {
    'account' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4.75 7.25h14.5v9.5H4.75z" fill="none" stroke="currentColor" stroke-width="1.7" rx="1.8"/><path d="m5.5 8 6.5 5 6.5-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15.5v3.75M9.75 17.5h4.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    'linked-accounts' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8.5 8.5h-1.5a3 3 0 1 0 0 6H8.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M15.5 8.5H17a3 3 0 1 1 0 6h-1.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M9.5 12h5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    'notifications' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4.75a4 4 0 0 0-4 4v1.35c0 1.12-.37 2.21-1.05 3.1L5.8 14.75h12.4l-1.15-1.55A5.4 5.4 0 0 1 16 10.1V8.75a4 4 0 0 0-4-4Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /><path d="M9.75 17.5a2.25 2.25 0 0 0 4.5 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>',
    'privacy' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4.5c3.75 0 6.98 2.24 8.5 5.45-1.52 3.21-4.75 5.45-8.5 5.45S5.02 13.16 3.5 9.95C5.02 6.74 8.25 4.5 12 4.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /><circle cx="12" cy="9.95" r="2.2" fill="none" stroke="currentColor" stroke-width="1.7" /></svg>',
    'muted' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 10.5h3.25L13 6.75v10.5l-4.75-3.75H5v-3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /><path d="m16.5 9.25 4 5.5M20.5 9.25l-4 5.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>',
    'blocked' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7.25" fill="none" stroke="currentColor" stroke-width="1.7" /><path d="m7 17 10-10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>',
    'interface' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6.5h11M6 12h6M6 17.5h11" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /><circle cx="9.5" cy="6.5" r="1.75" fill="none" stroke="currentColor" stroke-width="1.7" /><circle cx="14.5" cy="12" r="1.75" fill="none" stroke="currentColor" stroke-width="1.7" /><circle cx="11" cy="17.5" r="1.75" fill="none" stroke="currentColor" stroke-width="1.7" /></svg>',
    default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7.25" fill="none" stroke="currentColor" stroke-width="1.7" /></svg>',
  };
};

$settingsSections = [
  'account' => [
    'title' => 'Account',
    'nav_description' => 'Email and password access',
    'hero_description' => 'Manage email trust, verification, and password access.',
  ],
  'linked-accounts' => [
    'title' => 'Linked Accounts',
    'nav_description' => 'OAuth and service bridges',
    'hero_description' => 'Manage external identities and Nicholas Foundation service connections.',
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
        ? 'Email updated. Check your inbox and use the verification link within 5 minutes to confirm control of the new address.'
        : 'Email updated, but we could not send the verification email yet.');
      if (!$sent) {
        trux_flash_set('error', 'Use the resend action after the 5-minute timer to send a fresh verification email.');
      }

      $domainValidation = is_array($emailResult['email_domain'] ?? null) ? $emailResult['email_domain'] : validate_email_domain((string)($emailResult['email'] ?? ''));
      if (!($domainValidation['recognized'] ?? false)) {
        trux_flash_set('info', 'This email domain is not in our recognized-provider list. That is separate from ownership verification, which only happens after you click the verification email link.');
      }
    }
  } elseif ($action === 'change_password') {
    if (empty($me['email_verified'])) {
      trux_flash_set('error', 'Verify control of your email address before changing your password.');
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
  } elseif ($action === 'save_interface_preferences') {
    $theme = trim((string)($_POST['theme_preference'] ?? 'system'));
    if (!in_array($theme, ['light', 'dark', 'system'], true)) {
      $theme = 'system';
    }
    $uiPerformanceMode = trim((string)($_POST['ui_performance_mode'] ?? 'full'));
    if (!in_array($uiPerformanceMode, ['full', 'balanced', 'lite'], true)) {
      $uiPerformanceMode = 'full';
    }
    $db = trux_db();
    try {
      $stmt = $db->prepare('UPDATE users SET theme_preference = ?, ui_performance_mode = ? WHERE id = ?');
      $stmt->execute([$theme, $uiPerformanceMode, (int)$me['id']]);
      trux_flash_set('success', 'Interface preferences updated.');
    } catch (PDOException) {
      $stmt = $db->prepare('UPDATE users SET theme_preference = ? WHERE id = ?');
      $stmt->execute([$theme, (int)$me['id']]);
      trux_flash_set('success', 'Theme preference updated.');
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
$linkedAccountCards = trux_linked_account_cards_for_user((int)$me['id']);
$linkedAccountSummary = trux_linked_account_settings_summary((int)$me['id']);
$linkedAccountsStorageReady = trux_linked_accounts_schema_supports_v061();
$liveLinkedProviderLabels = trux_linked_account_live_provider_labels();
$liveLinkedProviderText = $liveLinkedProviderLabels !== [] ? implode(', ', $liveLinkedProviderLabels) : '';
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
              <p class="muted">Known provider domains are advisory only. Verification is what proves you control the inbox for recovery, password changes, and future linked-account sign-in.</p>
            </div>

            <div class="settingRow settingRow--stacked">
              <span class="settingRow__label">
                <strong>Verification status</strong>
                <small class="muted">
                  <?= $isEmailVerified ? 'This inbox was confirmed by the verification link and can be used for recovery and sensitive account actions.' : 'A recognized domain alone does not prove ownership. Sensitive account actions stay locked until you verify this inbox.' ?>
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
                <small class="muted"><?= $isEmailVerified ? 'Inbox confirmed' : 'Awaiting email-link confirmation' ?></small>
              <?php endif; ?>
            </div>

            <?php if (!empty($accountState['email_domain_unrecognized'])): ?>
              <div class="flash flash--warning accountNotice">
                <div class="accountNotice__body">
                  <strong>Unrecognized email domain</strong>
                  <p>This address uses a domain outside our recognized-provider list. That warning is separate from email verification, which still requires clicking the email link. Updating to a mainstream mailbox provider is optional but recommended.</p>
                </div>
                <button class="accountNotice__dismiss" type="button" data-dismiss-parent="1" aria-label="Dismiss warning">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            <?php endif; ?>

            <?php if (!$isEmailVerified): ?>
              <div class="flash flash--info">
                <strong>Email verification required</strong>
                <div>Browse and post as usual, but password changes and linked-account actions stay locked until you click the verification link and prove control of this inbox.</div>
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
                <p class="muted">Changing your email resets verification and sends a fresh verification message. Each verification link expires in 5 minutes.</p>
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
                  <small class="emailDomainHint__text muted" data-email-domain-message="1">Domain recognition appears here. Ownership still requires email verification.</small>
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
                <p class="muted">Use this when the original message expired or never reached your inbox. Verification links stay valid for 5 minutes.</p>
              </div>

                <div class="settingsActions settingsActions--stacked">
                  <button class="shellButton shellButton--accent" type="submit" <?= !$canResendVerification ? 'disabled' : '' ?>>Resend verification email</button>
                  <?php if (!$canResendVerification): ?>
                    <small class="muted"><?= trux_e(trux_email_verification_cooldown_text($verificationCooldownRemaining)) ?></small>
                  <?php elseif (!empty($accountState['verification_expired'])): ?>
                    <small class="muted">Your previous link expired after 5 minutes. Sending a new message will issue a fresh token.</small>
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
                  <div>Verify your email first. Password changes are restricted until you confirm control of this inbox through the email link.</div>
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
              <h3>Connection center</h3>
              <p class="muted">Discord, Google, Facebook, X, and future Nicholas Foundation identity bridges now live in a dedicated linked-accounts section.</p>
            </div>

            <div class="settingRow settingRow--stacked">
              <span class="settingRow__label">
                <strong><?= (int)$linkedAccountSummary['connected'] ?> active connection<?= (int)$linkedAccountSummary['connected'] === 1 ? '' : 's' ?></strong>
                <small class="muted">
                  <?php if (!$linkedAccountsStorageReady): ?>
                    Apply the v0.6.1 linked-account migration before live provider linking can be enabled.
                  <?php elseif ((int)$linkedAccountSummary['total'] > 0): ?>
                    <?= (int)$linkedAccountSummary['connected'] ?> connected, <?= (int)$linkedAccountSummary['error'] ?> error, <?= (int)$linkedAccountSummary['revoked'] ?> revoked.
                  <?php elseif ($liveLinkedProviderText !== ''): ?>
                    No service identities are linked yet. Live linking is ready for <?= trux_e($liveLinkedProviderText) ?> from the Linked Accounts section.
                  <?php else: ?>
                    No service identities are linked yet. Configure provider credentials to unlock live linking.
                  <?php endif; ?>
                </small>
              </span>
              <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL . $linkedAccountsRedirectPath ?>">Open linked accounts</a>
            </div>

          </div>
        </section>
      <?php elseif ($activeSection === 'linked-accounts'): ?>
        <section class="settingsSectionCard" id="settings-linked-accounts" data-settings-section="linked-accounts">
          <a class="settingsSectionBack shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/settings.php">All sections</a>

          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Linked accounts</span>
              <h3>Service connections</h3>
              <p class="muted">Connect Discord, Google, Facebook, and X identities for notifications, support tools, and Nicholas Foundation ecosystem access without relying on a fake placeholder flow.</p>
            </div>

            <?php if (!$linkedAccountsStorageReady): ?>
              <div class="flash flash--warning">
                <strong>Migration required</strong>
                <div>The v0.6.1 linked-account migration has not been applied yet. Existing rows still display, but live provider linking stays locked until the upgraded table shape is available.</div>
              </div>
            <?php endif; ?>

            <?php if (!$isEmailVerified): ?>
              <div class="flash flash--info">
                <strong>Email verification required</strong>
                <div>View provider details freely, but linking, relinking, and unlinking stay locked until you verify control of your TruX email address.</div>
              </div>
            <?php endif; ?>

            <div class="linkedAccountsStats" aria-label="Linked account summary">
              <div class="linkedAccountsStat">
                <span>Connected</span>
                <strong><?= (int)$linkedAccountSummary['connected'] ?></strong>
              </div>
              <div class="linkedAccountsStat">
                <span>Needs attention</span>
                <strong><?= (int)$linkedAccountSummary['error'] + (int)$linkedAccountSummary['revoked'] ?></strong>
              </div>
              <div class="linkedAccountsStat">
                <span>Providers</span>
                <strong><?= count($linkedAccountCards) ?></strong>
              </div>
            </div>

            <div class="linkedAccountsGrid linkedAccountsGrid--rich">
              <?php foreach ($linkedAccountCards as $providerKey => $card): ?>
                <?php
                $providerMeta = is_array($card['meta'] ?? null) ? $card['meta'] : [];
                $linkedAccount = is_array($card['account'] ?? null) ? $card['account'] : null;
                $presenter = is_array($card['presenter'] ?? null) ? $card['presenter'] : ['label' => 'Unknown', 'class' => 'is-muted', 'summary' => ''];
                $providerLabel = (string)($providerMeta['label'] ?? ucfirst($providerKey));
                $availability = (string)($providerMeta['availability'] ?? 'coming_soon');
                $availabilityNote = trim((string)($providerMeta['availability_note'] ?? ''));
                $providerBrand = trim((string)($providerMeta['brand'] ?? ''));
                $identifier = trim((string)($card['identifier'] ?? ''));
                $linkedAt = trim((string)($linkedAccount['linked_at'] ?? ''));
                $linkedAtExact = $linkedAt !== '' ? trux_format_exact_time($linkedAt) : '';
                $updatedAt = trim((string)($linkedAccount['updated_at'] ?? ''));
                $updatedAtExact = $updatedAt !== '' ? trux_format_exact_time($updatedAt) : '';
                $verifiedAt = trim((string)($linkedAccount['last_verified_at'] ?? ''));
                $verifiedAtExact = $verifiedAt !== '' ? trux_format_exact_time($verifiedAt) : '';
                $lastUsedAt = trim((string)($linkedAccount['last_used_at'] ?? ''));
                $lastUsedAtExact = $lastUsedAt !== '' ? trux_format_exact_time($lastUsedAt) : '';
                $providerUserId = trim((string)($linkedAccount['provider_user_id'] ?? ''));
                $providerAvatarUrl = trim((string)($linkedAccount['provider_avatar_url'] ?? ''));
                $statusReason = trim((string)($linkedAccount['status_reason'] ?? ''));
                $showLiveAction = $availability === 'available';
                $actionsLocked = !$isEmailVerified || !$linkedAccountsStorageReady;
                $primaryActionLabel = $linkedAccount ? 'Relink' : 'Link';
                $disabledActionLabel = $availability === 'pending_setup' ? 'Pending setup' : 'Coming soon';
                ?>
                <article class="linkedAccountCard linkedAccountCard--detailed" id="linked-account-<?= trux_e($providerKey) ?>">
                  <div class="linkedAccountCard__identity">
                    <span class="linkedAccountCard__icon" aria-hidden="true">
                      <?php if ($providerAvatarUrl !== ''): ?>
                        <img class="linkedAccountAvatar" src="<?= trux_e($providerAvatarUrl) ?>" alt="">
                      <?php else: ?>
                        <?= trux_linked_account_provider_icon_svg($providerKey) ?>
                      <?php endif; ?>
                    </span>
                    <span class="linkedAccountCard__copy">
                      <strong><?= trux_e($providerLabel) ?></strong>
                      <?php if ($providerBrand !== ''): ?>
                        <small class="muted"><?= trux_e($providerBrand) ?></small>
                      <?php endif; ?>
                      <?php if ($identifier !== ''): ?>
                        <small class="linkedAccountIdentity"><?= trux_e($identifier) ?></small>
                      <?php endif; ?>
                    </span>
                  </div>

                  <p class="muted linkedAccountCard__description"><?= trux_e((string)($providerMeta['description'] ?? '')) ?></p>

                  <?php if ($statusReason !== ''): ?>
                    <p class="linkedAccountStatusNote muted"><?= trux_e($statusReason) ?></p>
                  <?php endif; ?>

                  <div class="linkedAccountCard__facts">
                    <span class="statusPill <?= trux_e((string)$presenter['class']) ?>"><?= trux_e((string)$presenter['label']) ?></span>
                    <?php if ($linkedAt !== ''): ?>
                      <span class="linkedAccountFact">
                        Linked
                        <span data-time-ago="1" data-time-source="<?= trux_e($linkedAt) ?>" title="<?= trux_e($linkedAtExact) ?>">
                          <?= trux_e(trux_time_ago($linkedAt)) ?>
                        </span>
                      </span>
                    <?php endif; ?>
                    <?php if ($verifiedAt !== '' && $verifiedAt !== $linkedAt): ?>
                      <span class="linkedAccountFact">
                        Verified
                        <span data-time-ago="1" data-time-source="<?= trux_e($verifiedAt) ?>" title="<?= trux_e($verifiedAtExact) ?>">
                          <?= trux_e(trux_time_ago($verifiedAt)) ?>
                        </span>
                      </span>
                    <?php endif; ?>
                    <?php if ($updatedAt !== '' && $updatedAt !== $linkedAt): ?>
                      <span class="linkedAccountFact">
                        Updated
                        <span data-time-ago="1" data-time-source="<?= trux_e($updatedAt) ?>" title="<?= trux_e($updatedAtExact) ?>">
                          <?= trux_e(trux_time_ago($updatedAt)) ?>
                        </span>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="linkedAccountCard__meta">
                    <div class="linkedAccountActions">
                      <?php if ($showLiveAction): ?>
                        <form method="post" action="<?= TRUX_BASE_URL ?>/settings/link-account.php?provider=<?= urlencode($providerKey) ?>">
                          <?= trux_csrf_field() ?>
                          <button class="shellButton shellButton--ghost" type="submit" <?= $actionsLocked ? 'disabled' : '' ?>><?= trux_e($primaryActionLabel) ?></button>
                        </form>
                      <?php else: ?>
                        <button class="shellButton shellButton--ghost" type="button" disabled><?= trux_e($disabledActionLabel) ?></button>
                      <?php endif; ?>

                      <?php if ($linkedAccount): ?>
                        <form method="post" action="<?= TRUX_BASE_URL ?>/settings/unlink-account.php?provider=<?= urlencode($providerKey) ?>" data-confirm="Unlink <?= trux_e($providerLabel) ?> from your TruX account?">
                          <?= trux_csrf_field() ?>
                          <button class="shellButton shellButton--ghost" type="submit" <?= $actionsLocked ? 'disabled' : '' ?>>Unlink</button>
                        </form>
                      <?php endif; ?>
                    </div>

                    <?php if ($actionsLocked && $showLiveAction): ?>
                      <small class="muted linkedAccountCard__helper"><?= !$isEmailVerified ? 'Verify your email to manage this connection.' : 'Apply the v0.6.1 linked-account migration to unlock live provider linking.' ?></small>
                    <?php endif; ?>
                  </div>

                  <details class="linkedAccountDetails">
                    <summary>View details</summary>
                    <div class="linkedAccountDetails__body">
                      <p class="muted"><?= trux_e((string)($presenter['summary'] ?? '')) ?></p>
                      <?php if ($availabilityNote !== ''): ?>
                        <p class="muted"><?= trux_e($availabilityNote) ?></p>
                      <?php endif; ?>
                      <dl class="linkedAccountDetails__grid">
                        <div>
                          <dt>Availability</dt>
                          <dd><?= trux_e(ucwords(str_replace('_', ' ', $availability))) ?></dd>
                        </div>
                        <div>
                          <dt>Status</dt>
                          <dd><?= trux_e((string)($presenter['label'] ?? 'Unknown')) ?></dd>
                        </div>
                        <div>
                          <dt>Identifier</dt>
                          <dd><?= $identifier !== '' ? trux_e($identifier) : 'Not linked yet' ?></dd>
                        </div>
                        <div>
                          <dt>Provider user ID</dt>
                          <dd><?= $providerUserId !== '' ? trux_e($providerUserId) : 'Not stored yet' ?></dd>
                        </div>
                        <div>
                          <dt>Linked at</dt>
                          <dd><?= $linkedAtExact !== '' ? trux_e($linkedAtExact) : 'Not linked yet' ?></dd>
                        </div>
                        <div>
                          <dt>Updated at</dt>
                          <dd><?= $updatedAtExact !== '' ? trux_e($updatedAtExact) : 'Not available yet' ?></dd>
                        </div>
                        <div>
                          <dt>Last verified</dt>
                          <dd><?= $verifiedAtExact !== '' ? trux_e($verifiedAtExact) : 'Not available yet' ?></dd>
                        </div>
                        <div>
                          <dt>Last used</dt>
                          <dd><?= $lastUsedAtExact !== '' ? trux_e($lastUsedAtExact) : 'Not tracked yet' ?></dd>
                        </div>
                      </dl>

                      <?php if ($statusReason !== ''): ?>
                        <div class="linkedAccountDetails__note">
                          <strong>Status note</strong>
                          <p class="muted"><?= trux_e($statusReason) ?></p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </details>
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

            <form method="post" action="<?= TRUX_BASE_URL ?>/settings.php" class="settingSection__form">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="section" value="interface">
              <input type="hidden" name="action" value="save_interface_preferences">

              <div class="settingRow settingRow--stacked">
                <span class="settingRow__label">
                  <strong>Color theme</strong>
                  <small class="muted">Choose your preferred color scheme. The toggle button in the header switches instantly.</small>
                </span>
                <div class="radioGroup">
                  <?php foreach (['system' => 'Follow system', 'dark' => 'Dark', 'light' => 'Light'] as $themeVal => $themeLabel): ?>
                    <label class="radioGroup__item">
                      <input type="radio" name="theme_preference" value="<?= trux_e($themeVal) ?>"
                             <?= ($me['theme_preference'] ?? 'system') === $themeVal ? 'checked' : '' ?>>
                      <span><?= trux_e($themeLabel) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="settingRow settingRow--stacked">
                <span class="settingRow__label">
                  <strong>UI performance mode</strong>
                  <small class="muted">Tune motion and decorative effects for the device you are using right now.</small>
                </span>
                <div class="radioGroup">
                  <?php foreach ([
                    'full' => ['label' => 'Full', 'copy' => 'All motion and shell effects stay enabled.'],
                    'balanced' => ['label' => 'Balanced', 'copy' => 'Keep motion, but tone down heavier shell polish.'],
                    'lite' => ['label' => 'Lite', 'copy' => 'Static shell, reduced effects, and the lightest runtime path.'],
                  ] as $perfMode => $perfMeta): ?>
                    <label class="radioGroup__item">
                      <input type="radio" name="ui_performance_mode" value="<?= trux_e($perfMode) ?>"
                             <?= ($me['ui_performance_mode'] ?? 'full') === $perfMode ? 'checked' : '' ?>>
                      <span>
                        <strong><?= trux_e($perfMeta['label']) ?></strong>
                        <small class="muted"><?= trux_e($perfMeta['copy']) ?></small>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="settingSection__actions">
                <button class="shellButton shellButton--accent" type="submit">Save interface settings</button>
              </div>
            </form>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
