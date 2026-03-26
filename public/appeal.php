<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'appeal';
$pageLayout = 'app';

$token = trim(trux_str_param('token', ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = trim((string)($_POST['token'] ?? $token));
}

$enforcement = $token !== '' ? trux_moderation_fetch_user_enforcement_by_token($token) : null;
$existingAppeal = $enforcement ? trux_moderation_fetch_appeal_by_enforcement_id((int)$enforcement['id']) : null;
$me = trux_current_user();
$error = null;
$success = null;
$body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = trim((string)($_POST['reason'] ?? ''));
  if ($token === '' || !$enforcement) {
    $error = 'This appeal link is invalid or expired.';
  } elseif ($existingAppeal) {
    $success = 'An appeal for this action has already been submitted.';
  } else {
    $result = trux_moderation_submit_appeal($token, $body, $me ? (int)$me['id'] : null);
    if (!empty($result['ok'])) {
      $success = 'Appeal submitted. The moderation team will review it.';
      $existingAppeal = trux_moderation_fetch_appeal_by_enforcement_id((int)$enforcement['id']);
    } else {
      $error = (string)($result['error'] ?? 'Could not submit the appeal right now.');
    }
  }
}

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--studio">
  <section class="pageBand pageBand--studio">
    <div class="pageBand__main">
      <span class="pageBand__eyebrow">Appeal form</span>
      <h2 class="pageBand__title">Appeal moderation action</h2>
      <p class="pageBand__copy">Use this page to request a review of an account-level moderation action.</p>
    </div>
  </section>

  <section class="bandSurface">
    <?php if ($error !== null): ?>
      <div class="flash flash--error"><?= trux_e($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== null): ?>
      <div class="flash flash--success"><?= trux_e($success) ?></div>
    <?php endif; ?>

    <?php if (!$enforcement): ?>
      <section class="bandSurface bandSurface--empty bandSurface--nested">
        <strong>Invalid appeal link</strong>
        <p class="muted">This moderation appeal link is missing, expired, or no longer available.</p>
      </section>
    <?php else: ?>
      <div class="reviewModal__metaList">
        <div class="reviewModal__metaRow">
          <span class="muted">Action</span>
          <strong><?= trux_e(trux_moderation_resolution_action_label((string)$enforcement['action_key'])) ?></strong>
        </div>
        <div class="reviewModal__metaRow">
          <span class="muted">Status</span>
          <strong><?= trux_e(trux_moderation_label(trux_moderation_user_enforcement_statuses(), (string)$enforcement['status'])) ?></strong>
        </div>
        <?php if (!empty($enforcement['ends_at'])): ?>
          <div class="reviewModal__metaRow">
            <span class="muted">Ends at</span>
            <strong><?= trux_e((string)$enforcement['ends_at']) ?></strong>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($enforcement['reason_summary'])): ?>
        <div class="reviewModal__note"><?= trux_e((string)$enforcement['reason_summary']) ?></div>
      <?php endif; ?>
      <?php if (!empty($enforcement['details'])): ?>
        <p><?= nl2br(trux_e((string)$enforcement['details'])) ?></p>
      <?php endif; ?>

      <?php if ($existingAppeal): ?>
        <div class="reviewModal__note">
          Appeal status: <strong><?= trux_e(trux_moderation_label(trux_moderation_appeal_statuses(), (string)$existingAppeal['status'])) ?></strong>
        </div>
        <p class="muted">Only one appeal is allowed per moderation action. Staff will continue reviewing the existing submission.</p>
      <?php else: ?>
        <form class="form" method="post" action="<?= TRUX_BASE_URL ?>/appeal.php?token=<?= urlencode($token) ?>">
          <?= trux_csrf_field() ?>
          <input type="hidden" name="token" value="<?= trux_e($token) ?>">
          <label class="field">
            <span>Why should this action be reviewed?</span>
            <textarea name="reason" rows="8" maxlength="4000" required placeholder="Describe what happened, why you think the action was incorrect, and any context staff should review."><?= trux_e($body) ?></textarea>
          </label>
          <button class="shellButton shellButton--accent" type="submit">Submit appeal</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
