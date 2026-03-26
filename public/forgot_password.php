<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/mailer.php';

$pageKey = 'forgot-password';
$pageLayout = 'auth';

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

    $submitted = true;
  }
}

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--recovery">
  <div class="authGateway__signal">
    <span class="authGateway__eyebrow">Recovery</span>
    <h1 class="authGateway__title">Recover access without exposing account state.</h1>
    <p class="authGateway__copy">Reset-link generation, token expiry, and mail delivery stay exactly the same behind a cleaner recovery flow.</p>

    <div class="authGateway__stats">
      <div class="authStat">
        <strong>Private</strong>
        <span>Submission feedback still avoids email enumeration.</span>
      </div>
      <div class="authStat">
        <strong>Secure</strong>
        <span>Reset token creation and email delivery are unchanged.</span>
      </div>
      <div class="authStat">
        <strong>Fast</strong>
        <span>Get back to login once the request is sent.</span>
      </div>
    </div>
  </div>

  <div class="authGateway__lane">
    <section class="authSlab">
      <div class="authSlab__head">
        <span class="authSlab__eyebrow">Recovery</span>
        <h2>Request reset link</h2>
        <p class="muted">Use the email address attached to your account.</p>
      </div>

      <?php if ($submitted): ?>
        <div class="flash flash--success">If that email is registered, a reset link has been sent. Check your inbox.</div>
        <a class="authSlab__metaLink" href="<?= TRUX_BASE_URL ?>/login.php">Back to login</a>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="flash flash--error">
            <?php foreach ($errors as $e): ?>
              <div><?= trux_e($e) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= TRUX_BASE_URL ?>/forgot_password.php" class="form authSlab__form">
          <?= trux_csrf_field() ?>

          <label class="field">
            <span>Email address</span>
            <input type="email" name="email" maxlength="255" required autocomplete="email" placeholder="you@example.com">
          </label>

          <div class="authSlab__actions">
            <button class="shellButton shellButton--accent" type="submit">Send reset link</button>
            <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/login.php">Back to login</a>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
