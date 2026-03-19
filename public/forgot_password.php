<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/mailer.php';

if (trux_is_logged_in()) {
    trux_redirect('/');
}

$submitted = false;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($errors === []) {
        $token = trux_create_password_reset_token($email);

        if ($token !== null) {
            $user     = trux_fetch_user_by_email($email);
            $name     = $user ? (string)($user['username'] ?? $email) : $email;
            $resetUrl = TRUX_BASE_URL . '/reset_password.php?token=' . urlencode($token);
            trux_send_password_reset_email($email, $name, $resetUrl);
        }

        // Always show success to prevent email enumeration
        $submitted = true;
    }
}

require_once __DIR__ . '/_header.php';
?>

<section class="card">
  <div class="card__body">
    <h1>Forgot password</h1>

    <?php if ($submitted): ?>
      <div class="flash flash--success">
        If that email is registered, a reset link has been sent. Check your inbox.
      </div>
      <p class="muted">
        <a href="<?= TRUX_BASE_URL ?>/login.php">Back to login</a>
      </p>
    <?php else: ?>
      <p class="muted">Enter your email address and we'll send you a reset link.</p>

      <?php if ($errors): ?>
        <div class="flash flash--error">
          <?php foreach ($errors as $e): ?>
            <div><?= trux_e($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= TRUX_BASE_URL ?>/forgot_password.php" class="form">
        <?= trux_csrf_field() ?>

        <label class="field">
          <span>Email address</span>
          <input
            type="email"
            name="email"
            maxlength="255"
            required
            autocomplete="email"
            placeholder="you@example.com">
        </label>

        <div class="row">
          <button class="btn" type="submit">Send reset link</button>
          <a class="muted" href="<?= TRUX_BASE_URL ?>/login.php">Back to login</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>