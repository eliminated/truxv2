CREATE TABLE IF NOT EXISTS moderation_report_discussions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  body VARCHAR(280) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_moderation_report_discussions_report_created (report_id, created_at),
  KEY idx_moderation_report_discussions_author (author_user_id),
  CONSTRAINT fk_moderation_report_discussions_report FOREIGN KEY (report_id) REFERENCES moderation_reports(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_report_discussions_author FOREIGN KEY (author_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_report_votes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id BIGINT UNSIGNED NOT NULL,
  staff_user_id BIGINT UNSIGNED NOT NULL,
  vote_value VARCHAR(8) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moderation_report_votes_report_staff (report_id, staff_user_id),
  KEY idx_moderation_report_votes_report_value (report_id, vote_value),
  KEY idx_moderation_report_votes_staff (staff_user_id),
  CONSTRAINT fk_moderation_report_votes_report FOREIGN KEY (report_id) REFERENCES moderation_reports(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_report_votes_staff FOREIGN KEY (staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
