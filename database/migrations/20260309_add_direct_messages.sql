CREATE TABLE IF NOT EXISTS direct_conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_one_id BIGINT UNSIGNED NOT NULL,
  user_two_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_direct_conversations_pair (user_one_id, user_two_id),
  KEY idx_direct_conversations_user_one (user_one_id, updated_at, id),
  KEY idx_direct_conversations_user_two (user_two_id, updated_at, id),
  CONSTRAINT fk_direct_conversations_user_one FOREIGN KEY (user_one_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_direct_conversations_user_two FOREIGN KEY (user_two_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT chk_direct_conversations_pair CHECK (user_one_id < user_two_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS direct_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  body VARCHAR(2000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_direct_messages_conversation (conversation_id, id),
  KEY idx_direct_messages_sender (sender_user_id),
  KEY idx_direct_messages_read (conversation_id, read_at, id),
  CONSTRAINT fk_direct_messages_conversation FOREIGN KEY (conversation_id) REFERENCES direct_conversations(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_direct_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
