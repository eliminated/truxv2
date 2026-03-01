<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reduceMotion = isset($_POST['reduce_motion']) && (string)($_POST['reduce_motion'] ?? '') === '1';
    $classicAppearance = isset($_POST['classic_appearance']) && (string)($_POST['classic_appearance'] ?? '') === '1';

    trux_set_ui_preferences($reduceMotion, $classicAppearance);
    trux_flash_set('success', 'Display settings updated.');
    trux_redirect('/settings.php');
}

$uiPrefs = trux_get_ui_preferences();

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Settings</h1>
  <p class="muted">Tune visuals for smoother browsing on lower-memory devices. Saved to your account.</p>
</section>

<section class="card settingsCard">
  <div class="card__body">
    <form class="form settingsForm" method="post" action="/settings.php">
      <?= trux_csrf_field() ?>

      <label class="settingRow">
        <span class="settingRow__label">
          <strong>Reduce motions</strong>
          <small class="muted">Turns off heavy motion effects and transition overlay.</small>
        </span>
        <input type="checkbox" name="reduce_motion" value="1" <?= $uiPrefs['reduce_motion'] ? 'checked' : '' ?>>
      </label>

      <label class="settingRow">
        <span class="settingRow__label">
          <strong>Classic appearance</strong>
          <small class="muted">Uses a simpler style without neon borders, glows, and glass effects.</small>
        </span>
        <input type="checkbox" name="classic_appearance" value="1" <?= $uiPrefs['classic_appearance'] ? 'checked' : '' ?>>
      </label>

      <div class="row">
        <button class="btn" type="submit">Save settings</button>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
