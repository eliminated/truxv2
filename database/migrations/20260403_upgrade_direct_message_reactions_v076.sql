-- DM reactions v0.7.6: shared six-reaction picker with one active reaction per user/message

SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS direct_message_reactions (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reaction VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id),
  KEY idx_dm_reactions_user (user_id, created_at),
  KEY idx_dm_reactions_lookup (message_id, reaction),
  CONSTRAINT fk_dm_reactions_message FOREIGN KEY (message_id)
    REFERENCES direct_messages(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dm_reactions_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

UPDATE direct_message_reactions
SET reaction = 'heart'
WHERE reaction = 'like';

DROP TEMPORARY TABLE IF EXISTS trux_dm_reaction_dedup;
CREATE TEMPORARY TABLE trux_dm_reaction_dedup (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reaction VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (message_id, user_id)
) ENGINE=InnoDB;

INSERT INTO trux_dm_reaction_dedup (message_id, user_id, reaction, created_at)
SELECT
  source.message_id,
  source.user_id,
  SUBSTRING_INDEX(
    GROUP_CONCAT(
      source.reaction
      ORDER BY source.created_at DESC, FIELD(source.reaction, 'heart', 'fire', 'clap', 'laugh', 'think', 'hundred') ASC
      SEPARATOR ','
    ),
    ',',
    1
  ) AS reaction,
  SUBSTRING_INDEX(
    GROUP_CONCAT(
      DATE_FORMAT(source.created_at, '%Y-%m-%d %H:%i:%s')
      ORDER BY source.created_at DESC, FIELD(source.reaction, 'heart', 'fire', 'clap', 'laugh', 'think', 'hundred') ASC
      SEPARATOR ','
    ),
    ',',
    1
  ) AS created_at
FROM direct_message_reactions source
WHERE source.reaction IN ('heart', 'fire', 'clap', 'laugh', 'think', 'hundred')
GROUP BY source.message_id, source.user_id;

DELETE FROM direct_message_reactions;

SET @pk_columns = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'direct_message_reactions'
    AND CONSTRAINT_NAME = 'PRIMARY'
);
SET @sql = IF(
  @pk_columns = 2,
  'SELECT 1',
  'ALTER TABLE direct_message_reactions DROP PRIMARY KEY, ADD PRIMARY KEY (message_id, user_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO direct_message_reactions (message_id, user_id, reaction, created_at)
SELECT message_id, user_id, reaction, created_at
FROM trux_dm_reaction_dedup
ORDER BY created_at ASC;

DROP TEMPORARY TABLE IF EXISTS trux_dm_reaction_dedup;
