ALTER TABLE users
  ADD COLUMN IF NOT EXISTS notify_post_likes TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash,
  ADD COLUMN IF NOT EXISTS notify_comment_votes TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_post_likes,
  ADD COLUMN IF NOT EXISTS notify_mentions TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_comment_votes,
  ADD COLUMN IF NOT EXISTS notify_follows TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_mentions,
  ADD COLUMN IF NOT EXISTS notify_post_comments TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_follows,
  ADD COLUMN IF NOT EXISTS notify_replies TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_post_comments;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(32) NOT NULL,
  event_key VARCHAR(120) NOT NULL,
  post_id BIGINT UNSIGNED NULL,
  comment_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notifications_event (recipient_user_id, event_key),
  KEY idx_notifications_recipient (recipient_user_id, read_at, id),
  KEY idx_notifications_actor (actor_user_id),
  KEY idx_notifications_post (post_id),
  KEY idx_notifications_comment (comment_id),
  CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_notifications_post FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_notifications_comment FOREIGN KEY (comment_id) REFERENCES post_comments(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
