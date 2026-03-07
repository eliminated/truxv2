ALTER TABLE post_comments
  ADD COLUMN IF NOT EXISTS parent_comment_id BIGINT UNSIGNED NULL AFTER post_id,
  ADD COLUMN IF NOT EXISTS reply_to_user_id BIGINT UNSIGNED NULL AFTER user_id;

ALTER TABLE post_comments
  ADD KEY idx_post_comments_parent (parent_comment_id),
  ADD KEY idx_post_comments_reply_to_user (reply_to_user_id);

ALTER TABLE post_comments
  ADD CONSTRAINT fk_post_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES post_comments(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  ADD CONSTRAINT fk_post_comments_reply_to_user FOREIGN KEY (reply_to_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
