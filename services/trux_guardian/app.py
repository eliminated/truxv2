from __future__ import annotations

import json
from typing import Any

import pyotp
from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

from .config import settings
from .database import db_cursor
from .mailer import send_email_otp, send_password_reset, send_suspicious_login_alert
from .security_utils import (
    dt_in_minutes,
    mask_email,
    pbkdf2_hash,
    pbkdf2_verify,
    random_numeric_code,
    random_recovery_code,
    random_token_hex,
    utcnow,
)


app = FastAPI(title="TruX Guardian", version="0.8.5")


def require_internal_auth(authorization: str | None = Header(default=None, alias="Authorization")) -> None:
    expected = settings.shared_secret.strip()
    if expected == "":
        raise HTTPException(status_code=503, detail="guardian_secret_missing")
    if authorization != f"Bearer {expected}":
        raise HTTPException(status_code=401, detail="unauthorized")


class UserRequest(BaseModel):
    user_id: int


class TotpSetupStartRequest(UserRequest):
    issuer: str | None = None


class TotpSetupVerifyRequest(UserRequest):
    challenge_public_id: str
    code: str
    primary_method: str = Field(default="totp")


class TotpCodeVerifyRequest(UserRequest):
    code: str
    purpose: str = Field(default="login")


class RecoveryVerifyRequest(UserRequest):
    code: str
    purpose: str = Field(default="login")


class EmailOtpSendRequest(UserRequest):
    purpose: str = Field(default="login")
    activate_email_2fa: bool = False


class EmailOtpVerifyRequest(UserRequest):
    challenge_public_id: str
    code: str
    purpose: str = Field(default="login")
    activate_email_2fa: bool = False
    make_primary: bool = False


class AnalyzeLoginRequest(BaseModel):
    user_id: int | None = None
    login_identifier: str | None = None
    ip_address: str | None = None
    user_agent: str | None = None


class RecordLoginEventRequest(BaseModel):
    user_id: int | None = None
    login_identifier: str | None = None
    outcome: str
    login_method: str = Field(default="password")
    provider: str | None = None
    session_public_id: str | None = None
    ip_address: str | None = None
    user_agent: str | None = None
    device_label: str | None = None
    browser_name: str | None = None
    platform_name: str | None = None
    suspicious: bool = False
    reasons: list[str] = Field(default_factory=list)


class RevokeSessionRequest(UserRequest):
    session_public_id: str | None = None
    exclude_session_public_id: str | None = None
    revoke_all: bool = False
    reason: str = Field(default="revoked")
    revoked_by_session_public_id: str | None = None


class PasswordResetIssueRequest(BaseModel):
    email: str
    ip_address: str | None = None
    user_agent: str | None = None


class PasswordResetConsumeRequest(BaseModel):
    selector: str
    validator: str
    preview: bool = False
    password_hash: str | None = None
    step_up_challenge_public_id: str | None = None
    step_up_code: str | None = None
    ip_address: str | None = None
    user_agent: str | None = None


def fetch_user(cursor, user_id: int) -> dict[str, Any] | None:
    cursor.execute(
        """
        SELECT id, username, email, display_name, email_verified, password_hash, notify_security_alerts
        FROM users
        WHERE id = %s
        LIMIT 1
        """,
        (user_id,),
    )
    return cursor.fetchone()


def ensure_2fa_row(cursor, user_id: int) -> None:
    cursor.execute("SELECT user_id FROM user_2fa_settings WHERE user_id = %s LIMIT 1", (user_id,))
    if cursor.fetchone():
        return
    cursor.execute(
        """
        INSERT INTO user_2fa_settings
            (user_id, primary_method, totp_enabled, email_otp_enabled, challenge_on_sensitive, created_at, updated_at)
        VALUES (%s, 'none', 0, 0, 1, NOW(), NOW())
        """,
        (user_id,),
    )


def fetch_2fa_settings(cursor, user_id: int) -> dict[str, Any] | None:
    ensure_2fa_row(cursor, user_id)
    cursor.execute("SELECT * FROM user_2fa_settings WHERE user_id = %s LIMIT 1", (user_id,))
    return cursor.fetchone()


