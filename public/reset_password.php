<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/mailer.php';

$pageKey = 'reset-password';
$pageLayout = 'auth';

if (trux_is_logged_in()) {
  trux_redirect('/');
}

$selector = trim(trux_str_param('selector', ''));
$validator = trim(trux_str_param('validator', ''));
$errors = [];
$info = null;
$valid  = false;
$preview = ['ok' => false, 'valid' => false, 'step_up_required' => false];
$passwordResetChallengePublicId = trim((string)($_SESSION['password_reset_challenge_public_id'] ?? ''));

if ($selector !== '' && $validator !== '') {
  $preview = trux_guardian_preview_password_reset($selector, $validator);
  $valid = ($preview['ok'] ?? false) && !empty($preview['valid']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selector = trim((string)($_POST['selector'] ?? ''));
  $validator = trim((string)($_POST['validator'] ?? ''));
  $preview = trux_guardian_preview_password_reset($selector, $validator);
  $valid = ($preview['ok'] ?? false) && !empty($preview['valid']);
  $password = (string)($_POST['password'] ?? '');
  $confirm  = (string)($_POST['password_confirm'] ?? '');
  $action = (string)($_POST['action'] ?? 'consume');
  $stepUpCode = trim((string)($_POST['step_up_code'] ?? ''));

  if ($selector === '' || $validator === '') {
    $errors[] = 'Invalid or missing reset token.';
  }

  if ($action === 'send_email_code') {
    $resetUserId = (int)($preview['user_id'] ?? 0);
    if (!$valid || $resetUserId <= 0) {
      $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
      $sendResult = trux_guardian_send_email_otp($resetUserId, 'password_reset');
      if ($sendResult['ok'] ?? false) {
        $passwordResetChallengePublicId = (string)($sendResult['challenge_public_id'] ?? '');
        $_SESSION['password_reset_challenge_public_id'] = $passwordResetChallengePublicId;
        $info = 'A reset verification code was sent to ' . (string)($preview['masked_email'] ?? 'your inbox') . '.';
      } else {
        $errors[] = 'Could not send a reset verification code right now.';
      }
    }
  } else {
    if (mb_strlen($password) < 8) {
      $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm) {
      $errors[] = 'Passwords do not match.';
    }

    if ($errors === []) {
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      if (!is_string($passwordHash) || $passwordHash === '') {
        $errors[] = 'Could not secure your new password right now.';
      } else {
        $context = trux_security_device_context();
        $consumeResult = trux_guardian_consume_password_reset(
          $selector,
          $validator,
          $passwordHash,
          $passwordResetChallengePublicId !== '' ? $passwordResetChallengePublicId : null,
          $stepUpCode !== '' ? $stepUpCode : null,
          $context['ip_address'],
          $context['user_agent']
        );
        if ($consumeResult['ok'] ?? false) {
          unset($_SESSION['password_reset_challenge_public_id']);
          trux_flash_set('success', 'Password reset successfully. Please log in with your new password.');
          trux_redirect('/login.php');
        }
        if (($consumeResult['error'] ?? '') === 'step_up_required' || ($consumeResult['error'] ?? '') === 'invalid_step_up_code') {
          $errors[] = ($consumeResult['error'] ?? '') === 'invalid_step_up_code'
            ? 'That reset verification code was invalid.'
            : 'This reset requires an additional email verification code.';
        } else {
          $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
        }
      }
    }
  }
}

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--recovery">
  <div class="authGateway__signal">
    <div class="authGateway__signalFrame">
      <div class="authGateway__signalHead">
        <span class="authGateway__eyebrow">Credential refresh</span>
        <h1 class="authGateway__title">Set a new password and reopen your workspace.</h1>
        <p class="authGateway__copy">Selector and validator reset tokens, single-use invalidation, strict expiry, and optional risky-reset step-up now run through Guardian.</p>
      </div>

      <div class="authReadoutGrid" aria-hidden="true">
        <div class="authReadout">
          <span>Lane</span>
          <strong>Credential refresh</strong>
        </div>
        <div class="authReadout">
          <span>Protocol</span>
          <strong>Token consume</strong>
        </div>
        <div class="authReadout">
          <span>Policy</span>
          <strong>Minimum 8 chars</strong>
        </div>
      </div>
    </div>

    <div class="authGateway__stats">
      <div class="authStat">
        <strong>Token</strong>
        <span>Existing reset-link validation and expiry remain intact.</span>
      </div>
      <div class="authStat">
        <strong>Policy</strong>
        <span>Password length rules and confirmation checks still apply.</span>
      </div>
      <div class="authStat">
        <strong>Access</strong>
        <span>Successful resets still return you to login with the same flow.</span>
      </div>
    </div>
  </div>

  <div class="authGateway__lane">
    <section class="authSlab">
      <div class="authSlab__frame">
        <div class="authSlab__head">
          <span class="authSlab__eyebrow">Reset password</span>
          <h2>Choose a new password</h2>
          <p class="muted">Use at least eight characters.</p>
        </div>

        <div class="authSlab__status" aria-hidden="true">
          <span>Route</span>
          <strong>/reset_password.php</strong>
          <small>Token consume relay</small>
        </div>

        <?php if (!$valid && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
          <div class="flash flash--error">This reset link is invalid or has expired.</div>
          <a class="authSlab__metaLink" href="<?= TRUX_BASE_URL ?>/forgot_password.php">Request a new reset link</a>
        <?php else: ?>
          <?php if ($errors): ?>
            <div class="flash flash--error">
              <?php foreach ($errors as $e): ?>
                <div><?= trux_e($e) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($info): ?>
            <div class="flash flash--success"><?= trux_e($info) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= TRUX_BASE_URL ?>/reset_password.php" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="selector" value="<?= trux_e($selector) ?>">
            <input type="hidden" name="validator" value="<?= trux_e($validator) ?>">

            <label class="field">
              <span>New password</span>
              <input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Minimum 8 characters">
            </label>

            <label class="field">
              <span>Confirm new password</span>
              <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password" placeholder="Repeat your new password">
            </label>

            <?php if (!empty($preview['step_up_required'])): ?>
              <div class="settingRow settingRow--stacked">
                <span class="settingRow__label">
                  <strong>Extra verification required</strong>
                  <small class="muted">Guardian marked this reset as risky. Send a one-time email code to <?= trux_e((string)($preview['masked_email'] ?? 'your inbox')) ?>, then enter it below to complete the reset.</small>
                </span>
              </div>

              <div class="authSlab__actions">
                <button class="shellButton shellButton--ghost" type="submit" name="action" value="send_email_code">Send reset verification code</button>
              </div>

              <label class="field">
                <span>Email verification code</span>
                <input type="text" name="step_up_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456">
              </label>
            <?php endif; ?>

            <div class="authSlab__actions">
              <button class="shellButton shellButton--accent" type="submit" name="action" value="consume">Set new password</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
