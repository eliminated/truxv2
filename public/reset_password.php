<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/mailer.php';

$pageKey = 'reset-password';
$pageLayout = 'auth';

if (trux_is_logged_in()) {
  trux_redirect('/');
}

$token  = trim(trux_str_param('token', ''));
$errors = [];
$valid  = false;

if ($token !== '') {
  $email = trux_validate_password_reset_token($token);
  $valid = $email !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token    = trim((string)($_POST['token'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $confirm  = (string)($_POST['password_confirm'] ?? '');

  if ($token === '') {
    $errors[] = 'Invalid or missing reset token.';
  }

  if (mb_strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
  }

  if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
  }

  if ($errors === []) {
    $ok = trux_consume_password_reset_token($token, $password);
    if ($ok) {
      trux_flash_set('success', 'Password reset successfully. Please log in with your new password.');
      trux_redirect('/login.php');
    } else {
      $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    }
  }
}

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--recovery">
  <div class="authGateway__signal">
    <span class="authGateway__eyebrow">Credential refresh</span>
    <h1 class="authGateway__title">Set a new password and reopen your workspace.</h1>
    <p class="authGateway__copy">Token validation, password rules, and redirect behavior remain unchanged while the reset flow adopts the new gateway system.</p>

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
      <div class="authSlab__head">
        <span class="authSlab__eyebrow">Reset password</span>
        <h2>Choose a new password</h2>
        <p class="muted">Use at least eight characters.</p>
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

        <form method="post" action="<?= TRUX_BASE_URL ?>/reset_password.php" class="form authSlab__form">
          <?= trux_csrf_field() ?>
          <input type="hidden" name="token" value="<?= trux_e($token) ?>">

          <label class="field">
            <span>New password</span>
            <input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Minimum 8 characters">
          </label>

          <label class="field">
            <span>Confirm new password</span>
            <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password" placeholder="Repeat your new password">
          </label>

          <div class="authSlab__actions">
            <button class="shellButton shellButton--accent" type="submit">Set new password</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
