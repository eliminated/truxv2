ALTER TABLE moderation_reports
  ADD COLUMN IF NOT EXISTS target_snapshot_json TEXT NULL DEFAULT NULL AFTER source_url,
  ADD COLUMN IF NOT EXISTS resolution_action_key VARCHAR(32) NULL DEFAULT NULL AFTER resolved_at;

CREATE TABLE IF NOT EXISTS moderation_user_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  watchlisted TINYINT(1) NOT NULL DEFAULT 0,
  watch_reason VARCHAR(280) NULL DEFAULT NULL,
  summary TEXT NULL DEFAULT NULL,
  assigned_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_by_staff_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_staff_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moderation_user_cases_user (user_id),
  KEY idx_moderation_user_cases_watchlisted (watchlisted, updated_at),
  KEY idx_moderation_user_cases_assignee (assigned_staff_user_id),
  CONSTRAINT fk_moderation_user_cases_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_cases_created_by FOREIGN KEY (created_by_staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_cases_updated_by FOREIGN KEY (updated_by_staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_cases_assignee FOREIGN KEY (assigned_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_user_case_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_case_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  linked_report_id BIGINT UNSIGNED NULL DEFAULT NULL,
  body VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_moderation_user_case_notes_case_created (user_case_id, created_at),
  KEY idx_moderation_user_case_notes_author (author_user_id),
  KEY idx_moderation_user_case_notes_report (linked_report_id),
  CONSTRAINT fk_moderation_user_case_notes_case FOREIGN KEY (user_case_id) REFERENCES moderation_user_cases(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_case_notes_author FOREIGN KEY (author_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_case_notes_report FOREIGN KEY (linked_report_id) REFERENCES moderation_reports(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;
