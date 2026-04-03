<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'login';
$pageLayout = 'auth';

if (trux_is_logged_in()) {
  trux_redirect(TRUX_BASE_URL . '/');
}

$login = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = is_string($_POST['login'] ?? null) ? trim((string) $_POST['login']) : '';
  $password = is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '';

  $res = trux_attempt_login($login, $password);
  if ($res['ok'] ?? false) {
    trux_flash_set('success', 'Welcome back!');
    trux_redirect(TRUX_BASE_URL . '/');
  } elseif ($res['challenge_required'] ?? false) {
    trux_redirect((string)($res['redirect'] ?? '/login_challenge.php'));
  } else {
    $error = (string) ($res['error'] ?? 'Login failed.');
  }
}

$providerLoginOptions = [];
foreach (trux_linked_account_providers() as $providerKey => $providerMeta) {
  if ((string)($providerMeta['availability'] ?? '') !== 'available' || empty($providerMeta['supports_oauth'])) {
    continue;
  }
  if (!in_array($providerKey, ['google', 'discord', 'facebook', 'x'], true)) {
    continue;
  }
  $providerLoginOptions[$providerKey] = $providerMeta;
}

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--login">
  <div class="authGateway__signal">
    <div class="authGateway__signalFrame">
      <div class="authGateway__signalHead">
        <span class="authGateway__eyebrow">TruX Access</span>
        <h1 class="authGateway__title">Enter the network without friction.</h1>
        <p class="authGateway__copy">One secure entry point into your feed, saved threads, direct messages, and moderation workspace.</p>
      </div>

      <div class="authReadoutGrid" aria-hidden="true">
        <div class="authReadout">
          <span>Lane</span>
          <strong>Member return</strong>
        </div>
        <div class="authReadout">
          <span>Protocol</span>
          <strong>Session resume</strong>
        </div>
        <div class="authReadout">
          <span>Security</span>
          <strong>Existing auth rules</strong>
        </div>
      </div>
    </div>

    <div class="authGateway__stats">
      <div class="authStat">
        <strong>Feed</strong>
        <span>Jump back into discovery and posting.</span>
      </div>
      <div class="authStat">
        <strong>Inbox</strong>
        <span>Resume direct conversations and system updates.</span>
      </div>
      <div class="authStat">
        <strong>Control</strong>
        <span>Same auth, session, and CSRF rules as before.</span>
      </div>
    </div>
  </div>

  <div class="authGateway__lane">
    <section class="authSlab">
      <div class="authSlab__frame">
        <div class="authSlab__head">
          <span class="authSlab__eyebrow">Member login</span>
          <h2>Welcome back</h2>
          <p class="muted">Use your username or email to continue.</p>
        </div>

        <div class="authSlab__status" aria-hidden="true">
          <span>Route</span>
          <strong>/login.php</strong>
          <small>Credential handshake</small>
        </div>

        <?php if ($error): ?>
          <div class="flash flash--error"><?= trux_e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= TRUX_BASE_URL ?>/login.php" class="form authSlab__form">
          <?= trux_csrf_field() ?>

          <label class="field">
            <span>Username or email</span>
            <input name="login" value="<?= trux_e($login) ?>" required autocomplete="username">
          </label>

          <label class="field">
            <span>Password</span>
            <input type="password" name="password" required autocomplete="current-password">
          </label>

          <div class="authSlab__actions">
            <button class="shellButton shellButton--accent" type="submit">Login</button>
            <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/register.php">Create account</a>
          </div>

          <a class="authSlab__metaLink" href="<?= TRUX_BASE_URL ?>/forgot_password.php">Forgot your password?</a>
        </form>

        <?php if ($providerLoginOptions !== []): ?>
          <div class="authProviderOptions">
            <div class="authProviderOptions__head">
              <span>Linked provider sign-in</span>
              <small class="muted">Only works if this provider is already linked to your TruX account.</small>
            </div>
            <div class="authProviderOptions__grid">
              <?php foreach ($providerLoginOptions as $providerKey => $providerMeta): ?>
                <form method="post" action="<?= TRUX_BASE_URL ?>/provider_login.php" class="authProviderOptions__form">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="provider" value="<?= trux_e($providerKey) ?>">
                  <button class="shellButton shellButton--ghost authProviderOptions__button" type="submit">
                    <span class="authProviderOptions__icon" aria-hidden="true"><?= trux_linked_account_provider_icon_svg($providerKey) ?></span>
                    <span>Continue with <?= trux_e((string)$providerMeta['label']) ?></span>
                  </button>
                </form>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
