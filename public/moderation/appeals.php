<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
trux_require_staff_role('admin');

$pageKey = 'moderation-appeals';
$moderationActiveKey = 'appeals';
$staffUsers = trux_fetch_staff_users('admin');
$appealStatuses = trux_moderation_appeal_statuses();
$currentQuery = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$returnPath = '/moderation/appeals.php' . ($currentQuery !== '' ? '?' . $currentQuery : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!trux_can_moderation_write($moderationStaffRole)) {
        trux_flash_set('error', 'Your role is read-only in moderation.');
        trux_redirect($returnPath);
    }

    $appealIdRaw = $_POST['appeal_id'] ?? null;
    $appealId = is_string($appealIdRaw) && preg_match('/^\d+$/', $appealIdRaw) ? (int)$appealIdRaw : 0;
    $action = trim((string)($_POST['action'] ?? ''));
    if ($appealId <= 0) {
        trux_flash_set('error', 'Invalid appeal.');
        trux_redirect($returnPath);
    }

    if ($action === 'claim_appeal') {
        $ok = trux_moderation_assign_appeal($appealId, (int)$moderationMe['id'], (int)$moderationMe['id']);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Appeal assigned to you.' : 'Could not claim appeal.');
        trux_redirect($returnPath);
    }

    if ($action === 'assign_appeal') {
        $assignedRaw = $_POST['assigned_staff_user_id'] ?? '';
        $assignedStaffUserId = is_string($assignedRaw) && preg_match('/^\d+$/', $assignedRaw) ? (int)$assignedRaw : null;
        $ok = trux_moderation_assign_appeal($appealId, (int)$moderationMe['id'], $assignedStaffUserId);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Appeal assignee updated.' : 'Could not update the appeal assignee.');
        trux_redirect($returnPath);
    }

    if ($action === 'update_status') {
        $status = trim((string)($_POST['status'] ?? ''));
        $resolutionNotes = trim((string)($_POST['resolution_notes'] ?? ''));
        $ok = trux_moderation_update_appeal_status($appealId, (int)$moderationMe['id'], $status, $resolutionNotes);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Appeal updated.' : 'Could not update the appeal.');
        trux_redirect($returnPath);
    }

    trux_flash_set('error', 'Unknown moderation action.');
    trux_redirect($returnPath);
}

$filters = [
    'status' => trux_str_param('status', 'all'),
    'assignee' => trux_str_param('assignee', 'all'),
];
$page = max(1, trux_int_param('page', 1));
$selectedAppealId = max(0, trux_int_param('appeal', 0));
$appealPage = trux_moderation_fetch_appeals($filters, $page, 25);
$appeals = is_array($appealPage['items'] ?? null) ? $appealPage['items'] : [];
$selectedAppeal = $selectedAppealId > 0 ? trux_moderation_fetch_appeal_by_id($selectedAppealId) : null;
$selectedEnforcement = $selectedAppeal ? trux_moderation_fetch_user_enforcement_by_id((int)($selectedAppeal['enforcement_id'] ?? 0)) : null;
$totalPages = max(1, (int)($appealPage['total_pages'] ?? 1));

