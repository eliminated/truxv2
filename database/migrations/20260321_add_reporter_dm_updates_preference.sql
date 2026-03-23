ALTER TABLE moderation_reports
  ADD COLUMN IF NOT EXISTS wants_reporter_dm_updates TINYINT(1) NOT NULL DEFAULT 0
  AFTER source_url;
