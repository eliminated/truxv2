<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'register';
$pageLayout = 'auth';

if (trux_is_logged_in()) {
  trux_redirect('/');
}

$username = '';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = is_string($_POST['username'] ?? null) ? trim((string)$_POST['username']) : '';
  $email = is_string($_POST['email'] ?? null) ? trim((string)$_POST['email']) : '';
  $password = is_string($_POST['password'] ?? null) ? (string)$_POST['password'] : '';

  $res = trux_register_user($username, $email, $password);
  if ($res['ok'] ?? false) {
    trux_login_user((int)$res['user_id']);
    trux_flash_set('success', 'Account created. Welcome to TruX!');
    trux_redirect('/');
  } else {
    $errors = $res['errors'] ?? ['Registration failed.'];
  }
}

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--register">
  <div class="authGateway__signal">
    <div class="authGateway__signalFrame">
      <div class="authGateway__signalHead">
        <span class="authGateway__eyebrow">New account</span>
        <h1 class="authGateway__title">Create your TruX identity in one clean step.</h1>
        <p class="authGateway__copy">Join the network with the same existing validation, account creation, and authentication behavior behind a fully rebuilt gateway.</p>
      </div>

      <div class="authReadoutGrid" aria-hidden="true">
        <div class="authReadout">
          <span>Lane</span>
          <strong>Identity issue</strong>
        </div>
        <div class="authReadout">
          <span>Protocol</span>
          <strong>Account bootstrap</strong>
        </div>
        <div class="authReadout">
          <span>Validation</span>
          <strong>Existing ruleset</strong>
        </div>
      </div>
    </div>

    <div class="authGateway__stats">
      <div class="authStat">
        <strong>Identity</strong>
        <span>Claim your handle and profile surface.</span>
      </div>
      <div class="authStat">
        <strong>Discovery</strong>
        <span>Enter a feed, search, messages, and bookmarks workspace instantly.</span>
      </div>
      <div class="authStat">
        <strong>Safety</strong>
        <span>Reporting and moderation flows stay intact from day one.</span>
      </div>
    </div>
  </div>

  <div class="authGateway__lane">
    <section class="authSlab">
      <div class="authSlab__frame">
        <div class="authSlab__head">
          <span class="authSlab__eyebrow">Account setup</span>
          <h2>Create account</h2>
          <p class="muted">Same validation rules, new gateway experience.</p>
        </div>

        <div class="authSlab__status" aria-hidden="true">
          <span>Route</span>
          <strong>/register.php</strong>
          <small>Identity issuance</small>
        </div>

        <?php if ($errors): ?>
          <div class="flash flash--error">
            <ul class="list">
              <?php foreach ($errors as $e): ?>
                <li><?= trux_e((string)$e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= TRUX_BASE_URL ?>/register.php" class="form authSlab__form">
          <?= trux_csrf_field() ?>

          <label class="field">
            <span>Username</span>
            <input name="username" value="<?= trux_e($username) ?>" maxlength="32" required autocomplete="username">
            <small class="muted">3-32 chars, letters/numbers/underscore.</small>
          </label>

          <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= trux_e($email) ?>" maxlength="255" required autocomplete="email">
          </label>

          <label class="field">
            <span>Password</span>
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
            <small class="muted">Minimum 8 characters.</small>
          </label>

          <div class="authSlab__actions">
            <button class="shellButton shellButton--accent" type="submit">Create account</button>
            <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/login.php">Already have an account?</a>
          </div>
        </form>
      </div>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
