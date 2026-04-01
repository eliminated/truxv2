-- Add quoted_post_id to posts table for quote post (repost-with-commentary) feature
-- Also adds notify_post_quotes preference column to users

SET @schema_name = DATABASE();

-- Add quoted_post_id to posts
SET @sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME   = 'posts'
          AND COLUMN_NAME  = 'quoted_post_id'
    ),
    'SELECT 1',
    'ALTER TABLE posts ADD COLUMN quoted_post_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER image_path'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK for quoted_post_id (SET NULL on delete so quoting post survives original deletion)
SET @has_fk = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = @schema_name
      AND TABLE_NAME      = 'posts'
      AND CONSTRAINT_NAME = 'fk_posts_quoted_post'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(
    @has_fk > 0,
    'SELECT 1',
    'ALTER TABLE posts ADD CONSTRAINT fk_posts_quoted_post FOREIGN KEY (quoted_post_id) REFERENCES posts (id) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for reverse lookup (find all quotes of a given post)
SET @has_idx = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME   = 'posts'
      AND INDEX_NAME   = 'idx_posts_quoted_post_id'
);
SET @sql = IF(
    @has_idx > 0,
    'SELECT 1',
    'ALTER TABLE posts ADD INDEX idx_posts_quoted_post_id (quoted_post_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add notify_post_quotes preference column to users
SET @sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'notify_post_quotes'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN notify_post_quotes TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_post_likes'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
