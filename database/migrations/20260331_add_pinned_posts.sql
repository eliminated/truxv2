-- Add is_pinned column and index to posts table
-- Allows users to pin one post to the top of their profile

SET @schema_name = DATABASE();

SET @sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME   = 'posts'
          AND COLUMN_NAME  = 'is_pinned'
    ),
    'SELECT 1',
    'ALTER TABLE posts ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER edited_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME   = 'posts'
      AND INDEX_NAME   = 'idx_posts_user_pinned'
);
SET @sql = IF(
    @has_idx > 0,
    'SELECT 1',
    'ALTER TABLE posts ADD INDEX idx_posts_user_pinned (user_id, is_pinned)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
