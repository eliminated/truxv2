from __future__ import annotations

import smtplib
from email.message import EmailMessage

from .config import settings


def mail_ready() -> bool:
    return all(
        [
            settings.mail_host.strip(),
            settings.mail_user.strip(),
            settings.mail_pass.strip(),
        ]
    )


def send_email(to_email: str, subject: str, text_body: str, html_body: str | None = None) -> bool:
    if not mail_ready() or not to_email.strip():
        return False

    message = EmailMessage()
    message["Subject"] = subject
    message["From"] = f"{settings.mail_from_name} <{settings.mail_user}>"
    message["To"] = to_email
    message.set_content(text_body)
    if html_body:
        message.add_alternative(html_body, subtype="html")

    try:
        with smtplib.SMTP_SSL(settings.mail_host, settings.mail_port, timeout=15) as server:
            server.login(settings.mail_user, settings.mail_pass)
            server.send_message(message)
        return True
    except Exception:
        return False


def send_email_otp(to_email: str, name: str, code: str, purpose_label: str) -> bool:
    subject = f"{settings.app_name} security code"
    text_body = (
        f"Hi {name},\n\n"
        f"Your {settings.app_name} security code for {purpose_label} is: {code}\n\n"
        "This code expires in 10 minutes.\n"
        "If you did not request this code, you can ignore this message.\n"
    )
    html_body = (
        "<html><body style='font-family:Arial,sans-serif;background:#0b0f18;color:#e9eef7;padding:24px'>"
        f"<h2>{settings.app_name} security code</h2>"
        f"<p>Hi {name},</p>"
        f"<p>Your security code for <strong>{purpose_label}</strong> is:</p>"
        f"<p style='font-size:28px;font-weight:700;letter-spacing:0.18em'>{code}</p>"
        "<p>This code expires in 10 minutes.</p>"
        "<p>If you did not request this code, you can ignore this message.</p>"
        "</body></html>"
    )
    return send_email(to_email, subject, text_body, html_body)


def send_password_reset(to_email: str, name: str, reset_url: str) -> bool:
    subject = f"Reset your {settings.app_name} password"
    text_body = (
        f"Hi {name},\n\n"
        f"Use the link below to reset your {settings.app_name} password:\n{reset_url}\n\n"
        "This link expires in 30 minutes.\n"
        "If you did not request a reset, you can ignore this email.\n"
    )
    html_body = (
        "<html><body style='font-family:Arial,sans-serif;background:#0b0f18;color:#e9eef7;padding:24px'>"
        f"<h2>Reset your {settings.app_name} password</h2>"
        f"<p>Hi {name},</p>"
        "<p>Use the link below to choose a new password.</p>"
        f"<p><a href='{reset_url}'>{reset_url}</a></p>"
        "<p>This link expires in 30 minutes.</p>"
        "<p>If you did not request a reset, you can ignore this email.</p>"
        "</body></html>"
    )
    return send_email(to_email, subject, text_body, html_body)


def send_suspicious_login_alert(to_email: str, name: str, summary: str) -> bool:
    subject = f"{settings.app_name} suspicious sign-in alert"
    text_body = (
        f"Hi {name},\n\n"
        "We noticed a sign-in that may need your attention.\n\n"
        f"{summary}\n\n"
        f"If this was not you, change your password and review active sessions in {settings.app_name} immediately.\n"
    )
    html_body = (
        "<html><body style='font-family:Arial,sans-serif;background:#0b0f18;color:#e9eef7;padding:24px'>"
        f"<h2>{settings.app_name} suspicious sign-in alert</h2>"
        f"<p>Hi {name},</p>"
        "<p>We noticed a sign-in that may need your attention.</p>"
        f"<p>{summary}</p>"
        f"<p>If this was not you, change your password and review active sessions in {settings.app_name} immediately.</p>"
        "</body></html>"
    )
    return send_email(to_email, subject, text_body, html_body)

