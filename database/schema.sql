CREATE DATABASE IF NOT EXISTS trux
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE trux;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(32) NOT NULL,
  email VARCHAR(255) NOT NULL,
  email_domain_unrecognized TINYINT(1) NOT NULL DEFAULT 0,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  email_verify_token VARCHAR(255) NULL DEFAULT NULL,
  email_verify_sent_at DATETIME NULL DEFAULT NULL,
  display_name VARCHAR(80) NULL DEFAULT NULL,
  bio VARCHAR(280) NULL DEFAULT NULL,
  about_me TEXT NULL DEFAULT NULL,
  location VARCHAR(100) NULL DEFAULT NULL,
  website_url VARCHAR(255) NULL DEFAULT NULL,
  profile_links_json TEXT NULL DEFAULT NULL,
  avatar_path VARCHAR(255) NULL DEFAULT NULL,
  banner_path VARCHAR(255) NULL DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  notify_post_likes TINYINT(1) NOT NULL DEFAULT 1,
  notify_comment_votes TINYINT(1) NOT NULL DEFAULT 1,
  notify_mentions TINYINT(1) NOT NULL DEFAULT 1,
  notify_follows TINYINT(1) NOT NULL DEFAULT 1,
  notify_post_comments TINYINT(1) NOT NULL DEFAULT 1,
  notify_replies TINYINT(1) NOT NULL DEFAULT 1,
  notify_report_updates_default TINYINT(1) NOT NULL DEFAULT 0,
  show_likes_public TINYINT(1) NOT NULL DEFAULT 1,
  show_bookmarks_public TINYINT(1) NOT NULL DEFAULT 1,
  staff_role VARCHAR(16) NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_staff_role (staff_role)
) ENGINE=InnoDB;

INSERT INTO users (username, email, display_name, bio, password_hash, staff_role)
SELECT
  'report_system_updates_bot',
  'report-system-updates@system.invalid',
  'Report System Updates',
  'Automated moderation update account.',
  '$2y$10$i0se7ZzdTkvT7ceyV7gOyurV/u7s1qTsp.6/QFlv8La1X4MmV3Squ',
  'user'
WHERE NOT EXISTS (
  SELECT 1
  FROM users
  WHERE username = 'report_system_updates_bot'
     OR email = 'report-system-updates@system.invalid'
);

