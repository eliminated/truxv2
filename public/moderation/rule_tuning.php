<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
trux_require_staff_role('admin');

$moderationActiveKey = 'rule_tuning';
$ruleDefaults = trux_moderation_rule_config_defaults();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!trux_can_moderation_write($moderationStaffRole)) {
        trux_flash_set('error', 'Your role is read-only in moderation.');
        trux_redirect('/moderation/rule_tuning.php');
    }

    $ruleKey = trim((string)($_POST['rule_key'] ?? ''));
    $defaultConfig = is_array($ruleDefaults[$ruleKey] ?? null) ? $ruleDefaults[$ruleKey] : null;
    if ($defaultConfig === null) {
        trux_flash_set('error', 'Unknown rule.');
        trux_redirect('/moderation/rule_tuning.php');
    }

    $settings = [];
    foreach (array_keys((array)($defaultConfig['settings'] ?? [])) as $settingKey) {
        $rawValue = $_POST['settings'][$settingKey] ?? null;
        $settings[$settingKey] = is_string($rawValue) && preg_match('/^\d+$/', $rawValue) ? (int)$rawValue : (int)($defaultConfig['settings'][$settingKey] ?? 0);
    }

    $ok = trux_moderation_update_rule_config((int)$moderationMe['id'], $ruleKey, !empty($_POST['enabled']), $settings);
    trux_flash_set($ok ? 'success' : 'error', $ok ? 'Rule configuration updated.' : 'Could not update the rule configuration.');
    trux_redirect('/moderation/rule_tuning.php');
}

$ruleConfigs = trux_moderation_fetch_rule_config_map(true);

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>Rule Tuning</h1>
  <p class="muted">Adjust moderation thresholds and heuristic windows without changing code.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <section class="moderationPanelGrid">
      <?php foreach ($ruleConfigs as $ruleKey => $config): ?>
        <?php $defaultSettings = is_array($ruleDefaults[$ruleKey]['settings'] ?? null) ? $ruleDefaults[$ruleKey]['settings'] : []; ?>
        <article class="card moderationPanel">
          <div class="card__body">
            <div class="moderationPanel__head">
              <div>
                <h2 class="h2"><?= trux_e(trux_moderation_rule_label($ruleKey)) ?></h2>
                <p class="muted">Rule key: <code><?= trux_e($ruleKey) ?></code></p>
              </div>
              <span class="moderationBadge <?= !empty($config['enabled']) ? 'is-success' : 'is-muted' ?>"><?= !empty($config['enabled']) ? 'Enabled' : 'Disabled' ?></span>
            </div>

            <form class="form" method="post" action="<?= TRUX_BASE_URL ?>/moderation/rule_tuning.php">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="rule_key" value="<?= trux_e($ruleKey) ?>">
              <label class="field" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>>
                <span>Enable this rule</span>
              </label>

              <?php foreach ($defaultSettings as $settingKey => $defaultValue): ?>
                <label class="field">
                  <span><?= trux_e(ucwords(str_replace('_', ' ', (string)$settingKey))) ?></span>
                  <input type="number" min="1" step="1" name="settings[<?= trux_e((string)$settingKey) ?>]" value="<?= (int)($config['settings'][$settingKey] ?? $defaultValue) ?>">
                </label>
              <?php endforeach; ?>

              <div class="row row--spaced">
                <span class="muted">
                  <?php if (!empty($config['updated_at'])): ?>
                    Last updated <?= trux_e((string)$config['updated_at']) ?>
                  <?php else: ?>
                    Using seeded defaults
                  <?php endif; ?>
                </span>
                <button class="btn btn--small" type="submit">Save rule</button>
              </div>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
