-- Run manually or via migration runner. Check for column existence before applying.

CREATE TABLE IF NOT EXISTS linked_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  provider ENUM('google', 'facebook', 'x') NOT NULL,
  provider_user_id VARCHAR(255) NULL DEFAULT NULL,
  linked_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_user_provider (user_id, provider),
  KEY idx_linked_accounts_provider (provider),
  CONSTRAINT fk_linked_accounts_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
