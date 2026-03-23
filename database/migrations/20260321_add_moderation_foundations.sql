ALTER TABLE users
  ADD COLUMN IF NOT EXISTS staff_role VARCHAR(16) NOT NULL DEFAULT 'user' AFTER show_bookmarks_public,
  ADD INDEX idx_users_staff_role (staff_role);

CREATE TABLE IF NOT EXISTS moderation_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_type VARCHAR(16) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  reporter_user_id BIGINT UNSIGNED NOT NULL,
  target_owner_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  reason_key VARCHAR(32) NOT NULL,
  details TEXT NULL DEFAULT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  priority VARCHAR(16) NOT NULL DEFAULT 'normal',
  assigned_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  source_url VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_moderation_reports_status (status, priority, created_at),
  KEY idx_moderation_reports_target (target_type, target_id),
  KEY idx_moderation_reports_reporter (reporter_user_id),
  KEY idx_moderation_reports_target_owner (target_owner_user_id),
  KEY idx_moderation_reports_assignee (assigned_staff_user_id),
  CONSTRAINT fk_moderation_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_reports_target_owner FOREIGN KEY (target_owner_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_reports_assignee FOREIGN KEY (assigned_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_activity_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(64) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  subject_type VARCHAR(32) NULL DEFAULT NULL,
  subject_id BIGINT UNSIGNED NULL DEFAULT NULL,
  related_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  source_url VARCHAR(255) NULL DEFAULT NULL,
  ip_address VARCHAR(45) NULL DEFAULT NULL,
  user_agent VARCHAR(255) NULL DEFAULT NULL,
  metadata_json TEXT NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_moderation_activity_type_created (event_type, created_at),
  KEY idx_moderation_activity_actor_created (actor_user_id, created_at),
  KEY idx_moderation_activity_related_created (related_user_id, created_at),
  KEY idx_moderation_activity_subject (subject_type, subject_id),
  CONSTRAINT fk_moderation_activity_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_activity_related_user FOREIGN KEY (related_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_suspicious_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_key VARCHAR(64) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  severity VARCHAR(16) NOT NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  summary VARCHAR(255) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  linked_report_id BIGINT UNSIGNED NULL DEFAULT NULL,
  window_started_at DATETIME NULL DEFAULT NULL,
  window_expires_at DATETIME NULL DEFAULT NULL,
  first_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  reviewed_at DATETIME NULL DEFAULT NULL,
  metadata_json TEXT NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_moderation_suspicious_status (status, severity, last_detected_at),
  KEY idx_moderation_suspicious_rule_actor (rule_key, actor_user_id),
  KEY idx_moderation_suspicious_linked_report (linked_report_id),
  KEY idx_moderation_suspicious_reviewer (reviewed_by_staff_user_id),
  CONSTRAINT fk_moderation_suspicious_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_suspicious_linked_report FOREIGN KEY (linked_report_id) REFERENCES moderation_reports(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_suspicious_reviewer FOREIGN KEY (reviewed_by_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(64) NOT NULL,
  subject_type VARCHAR(32) NOT NULL,
  subject_id BIGINT UNSIGNED NULL DEFAULT NULL,
  details_json TEXT NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_moderation_audit_actor_created (actor_user_id, created_at),
  KEY idx_moderation_audit_action_created (action_type, created_at),
  KEY idx_moderation_audit_subject (subject_type, subject_id),
  CONSTRAINT fk_moderation_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
