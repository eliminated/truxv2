ALTER TABLE post_comments
  ADD COLUMN IF NOT EXISTS parent_comment_id BIGINT UNSIGNED NULL AFTER post_id,
  ADD COLUMN IF NOT EXISTS reply_to_user_id BIGINT UNSIGNED NULL AFTER user_id;

SET @trux_schema = DATABASE();

SET @sql_add_idx_parent = IF(
  EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = @trux_schema
      AND table_name = 'post_comments'
      AND index_name = 'idx_post_comments_parent'
  ),
  'SELECT 1',
  'ALTER TABLE post_comments ADD KEY idx_post_comments_parent (parent_comment_id)'
);
PREPARE stmt_add_idx_parent FROM @sql_add_idx_parent;
EXECUTE stmt_add_idx_parent;
DEALLOCATE PREPARE stmt_add_idx_parent;

SET @sql_add_idx_reply_to_user = IF(
  EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = @trux_schema
      AND table_name = 'post_comments'
      AND index_name = 'idx_post_comments_reply_to_user'
  ),
  'SELECT 1',
  'ALTER TABLE post_comments ADD KEY idx_post_comments_reply_to_user (reply_to_user_id)'
);
PREPARE stmt_add_idx_reply_to_user FROM @sql_add_idx_reply_to_user;
EXECUTE stmt_add_idx_reply_to_user;
DEALLOCATE PREPARE stmt_add_idx_reply_to_user;

SET @sql_add_fk_parent = IF(
  EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE constraint_schema = @trux_schema
      AND table_name = 'post_comments'
      AND constraint_name = 'fk_post_comments_parent'
  ),
  'SELECT 1',
  'ALTER TABLE post_comments ADD CONSTRAINT fk_post_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES post_comments(id) ON DELETE CASCADE ON UPDATE CASCADE'
);
PREPARE stmt_add_fk_parent FROM @sql_add_fk_parent;
EXECUTE stmt_add_fk_parent;
DEALLOCATE PREPARE stmt_add_fk_parent;

SET @sql_add_fk_reply_to_user = IF(
  EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE constraint_schema = @trux_schema
      AND table_name = 'post_comments'
      AND constraint_name = 'fk_post_comments_reply_to_user'
  ),
  'SELECT 1',
  'ALTER TABLE post_comments ADD CONSTRAINT fk_post_comments_reply_to_user FOREIGN KEY (reply_to_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt_add_fk_reply_to_user FROM @sql_add_fk_reply_to_user;
EXECUTE stmt_add_fk_reply_to_user;
DEALLOCATE PREPARE stmt_add_fk_reply_to_user;
