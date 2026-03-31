-- DMs V2: final schema convergence for edit, unsend, and attachments

SET @schema_name = DATABASE();

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'direct_messages'
          AND COLUMN_NAME = 'edited_at'
    ),
    'SELECT 1',
    'ALTER TABLE direct_messages ADD COLUMN edited_at DATETIME NULL DEFAULT NULL AFTER read_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'direct_messages'
          AND COLUMN_NAME = 'edit_window_expires_at'
    ),
    'SELECT 1',
    'ALTER TABLE direct_messages ADD COLUMN edit_window_expires_at DATETIME NULL DEFAULT NULL AFTER edited_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'direct_messages'
          AND COLUMN_NAME = 'deleted_for_everyone_at'
    ),
    'SELECT 1',
    'ALTER TABLE direct_messages ADD COLUMN deleted_for_everyone_at DATETIME NULL DEFAULT NULL AFTER edit_window_expires_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'direct_messages'
          AND COLUMN_NAME = 'delete_window_expires_at'
    ),
    'SELECT 1',
    'ALTER TABLE direct_messages ADD COLUMN delete_window_expires_at DATETIME NULL DEFAULT NULL AFTER deleted_for_everyone_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE direct_messages
  MODIFY COLUMN body VARCHAR(2000) NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS direct_message_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL DEFAULT '',
  mime_type VARCHAR(100) NOT NULL DEFAULT '',
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  image_width SMALLINT UNSIGNED NULL DEFAULT NULL,
  image_height SMALLINT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dm_attachments_message (message_id),
  CONSTRAINT fk_dm_attachments_message FOREIGN KEY (message_id)
    REFERENCES direct_messages(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

SET @has_original_name = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'original_name'
);
SET @has_legacy_file_name = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'file_name'
);
SET @sql = IF(
    @has_legacy_file_name > 0 AND @has_original_name = 0,
    'ALTER TABLE direct_message_attachments CHANGE COLUMN file_name original_name VARCHAR(255) NOT NULL DEFAULT ''''',
    IF(
        @has_legacy_file_name > 0 AND @has_original_name > 0,
        'UPDATE direct_message_attachments SET original_name = COALESCE(NULLIF(original_name, ''''), file_name) WHERE (original_name IS NULL OR original_name = '''')',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_image_width = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'image_width'
);
SET @has_legacy_width = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'width'
);
SET @sql = IF(
    @has_legacy_width > 0 AND @has_image_width = 0,
    'ALTER TABLE direct_message_attachments CHANGE COLUMN width image_width SMALLINT UNSIGNED NULL DEFAULT NULL',
    IF(
        @has_legacy_width > 0 AND @has_image_width > 0,
        'UPDATE direct_message_attachments SET image_width = COALESCE(image_width, width) WHERE image_width IS NULL',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_image_height = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'image_height'
);
SET @has_legacy_height = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'height'
);
SET @sql = IF(
    @has_legacy_height > 0 AND @has_image_height = 0,
    'ALTER TABLE direct_message_attachments CHANGE COLUMN height image_height SMALLINT UNSIGNED NULL DEFAULT NULL',
    IF(
        @has_legacy_height > 0 AND @has_image_height > 0,
        'UPDATE direct_message_attachments SET image_height = COALESCE(image_height, height) WHERE image_height IS NULL',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_legacy_file_name = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'file_name'
);
SET @sql = IF(
    @has_legacy_file_name > 0,
    'ALTER TABLE direct_message_attachments DROP COLUMN file_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_legacy_width = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'width'
);
SET @sql = IF(
    @has_legacy_width > 0,
    'ALTER TABLE direct_message_attachments DROP COLUMN width',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_legacy_height = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'direct_message_attachments'
      AND COLUMN_NAME = 'height'
);
SET @sql = IF(
    @has_legacy_height > 0,
    'ALTER TABLE direct_message_attachments DROP COLUMN height',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
