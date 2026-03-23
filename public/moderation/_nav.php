<?php
declare(strict_types=1);

$moderationModules = trux_visible_moderation_modules($moderationStaffRole);
$moderationBadgeCounts = trux_moderation_fetch_staff_badge_counts((int)($moderationMe['id'] ?? 0), $moderationStaffRole);
$moderationActiveKey = isset($moderationActiveKey) && is_string($moderationActiveKey)
    ? $moderationActiveKey
    : 'dashboard';
?>
<aside class="moderationSidebar">
  <div class="card moderationSidebarCard">
    <div class="card__body">
      <div class="moderationSidebarCard__head">
        <h2 class="h2">Moderation</h2>
        <p class="muted">Private tools for staff accounts.</p>
      </div>
      <nav class="moderationNav" aria-label="Moderation sections">
        <?php foreach ($moderationModules as $moduleKey => $module): ?>
          <a
            class="moderationNav__item<?= $moderationActiveKey === $moduleKey ? ' is-active' : '' ?>"
            href="<?= TRUX_BASE_URL . $module['path'] ?>">
            <strong>
              <?= trux_e((string)$module['title']) ?>
              <?php if ((int)($moderationBadgeCounts[$moduleKey] ?? 0) > 0): ?>
                <span class="menuBadge"><?= (int)$moderationBadgeCounts[$moduleKey] ?></span>
              <?php endif; ?>
            </strong>
            <small class="muted"><?= trux_e((string)$module['description']) ?></small>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>

  <div class="card moderationSidebarCard moderationSidebarCard--meta">
    <div class="card__body">
      <div class="moderationRoleBadge"><?= trux_e(ucfirst($moderationStaffRole)) ?></div>
      <div class="moderationSidebarMeta">
        <div class="moderationSidebarMeta__row">
          <span class="muted">Write access</span>
          <strong><?= trux_can_moderation_write($moderationStaffRole) ? 'Yes' : 'Read only' ?></strong>
        </div>
        <div class="moderationSidebarMeta__row">
          <span class="muted">Reassign reports</span>
          <strong><?= trux_can_moderation_reassign($moderationStaffRole) ? 'Yes' : 'No' ?></strong>
        </div>
        <div class="moderationSidebarMeta__row">
          <span class="muted">Full audit JSON</span>
          <strong><?= trux_can_view_full_moderation_audit($moderationStaffRole) ? 'Yes' : 'Summary only' ?></strong>
        </div>
      </div>
    </div>
  </div>
</aside>
