<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if (trux_is_logged_in()) trux_redirect('/');

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

<section class="card">
  <div class="card__body">
    <h1>Create your account</h1>

    <?php if ($errors): ?>
      <div class="flash flash--error">
        <ul class="list">
          <?php foreach ($errors as $e): ?>
            <li><?= trux_e((string)$e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="/register.php" class="form">
      <?= trux_csrf_field() ?>

      <label class="field">
        <span>Username</span>
        <input name="username" value="<?= trux_e($username) ?>" maxlength="32" required autocomplete="username">
        <small class="muted">3–32 chars, letters/numbers/underscore.</small>
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

      <div class="row">
        <button class="btn" type="submit">Create account</button>
        <a class="muted" href="/login.php">Already have an account?</a>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>