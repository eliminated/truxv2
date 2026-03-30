<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/src/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    require_once __DIR__ . '/_header.php';
    ?>
    <section class="settingsSectionCard">
      <div class="settingSection">
        <div class="settingSection__head">
          <span class="settingSection__eyebrow">Verification</span>
          <h3>Method not allowed</h3>
          <p class="muted">Use a verification resend button from the app or the verification page.</p>
        </div>
        <div class="row">
          <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/login.php">Back to login</a>
        </div>
      </div>
    </section>
    <?php
    require_once __DIR__ . '/_footer.php';
    exit;
}

$redirectPath = trux_safe_local_redirect_path($_POST['redirect'] ?? null, '/settings.php?section=account');

if (trux_is_logged_in()) {
    $me = trux_current_user();
    if (!$me) {
        trux_flash_set('error', 'Please log in to continue.');
        trux_redirect('/login.php');
    }

    $issueResult = trux_issue_email_verification_token((int)$me['id'], true);
    $errorCode = (string)($issueResult['error'] ?? '');
    if (!($issueResult['ok'] ?? false)) {
        if ($errorCode === 'already_verified') {
            trux_flash_set('info', 'Your email address is already verified.');
        } elseif ($errorCode === 'cooldown') {
            trux_flash_set('info', trux_email_verification_cooldown_text((int)($issueResult['remaining'] ?? 0)));
        } else {
            trux_flash_set('error', 'We could not prepare a new verification email right now.');
        }
        trux_redirect($redirectPath);
    }

    $verifyUser = is_array($issueResult['user'] ?? null) ? $issueResult['user'] : [];
    $sent = trux_send_email_verification_email(
        (string)($verifyUser['email'] ?? ''),
        (string)($verifyUser['username'] ?? 'TruX user'),
        (int)($verifyUser['id'] ?? 0),
        (string)($issueResult['token'] ?? '')
    );

    trux_flash_set($sent ? 'success' : 'error', $sent
        ? 'A new verification email has been sent. Use the link within 5 minutes to confirm ownership of this inbox.'
        : 'We generated a new verification link, but email delivery failed. Please try again after the 5-minute timer.');
    trux_redirect($redirectPath);
}

$uid = is_string($_POST['uid'] ?? null) && preg_match('/^\d+$/', (string)$_POST['uid'])
    ? (int)$_POST['uid']
    : 0;
$token = trim((string)($_POST['token'] ?? ''));
$loginRedirect = '/login.php';

if ($uid <= 0 || $token === '') {
    trux_flash_set('error', 'We could not resend verification for that link. Log in and try again from account settings.');
    trux_redirect($loginRedirect);
}

$account = trux_fetch_account_user_by_id($uid);
$storedToken = trim((string)($account['email_verify_token'] ?? ''));
if (
    !$account
    || !empty($account['email_verified'])
    || $storedToken === ''
    || !hash_equals($storedToken, $token)
) {
    trux_flash_set('error', 'We could not resend verification for that link. Log in and try again from account settings.');
    trux_redirect($loginRedirect);
}

$issueResult = trux_issue_email_verification_token($uid, true);
$errorCode = (string)($issueResult['error'] ?? '');
if (!($issueResult['ok'] ?? false)) {
    if ($errorCode === 'cooldown') {
        trux_flash_set('info', trux_email_verification_cooldown_text((int)($issueResult['remaining'] ?? 0)));
    } else {
        trux_flash_set('error', 'We could not prepare a new verification email right now.');
    }
    trux_redirect($loginRedirect);
}

$verifyUser = is_array($issueResult['user'] ?? null) ? $issueResult['user'] : [];
$sent = trux_send_email_verification_email(
    (string)($verifyUser['email'] ?? ''),
    (string)($verifyUser['username'] ?? 'TruX user'),
    (int)($verifyUser['id'] ?? 0),
    (string)($issueResult['token'] ?? '')
);

trux_flash_set($sent ? 'success' : 'error', $sent
    ? 'A new verification email has been sent. Check your inbox and use the link within 5 minutes before signing in again.'
    : 'We generated a new verification link, but email delivery failed. Please try again later from account settings after the 5-minute timer.');
trux_redirect($loginRedirect);