$buildUrl = static function (array $overrides = []) use ($filters, $page): string {
    $params = array_merge($filters, ['page' => $page], $overrides);
    foreach ($params as $key => $value) {
        if (!is_string($key) || $value === null || $value === '' || $value === 'all' || ($key === 'page' && (int)$value <= 1)) {
            unset($params[$key]);
        }
    }
    return TRUX_BASE_URL . '/moderation/appeals.php' . ($params ? '?' . http_build_query($params) : '');
};

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>Appeals</h1>
  <p class="muted">Admin queue for account-action appeals submitted from moderation notices.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <section class="card moderationPanel">
      <div class="card__body">
        <form class="moderationFilters" method="get" action="<?= TRUX_BASE_URL ?>/moderation/appeals.php">
          <label class="field">
            <span>Status</span>
            <select name="status">
              <option value="all">All</option>
              <?php foreach ($appealStatuses as $statusKey => $statusLabel): ?>
                <option value="<?= trux_e($statusKey) ?>" <?= $filters['status'] === $statusKey ? 'selected' : '' ?>><?= trux_e($statusLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">
            <span>Assignee</span>
            <select name="assignee">
              <option value="all">All</option>
              <option value="unassigned" <?= $filters['assignee'] === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
              <?php foreach ($staffUsers as $staffUser): ?>
                <option value="<?= (int)$staffUser['id'] ?>" <?= (string)$filters['assignee'] === (string)$staffUser['id'] ? 'selected' : '' ?>>@<?= trux_e((string)$staffUser['username']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="moderationFilters__actions">
            <button class="btn btn--small" type="submit">Apply filters</button>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/appeals.php">Reset</a>
          </div>
        </form>
      </div>
    </section>

    <section class="moderationPanelGrid">
      <article class="card moderationPanel">
        <div class="card__body">
          <div class="moderationPanel__head">
            <div>
              <h2 class="h2">Queue</h2>
              <p class="muted"><?= (int)($appealPage['total'] ?? 0) ?> appeal<?= (int)($appealPage['total'] ?? 0) === 1 ? '' : 's' ?> matched.</p>
            </div>
          </div>

          <?php if (!$appeals): ?>
            <div class="moderationEmptyState">
              <strong>No appeals matched</strong>
              <p class="muted">The current filters do not match any appeal records.</p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($appeals as $item): ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong>#<?= (int)$item['id'] ?> · <?= trux_e(trux_moderation_resolution_action_label((string)$item['action_key'])) ?></strong>
                    <div class="moderationBadgeRow">
                      <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$item['status']) ?>"><?= trux_e(trux_moderation_label($appealStatuses, (string)$item['status'])) ?></span>
                    </div>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <span>User #<?= (int)$item['user_id'] ?></span>
                    <span>Assignee: <?= !empty($item['assigned_staff_username']) ? '@' . trux_e((string)$item['assigned_staff_username']) : 'Unassigned' ?></span>
                  </div>
                  <p class="moderationListItem__summary"><?= trux_e(trux_moderation_trimmed_excerpt((string)$item['submitter_reason'], 220)) ?></p>
                  <div class="moderationActions">
                    <a class="btn btn--small btn--ghost" href="<?= $buildUrl(['appeal' => (int)$item['id']]) ?>">Inspect</a>
                    <?php if (!empty($item['user_case_id'])): ?>
                      <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/user_review.php?user_id=<?= (int)$item['user_id'] ?>">Open case</a>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>

            <div class="moderationPagination">
              <?php if ($page > 1): ?>
                <a class="btn btn--small btn--ghost" href="<?= $buildUrl(['page' => $page - 1]) ?>">Previous</a>
              <?php endif; ?>
              <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
              <?php if ($page < $totalPages): ?>
                <a class="btn btn--small btn--ghost" href="<?= $buildUrl(['page' => $page + 1]) ?>">Next</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </article>

      <article class="card moderationPanel">
        <div class="card__body">
          <div class="moderationPanel__head">
            <div>
              <h2 class="h2">Selected Appeal</h2>
              <p class="muted"><?= $selectedAppeal ? 'Review the linked enforcement and resolve the appeal.' : 'Select an appeal from the queue to review it.' ?></p>
            </div>
          </div>

          <?php if (!$selectedAppeal): ?>
            <div class="moderationEmptyState">
              <strong>No appeal selected</strong>
              <p class="muted">Choose an appeal from the queue to inspect the enforcement, reason, and final outcome.</p>
            </div>
          <?php else: ?>
            <div class="reviewModal__metaList">
              <div class="reviewModal__metaRow">
                <span class="muted">Enforcement</span>
                <strong><?= trux_e(trux_moderation_resolution_action_label((string)$selectedAppeal['action_key'])) ?></strong>
              </div>
              <div class="reviewModal__metaRow">
                <span class="muted">Appeal status</span>
                <strong><?= trux_e(trux_moderation_label($appealStatuses, (string)$selectedAppeal['status'])) ?></strong>
              </div>
              <div class="reviewModal__metaRow">
                <span class="muted">Assignee</span>
                <strong><?= !empty($selectedAppeal['assigned_staff_username']) ? '@' . trux_e((string)$selectedAppeal['assigned_staff_username']) : 'Unassigned' ?></strong>
              </div>
            </div>

            <?php if ($selectedEnforcement): ?>
              <div class="reviewModal__note">
                <strong>Enforcement details</strong><br>
                Status: <?= trux_e(trux_moderation_label(trux_moderation_user_enforcement_statuses(), (string)$selectedEnforcement['status'])) ?><br>
                <?php if (!empty($selectedEnforcement['reason_summary'])): ?>
                  Reason: <?= trux_e((string)$selectedEnforcement['reason_summary']) ?><br>
                <?php endif; ?>
                <?php if (!empty($selectedEnforcement['ends_at'])): ?>
                  Ends at: <?= trux_e((string)$selectedEnforcement['ends_at']) ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <p><?= nl2br(trux_e((string)$selectedAppeal['submitter_reason'])) ?></p>

            <div class="moderationActions">
              <?php if (!empty($selectedAppeal['user_case_id'])): ?>
                <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/user_review.php?user_id=<?= (int)$selectedAppeal['user_id'] ?>">Open case</a>
              <?php endif; ?>
              <form method="post" action="<?= $buildUrl(['appeal' => (int)$selectedAppeal['id']]) ?>" class="moderationInlineForm">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="action" value="claim_appeal">
                <input type="hidden" name="appeal_id" value="<?= (int)$selectedAppeal['id'] ?>">
                <button class="btn btn--small btn--ghost" type="submit">Assign to me</button>
              </form>
            </div>

            <form class="reviewModal__section" method="post" action="<?= $buildUrl(['appeal' => (int)$selectedAppeal['id']]) ?>">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="assign_appeal">
              <input type="hidden" name="appeal_id" value="<?= (int)$selectedAppeal['id'] ?>">
              <label class="field">
                <span>Assignee</span>
                <select name="assigned_staff_user_id">
                  <option value="">Unassigned</option>
                  <?php foreach ($staffUsers as $staffUser): ?>
                    <option value="<?= (int)$staffUser['id'] ?>" <?= (int)($selectedAppeal['assigned_staff_user_id'] ?? 0) === (int)$staffUser['id'] ? 'selected' : '' ?>>@<?= trux_e((string)$staffUser['username']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button class="btn btn--small" type="submit">Save assignee</button>
            </form>

            <form class="reviewModal__section" method="post" action="<?= $buildUrl(['appeal' => (int)$selectedAppeal['id']]) ?>">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="appeal_id" value="<?= (int)$selectedAppeal['id'] ?>">
              <label class="field">
                <span>Status</span>
                <select name="status">
                  <?php foreach ($appealStatuses as $statusKey => $statusLabel): ?>
                    <option value="<?= trux_e($statusKey) ?>" <?= (string)($selectedAppeal['status'] ?? '') === $statusKey ? 'selected' : '' ?>><?= trux_e($statusLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field">
                <span>Resolution notes</span>
                <textarea name="resolution_notes" rows="5" maxlength="4000" placeholder="Explain why the appeal was upheld or denied."><?= trux_e((string)($selectedAppeal['resolution_notes'] ?? '')) ?></textarea>
              </label>
              <button class="btn btn--small" type="submit">Save status</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    </section>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
