CREATE TABLE IF NOT EXISTS muted_users (
  user_id BIGINT UNSIGNED NOT NULL,
  muted_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, muted_user_id),
  KEY idx_muted_users_muted (muted_user_id),
  CONSTRAINT fk_muted_users_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_muted_users_muted_user FOREIGN KEY (muted_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT chk_muted_users_not_self CHECK (user_id <> muted_user_id)
) ENGINE=InnoDB;
