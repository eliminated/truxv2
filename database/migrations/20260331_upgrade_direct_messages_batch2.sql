-- DMs Batch 2: replies and reactions

SET @schema_name = DATABASE();

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'direct_messages'
          AND COLUMN_NAME = 'reply_to_message_id'
    ),
    'SELECT 1',
    'ALTER TABLE direct_messages ADD COLUMN reply_to_message_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER body'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_reply_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_messages'
      AND INDEX_NAME = 'idx_direct_messages_reply'
);
SET @sql = IF(
    @has_reply_index > 0,
    'SELECT 1',
    'ALTER TABLE direct_messages ADD KEY idx_direct_messages_reply (reply_to_message_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_reply_fk = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @schema_name
      AND CONSTRAINT_NAME = 'fk_direct_messages_reply'
);
SET @sql = IF(
    @has_reply_fk > 0,
    'SELECT 1',
    'ALTER TABLE direct_messages ADD CONSTRAINT fk_direct_messages_reply FOREIGN KEY (reply_to_message_id) REFERENCES direct_messages(id) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS direct_message_reactions (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reaction VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id, reaction),
  KEY idx_dm_reactions_user (user_id, created_at),
  KEY idx_dm_reactions_lookup (message_id, reaction),
  CONSTRAINT fk_dm_reactions_message FOREIGN KEY (message_id)
    REFERENCES direct_messages(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dm_reactions_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
