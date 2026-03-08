<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();
$me = trux_current_user();
if (!$me) {
    trux_redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = [];
    foreach (array_keys(trux_notification_defaults()) as $key) {
        $submitted[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
    }

    if (trux_update_notification_preferences((int)$me['id'], $submitted)) {
        trux_flash_set('success', 'Notification preferences updated.');
    } else {
        trux_flash_set('error', 'Could not update notification preferences right now.');
    }

    trux_redirect('/settings.php');
}

$prefs = trux_fetch_notification_preferences((int)$me['id']);
$prefLabels = trux_notification_pref_labels();

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Settings</h1>
  <p class="muted">Manage the notifications you want to receive.</p>
</section>

<section class="card settingsCard">
  <div class="card__body">
    <form class="form settingsForm" method="post" action="/settings.php">
      <?= trux_csrf_field() ?>

      <div class="settingSection">
        <div class="settingSection__head">
          <h2 class="h2">Notifications</h2>
          <p class="muted">Choose which activity should appear in your notification feed.</p>
        </div>

        <?php foreach ($prefLabels as $key => $meta): ?>
          <label class="settingRow" for="<?= trux_e($key) ?>">
            <span class="settingRow__label">
              <strong><?= trux_e((string)$meta['title']) ?></strong>
              <small class="muted"><?= trux_e((string)$meta['description']) ?></small>
            </span>
            <input
              id="<?= trux_e($key) ?>"
              type="checkbox"
              name="<?= trux_e($key) ?>"
              value="1"
              <?= !empty($prefs[$key]) ? 'checked' : '' ?>>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="settingSection">
        <div class="settingRow">
          <span class="settingRow__label">
            <strong>Visual settings</strong>
            <small class="muted">The interface now uses the fixed classic baseline across the site.</small>
          </span>
          <strong class="muted">Removed</strong>
        </div>
      </div>

      <div class="row">
        <button class="btn" type="submit">Save settings</button>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
