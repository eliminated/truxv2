ALTER TABLE users
  ADD COLUMN IF NOT EXISTS notify_security_alerts TINYINT(1) NOT NULL DEFAULT 1
    AFTER notify_report_updates_default;

ALTER TABLE linked_accounts
  ADD COLUMN IF NOT EXISTS provider_email VARCHAR(255) NULL DEFAULT NULL
    AFTER provider_username,
  ADD COLUMN IF NOT EXISTS provider_email_verified TINYINT(1) NOT NULL DEFAULT 0
    AFTER provider_email,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL DEFAULT NULL
    AFTER last_used_at;

SET @idx_linked_accounts_user_email := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'linked_accounts'
    AND index_name = 'idx_linked_accounts_user_email'
);
SET @sql_linked_accounts_user_email := IF(
  @idx_linked_accounts_user_email = 0,
  'CREATE INDEX idx_linked_accounts_user_email ON linked_accounts (user_id, provider_email)',
  'SELECT 1'
);
PREPARE stmt_linked_accounts_user_email FROM @sql_linked_accounts_user_email;
EXECUTE stmt_linked_accounts_user_email;
DEALLOCATE PREPARE stmt_linked_accounts_user_email;

SET @idx_linked_accounts_provider_email := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'linked_accounts'
    AND index_name = 'idx_linked_accounts_provider_email'
);
SET @sql_linked_accounts_provider_email := IF(
  @idx_linked_accounts_provider_email = 0,
  'CREATE INDEX idx_linked_accounts_provider_email ON linked_accounts (provider, provider_email)',
  'SELECT 1'
);
PREPARE stmt_linked_accounts_provider_email FROM @sql_linked_accounts_provider_email;
EXECUTE stmt_linked_accounts_provider_email;
DEALLOCATE PREPARE stmt_linked_accounts_provider_email;

CREATE TABLE IF NOT EXISTS user_2fa_settings (
  user_id BIGINT UNSIGNED NOT NULL,
  primary_method VARCHAR(24) NOT NULL DEFAULT 'none',
  totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  email_otp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  totp_secret_ciphertext TEXT NULL,
  totp_confirmed_at DATETIME NULL DEFAULT NULL,
  email_confirmed_at DATETIME NULL DEFAULT NULL,
  recovery_codes_generated_at DATETIME NULL DEFAULT NULL,
  challenge_on_sensitive TINYINT(1) NOT NULL DEFAULT 1,
  last_challenge_at DATETIME NULL DEFAULT NULL,
  disabled_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY idx_user_2fa_primary_method (primary_method),
  CONSTRAINT fk_user_2fa_settings_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_recovery_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  batch_id VARCHAR(64) NOT NULL,
  code_slot SMALLINT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  code_hint VARCHAR(32) NULL DEFAULT NULL,
  used_at DATETIME NULL DEFAULT NULL,
  replaced_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_recovery_batch_slot (user_id, batch_id, code_slot),
  KEY idx_user_recovery_active (user_id, used_at, replaced_at),
  CONSTRAINT fk_user_recovery_codes_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_challenges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  purpose VARCHAR(32) NOT NULL,
  method VARCHAR(24) NOT NULL,
  target_email VARCHAR(255) NULL DEFAULT NULL,
  code_hash VARCHAR(255) NULL DEFAULT NULL,
  totp_secret_ciphertext TEXT NULL,
  payload_json TEXT NULL,
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  sent_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_sent_at DATETIME NULL DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME NULL DEFAULT NULL,
  consumed_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_security_challenges_public_id (public_id),
  KEY idx_security_challenges_user_purpose (user_id, purpose, created_at),
  KEY idx_security_challenges_lookup (user_id, purpose, method, consumed_at),
  KEY idx_security_challenges_expires (expires_at),
  CONSTRAINT fk_security_challenges_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  session_public_id VARCHAR(64) NOT NULL,
  session_hash CHAR(64) NOT NULL,
  session_name VARCHAR(64) NOT NULL DEFAULT 'PHPSESSID',
  login_method VARCHAR(24) NOT NULL DEFAULT 'password',
  provider VARCHAR(32) NULL DEFAULT NULL,
  ip_address VARCHAR(45) NULL DEFAULT NULL,
  user_agent VARCHAR(255) NULL DEFAULT NULL,
  device_label VARCHAR(191) NULL DEFAULT NULL,
  browser_name VARCHAR(80) NULL DEFAULT NULL,
  platform_name VARCHAR(80) NULL DEFAULT NULL,
  is_suspicious TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL DEFAULT NULL,
  revoke_reason VARCHAR(120) NULL DEFAULT NULL,
  revoked_by_session_public_id VARCHAR(64) NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_sessions_public_id (session_public_id),
  KEY idx_user_sessions_user_active (user_id, revoked_at, last_active_at),
  KEY idx_user_sessions_lookup (user_id, session_public_id),
  KEY idx_user_sessions_method (user_id, login_method, created_at),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  login_identifier VARCHAR(255) NULL DEFAULT NULL,
  attempt_method VARCHAR(24) NOT NULL DEFAULT 'password',
  provider VARCHAR(32) NULL DEFAULT NULL,
  outcome VARCHAR(24) NOT NULL,
  failure_reason VARCHAR(80) NULL DEFAULT NULL,
  challenge_public_id VARCHAR(64) NULL DEFAULT NULL,
  ip_address VARCHAR(45) NULL DEFAULT NULL,
  user_agent VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_user_outcome_created (user_id, outcome, created_at),
  KEY idx_login_attempts_identifier_created (login_identifier, created_at),
  KEY idx_login_attempts_ip_created (ip_address, created_at),
  CONSTRAINT fk_login_attempts_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  session_public_id VARCHAR(64) NULL DEFAULT NULL,
  login_method VARCHAR(24) NOT NULL,
  provider VARCHAR(32) NULL DEFAULT NULL,
  ip_address VARCHAR(45) NULL DEFAULT NULL,
  user_agent VARCHAR(255) NULL DEFAULT NULL,
  device_label VARCHAR(191) NULL DEFAULT NULL,
  browser_name VARCHAR(80) NULL DEFAULT NULL,
  platform_name VARCHAR(80) NULL DEFAULT NULL,
  location_label VARCHAR(120) NULL DEFAULT NULL,
  is_new_device TINYINT(1) NOT NULL DEFAULT 0,
  is_unusual_ip TINYINT(1) NOT NULL DEFAULT 0,
  had_recent_failures TINYINT(1) NOT NULL DEFAULT 0,
  is_suspicious TINYINT(1) NOT NULL DEFAULT 0,
  risk_score INT UNSIGNED NOT NULL DEFAULT 0,
  risk_reasons_json TEXT NULL DEFAULT NULL,
  alert_sent_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_history_user_created (user_id, created_at),
  KEY idx_login_history_session (session_public_id),
  KEY idx_login_history_suspicious (user_id, is_suspicious, created_at),
  CONSTRAINT fk_login_history_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  selector VARCHAR(32) NOT NULL,
  verifier_hash VARCHAR(255) NOT NULL,
  requested_ip VARCHAR(45) NULL DEFAULT NULL,
  requested_user_agent VARCHAR(255) NULL DEFAULT NULL,
  risk_level VARCHAR(24) NOT NULL DEFAULT 'normal',
  requires_step_up TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL DEFAULT NULL,
  consumed_ip_address VARCHAR(45) NULL DEFAULT NULL,
  consumed_user_agent VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_reset_selector (selector),
  KEY idx_password_reset_user_active (user_id, used_at, expires_at),
  KEY idx_password_reset_expires (expires_at),
  CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
