# TruX Guardian

Internal FastAPI service for security-sensitive TruX flows.

## Scope

- TOTP setup and verification
- Email OTP send/verify
- Recovery code generation and verification
- Login anomaly analysis and security event recording
- Password-reset issuance and consumption
- Session revocation support

## Run

```bash
cd services
python -m venv .venv
.venv\Scripts\activate
pip install -r trux_guardian/requirements.txt
uvicorn trux_guardian.app:app --host 127.0.0.1 --port 8787
```

The service reads the repo-root `.env`.

## Required env

- `TRUX_GUARDIAN_BASE_URL`
- `TRUX_GUARDIAN_SHARED_SECRET`
- `TRUX_GUARDIAN_TOTP_ENCRYPTION_KEY`
- `TRUX_GUARDIAN_RESET_SIGNING_SECRET`
- Existing `TRUX_DB_*`
- Existing `TRUX_MAIL_*`
