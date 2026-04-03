<?php
declare(strict_types=1);

const TRUX_MAILER_CLASS = 'PHPMailer\\PHPMailer\\PHPMailer';
const TRUX_MAILER_ENCRYPTION_SMTPS = 'PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS';

$_truxMailerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($_truxMailerAutoload)) {
    require_once $_truxMailerAutoload;
}

function trux_mailer_is_available(): bool {
    return class_exists(TRUX_MAILER_CLASS);
}

function trux_mailer_instance() {
    if (!trux_mailer_is_available()) {
        return null;
    }

    $mailerClass = TRUX_MAILER_CLASS;
    $mail = new $mailerClass(true);
    $mail->isSMTP();
    $mail->Host = TRUX_MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = TRUX_MAIL_USER;
    $mail->Password = TRUX_MAIL_PASS;
    $mail->SMTPSecure = defined(TRUX_MAILER_ENCRYPTION_SMTPS)
        ? constant(TRUX_MAILER_ENCRYPTION_SMTPS)
        : 'ssl';
    $mail->Port = TRUX_MAIL_PORT;

    $fromName = TRUX_MAIL_FROM_NAME !== '' ? TRUX_MAIL_FROM_NAME : 'TruX';
    $fromEmail = TRUX_MAIL_USER;
    $mail->setFrom($fromEmail, $fromName);

    return $mail;
}

function trux_build_email_verification_url(int $userId, string $token): string {
    return TRUX_BASE_URL . '/verify-email.php?token=' . urlencode($token) . '&uid=' . $userId;
}

function trux_send_password_reset_email(string $toEmail, string $toName, string $resetUrl): bool {
    if (!trux_mailer_is_available()) {
        return false;
    }

    try {
        $mail = trux_mailer_instance();
        if ($mail === null) {
            return false;
        }
        $fromName = TRUX_MAIL_FROM_NAME !== '' ? TRUX_MAIL_FROM_NAME : 'TruX';

        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your ' . $fromName . ' password';
        $mail->Body = trux_password_reset_email_html($toName, $resetUrl, $fromName);
        $mail->AltBody = trux_password_reset_email_text($toName, $resetUrl, $fromName);

        $mail->send();
        return true;
    } catch (Throwable) {
        return false;
    }
}

function trux_send_email_verification_email(string $toEmail, string $toName, int $userId, string $token): bool {
    if ($userId <= 0 || trim($token) === '' || !trux_mailer_is_available()) {
        return false;
    }

    $verifyUrl = trux_build_email_verification_url($userId, $token);
    $appName = TRUX_MAIL_FROM_NAME !== '' ? TRUX_MAIL_FROM_NAME : 'TruX';

    try {
        $mail = trux_mailer_instance();
        if ($mail === null) {
            return false;
        }
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your TruX email address';
        $mail->Body = trux_email_verification_email_html($toName, $verifyUrl, $appName);
        $mail->AltBody = trux_email_verification_email_text($toName, $verifyUrl, $appName);

        $mail->send();
        return true;
    } catch (Throwable) {
        return false;
    }
}

function trux_password_reset_email_html(string $name, string $resetUrl, string $appName): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset your password</title>
</head>
<body style="margin:0;padding:0;background:#0b0f18;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f18;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="520" cellpadding="0" cellspacing="0" style="background:#111827;border-radius:16px;border:1px solid rgba(255,255,255,.10);padding:40px;">
          <tr>
            <td>
              <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#e9eef7;">{$safeAppName}</h1>
              <p style="margin:0 0 28px;font-size:14px;color:#a9b4c7;">Social platform</p>
              <h2 style="margin:0 0 16px;font-size:18px;font-weight:700;color:#e9eef7;">Reset your password</h2>
              <p style="margin:0 0 24px;font-size:15px;color:#a9b4c7;line-height:1.6;">
                Hi {$safeName}, we received a request to reset your password.
                Click the button below to choose a new one. This link expires in <strong style="color:#e9eef7;">1 hour</strong>.
              </p>
              <a href="{$safeUrl}"
                 style="display:inline-block;padding:12px 28px;background:rgba(122,167,255,.16);color:#e9eef7;border-radius:14px;border:1px solid rgba(122,167,255,.40);text-decoration:none;font-weight:900;font-size:15px;">
                Reset password
              </a>
              <p style="margin:28px 0 0;font-size:13px;color:#a9b4c7;line-height:1.6;">
                If you didn't request this, you can safely ignore this email. Your password won't change.
              </p>
              <p style="margin:16px 0 0;font-size:12px;color:#a9b4c7;">
                Or copy this link: <a href="{$safeUrl}" style="color:#7aa7ff;">{$safeUrl}</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function trux_password_reset_email_text(string $name, string $resetUrl, string $appName): string {
    return "Hi {$name},\n\nWe received a request to reset your {$appName} password.\n\nClick this link to reset it (expires in 1 hour):\n{$resetUrl}\n\nIf you didn't request this, ignore this email.\n\n- The {$appName} team";
}

