from __future__ import annotations

import base64
import hashlib
import hmac
import os
import secrets
from datetime import datetime, timedelta

from .config import settings


def utcnow() -> datetime:
    return datetime.utcnow().replace(microsecond=0)


def dt_in_minutes(minutes: int) -> datetime:
    return utcnow() + timedelta(minutes=minutes)


def random_token_hex(length: int = 32) -> str:
    return secrets.token_hex(length)


def random_numeric_code(length: int = 6) -> str:
    upper = 10 ** length
    return f"{secrets.randbelow(upper):0{length}d}"


def random_recovery_code() -> str:
    alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
    raw = "".join(secrets.choice(alphabet) for _ in range(8))
    return raw[:4] + "-" + raw[4:]


def fingerprint_value(value: str) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


def pbkdf2_hash(value: str) -> str:
    salt = os.urandom(16)
    digest = hashlib.pbkdf2_hmac(
        "sha256",
        value.encode("utf-8"),
        salt + settings.reset_signing_secret.encode("utf-8"),
        120_000,
    )
    return base64.urlsafe_b64encode(salt).decode("ascii") + "$" + base64.urlsafe_b64encode(digest).decode("ascii")


def pbkdf2_verify(value: str, stored: str) -> bool:
    try:
        salt_b64, digest_b64 = stored.split("$", 1)
        salt = base64.urlsafe_b64decode(salt_b64.encode("ascii"))
        expected = base64.urlsafe_b64decode(digest_b64.encode("ascii"))
    except Exception:
        return False

    candidate = hashlib.pbkdf2_hmac(
        "sha256",
        value.encode("utf-8"),
        salt + settings.reset_signing_secret.encode("utf-8"),
        120_000,
    )
    return hmac.compare_digest(candidate, expected)


def mask_email(email: str) -> str:
    email = email.strip()
    if "@" not in email:
        return email
    local, domain = email.split("@", 1)
    if len(local) <= 2:
        local_mask = local[:1] + "*"
    else:
        local_mask = local[:2] + "*" * max(1, len(local) - 2)
    return local_mask + "@" + domain

