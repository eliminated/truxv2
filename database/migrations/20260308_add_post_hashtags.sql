CREATE TABLE IF NOT EXISTS post_hashtags (
  hashtag VARCHAR(50) NOT NULL,
  post_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hashtag, post_id),
  KEY idx_post_hashtags_post (post_id),
  CONSTRAINT fk_post_hashtags_post FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
