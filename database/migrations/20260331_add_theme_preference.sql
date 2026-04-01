-- Add theme_preference column to users table
-- Stores user's preferred color scheme: light, dark, or system (follow OS)

SET @sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'theme_preference'
    ),
    'SELECT 1',
    "ALTER TABLE users ADD COLUMN theme_preference ENUM('light','dark','system') NOT NULL DEFAULT 'system' AFTER banner_path"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
