<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'login-challenge';
$pageLayout = 'auth';

if (trux_is_logged_in()) {
    trux_redirect('/');
}

$pending = trux_security_pending_auth();
if ($pending === null || ((int)($pending['created_at'] ?? 0)) < (time() - 900)) {
    trux_security_clear_pending_auth();
    trux_flash_set('error', 'That login challenge expired. Please sign in again.');
    trux_redirect('/login.php');
}

$userId = (int)($pending['user_id'] ?? 0);
$account = trux_fetch_account_user_by_id($userId);
if (!$account) {
    trux_security_clear_pending_auth();
    trux_flash_set('error', 'That login challenge is no longer valid.');
    trux_redirect('/login.php');
}

$twoFactorState = trux_security_fetch_2fa_state($userId);
$emailAvailable = !empty($twoFactorState['email_otp_enabled']) || !empty($account['email_verified']);
$totpAvailable = !empty($twoFactorState['totp_enabled']);
$recoveryAvailable = (int)($twoFactorState['recovery_codes_available'] ?? 0) > 0;
$primaryMethod = (string)($twoFactorState['primary_method'] ?? 'none');
$error = null;
$info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'verify');
    if ($action === 'cancel') {
        trux_security_clear_pending_auth();
        trux_flash_set('info', 'Sign-in cancelled.');
        trux_redirect('/login.php');
    }

    if ($action === 'send_email_code') {
        if (!$emailAvailable) {
            $error = 'Email verification is not available for this account.';
        } else {
            $sendResult = trux_guardian_send_email_otp($userId, 'login');
            if ($sendResult['ok'] ?? false) {
                if (!empty($sendResult['challenge_public_id'])) {
                    trux_security_update_pending_auth(['email_challenge_public_id' => (string)$sendResult['challenge_public_id']]);
                }
                $target = (string)($sendResult['masked_target'] ?? 'your email');
                $info = 'A one-time code was sent to ' . $target . '.';
            } else {
                $error = 'Could not send an email code right now.';
            }
        }
    } else {
        $method = (string)($_POST['method'] ?? $primaryMethod);
        $code = trim((string)($_POST['code'] ?? ''));
        if ($code === '') {
            $error = 'Enter your security code to continue.';
        } else {
            if ($method === 'totp') {
                $result = trux_guardian_verify_totp_challenge($userId, $code, 'login');
            } elseif ($method === 'recovery') {
                $result = trux_guardian_verify_recovery_code($userId, $code, 'login');
            } else {
                $challengePublicId = trim((string)($pending['email_challenge_public_id'] ?? ''));
                if ($challengePublicId === '') {
                    $result = ['ok' => false, 'error' => 'missing_email_challenge'];
                } else {
                    $result = trux_guardian_verify_email_otp($userId, $challengePublicId, $code, 'login');
                }
            }

            if ($result['ok'] ?? false) {
                trux_login_user(
                    $userId,
                    (string)($pending['login_method'] ?? 'password'),
                    isset($pending['provider']) && is_string($pending['provider']) ? $pending['provider'] : null,
                    is_array($pending['analysis'] ?? null) ? $pending['analysis'] : [],
                    isset($pending['login_identifier']) && is_string($pending['login_identifier']) ? $pending['login_identifier'] : null
                );
                if (!empty($pending['provider']) && is_string($pending['provider'])) {
                    trux_linked_account_touch_provider_login($userId, (string)$pending['provider']);
                }
                trux_moderation_record_activity_event('login_success', $userId, [
                    'metadata' => [
                        'login_identifier' => (string)($pending['login_identifier'] ?? 'challenge'),
                        'challenge_method' => $method,
                    ],
                ]);
                trux_flash_set('success', 'Welcome back!');
                trux_redirect((string)($pending['redirect_path'] ?? '/'));
            }

            $error = match ((string)($result['error'] ?? 'invalid_code')) {
                'missing_email_challenge' => 'Send an email code first.',
                'challenge_missing' => 'That email code expired. Send a new one.',
                'too_many_attempts' => 'Too many attempts were made with that code. Send a new one.',
                'totp_not_enabled' => 'Authenticator verification is not enabled for this account.',
                default => 'That security code was invalid.',
            };
            trux_moderation_record_activity_event('login_failed', $userId, [
                'metadata' => [
                    'login_identifier' => (string)($pending['login_identifier'] ?? 'challenge'),
                    'challenge_method' => $method,
                ],
            ]);
        }
    }
}

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--recovery">
  <div class="authGateway__signal">
    <div class="authGateway__signalFrame">
      <div class="authGateway__signalHead">
        <span class="authGateway__eyebrow">Security checkpoint</span>
        <h1 class="authGateway__title">Finish sign-in with a second verification step.</h1>
        <p class="authGateway__copy">Your password was accepted. Complete one additional check to activate this session.</p>
      </div>
    </div>
  </div>

  <div class="authGateway__lane">
    <section class="authSlab">
      <div class="authSlab__frame">
        <div class="authSlab__head">
          <span class="authSlab__eyebrow">Step-up</span>
          <h2>Security challenge</h2>
          <p class="muted">Choose one available verification method for @<?= trux_e((string)($account['username'] ?? '')) ?>.</p>
        </div>

        <?php if ($error): ?>
          <div class="flash flash--error"><?= trux_e($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
          <div class="flash flash--success"><?= trux_e($info) ?></div>
        <?php endif; ?>

        <?php if ($totpAvailable): ?>
          <form method="post" action="<?= TRUX_BASE_URL ?>/login_challenge.php" class="form authSlab__form">
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

        <?php if ($emailAvailable): ?>
          <form method="post" action="<?= TRUX_BASE_URL ?>/login_challenge.php" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="send_email_code">
            <div class="authSlab__actions">
              <button class="shellButton shellButton--ghost" type="submit">Send email code</button>
            </div>
          </form>

          <form method="post" action="<?= TRUX_BASE_URL ?>/login_challenge.php" class="form authSlab__form">
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
          <form method="post" action="<?= TRUX_BASE_URL ?>/login_challenge.php" class="form authSlab__form">
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

        <form method="post" action="<?= TRUX_BASE_URL ?>/login_challenge.php" class="form authSlab__form">
          <?= trux_csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <div class="authSlab__actions">
            <button class="shellButton shellButton--ghost" type="submit">Cancel sign-in</button>
          </div>
        </form>
      </div>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>