function trux_email_verification_email_html(string $name, string $verifyUrl, string $appName): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
    $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify your email</title>
</head>
<body style="margin:0;padding:0;background:#060810;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#060810;padding:36px 0;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:linear-gradient(180deg,#0a0f1d,#0d1322);border-radius:20px;border:1px solid rgba(89,243,255,.22);padding:40px;box-shadow:0 28px 80px rgba(0,0,0,.35);">
          <tr>
            <td>
              <p style="margin:0 0 12px;color:#59f3ff;font-size:12px;font-weight:800;letter-spacing:.22em;text-transform:uppercase;">Identity handshake</p>
              <h1 style="margin:0 0 8px;color:#f5f7ff;font-size:28px;font-weight:900;">Verify your TruX email address</h1>
              <p style="margin:0 0 24px;color:#a9b4c7;font-size:15px;line-height:1.7;">
                Hi {$safeName}, click the verification link below to prove that you control this inbox and unlock sensitive account controls in {$safeAppName}.
              </p>
              <div style="margin:0 0 24px;padding:18px 20px;border:1px solid rgba(255,0,170,.18);border-radius:16px;background:linear-gradient(135deg,rgba(89,243,255,.08),rgba(255,0,170,.08));color:#dbe7ff;font-size:14px;line-height:1.7;">
                A recognized domain like Gmail or Outlook only tells us the domain is known. It does <strong style="color:#ffffff;">not</strong> prove that you own this inbox. This link expires in <strong style="color:#ffffff;">5 minutes</strong>.
              </div>
              <a href="{$safeUrl}" style="display:inline-block;padding:14px 26px;border-radius:14px;border:1px solid rgba(89,243,255,.4);background:linear-gradient(135deg,rgba(89,243,255,.22),rgba(255,0,170,.16));color:#f5f7ff;text-decoration:none;font-weight:900;font-size:15px;">
                Verify email address
              </a>
              <p style="margin:24px 0 0;color:#a9b4c7;font-size:13px;line-height:1.7;">
                If the button does not open, use this link:<br>
                <a href="{$safeUrl}" style="color:#59f3ff;">{$safeUrl}</a>
              </p>
              <p style="margin:18px 0 0;color:#8c95a8;font-size:12px;line-height:1.7;">
                If the link expires, request a fresh verification email from your TruX account settings. If you did not create this account, you can ignore this email.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function trux_email_verification_email_text(string $name, string $verifyUrl, string $appName): string {
    return "Hi {$name},\n\nOpen the link below to verify that you control this {$appName} inbox:\n{$verifyUrl}\n\nA recognized email domain does not prove inbox ownership.\nThis link expires in 5 minutes.\n\nIf the link expires, request a fresh verification email from account settings. If you did not create this account, you can ignore this email.\n\n- The {$appName} team";
}

function trux_create_password_reset_token(string $email): ?string {
    $context = function_exists('trux_security_device_context') ? trux_security_device_context() : ['ip_address' => null, 'user_agent' => null];
    $result = function_exists('trux_guardian_issue_password_reset')
        ? trux_guardian_issue_password_reset($email, $context['ip_address'] ?? null, $context['user_agent'] ?? null)
        : ['ok' => false];

    return ($result['ok'] ?? false) ? '__guardian__' : null;
}

function trux_validate_password_reset_token(string $token): ?string {
    return null;
}

function trux_consume_password_reset_token(string $token, string $newPassword): bool {
    return false;
}
