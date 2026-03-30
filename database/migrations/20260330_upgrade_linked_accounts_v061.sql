-- Run manually or via migration runner. Check for column existence before applying.

ALTER TABLE linked_accounts
  MODIFY provider VARCHAR(32) NOT NULL,
  MODIFY provider_user_id VARCHAR(191) NULL DEFAULT NULL,
  ADD COLUMN provider_username VARCHAR(191) NULL DEFAULT NULL AFTER provider_user_id,
  ADD COLUMN provider_display_name VARCHAR(191) NULL DEFAULT NULL AFTER provider_username,
  ADD COLUMN provider_avatar_url VARCHAR(255) NULL DEFAULT NULL AFTER provider_display_name,
  ADD COLUMN status VARCHAR(24) NOT NULL DEFAULT 'connected' AFTER provider_avatar_url,
  ADD COLUMN status_reason VARCHAR(255) NULL DEFAULT NULL AFTER status,
  ADD COLUMN metadata_json TEXT NULL AFTER status_reason,
  ADD COLUMN last_verified_at DATETIME NULL DEFAULT NULL AFTER linked_at,
  ADD COLUMN last_used_at DATETIME NULL DEFAULT NULL AFTER last_verified_at,
  ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER last_used_at,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE linked_accounts
SET status = 'connected',
    linked_at = COALESCE(linked_at, NOW()),
    last_verified_at = COALESCE(last_verified_at, linked_at, NOW()),
    created_at = COALESCE(linked_at, created_at, NOW()),
    updated_at = COALESCE(linked_at, updated_at, NOW())
WHERE status IS NULL
   OR status = ''
   OR linked_at IS NULL
   OR last_verified_at IS NULL
   OR created_at IS NULL
   OR updated_at IS NULL;

ALTER TABLE linked_accounts
  DROP INDEX idx_linked_accounts_provider,
  ADD UNIQUE KEY unique_provider_identity (provider, provider_user_id),
  ADD KEY idx_linked_accounts_user_status (user_id, status),
  ADD KEY idx_linked_accounts_provider_status (provider, status);
