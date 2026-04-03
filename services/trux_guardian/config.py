from __future__ import annotations

import os
from pathlib import Path

from cryptography.fernet import Fernet
from dotenv import load_dotenv
from pydantic import BaseModel, Field


ROOT_DIR = Path(__file__).resolve().parents[2]
load_dotenv(ROOT_DIR / ".env")


class Settings(BaseModel):
    app_name: str = Field(default=os.getenv("TRUX_APP_NAME", "TruX"))
    base_url: str = Field(default=os.getenv("TRUX_BASE_URL", "http://localhost/truxv2/public").rstrip("/"))
    db_host: str = Field(default=os.getenv("TRUX_DB_HOST", "127.0.0.1"))
    db_port: int = Field(default=int(os.getenv("TRUX_DB_PORT", "3306")))
    db_name: str = Field(default=os.getenv("TRUX_DB_NAME", "trux"))
    db_user: str = Field(default=os.getenv("TRUX_DB_USER", "root"))
    db_pass: str = Field(default=os.getenv("TRUX_DB_PASS", ""))
    db_charset: str = Field(default=os.getenv("TRUX_DB_CHARSET", "utf8mb4"))
    mail_host: str = Field(default=os.getenv("TRUX_MAIL_HOST", ""))
    mail_port: int = Field(default=int(os.getenv("TRUX_MAIL_PORT", "465")))
    mail_user: str = Field(default=os.getenv("TRUX_MAIL_USER", ""))
    mail_pass: str = Field(default=os.getenv("TRUX_MAIL_PASS", ""))
    mail_from_name: str = Field(default=os.getenv("TRUX_MAIL_FROM_NAME", "TruX"))
    shared_secret: str = Field(default=os.getenv("TRUX_GUARDIAN_SHARED_SECRET", ""))
    totp_encryption_key: str = Field(default=os.getenv("TRUX_GUARDIAN_TOTP_ENCRYPTION_KEY", ""))
    reset_signing_secret: str = Field(default=os.getenv("TRUX_GUARDIAN_RESET_SIGNING_SECRET", ""))
    session_touch_seconds: int = Field(default=int(os.getenv("TRUX_GUARDIAN_LAST_ACTIVE_TOUCH_SECONDS", "120")))

    @property
    def fernet(self) -> Fernet:
        return Fernet(self.totp_encryption_key.encode("utf-8"))


settings = Settings()