def purpose_label(purpose: str) -> str:
    labels = {
        "login": "sign-in",
        "sensitive_action": "a security confirmation",
        "2fa_email_enable": "email two-factor setup",
        "password_reset": "password reset verification",
    }
    return labels.get(purpose, "a security check")


def summarize_device(ip_address: str | None, browser_name: str | None, platform_name: str | None) -> str:
    parts = [part for part in [browser_name, platform_name, ip_address] if part]
    return " / ".join(parts) if parts else "Unknown device"


def analyze_login_risk(cursor, user_id: int | None, login_identifier: str | None, ip_address: str | None, user_agent: str | None) -> dict[str, Any]:
    reasons: list[str] = []
    identifier = (login_identifier or "").strip().lower()
    ip_value = (ip_address or "").strip()
    ua_value = (user_agent or "").strip()

    repeated_failures = 0
    cursor.execute(
        """
        SELECT COUNT(*) AS failure_count
        FROM login_attempts
        WHERE created_at >= (NOW() - INTERVAL 15 MINUTE)
          AND outcome = 'failure'
          AND ((login_identifier IS NOT NULL AND login_identifier = %s) OR (ip_address IS NOT NULL AND ip_address = %s))
        """,
        (identifier if identifier else None, ip_value if ip_value else None),
    )
    failure_row = cursor.fetchone() or {}
    repeated_failures = int(failure_row.get("failure_count") or 0)
    if repeated_failures >= 5:
        reasons.append("repeated_failures")

    if user_id and user_id > 0:
        cursor.execute("SELECT COUNT(*) AS total_logins FROM login_history WHERE user_id = %s", (user_id,))
        total_logins = int((cursor.fetchone() or {}).get("total_logins") or 0)

        if ua_value:
            cursor.execute(
                "SELECT COUNT(*) AS matches FROM login_history WHERE user_id = %s AND user_agent = %s",
                (user_id, ua_value),
            )
            ua_matches = int((cursor.fetchone() or {}).get("matches") or 0)
            if total_logins > 0 and ua_matches == 0:
                reasons.append("new_device")

        if ip_value:
            cursor.execute(
                "SELECT COUNT(*) AS matches FROM login_history WHERE user_id = %s AND ip_address = %s",
                (user_id, ip_value),
            )
            ip_matches = int((cursor.fetchone() or {}).get("matches") or 0)
            if total_logins > 0 and ip_matches == 0:
                reasons.append("unusual_ip")

    unique_reasons = list(dict.fromkeys(reasons))
    return {
        "suspicious": unique_reasons != [],
        "reasons": unique_reasons,
        "repeated_failures": repeated_failures,
    }


def create_security_notification(cursor, user_id: int, event_key: str, target_url: str, summary: str) -> None:
    cursor.execute("SELECT id FROM users WHERE username = 'report_system_updates_bot' LIMIT 1")
    system_user = cursor.fetchone() or {}
    actor_user_id = int(system_user.get("id") or 0)
    if actor_user_id <= 0:
        return

    cursor.execute(
        """
        INSERT INTO notifications (recipient_user_id, actor_user_id, type, event_key, target_url)
        VALUES (%s, %s, 'security_alert', %s, %s)
        ON DUPLICATE KEY UPDATE created_at = created_at
        """,
        (user_id, actor_user_id, event_key, target_url),
    )
    cursor.execute(
        """
        INSERT INTO moderation_activity_events
            (event_type, actor_user_id, subject_type, subject_id, related_user_id, source_url, metadata_json)
        VALUES ('security_alert', %s, 'user', %s, %s, %s, %s)
        """,
        (actor_user_id, user_id, user_id, target_url, json.dumps({"summary": summary})),
    )


