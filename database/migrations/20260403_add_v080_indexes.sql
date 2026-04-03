-- v0.8.0 keeps unread-count and DM indexes as-is.
-- The following-feed rewrite benefits from a posts(user_id, id) composite when it is missing.

SET @posts_user_id_id_supporting_indexes = (
    SELECT COUNT(*)
    FROM (
        SELECT s.INDEX_NAME
        FROM INFORMATION_SCHEMA.STATISTICS s
        WHERE s.TABLE_SCHEMA = DATABASE()
          AND s.TABLE_NAME = 'posts'
        GROUP BY s.INDEX_NAME
        HAVING MAX(CASE WHEN s.SEQ_IN_INDEX = 1 AND s.COLUMN_NAME = 'user_id' THEN 1 ELSE 0 END) = 1
           AND MAX(CASE WHEN s.SEQ_IN_INDEX = 2 AND s.COLUMN_NAME = 'id' THEN 1 ELSE 0 END) = 1
    ) supporting_indexes
);

SET @sql = IF(
    @posts_user_id_id_supporting_indexes > 0,
    'SELECT 1',
    'CREATE INDEX idx_posts_user_id_id ON posts (user_id, id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
