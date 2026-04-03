-- Add ui_performance_mode to users so the shell can switch between full, balanced, and lite rendering profiles.

SET @sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'users'
          AND COLUMN_NAME  = 'ui_performance_mode'
    ),
    'SELECT 1',
    "ALTER TABLE users ADD COLUMN ui_performance_mode ENUM('full','balanced','lite') NOT NULL DEFAULT 'full' AFTER theme_preference"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

