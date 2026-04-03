<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'security-confirm';
$pageLayout = 'auth';

trux_require_login();
$me = trux_current_user();
if (!$me) {
    trux_redirect('/login.php');
}

$pendingAction = trux_security_pending_action();
$returnPath = trux_safe_local_redirect_path(trux_str_param('return', ''), '/settings.php?section=security');
if ($pendingAction !== null && is_string($pendingAction['return_path'] ?? null)) {
    $returnPath = trux_safe_local_redirect_path((string)$pendingAction['return_path'], $returnPath);
}

$twoFactorState = trux_security_fetch_2fa_state((int)$me['id']);
$fullAccount = trux_fetch_account_user_by_id((int)$me['id'], true);
$passwordAvailable = $fullAccount !== null && trim((string)($fullAccount['password_hash'] ?? '')) !== '';
$emailAvailable = !empty($me['email_verified']);
$totpAvailable = !empty($twoFactorState['totp_enabled']);
$recoveryAvailable = (int)($twoFactorState['recovery_codes_available'] ?? 0) > 0;
$challengePublicId = trim((string)($_SESSION['security_email_challenge_public_id'] ?? ''));
$error = null;
$info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'verify');
    if ($action === 'send_email_code') {
        if (!$emailAvailable) {
            $error = 'Verify your email before using email step-up.';
        } else {
                $sendResult = trux_guardian_send_email_otp((int)$me['id'], 'sensitive_action');
                if ($sendResult['ok'] ?? false) {
                    $challengePublicId = (string)($sendResult['challenge_public_id'] ?? $challengePublicId);
                    $_SESSION['security_email_challenge_public_id'] = $challengePublicId;
                    $info = 'A security code was sent to ' . (string)($sendResult['masked_target'] ?? 'your inbox') . '.';
                } else {
                $error = 'Could not send a security code right now.';
            }
        }
    } else {
        $method = (string)($_POST['method'] ?? 'totp');
        $code = trim((string)($_POST['code'] ?? ''));
        if ($method !== 'password' && $code === '') {
            $error = 'Enter a security code to continue.';
        } else {
            if ($method === 'password') {
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $passwordOk = $passwordAvailable && $currentPassword !== '' && password_verify($currentPassword, (string)($fullAccount['password_hash'] ?? ''));
                $result = ['ok' => $passwordOk];
                if (!$passwordOk) {
                    $result['error'] = 'password_invalid';
                }
            } elseif ($method === 'totp') {
                $result = trux_guardian_verify_totp_challenge((int)$me['id'], $code, 'sensitive_action');
            } elseif ($method === 'recovery') {
                $result = trux_guardian_verify_recovery_code((int)$me['id'], $code, 'sensitive_action');
            } else {
                if ($challengePublicId === '') {
                    $result = ['ok' => false, 'error' => 'missing_email_challenge'];
                } else {
                    $result = trux_guardian_verify_email_otp((int)$me['id'], $challengePublicId, $code, 'sensitive_action');
                }
            }

            if ($result['ok'] ?? false) {
                trux_security_mark_step_up((int)$me['id'], $method, 'sensitive_action');
                unset($_SESSION['security_email_challenge_public_id']);
                $pendingAction = trux_security_pending_action();
                if ($pendingAction !== null) {
                    $pendingType = (string)($pendingAction['action'] ?? '');
                    if ($pendingType === 'disable_2fa') {
                        $disable = trux_guardian_disable_2fa((int)$me['id']);
                        trux_security_clear_pending_action();
                        trux_flash_set(($disable['ok'] ?? false) ? 'success' : 'error', ($disable['ok'] ?? false) ? 'Two-factor authentication was disabled.' : 'Could not disable two-factor authentication right now.');
                        trux_redirect($returnPath);
                    }
                    if ($pendingType === 'regenerate_recovery_codes') {
                        $regen = trux_guardian_regenerate_recovery_codes((int)$me['id']);
                        trux_security_clear_pending_action();
                        if ($regen['ok'] ?? false) {
                            $_SESSION['security_recovery_codes'] = is_array($regen['recovery_codes'] ?? null) ? $regen['recovery_codes'] : [];
                            trux_flash_set('success', 'Recovery codes were regenerated.');
                        } else {
                            trux_flash_set('error', 'Could not regenerate recovery codes right now.');
                        }
                        trux_redirect($returnPath);
                    }
                    if ($pendingType === 'link_provider') {
                        $provider = trux_normalize_linked_account_provider((string)($pendingAction['provider'] ?? ''));
                        trux_security_clear_pending_action();
                        if ($provider !== '') {
                            $startResult = trux_start_linked_account_flow((int)$me['id'], $provider, $returnPath, 'link');
                            if ($startResult['ok'] ?? false) {
                                trux_redirect((string)$startResult['redirect_url']);
                            }
                        }
                        trux_flash_set('error', 'Could not resume that provider link right now.');
                        trux_redirect($returnPath);
                    }
                    if ($pendingType === 'unlink_provider') {
                        $provider = trux_normalize_linked_account_provider((string)($pendingAction['provider'] ?? ''));
                        trux_security_clear_pending_action();
                        if ($provider !== '') {
                            $providerMeta = trux_linked_account_provider($provider);
                            $providerLabel = (string)(($providerMeta['label'] ?? '') !== '' ? $providerMeta['label'] : ucfirst($provider));
                            $unlinkResult = trux_unlink_linked_account((int)$me['id'], $provider);
                            if ($unlinkResult['ok'] ?? false) {
                                trux_linked_account_record_activity('linked_account_unlinked', (int)$me['id'], $provider, ['label' => $providerLabel]);
                            }
                            trux_flash_set(($unlinkResult['ok'] ?? false) ? 'success' : 'error', ($unlinkResult['ok'] ?? false)
                                ? $providerLabel . ' was unlinked from your account.'
                                : 'Could not unlink ' . $providerLabel . ' right now.');
                        }
                        trux_redirect($returnPath);
                    }
                    trux_security_clear_pending_action();
                }
                trux_flash_set('success', 'Security confirmation complete. Continue with your sensitive change.');
                trux_redirect($returnPath);
            }

            $error = match ((string)($result['error'] ?? 'invalid_code')) {
                'password_invalid' => 'Your current password was incorrect.',
                'missing_email_challenge' => 'Send an email code first.',
                'challenge_missing' => 'That email challenge expired. Send a new one.',
                'too_many_attempts' => 'Too many attempts were made with that code. Send a new one.',
                default => 'That security code was invalid.',
            };
        }
    }
}

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--recovery">
  <div class="authGateway__signal">
    <div class="authGateway__signalFrame">
      <div class="authGateway__signalHead">
        <span class="authGateway__eyebrow">Sensitive action</span>
        <h1 class="authGateway__title">Reconfirm this session before changing account security.</h1>
        <p class="authGateway__copy">Changes to login methods, sessions, linked providers, password access, and recovery controls require a recent step-up check.</p>
      </div>
    </div>
  </div>

  <div class="authGateway__lane">
    <section class="authSlab">
      <div class="authSlab__frame">
        <div class="authSlab__head">
          <span class="authSlab__eyebrow">Verify</span>
          <h2>Confirm your session</h2>
          <p class="muted">Pick one verification method before returning to your settings change.</p>
        </div>

        <?php if ($error): ?>
          <div class="flash flash--error"><?= trux_e($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
          <div class="flash flash--success"><?= trux_e($info) ?></div>
        <?php endif; ?>

        <?php if ($totpAvailable): ?>
          <form method="post" action="<?= TRUX_BASE_URL ?>/security_confirm.php?return=<?= urlencode($returnPath) ?>" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="method" value="totp">
            <label class="field">
              <span>Authenticator code</span>
              <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456">
            </label>
            <div class="authSlab__actions">
              <button class="shellButton shellButton--accent" type="submit">Verify authenticator code</button>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($passwordAvailable): ?>
          <form method="post" action="<?= TRUX_BASE_URL ?>/security_confirm.php?return=<?= urlencode($returnPath) ?>" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="method" value="password">
            <label class="field">
              <span>Current password</span>
              <input type="password" name="current_password" autocomplete="current-password" placeholder="Enter your current password">
            </label>
            <div class="authSlab__actions">
              <button class="shellButton shellButton--ghost" type="submit">Verify with password</button>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($emailAvailable): ?>
          <form method="post" action="<?= TRUX_BASE_URL ?>/security_confirm.php?return=<?= urlencode($returnPath) ?>" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="send_email_code">
            <div class="authSlab__actions">
              <button class="shellButton shellButton--ghost" type="submit">Send email security code</button>
            </div>
          </form>

          <form method="post" action="<?= TRUX_BASE_URL ?>/security_confirm.php?return=<?= urlencode($returnPath) ?>" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="method" value="email">
            <label class="field">
              <span>Email code</span>
              <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456">
            </label>
            <div class="authSlab__actions">
              <button class="shellButton shellButton--ghost" type="submit">Verify email code</button>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($recoveryAvailable): ?>
          <form method="post" action="<?= TRUX_BASE_URL ?>/security_confirm.php?return=<?= urlencode($returnPath) ?>" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="method" value="recovery">
            <label class="field">
              <span>Recovery code</span>
              <input name="code" autocomplete="one-time-code" placeholder="ABCD-EFGH">
            </label>
            <div class="authSlab__actions">
              <button class="shellButton shellButton--ghost" type="submit">Use recovery code</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
