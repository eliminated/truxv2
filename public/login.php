<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if (trux_is_logged_in()) trux_redirect('/');

$login = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = is_string($_POST['login'] ?? null) ? trim((string)$_POST['login']) : '';
    $password = is_string($_POST['password'] ?? null) ? (string)$_POST['password'] : '';

    $res = trux_attempt_login($login, $password);
    if ($res['ok'] ?? false) {
        trux_flash_set('success', 'Welcome back!');
        trux_redirect('/');
    } else {
        $error = (string)($res['error'] ?? 'Login failed.');
    }
}

require_once __DIR__ . '/_header.php';
?>

<section class="card">
  <div class="card__body">
    <h1>Login</h1>

    <?php if ($error): ?>
      <div class="flash flash--error"><?= trux_e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php" class="form">
      <?= trux_csrf_field() ?>

      <label class="field">
        <span>Username or Email</span>
        <input name="login" value="<?= trux_e($login) ?>" required autocomplete="username">
      </label>

      <label class="field">
        <span>Password</span>
        <input type="password" name="password" required autocomplete="current-password">
      </label>

      <div class="row">
        <button class="btn" type="submit">Login</button>
        <a class="muted" href="/register.php">Create account</a>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>