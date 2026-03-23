REATE TABLE IF NOT EXISTS blocked_users (
  user_id         BIGINT UNSIGNED NOT NULL,
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, blocked_user_id),
  KEY idx_blocked_users_blocked (blocked_user_id),
  CONSTRAINT fk_blocked_users_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_blocked_users_target
    FOREIGN KEY (blocked_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;