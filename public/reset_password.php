<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/mailer.php';

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

<section class="card">
  <div class="card__body">
    <h1>Reset password</h1>

    <?php if (!$valid && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
      <div class="flash flash--error">
        This reset link is invalid or has expired.
      </div>
      <p class="muted">
        <a href="<?= TRUX_BASE_URL ?>/forgot_password.php">Request a new reset link</a>
      </p>
    <?php else: ?>

      <?php if ($errors): ?>
        <div class="flash flash--error">
          <?php foreach ($errors as $e): ?>
            <div><?= trux_e($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p class="muted">Enter your new password below.</p>

      <form method="post" action="<?= TRUX_BASE_URL ?>/reset_password.php" class="form">
        <?= trux_csrf_field() ?>
        <input type="hidden" name="token" value="<?= trux_e($token) ?>">

        <label class="field">
          <span>New password</span>
          <input
            type="password"
            name="password"
            minlength="8"
            required
            autocomplete="new-password"
            placeholder="Minimum 8 characters">
        </label>

        <label class="field">
          <span>Confirm new password</span>
          <input
            type="password"
            name="password_confirm"
            minlength="8"
            required
            autocomplete="new-password"
            placeholder="Repeat your new password">
        </label>

        <div class="row">
          <button class="btn" type="submit">Set new password</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>