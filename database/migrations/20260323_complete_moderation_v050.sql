ALTER TABLE users
  ADD COLUMN IF NOT EXISTS notify_report_updates_default TINYINT(1) NOT NULL DEFAULT 0
  AFTER notify_replies;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS target_url VARCHAR(255) NULL DEFAULT NULL
  AFTER comment_id;

ALTER TABLE moderation_user_cases
  ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'open' AFTER summary,
  ADD COLUMN IF NOT EXISTS priority VARCHAR(16) NOT NULL DEFAULT 'normal' AFTER status,
  ADD COLUMN IF NOT EXISTS resolution_action_key VARCHAR(32) NULL DEFAULT NULL AFTER priority,
  ADD COLUMN IF NOT EXISTS resolution_notes TEXT NULL DEFAULT NULL AFTER resolution_action_key,
  ADD COLUMN IF NOT EXISTS current_escalation_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER assigned_staff_user_id,
  ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL DEFAULT NULL AFTER updated_at,
  ADD COLUMN IF NOT EXISTS closed_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER closed_at;

ALTER TABLE moderation_user_cases
  ADD INDEX IF NOT EXISTS idx_moderation_user_cases_status_priority (status, priority, updated_at),
  ADD INDEX IF NOT EXISTS idx_moderation_user_cases_escalation (current_escalation_id),
  ADD INDEX IF NOT EXISTS idx_moderation_user_cases_closed_by (closed_by_staff_user_id);

ALTER TABLE moderation_suspicious_events
  ADD COLUMN IF NOT EXISTS assigned_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL
  AFTER reviewed_by_staff_user_id;

ALTER TABLE moderation_suspicious_events
  ADD INDEX IF NOT EXISTS idx_moderation_suspicious_assignee (assigned_staff_user_id);

CREATE TABLE IF NOT EXISTS moderation_escalations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_type VARCHAR(32) NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  queue_role VARCHAR(16) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  priority VARCHAR(16) NOT NULL DEFAULT 'normal',
  summary VARCHAR(280) NOT NULL,
  resolution_notes TEXT NULL DEFAULT NULL,
  assigned_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_by_staff_user_id BIGINT UNSIGNED NOT NULL,
  resolved_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_moderation_escalations_queue (queue_role, status, priority, updated_at),
  KEY idx_moderation_escalations_subject (subject_type, subject_id),
  KEY idx_moderation_escalations_assignee (assigned_staff_user_id),
  KEY idx_moderation_escalations_creator (created_by_staff_user_id),
  KEY idx_moderation_escalations_resolver (resolved_by_staff_user_id),
  CONSTRAINT fk_moderation_escalations_assignee FOREIGN KEY (assigned_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_escalations_creator FOREIGN KEY (created_by_staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_escalations_resolver FOREIGN KEY (resolved_by_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_user_enforcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  source_report_id BIGINT UNSIGNED NULL DEFAULT NULL,
  user_case_id BIGINT UNSIGNED NULL DEFAULT NULL,
  action_key VARCHAR(32) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  appeal_token VARCHAR(64) NULL DEFAULT NULL,
  reason_summary VARCHAR(280) NULL DEFAULT NULL,
  details TEXT NULL DEFAULT NULL,
  created_by_staff_user_id BIGINT UNSIGNED NOT NULL,
  revoked_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL DEFAULT NULL,
  revoked_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moderation_user_enforcements_appeal_token (appeal_token),
  KEY idx_moderation_user_enforcements_user (user_id, status, ends_at),
  KEY idx_moderation_user_enforcements_report (source_report_id),
  KEY idx_moderation_user_enforcements_case (user_case_id),
  KEY idx_moderation_user_enforcements_creator (created_by_staff_user_id),
  KEY idx_moderation_user_enforcements_revoker (revoked_by_staff_user_id),
  CONSTRAINT fk_moderation_user_enforcements_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_enforcements_report FOREIGN KEY (source_report_id) REFERENCES moderation_reports(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_enforcements_case FOREIGN KEY (user_case_id) REFERENCES moderation_user_cases(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_enforcements_creator FOREIGN KEY (created_by_staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_enforcements_revoker FOREIGN KEY (revoked_by_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_appeals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  enforcement_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  submitter_reason TEXT NOT NULL,
  resolution_notes TEXT NULL DEFAULT NULL,
  assigned_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  resolved_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moderation_appeals_enforcement (enforcement_id),
  KEY idx_moderation_appeals_status (status, updated_at),
  KEY idx_moderation_appeals_assignee (assigned_staff_user_id),
  KEY idx_moderation_appeals_creator (created_by_staff_user_id),
  KEY idx_moderation_appeals_resolver (resolved_by_staff_user_id),
  CONSTRAINT fk_moderation_appeals_enforcement FOREIGN KEY (enforcement_id) REFERENCES moderation_user_enforcements(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_appeals_assignee FOREIGN KEY (assigned_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_appeals_creator FOREIGN KEY (created_by_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_appeals_resolver FOREIGN KEY (resolved_by_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_rule_configs (
  rule_key VARCHAR(64) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  settings_json TEXT NULL DEFAULT NULL,
  updated_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (rule_key),
  KEY idx_moderation_rule_configs_updated_by (updated_by_staff_user_id),
  CONSTRAINT fk_moderation_rule_configs_updated_by FOREIGN KEY (updated_by_staff_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO moderation_rule_configs (rule_key, enabled, settings_json)
VALUES
  ('repeated_failed_login', 1, '{"threshold":5,"critical_threshold":8,"window_minutes":15}'),
  ('content_burst', 1, '{"threshold":6,"high_threshold":10,"window_minutes":10}'),
  ('dm_burst_multiple_recipients', 1, '{"threshold_messages":6,"threshold_recipients":5,"critical_messages":10,"critical_recipients":8,"window_minutes":15}'),
  ('multiple_reports_same_account', 1, '{"threshold":3,"critical_threshold":5,"window_hours":24}'),
  ('spam_link_burst', 1, '{"threshold":3,"critical_threshold":5,"window_minutes":10}'),
  ('duplicate_content_burst', 1, '{"threshold":3,"critical_threshold":5,"window_minutes":15}'),
  ('follow_burst', 1, '{"threshold":12,"critical_threshold":20,"window_minutes":15}'),
  ('multiple_blocks_same_account', 1, '{"threshold":3,"critical_threshold":5,"window_hours":24}')
ON DUPLICATE KEY UPDATE
  enabled = VALUES(enabled),
  settings_json = VALUES(settings_json);
