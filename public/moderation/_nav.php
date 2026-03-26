<?php
declare(strict_types=1);

$moderationModules = trux_visible_moderation_modules($moderationStaffRole);
$moderationBadgeCounts = trux_moderation_fetch_staff_badge_counts((int)($moderationMe['id'] ?? 0), $moderationStaffRole);
$moderationActiveKey = isset($moderationActiveKey) && is_string($moderationActiveKey)
  ? $moderationActiveKey
  : 'dashboard';
$activeModule = is_array($moderationModules[$moderationActiveKey] ?? null) ? $moderationModules[$moderationActiveKey] : null;
?>
<section class="opsQueueStrip">
  <div class="opsQueueStrip__head">
    <div>
      <span class="opsQueueStrip__eyebrow">Current module</span>
      <h2><?= trux_e((string)($activeModule['title'] ?? 'Moderation')) ?></h2>
    </div>
    <div class="opsQueueStrip__role">
      <span><?= trux_e(ucfirst($moderationStaffRole)) ?></span>
      <small><?= trux_can_moderation_write($moderationStaffRole) ? 'Write access' : 'Read only' ?></small>
    </div>
  </div>

  <div class="opsQueueStrip__metrics">
    <?php foreach ($moderationModules as $moduleKey => $module): ?>
      <a class="opsQueueStrip__metric<?= $moderationActiveKey === $moduleKey ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL . $module['path'] ?>">
        <strong><?= (int)($moderationBadgeCounts[$moduleKey] ?? 0) ?></strong>
        <span><?= trux_e((string)$module['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