def replace_recovery_codes(cursor, user_id: int) -> list[str]:
    batch_id = random_token_hex(16)
    codes = [random_recovery_code() for _ in range(10)]
    cursor.execute(
        "UPDATE user_recovery_codes SET replaced_at = NOW() WHERE user_id = %s AND replaced_at IS NULL AND used_at IS NULL",
        (user_id,),
    )
    for index, code in enumerate(codes, start=1):
        cursor.execute(
            """
            INSERT INTO user_recovery_codes
                (user_id, batch_id, code_slot, code_hash, code_hint, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
            """,
            (user_id, batch_id, index, pbkdf2_hash(code.replace("-", "")), code[-4:]),
        )
    cursor.execute(
        """
        UPDATE user_2fa_settings
        SET recovery_codes_generated_at = NOW(), updated_at = NOW()
        WHERE user_id = %s
        """,
        (user_id,),
    )
    return codes


def active_email_challenge(cursor, user_id: int, purpose: str) -> dict[str, Any] | None:
    cursor.execute(
        """
        SELECT *
        FROM security_challenges
        WHERE user_id = %s
          AND purpose = %s
          AND method = 'email'
          AND consumed_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
        """,
        (user_id, purpose),
    )
    return cursor.fetchone()


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/internal/2fa/setup/start", dependencies=[Depends(require_internal_auth)])
def start_totp_setup(payload: TotpSetupStartRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        user = fetch_user(cursor, payload.user_id)
        if not user:
            raise HTTPException(status_code=404, detail="user_not_found")
        ensure_2fa_row(cursor, payload.user_id)
        secret = pyotp.random_base32()
        public_id = random_token_hex(16)
        issuer = (payload.issuer or settings.app_name).strip() or settings.app_name
        label = f"{issuer}:{user['username']}"
        cursor.execute(
            """
            INSERT INTO security_challenges
                (public_id, user_id, purpose, method, totp_secret_ciphertext, expires_at, created_at, updated_at)
            VALUES (%s, %s, '2fa_setup', 'totp', %s, %s, NOW(), NOW())
            """,
            (public_id, payload.user_id, settings.fernet.encrypt(secret.encode("utf-8")).decode("utf-8"), dt_in_minutes(15)),
        )
        return {
            "ok": True,
            "challenge_public_id": public_id,
            "secret": secret,
            "otpauth_url": pyotp.totp.TOTP(secret).provisioning_uri(name=label, issuer_name=issuer),
        }


@app.post("/internal/2fa/setup/verify", dependencies=[Depends(require_internal_auth)])
def verify_totp_setup(payload: TotpSetupVerifyRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        challenge = None
        cursor.execute(
            """
            SELECT * FROM security_challenges
            WHERE public_id = %s AND user_id = %s AND purpose = '2fa_setup' AND consumed_at IS NULL AND expires_at > NOW()
            LIMIT 1
            """,
            (payload.challenge_public_id, payload.user_id),
        )
        challenge = cursor.fetchone()
        if not challenge:
            return {"ok": False, "error": "challenge_missing"}
        secret = settings.fernet.decrypt(challenge["totp_secret_ciphertext"].encode("utf-8")).decode("utf-8")
        if not pyotp.TOTP(secret).verify(payload.code.strip(), valid_window=1):
            cursor.execute("UPDATE security_challenges SET attempt_count = attempt_count + 1 WHERE id = %s", (challenge["id"],))
            return {"ok": False, "error": "invalid_code"}
        ensure_2fa_row(cursor, payload.user_id)
        recovery_codes = replace_recovery_codes(cursor, payload.user_id)
        cursor.execute(
            """
            UPDATE user_2fa_settings
            SET primary_method = %s,
                totp_enabled = 1,
                disabled_at = NULL,
                totp_secret_ciphertext = %s,
                totp_confirmed_at = NOW(),
                updated_at = NOW()
            WHERE user_id = %s
            """,
            (payload.primary_method if payload.primary_method in {"totp", "email"} else "totp", challenge["totp_secret_ciphertext"], payload.user_id),
        )
        cursor.execute("UPDATE security_challenges SET verified_at = NOW(), consumed_at = NOW() WHERE id = %s", (challenge["id"],))
        return {"ok": True, "recovery_codes": recovery_codes}


@app.post("/internal/2fa/challenge/verify", dependencies=[Depends(require_internal_auth)])
def verify_totp_challenge(payload: TotpCodeVerifyRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        settings_row = fetch_2fa_settings(cursor, payload.user_id)
        if not settings_row or not int(settings_row.get("totp_enabled") or 0) or not settings_row.get("totp_secret_ciphertext"):
            return {"ok": False, "error": "totp_not_enabled"}
        secret = settings.fernet.decrypt(settings_row["totp_secret_ciphertext"].encode("utf-8")).decode("utf-8")
        if not pyotp.TOTP(secret).verify(payload.code.strip(), valid_window=1):
            return {"ok": False, "error": "invalid_code"}
        cursor.execute(
            "UPDATE user_2fa_settings SET last_challenge_at = NOW(), updated_at = NOW() WHERE user_id = %s",
            (payload.user_id,),
        )
        return {"ok": True}


@app.post("/internal/2fa/recovery/verify", dependencies=[Depends(require_internal_auth)])
def verify_recovery_code(payload: RecoveryVerifyRequest) -> dict[str, Any]:
    candidate = "".join(ch for ch in payload.code.strip().upper() if ch.isalnum())
    with db_cursor() as (_, cursor):
        cursor.execute(
            """
            SELECT id, code_hash
            FROM user_recovery_codes
            WHERE user_id = %s AND used_at IS NULL AND replaced_at IS NULL
            ORDER BY id DESC
            """,
            (payload.user_id,),
        )
        rows = cursor.fetchall() or []
        for row in rows:
            if pbkdf2_verify(candidate, row["code_hash"]):
                cursor.execute("UPDATE user_recovery_codes SET used_at = NOW(), updated_at = NOW() WHERE id = %s", (row["id"],))
                cursor.execute("UPDATE user_2fa_settings SET last_challenge_at = NOW(), updated_at = NOW() WHERE user_id = %s", (payload.user_id,))
                return {"ok": True}
    return {"ok": False, "error": "invalid_code"}


@app.post("/internal/2fa/recovery/regenerate", dependencies=[Depends(require_internal_auth)])
def regenerate_recovery_codes(payload: UserRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        ensure_2fa_row(cursor, payload.user_id)
        return {"ok": True, "recovery_codes": replace_recovery_codes(cursor, payload.user_id)}


@app.post("/internal/2fa/disable", dependencies=[Depends(require_internal_auth)])
def disable_2fa(payload: UserRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        ensure_2fa_row(cursor, payload.user_id)
        cursor.execute(
            """
            UPDATE user_2fa_settings
            SET primary_method = 'none',
                totp_enabled = 0,
                email_otp_enabled = 0,
                totp_secret_ciphertext = NULL,
                disabled_at = NOW(),
                updated_at = NOW()
            WHERE user_id = %s
            """,
            (payload.user_id,),
        )
        cursor.execute(
            "UPDATE user_recovery_codes SET replaced_at = NOW(), updated_at = NOW() WHERE user_id = %s AND replaced_at IS NULL AND used_at IS NULL",
            (payload.user_id,),
        )
        return {"ok": True}


@app.post("/internal/email-otp/send", dependencies=[Depends(require_internal_auth)])
def send_email_otp_challenge(payload: EmailOtpSendRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        user = fetch_user(cursor, payload.user_id)
        if not user:
            raise HTTPException(status_code=404, detail="user_not_found")
        ensure_2fa_row(cursor, payload.user_id)
        challenge = active_email_challenge(cursor, payload.user_id, payload.purpose)
        if challenge and challenge.get("last_sent_at"):
            delta = int((utcnow() - challenge["last_sent_at"]).total_seconds())
            if delta < 60:
                return {
                    "ok": True,
                    "challenge_public_id": challenge["public_id"],
                    "cooldown_remaining": max(0, 60 - delta),
                    "masked_target": mask_email(user["email"]),
                }

        code = random_numeric_code(6)
        public_id = challenge["public_id"] if challenge else random_token_hex(16)
        if challenge:
            cursor.execute(
                """
                UPDATE security_challenges
                SET code_hash = %s,
                    target_email = %s,
                    payload_json = %s,
                    sent_count = sent_count + 1,
                    last_sent_at = NOW(),
                    expires_at = %s,
                    updated_at = NOW()
                WHERE id = %s
                """,
                (pbkdf2_hash(code), user["email"], json.dumps({"activate_email_2fa": payload.activate_email_2fa}), dt_in_minutes(10), challenge["id"]),
            )
        else:
            cursor.execute(
                """
                INSERT INTO security_challenges
                    (public_id, user_id, purpose, method, target_email, code_hash, payload_json, sent_count, last_sent_at, expires_at, created_at, updated_at)
                VALUES (%s, %s, %s, 'email', %s, %s, %s, 1, NOW(), %s, NOW(), NOW())
                """,
                (public_id, payload.user_id, payload.purpose, user["email"], pbkdf2_hash(code), json.dumps({"activate_email_2fa": payload.activate_email_2fa}), dt_in_minutes(10)),
            )
        send_email_otp(user["email"], user["display_name"] or user["username"], code, purpose_label(payload.purpose))
        return {"ok": True, "challenge_public_id": public_id, "masked_target": mask_email(user["email"])}


@app.post("/internal/email-otp/verify", dependencies=[Depends(require_internal_auth)])
def verify_email_otp(payload: EmailOtpVerifyRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        cursor.execute(
            """
            SELECT * FROM security_challenges
            WHERE public_id = %s AND user_id = %s AND purpose = %s AND method = 'email'
              AND consumed_at IS NULL AND expires_at > NOW()
            LIMIT 1
            """,
            (payload.challenge_public_id, payload.user_id, payload.purpose),
        )
        challenge = cursor.fetchone()
        if not challenge:
            return {"ok": False, "error": "challenge_missing"}
        if int(challenge.get("attempt_count") or 0) >= int(challenge.get("max_attempts") or 5):
            return {"ok": False, "error": "too_many_attempts"}
        if not pbkdf2_verify(payload.code.strip(), challenge["code_hash"]):
            cursor.execute("UPDATE security_challenges SET attempt_count = attempt_count + 1, updated_at = NOW() WHERE id = %s", (challenge["id"],))
            return {"ok": False, "error": "invalid_code"}
        cursor.execute("UPDATE security_challenges SET verified_at = NOW(), consumed_at = NOW(), updated_at = NOW() WHERE id = %s", (challenge["id"],))
        if payload.activate_email_2fa:
            ensure_2fa_row(cursor, payload.user_id)
            recovery_codes = replace_recovery_codes(cursor, payload.user_id)
            primary_method = "email" if payload.make_primary else "totp"
            settings_row = fetch_2fa_settings(cursor, payload.user_id) or {}
            if not int(settings_row.get("totp_enabled") or 0):
                primary_method = "email"
            cursor.execute(
                """
                UPDATE user_2fa_settings
                SET email_otp_enabled = 1,
                    email_confirmed_at = NOW(),
                    primary_method = %s,
                    disabled_at = NULL,
                    updated_at = NOW()
                WHERE user_id = %s
                """,
                (primary_method, payload.user_id),
            )
            return {"ok": True, "recovery_codes": recovery_codes}
        return {"ok": True}


@app.post("/internal/security/analyze-login", dependencies=[Depends(require_internal_auth)])
def analyze_login(payload: AnalyzeLoginRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        analysis = analyze_login_risk(cursor, payload.user_id, payload.login_identifier, payload.ip_address, payload.user_agent)
        if payload.user_id and payload.user_id > 0:
            settings_row = fetch_2fa_settings(cursor, payload.user_id)
            analysis["totp_enabled"] = bool(int(settings_row.get("totp_enabled") or 0)) if settings_row else False
            analysis["email_otp_enabled"] = bool(int(settings_row.get("email_otp_enabled") or 0)) if settings_row else False
            analysis["primary_method"] = (settings_row.get("primary_method") or "none") if settings_row else "none"
        return {"ok": True, **analysis}


@app.post("/internal/security/record-login-event", dependencies=[Depends(require_internal_auth)])
def record_login_event(payload: RecordLoginEventRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        outcome = payload.outcome.strip().lower()
        if outcome not in {"success", "failure"}:
            raise HTTPException(status_code=422, detail="invalid_outcome")
        cursor.execute(
            """
            INSERT INTO login_attempts
                (user_id, login_identifier, attempt_method, provider, outcome, failure_reason, challenge_public_id, ip_address, user_agent, created_at)
            VALUES (%s, %s, %s, %s, %s, %s, NULL, %s, %s, NOW())
            """,
            (
                payload.user_id if payload.user_id and payload.user_id > 0 else None,
                (payload.login_identifier or "").strip().lower() or None,
                payload.login_method,
                payload.provider,
                outcome,
                None if outcome == "success" else ",".join(payload.reasons)[:80] or "invalid_credentials",
                payload.ip_address,
                payload.user_agent,
            ),
        )
        if outcome == "success" and payload.user_id and payload.user_id > 0:
            cursor.execute(
                """
                INSERT INTO login_history
                    (user_id, session_public_id, login_method, provider, ip_address, user_agent, device_label, browser_name, platform_name,
                     is_new_device, is_unusual_ip, had_recent_failures, is_suspicious, risk_score, risk_reasons_json, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
                """,
                (
                    payload.user_id,
                    payload.session_public_id,
                    payload.login_method,
                    payload.provider,
                    payload.ip_address,
                    payload.user_agent,
                    payload.device_label,
                    payload.browser_name,
                    payload.platform_name,
                    1 if "new_device" in payload.reasons else 0,
                    1 if "unusual_ip" in payload.reasons else 0,
                    1 if "repeated_failures" in payload.reasons else 0,
                    1 if payload.suspicious else 0,
                    len(payload.reasons) * 25,
                    json.dumps(payload.reasons),
                ),
            )
            if payload.provider:
                cursor.execute(
                    "UPDATE linked_accounts SET last_login_at = NOW(), last_used_at = NOW(), updated_at = NOW() WHERE user_id = %s AND provider = %s",
                    (payload.user_id, payload.provider),
                )
            user = fetch_user(cursor, payload.user_id)
            if user and payload.suspicious and int(user.get("notify_security_alerts") or 0):
                summary = "Reasons: " + ", ".join(payload.reasons or ["security check"]) + ". Device: " + summarize_device(
                    payload.ip_address,
                    payload.browser_name,
                    payload.platform_name,
                )
                create_security_notification(cursor, payload.user_id, f"security_alert:login:{payload.session_public_id or random_token_hex(8)}", "/settings.php?section=security", summary)
                send_suspicious_login_alert(user["email"], user["display_name"] or user["username"], summary)
        return {"ok": True}


@app.post("/internal/security/revoke-session", dependencies=[Depends(require_internal_auth)])
def revoke_session(payload: RevokeSessionRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        if payload.revoke_all:
            cursor.execute(
                """
                UPDATE user_sessions
                SET revoked_at = NOW(), revoke_reason = %s, revoked_by_session_public_id = %s
                WHERE user_id = %s AND revoked_at IS NULL
                """,
                (payload.reason[:120], payload.revoked_by_session_public_id, payload.user_id),
            )
            return {"ok": True, "revoked_count": cursor.rowcount}
        if payload.exclude_session_public_id:
            cursor.execute(
                """
                UPDATE user_sessions
                SET revoked_at = NOW(), revoke_reason = %s, revoked_by_session_public_id = %s
                WHERE user_id = %s AND revoked_at IS NULL AND session_public_id <> %s
                """,
                (payload.reason[:120], payload.revoked_by_session_public_id, payload.user_id, payload.exclude_session_public_id),
            )
            return {"ok": True, "revoked_count": cursor.rowcount}
        if payload.session_public_id:
            cursor.execute(
                """
                UPDATE user_sessions
                SET revoked_at = NOW(), revoke_reason = %s, revoked_by_session_public_id = %s
                WHERE user_id = %s AND session_public_id = %s AND revoked_at IS NULL
                """,
                (payload.reason[:120], payload.revoked_by_session_public_id, payload.user_id, payload.session_public_id),
            )
            return {"ok": True, "revoked_count": cursor.rowcount}
        raise HTTPException(status_code=422, detail="missing_revoke_target")


@app.post("/internal/password-reset/issue", dependencies=[Depends(require_internal_auth)])
def password_reset_issue(payload: PasswordResetIssueRequest) -> dict[str, Any]:
    email = payload.email.strip().lower()
    if email == "":
        return {"ok": True}
    with db_cursor() as (_, cursor):
        cursor.execute(
            """
            SELECT id, username, display_name, email, email_verified, password_hash
            FROM users
            WHERE email = %s
            LIMIT 1
            """,
            (email,),
        )
        user = cursor.fetchone()
        if not user or not (user.get("password_hash") or "").strip():
            return {"ok": True}

        risk = analyze_login_risk(cursor, int(user["id"]), email, payload.ip_address, payload.user_agent)
        requires_step_up = bool(risk["suspicious"] and int(user.get("email_verified") or 0))
        selector = random_token_hex(8)
        validator = random_token_hex(32)
        cursor.execute(
            "UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = %s AND used_at IS NULL",
            (user["id"],),
        )
        cursor.execute(
            """
            INSERT INTO password_reset_tokens
                (user_id, selector, verifier_hash, requested_ip, requested_user_agent, requires_step_up, expires_at, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
            """,
            (user["id"], selector, pbkdf2_hash(validator), payload.ip_address, payload.user_agent, 1 if requires_step_up else 0, dt_in_minutes(30)),
        )
        reset_url = f"{settings.base_url}/reset_password.php?selector={selector}&validator={validator}"
        send_password_reset(user["email"], user["display_name"] or user["username"], reset_url)
        return {"ok": True, "step_up_required": requires_step_up}


@app.post("/internal/password-reset/consume", dependencies=[Depends(require_internal_auth)])
def password_reset_consume(payload: PasswordResetConsumeRequest) -> dict[str, Any]:
    with db_cursor() as (_, cursor):
        cursor.execute(
            """
            SELECT prt.*, u.email, u.username, u.display_name
            FROM password_reset_tokens prt
            JOIN users u ON u.id = prt.user_id
            WHERE prt.selector = %s AND prt.used_at IS NULL AND prt.expires_at > NOW()
            LIMIT 1
            """,
            (payload.selector.strip(),),
        )
        row = cursor.fetchone()
        if not row or not pbkdf2_verify(payload.validator.strip(), row["verifier_hash"]):
            return {"ok": False, "error": "invalid_or_expired"}
        if payload.preview:
            return {
                "ok": True,
                "valid": True,
                "user_id": int(row["user_id"]),
                "masked_email": mask_email(row["email"]),
                "step_up_required": bool(int(row.get("requires_step_up") or 0)),
            }
        if not payload.password_hash:
            return {"ok": False, "error": "missing_password_hash"}
        if int(row.get("requires_step_up") or 0):
            if not payload.step_up_challenge_public_id or not payload.step_up_code:
                return {"ok": False, "error": "step_up_required", "step_up_required": True}
            cursor.execute(
                """
                SELECT * FROM security_challenges
                WHERE public_id = %s AND user_id = %s AND purpose = 'password_reset' AND method = 'email'
                  AND consumed_at IS NULL AND expires_at > NOW()
                LIMIT 1
                """,
                (payload.step_up_challenge_public_id, row["user_id"]),
            )
            challenge = cursor.fetchone()
            if not challenge or not pbkdf2_verify(payload.step_up_code.strip(), challenge["code_hash"]):
                return {"ok": False, "error": "invalid_step_up_code", "step_up_required": True}
            cursor.execute("UPDATE security_challenges SET verified_at = NOW(), consumed_at = NOW(), updated_at = NOW() WHERE id = %s", (challenge["id"],))
        cursor.execute("UPDATE users SET password_hash = %s WHERE id = %s", (payload.password_hash, row["user_id"]))
        cursor.execute(
            """
            UPDATE password_reset_tokens
            SET used_at = NOW(), consumed_ip_address = %s, consumed_user_agent = %s, updated_at = NOW()
            WHERE id = %s
            """,
            (payload.ip_address, payload.user_agent, row["id"]),
        )
        cursor.execute(
            """
            UPDATE user_sessions
            SET revoked_at = NOW(), revoke_reason = 'password_reset', revoked_by_session_public_id = NULL
            WHERE user_id = %s AND revoked_at IS NULL
            """,
            (row["user_id"],),
        )
        return {"ok": True}
