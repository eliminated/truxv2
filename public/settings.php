<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'settings';
$pageLayout = 'app';

trux_require_login();
$me = trux_current_user();
if (!$me) {
  trux_redirect('/login.php');
}

$settingsSections = [
  'notifications' => [
    'title' => 'Notifications',
    'nav_description' => 'Feed alerts and activity',
    'hero_description' => 'Choose which activity should appear in your notification feed.',
  ],
  'privacy' => [
    'title' => 'Privacy',
    'nav_description' => 'Profile visibility controls',
    'hero_description' => 'Manage what other people can see on your profile.',
  ],
  'muted' => [
    'title' => 'Muted Users',
    'nav_description' => 'Users you muted',
    'hero_description' => 'Review and manage the people you have muted.',
  ],
  'blocked' => [
    'title'            => 'Blocked Users',
    'nav_description'  => 'Users you blocked',
    'hero_description' => 'Review and manage users you have blocked.',
  ],
  'interface' => [
    'title' => 'Interface',
    'nav_description' => 'Current UI status',
    'hero_description' => 'Check the current state of interface-related options.',
  ],
];

$activeSection = trim(trux_str_param('section', 'notifications'));
if (!isset($settingsSections[$activeSection])) {
  $activeSection = 'notifications';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $redirectSection = trim((string)($_POST['section'] ?? $activeSection));
  if (!isset($settingsSections[$redirectSection])) {
    $redirectSection = 'notifications';
  }

  $action = $_POST['action'] ?? 'save_notifications';
  if ($action === 'unmute_user') {
    $rawMutedUserId = $_POST['muted_user_id'] ?? null;
    if (is_string($rawMutedUserId) && preg_match('/^\d+$/', $rawMutedUserId)) {
      trux_unmute_user((int)$me['id'], (int)$rawMutedUserId);
      trux_flash_set('success', 'User unmuted.');
    } else {
      trux_flash_set('error', 'Invalid muted user.');
    }
  } elseif ($action === 'unblock_user') {
    $rawBlockedUserId = $_POST['blocked_user_id'] ?? null;
    if (is_string($rawBlockedUserId) && preg_match('/^\d+$/', $rawBlockedUserId)) {
      trux_unblock_user((int)$me['id'], (int)$rawBlockedUserId);
      trux_flash_set('success', 'User unblocked.');
    } else {
      trux_flash_set('error', 'Invalid user.');
    }
  } elseif ($action === 'save_privacy') {
    $submitted = [];
    foreach (array_keys(trux_profile_privacy_defaults()) as $key) {
      $submitted[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
    }

    if (trux_update_profile_privacy_preferences((int)$me['id'], $submitted)) {
      trux_flash_set('success', 'Privacy settings updated.');
    } else {
      trux_flash_set('error', 'Could not update privacy settings right now.');
    }
  } else {
    $submitted = [];
    foreach (array_keys(trux_notification_defaults()) as $key) {
      $submitted[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
    }

    if (trux_update_notification_preferences((int)$me['id'], $submitted)) {
      trux_flash_set('success', 'Notification preferences updated.');
    } else {
      trux_flash_set('error', 'Could not update notification preferences right now.');
    }
  }

  trux_redirect('/settings.php?section=' . urlencode($redirectSection));
}

$prefs = trux_fetch_notification_preferences((int)$me['id']);
$privacyPrefs = trux_fetch_profile_privacy_preferences((int)$me['id']);
$prefLabels = trux_notification_pref_labels();
$mutedUsers = trux_fetch_muted_users((int)$me['id']);
$blockedUsers = trux_fetch_blocked_users((int)$me['id']);

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--settings">
  <section class="inlineHeader inlineHeader--settings">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Workspace center</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title">Settings</h2>
        <p class="inlineHeader__copy"><?= trux_e((string)$settingsSections[$activeSection]['hero_description']) ?></p>
      </div>
    </div>
    <div class="inlineHeader__aside">
      <div class="inlineHeader__meta">
        <span>@<?= trux_e((string)$me['username']) ?></span>
        <strong><?= trux_e((string)$settingsSections[$activeSection]['title']) ?></strong>
      </div>
    </div>
  </section>

  <section class="settingsLayout">
    <aside class="settingsSidebar">
      <div class="settingsNavCard">
        <div class="settingsNavCard__head">
          <span class="settingsNavCard__eyebrow">Sections</span>
          <h3>Account controls</h3>
        </div>
        <nav class="settingsNav" aria-label="Settings sections">
          <?php foreach ($settingsSections as $sectionKey => $sectionMeta): ?>
            <a
              class="settingsNav__item<?= $activeSection === $sectionKey ? ' is-active' : '' ?>"
              href="<?= TRUX_BASE_URL ?>/settings.php?section=<?= urlencode($sectionKey) ?>"
              data-settings-nav="<?= trux_e($sectionKey) ?>">
              <strong><?= trux_e((string)$sectionMeta['title']) ?></strong>
              <small><?= trux_e((string)$sectionMeta['nav_description']) ?></small>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
    </aside>

    <div class="settingsContent">
      <?php if ($activeSection === 'notifications'): ?>
        <section class="settingsSectionCard" id="settings-notifications" data-settings-section="notifications">
          <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/settings.php">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="save_notifications">
            <input type="hidden" name="section" value="notifications">

            <div class="settingSection">
              <div class="settingSection__head">
                <span class="settingSection__eyebrow">Notifications</span>
                <h3>Feed alerts and activity</h3>
                <p class="muted">Choose which activity should appear in your notification feed.</p>
              </div>

              <?php foreach ($prefLabels as $key => $meta): ?>
                <label class="settingRow" for="<?= trux_e($key) ?>">
                  <span class="settingRow__label">
                    <strong><?= trux_e((string)$meta['title']) ?></strong>
                    <small class="muted"><?= trux_e((string)$meta['description']) ?></small>
                  </span>
                  <input id="<?= trux_e($key) ?>" type="checkbox" name="<?= trux_e($key) ?>" value="1" <?= !empty($prefs[$key]) ? 'checked' : '' ?>>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="settingsActions">
              <button class="shellButton shellButton--accent" type="submit">Save notifications</button>
            </div>
          </form>
        </section>
      <?php elseif ($activeSection === 'privacy'): ?>
        <section class="settingsSectionCard" id="settings-privacy" data-settings-section="privacy">
          <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/settings.php">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="save_privacy">
            <input type="hidden" name="section" value="privacy">

            <div class="settingSection">
              <div class="settingSection__head">
                <span class="settingSection__eyebrow">Privacy</span>
                <h3>Profile visibility</h3>
                <p class="muted">Control who can view the Likes and Bookmarks tabs on your profile. Both are public by default.</p>
              </div>

              <label class="settingRow" for="show_likes_public">
                <span class="settingRow__label">
                  <strong>Show likes publicly</strong>
                  <small class="muted">Allow other people to open your Likes tab and see the posts, comments, and replies you liked.</small>
                </span>
                <input id="show_likes_public" type="checkbox" name="show_likes_public" value="1" <?= !empty($privacyPrefs['show_likes_public']) ? 'checked' : '' ?>>
              </label>

              <label class="settingRow" for="show_bookmarks_public">
                <span class="settingRow__label">
                  <strong>Show bookmarks publicly</strong>
                  <small class="muted">Allow other people to open your Bookmarks tab and see the posts, comments, and replies you saved.</small>
                </span>
                <input id="show_bookmarks_public" type="checkbox" name="show_bookmarks_public" value="1" <?= !empty($privacyPrefs['show_bookmarks_public']) ? 'checked' : '' ?>>
              </label>
            </div>

            <div class="settingsActions">
              <button class="shellButton shellButton--accent" type="submit">Save privacy</button>
            </div>
          </form>
        </section>
      <?php elseif ($activeSection === 'muted'): ?>
        <section class="settingsSectionCard" id="settings-muted" data-settings-section="muted">
          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Muted users</span>
              <h3>Muted accounts</h3>
              <p class="muted">You will not receive notifications generated by users you mute.</p>
            </div>

            <?php if (!$mutedUsers): ?>
              <div class="settingRow">
                <span class="settingRow__label">
                  <strong>No muted users</strong>
                  <small class="muted">Mute a user from their profile to hide their likes, replies, follows, mentions, and vote notifications.</small>
                </span>
                <strong class="muted">0</strong>
              </div>
            <?php else: ?>
              <?php foreach ($mutedUsers as $mutedUser): ?>
                <div class="settingRow">
                  <span class="settingRow__label">
                    <strong><a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$mutedUser['username']) ?>">@<?= trux_e((string)$mutedUser['username']) ?></a></strong>
                    <small class="muted">
                      Muted
                      <span data-time-ago="1" data-time-source="<?= trux_e((string)$mutedUser['created_at']) ?>" title="<?= trux_e(trux_format_exact_time((string)$mutedUser['created_at'])) ?>">
                        <?= trux_e(trux_time_ago((string)$mutedUser['created_at'])) ?>
                      </span>
                    </small>
                  </span>
                  <form method="post" action="<?= TRUX_BASE_URL ?>/settings.php" class="inline">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="unmute_user">
                    <input type="hidden" name="section" value="muted">
                    <input type="hidden" name="muted_user_id" value="<?= (int)$mutedUser['id'] ?>">
                    <button class="shellButton shellButton--ghost" type="submit">Unmute</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      <?php elseif ($activeSection === 'blocked'): ?>
        <section class="settingsSectionCard" id="settings-blocked" data-settings-section="blocked">
          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Blocked users</span>
              <h3>Blocked accounts</h3>
              <p class="muted">Blocked users cannot see your profile, send you messages, or appear in your feeds.</p>
            </div>

            <?php if (!$blockedUsers): ?>
              <div class="settingRow">
                <span class="settingRow__label">
                  <strong>No blocked users</strong>
                  <small class="muted">Block a user from their profile to hide them entirely.</small>
                </span>
                <strong class="muted">0</strong>
              </div>
            <?php else: ?>
              <?php foreach ($blockedUsers as $blockedUser): ?>
                <div class="settingRow">
                  <span class="settingRow__label">
                    <strong><a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$blockedUser['username']) ?>">@<?= trux_e((string)$blockedUser['username']) ?></a></strong>
                    <small class="muted">
                      Blocked
                      <span data-time-ago="1" data-time-source="<?= trux_e((string)$blockedUser['created_at']) ?>" title="<?= trux_e(trux_format_exact_time((string)$blockedUser['created_at'])) ?>">
                        <?= trux_e(trux_time_ago((string)$blockedUser['created_at'])) ?>
                      </span>
                    </small>
                  </span>
                  <form method="post" action="<?= TRUX_BASE_URL ?>/settings.php" class="inline">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="unblock_user">
                    <input type="hidden" name="section" value="blocked">
                    <input type="hidden" name="blocked_user_id" value="<?= (int)$blockedUser['id'] ?>">
                    <button class="shellButton shellButton--ghost" type="submit">Unblock</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      <?php else: ?>
        <section class="settingsSectionCard" id="settings-interface" data-settings-section="interface">
          <div class="settingSection">
            <div class="settingSection__head">
              <span class="settingSection__eyebrow">Interface</span>
              <h3>UI status</h3>
              <p class="muted">A quick status check for UI-related options.</p>
            </div>

            <div class="settingRow">
              <span class="settingRow__label">
                <strong>Visual system</strong>
                <small class="muted">TruX now runs on the command-shell interface foundation across the site.</small>
              </span>
              <strong class="muted">Unified</strong>
            </div>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
