<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'verify-email';
$pageLayout = 'auth';

$token = trim(trux_str_param('token', ''));
$uid = trux_int_param('uid', 0);
$errorMessage = '';

if ($uid > 0 && $token !== '') {
    $verificationResult = trux_verify_email_token($uid, $token);
    if ($verificationResult['ok'] ?? false) {
        trux_flash_set('success', 'Your email address is now verified.');
        trux_redirect('/');
    }

    $errorCode = (string)($verificationResult['error'] ?? 'invalid');
    if ($errorCode === 'already_verified') {
        trux_flash_set('info', 'Your email address is already verified.');
        trux_redirect(trux_is_logged_in() ? '/' : '/login.php');
    }

    $errorMessage = $errorCode === 'expired'
        ? 'This verification link expired after 5 minutes. Request a new verification email below.'
        : 'This verification link is invalid. Request a new verification email below or resend from account settings after you log in.';
} else {
    $errorMessage = 'This verification link is incomplete or invalid.';
}

$resendRedirect = trux_is_logged_in() ? '/settings.php?section=account' : '/login.php';

require_once __DIR__ . '/_header.php';
?>

<section class="authGateway authGateway--recovery">
  <div class="authGateway__signal">
    <div class="authGateway__signalFrame">
      <div class="authGateway__signalHead">
        <span class="authGateway__eyebrow">Verification relay</span>
        <h1 class="authGateway__title">Email verification needs a fresh handshake.</h1>
        <p class="authGateway__copy">A recognized provider domain is advisory only. Your account stays active, but sensitive account controls remain locked until you click the verification link and prove you control this inbox.</p>
      </div>

      <div class="authReadoutGrid" aria-hidden="true">
        <div class="authReadout">
          <span>Lane</span>
          <strong>Email trust</strong>
        </div>
        <div class="authReadout">
          <span>Protocol</span>
          <strong>Token validate</strong>
        </div>
        <div class="authReadout">
          <span>Window</span>
          <strong>5 minutes</strong>
        </div>
      </div>
    </div>

      <div class="authGateway__stats">
        <div class="authStat">
          <strong>Account access</strong>
          <span>You can still browse and post while this address is unverified.</span>
        </div>
        <div class="authStat">
          <strong>Ownership</strong>
          <span>A known domain like Gmail or Outlook does not prove you own the inbox. Clicking the email link does.</span>
        </div>
        <div class="authStat">
          <strong>Expiry</strong>
          <span>Verification links expire after 5 minutes. When that happens, request a fresh email and continue from your inbox.</span>
        </div>
      </div>
  </div>

  <div class="authGateway__lane">
    <section class="authSlab">
      <div class="authSlab__frame">
        <div class="authSlab__head">
          <span class="authSlab__eyebrow">Verification</span>
          <h2>Verification issue</h2>
          <p class="muted">Request a fresh message and use the link within 5 minutes to confirm inbox ownership.</p>
        </div>

        <div class="authSlab__status" aria-hidden="true">
          <span>Route</span>
          <strong>/verify-email.php</strong>
          <small>Token validation</small>
        </div>

        <div class="flash flash--error"><?= trux_e($errorMessage) ?></div>

        <?php if ($uid > 0 && $token !== ''): ?>
          <form method="post" action="<?= TRUX_BASE_URL ?>/resend-verification.php" class="form authSlab__form">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="uid" value="<?= (int)$uid ?>">
            <input type="hidden" name="token" value="<?= trux_e($token) ?>">
            <input type="hidden" name="redirect" value="<?= trux_e($resendRedirect) ?>">

            <div class="authSlab__actions">
              <button class="shellButton shellButton--accent" type="submit">Resend verification email</button>
              <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/login.php">Back to login</a>
            </div>
          </form>
        <?php else: ?>
          <div class="authSlab__actions">
            <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/login.php">Back to login</a>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