CREATE TABLE IF NOT EXISTS linked_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(32) NOT NULL,
  provider_user_id VARCHAR(191) NULL DEFAULT NULL,
  provider_username VARCHAR(191) NULL DEFAULT NULL,
  provider_display_name VARCHAR(191) NULL DEFAULT NULL,
  provider_avatar_url VARCHAR(255) NULL DEFAULT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'connected',
  status_reason VARCHAR(255) NULL DEFAULT NULL,
  metadata_json TEXT NULL,
  linked_at DATETIME NULL DEFAULT NULL,
  last_verified_at DATETIME NULL DEFAULT NULL,
  last_used_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_user_provider (user_id, provider),
  UNIQUE KEY unique_provider_identity (provider, provider_user_id),
  KEY idx_linked_accounts_user_status (user_id, status),
  KEY idx_linked_accounts_provider_status (provider, status),
  CONSTRAINT fk_linked_accounts_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_posts_created_at (created_at),
  KEY idx_posts_user_created (user_id, id),
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS follows (
  follower_id BIGINT UNSIGNED NOT NULL,
  following_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  KEY idx_follows_follower (follower_id),
  KEY idx_follows_following (following_id),
  CONSTRAINT fk_follows_follower FOREIGN KEY (follower_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_follows_following FOREIGN KEY (following_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT chk_follows_not_self CHECK (follower_id <> following_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS muted_users (
  user_id BIGINT UNSIGNED NOT NULL,
  muted_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, muted_user_id),
  KEY idx_muted_users_muted (muted_user_id),
  CONSTRAINT fk_muted_users_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_muted_users_muted_user FOREIGN KEY (muted_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT chk_muted_users_not_self CHECK (user_id <> muted_user_id)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS post_likes (
  post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, user_id),
  KEY idx_post_likes_user (user_id),
  CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  parent_comment_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reply_to_user_id BIGINT UNSIGNED NULL,
  body VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_post_comments_post (post_id, id),
  KEY idx_post_comments_parent (parent_comment_id),
  KEY idx_post_comments_user (user_id),
  KEY idx_post_comments_reply_to_user (reply_to_user_id),
  CONSTRAINT fk_post_comments_post FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_post_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES post_comments(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_post_comments_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_post_comments_reply_to_user FOREIGN KEY (reply_to_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_comment_votes (
  comment_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  vote TINYINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (comment_id, user_id),
  KEY idx_post_comment_votes_user (user_id),
  CONSTRAINT fk_post_comment_votes_comment FOREIGN KEY (comment_id) REFERENCES post_comments(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_post_comment_votes_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_shares (
  post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, user_id),
  KEY idx_post_shares_user (user_id),
  CONSTRAINT fk_post_shares_post FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_post_shares_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_bookmarks (
  post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, user_id),
  KEY idx_post_bookmarks_user (user_id, created_at),
  CONSTRAINT fk_post_bookmarks_post FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_post_bookmarks_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comment_bookmarks (
  comment_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (comment_id, user_id),
  KEY idx_comment_bookmarks_user (user_id, created_at),
  CONSTRAINT fk_comment_bookmarks_comment FOREIGN KEY (comment_id) REFERENCES post_comments(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_comment_bookmarks_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS direct_conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_one_id BIGINT UNSIGNED NOT NULL,
  user_two_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_direct_conversations_pair (user_one_id, user_two_id),
  KEY idx_direct_conversations_user_one (user_one_id, updated_at, id),
  KEY idx_direct_conversations_user_two (user_two_id, updated_at, id),
  CONSTRAINT fk_direct_conversations_user_one FOREIGN KEY (user_one_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_direct_conversations_user_two FOREIGN KEY (user_two_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT chk_direct_conversations_pair CHECK (user_one_id < user_two_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS direct_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  body VARCHAR(2000) NULL DEFAULT NULL,
  reply_to_message_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL DEFAULT NULL,
  edited_at DATETIME NULL DEFAULT NULL,
  edit_window_expires_at DATETIME NULL DEFAULT NULL,
  deleted_for_everyone_at DATETIME NULL DEFAULT NULL,
  delete_window_expires_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_direct_messages_conversation (conversation_id, id),
  KEY idx_direct_messages_sender (sender_user_id),
  KEY idx_direct_messages_read (conversation_id, read_at, id),
  KEY idx_direct_messages_reply (reply_to_message_id),
  CONSTRAINT fk_direct_messages_conversation FOREIGN KEY (conversation_id) REFERENCES direct_conversations(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_direct_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_direct_messages_reply FOREIGN KEY (reply_to_message_id) REFERENCES direct_messages(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS direct_message_reactions (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reaction VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id, reaction),
  KEY idx_dm_reactions_user (user_id, created_at),
  KEY idx_dm_reactions_lookup (message_id, reaction),
  CONSTRAINT fk_dm_reactions_message FOREIGN KEY (message_id) REFERENCES direct_messages(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_dm_reactions_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS direct_message_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL DEFAULT '',
  mime_type VARCHAR(100) NOT NULL DEFAULT '',
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  image_width SMALLINT UNSIGNED NULL DEFAULT NULL,
  image_height SMALLINT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dm_attachments_message (message_id),
  CONSTRAINT fk_dm_attachments_message FOREIGN KEY (message_id) REFERENCES direct_messages(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(32) NOT NULL,
  event_key VARCHAR(120) NOT NULL,
  post_id BIGINT UNSIGNED NULL,
  comment_id BIGINT UNSIGNED NULL,
  target_url VARCHAR(255) NULL DEFAULT NULL,
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
  target_snapshot_json TEXT NULL DEFAULT NULL,
  wants_reporter_dm_updates TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL DEFAULT NULL,
  resolution_action_key VARCHAR(32) NULL DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS moderation_user_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  watchlisted TINYINT(1) NOT NULL DEFAULT 0,
  watch_reason VARCHAR(280) NULL DEFAULT NULL,
  summary TEXT NULL DEFAULT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  priority VARCHAR(16) NOT NULL DEFAULT 'normal',
  resolution_action_key VARCHAR(32) NULL DEFAULT NULL,
  resolution_notes TEXT NULL DEFAULT NULL,
  assigned_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  current_escalation_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_by_staff_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_staff_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at DATETIME NULL DEFAULT NULL,
  closed_by_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moderation_user_cases_user (user_id),
  KEY idx_moderation_user_cases_watchlisted (watchlisted, updated_at),
  KEY idx_moderation_user_cases_status_priority (status, priority, updated_at),
  KEY idx_moderation_user_cases_assignee (assigned_staff_user_id),
  KEY idx_moderation_user_cases_escalation (current_escalation_id),
  KEY idx_moderation_user_cases_closed_by (closed_by_staff_user_id),
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
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_cases_escalation FOREIGN KEY (current_escalation_id) REFERENCES moderation_escalations(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_user_cases_closed_by FOREIGN KEY (closed_by_staff_user_id) REFERENCES users(id)
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
  assigned_staff_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  reviewed_at DATETIME NULL DEFAULT NULL,
  metadata_json TEXT NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_moderation_suspicious_status (status, severity, last_detected_at),
  KEY idx_moderation_suspicious_rule_actor (rule_key, actor_user_id),
  KEY idx_moderation_suspicious_linked_report (linked_report_id),
  KEY idx_moderation_suspicious_reviewer (reviewed_by_staff_user_id),
  KEY idx_moderation_suspicious_assignee (assigned_staff_user_id),
  CONSTRAINT fk_moderation_suspicious_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_suspicious_linked_report FOREIGN KEY (linked_report_id) REFERENCES moderation_reports(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_moderation_suspicious_assignee FOREIGN KEY (assigned_staff_user_id) REFERENCES users(id)
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
