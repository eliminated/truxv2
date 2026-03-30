-- Run manually or via migration runner. Check for column existence before applying.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0
    AFTER email_domain_unrecognized,
  ADD COLUMN IF NOT EXISTS email_verify_token VARCHAR(255) NULL DEFAULT NULL
    AFTER email_verified,
  ADD COLUMN IF NOT EXISTS email_verify_sent_at DATETIME NULL DEFAULT NULL
    AFTER email_verify_token;
