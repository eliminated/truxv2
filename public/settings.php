<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Settings</h1>
  <p class="muted">Visual controls have been removed. This page remains available for future account and app settings.</p>
</section>

<section class="card settingsCard">
  <div class="card__body">
    <div class="form settingsForm">
      <div class="settingRow">
        <span class="settingRow__label">
          <strong>Visual settings</strong>
          <small class="muted">The interface now uses the fixed classic baseline across the site.</small>
        </span>
        <strong class="muted">Removed</strong>
      </div>

      <div class="settingRow">
        <span class="settingRow__label">
          <strong>More settings</strong>
          <small class="muted">Account and application preferences can be added here later without changing navigation again.</small>
        </span>
        <strong class="muted">Coming soon</strong>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
