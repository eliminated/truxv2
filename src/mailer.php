<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function trux_send_password_reset_email(string $toEmail, string $toName, string $resetUrl): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = (string)trux_env('TRUX_MAIL_HOST', 'smtp.hostinger.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string)trux_env('TRUX_MAIL_USER', '');
        $mail->Password   = (string)trux_env('TRUX_MAIL_PASS', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)trux_env('TRUX_MAIL_PORT', '465');

        $fromName = (string)trux_env('TRUX_MAIL_FROM_NAME', 'TruX');
        $fromEmail = (string)trux_env('TRUX_MAIL_USER', '');

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Reset your ' . $fromName . ' password';
        $mail->Body    = trux_password_reset_email_html($toName, $resetUrl, $fromName);
        $mail->AltBody = trux_password_reset_email_text($toName, $resetUrl, $fromName);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function trux_password_reset_email_html(string $name, string $resetUrl, string $appName): string {
    $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUrl     = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
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
    return "Hi {$name},\n\nWe received a request to reset your {$appName} password.\n\nClick this link to reset it (expires in 1 hour):\n{$resetUrl}\n\nIf you didn't request this, ignore this email.\n\n— The {$appName} team";
}

function trux_create_password_reset_token(string $email): ?string {
    $email = trim(strtolower($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $db = trux_db();

    $userStmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $userStmt->execute([$email]);
    if (!$userStmt->fetch()) {
        return null;
    }

    try {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $delStmt = $db->prepare('DELETE FROM password_resets WHERE email = ?');
        $delStmt->execute([$email]);

        $insStmt = $db->prepare(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)'
        );
        $insStmt->execute([$email, $token, $expiresAt]);

        return $token;
    } catch (PDOException) {
        return null;
    }
}

function trux_validate_password_reset_token(string $token): ?string {
    if ($token === '' || strlen($token) !== 64) {
        return null;
    }

    try {
        $db   = trux_db();
        $stmt = $db->prepare(
            'SELECT email FROM password_resets
             WHERE token = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        return $row ? (string)$row['email'] : null;
    } catch (PDOException) {
        return null;
    }
}

function trux_consume_password_reset_token(string $token, string $newPassword): bool {
    $email = trux_validate_password_reset_token($token);
    if ($email === null) {
        return false;
    }

    if (mb_strlen($newPassword) < 8) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($hash === false) {
        return false;
    }

    try {
        $db = trux_db();
        $db->beginTransaction();

        $updateUser = $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
        $updateUser->execute([$hash, $email]);

        $markUsed = $db->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE token = ?'
        );
        $markUsed->execute([$token]);

        $db->commit();
        return true;
    } catch (PDOException) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}