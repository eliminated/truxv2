-- Run manually or via migration runner. Check for column existence before applying.

UPDATE users
SET email_verified = 0,
    email_verify_token = NULL,
    email_verify_sent_at = NULL;
