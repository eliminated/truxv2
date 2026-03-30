-- Run manually or via migration runner. Check for column existence before applying.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_domain_unrecognized TINYINT(1) NOT NULL DEFAULT 0
  AFTER email;
