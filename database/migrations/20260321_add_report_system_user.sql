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
