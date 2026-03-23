<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
trux_require_staff_role('admin');

$moderationActiveKey = 'escalations';
$staffUsers = trux_fetch_staff_users('admin');
$statuses = trux_moderation_escalation_statuses();
$queueRoles = trux_moderation_escalation_queue_roles();
$priorities = trux_moderation_user_case_priorities();
$currentQuery = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$returnPath = '/moderation/escalations.php' . ($currentQuery !== '' ? '?' . $currentQuery : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!trux_can_moderation_write($moderationStaffRole)) {
        trux_flash_set('error', 'Your role is read-only in moderation.');
        trux_redirect($returnPath);
    }

    $escalationIdRaw = $_POST['escalation_id'] ?? null;
    $escalationId = is_string($escalationIdRaw) && preg_match('/^\d+$/', $escalationIdRaw) ? (int)$escalationIdRaw : 0;
    $action = trim((string)($_POST['action'] ?? ''));

    if ($escalationId <= 0) {
        trux_flash_set('error', 'Invalid escalation.');
        trux_redirect($returnPath);
    }

    if ($action === 'claim_escalation') {
        $ok = trux_moderation_assign_escalation($escalationId, (int)$moderationMe['id'], (int)$moderationMe['id']);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Escalation assigned to you.' : 'Could not claim escalation.');
        trux_redirect($returnPath);
    }

    if ($action === 'assign_escalation') {
        $assignedRaw = $_POST['assigned_staff_user_id'] ?? '';
        $assignedStaffUserId = is_string($assignedRaw) && preg_match('/^\d+$/', $assignedRaw) ? (int)$assignedRaw : null;
        $ok = trux_moderation_assign_escalation($escalationId, (int)$moderationMe['id'], $assignedStaffUserId);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Escalation assignee updated.' : 'Could not update the escalation assignee.');
        trux_redirect($returnPath);
    }

    if ($action === 'update_status') {
        $status = trim((string)($_POST['status'] ?? ''));
        $resolutionNotes = trim((string)($_POST['resolution_notes'] ?? ''));
        $ok = trux_moderation_update_escalation_status($escalationId, (int)$moderationMe['id'], $status, $resolutionNotes);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Escalation updated.' : 'Could not update the escalation.');
        trux_redirect($returnPath);
    }

    trux_flash_set('error', 'Unknown moderation action.');
    trux_redirect($returnPath);
}

$filters = [
    'status' => trux_str_param('status', 'all'),
    'queue_role' => trux_str_param('queue_role', 'all'),
    'assignee' => trux_str_param('assignee', 'all'),
];
$page = max(1, trux_int_param('page', 1));
$selectedEscalationId = max(0, trux_int_param('escalation', 0));
$escalationPage = trux_moderation_fetch_escalations($filters, $page, 25);
$escalations = is_array($escalationPage['items'] ?? null) ? $escalationPage['items'] : [];
$selectedEscalation = $selectedEscalationId > 0 ? trux_moderation_fetch_escalation_by_id($selectedEscalationId) : null;
$totalPages = max(1, (int)($escalationPage['total_pages'] ?? 1));

$buildUrl = static function (array $overrides = []) use ($filters, $page): string {
    $params = array_merge($filters, ['page' => $page], $overrides);
    foreach ($params as $key => $value) {
        if (!is_string($key) || $value === null || $value === '' || $value === 'all' || ($key === 'page' && (int)$value <= 1)) {
            unset($params[$key]);
        }
    }
    return TRUX_BASE_URL . '/moderation/escalations.php' . ($params ? '?' . http_build_query($params) : '');
};

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>Escalations</h1>
  <p class="muted">Admin and owner queue for reports, user cases, suspicious events, and appeals that need higher-level review.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <section class="card moderationPanel">
      <div class="card__body">
        <form class="moderationFilters" method="get" action="<?= TRUX_BASE_URL ?>/moderation/escalations.php">
          <label class="field">
            <span>Status</span>
            <select name="status">
              <option value="all">All</option>
              <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                <option value="<?= trux_e($statusKey) ?>" <?= $filters['status'] === $statusKey ? 'selected' : '' ?>><?= trux_e($statusLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">
            <span>Queue</span>
            <select name="queue_role">
              <option value="all">All</option>
              <?php foreach ($queueRoles as $queueRoleKey => $queueRoleLabel): ?>
                <option value="<?= trux_e($queueRoleKey) ?>" <?= $filters['queue_role'] === $queueRoleKey ? 'selected' : '' ?>><?= trux_e($queueRoleLabel) ?></option>
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
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/escalations.php">Reset</a>
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
              <p class="muted"><?= (int)($escalationPage['total'] ?? 0) ?> escalation<?= (int)($escalationPage['total'] ?? 0) === 1 ? '' : 's' ?> matched.</p>
            </div>
          </div>

          <?php if (!$escalations): ?>
            <div class="moderationEmptyState">
              <strong>No escalations matched</strong>
              <p class="muted">The current filters do not match any open escalation records.</p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($escalations as $item): ?>
                <?php $subjectUrl = trux_moderation_subject_url((string)$item['subject_type'], (int)$item['subject_id']); ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong>#<?= (int)$item['id'] ?> · <?= trux_e((string)$item['summary']) ?></strong>
                    <div class="moderationBadgeRow">
                      <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$item['status']) ?>"><?= trux_e(trux_moderation_label($statuses, (string)$item['status'])) ?></span>
                      <span class="moderationBadge <?= trux_moderation_priority_badge_class((string)$item['priority']) ?>"><?= trux_e(trux_moderation_label($priorities, (string)$item['priority'])) ?></span>
                    </div>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <span><?= trux_e(trux_moderation_subject_label((string)$item['subject_type'])) ?> #<?= (int)$item['subject_id'] ?></span>
                    <span>Queue: <?= trux_e(trux_moderation_label($queueRoles, (string)$item['queue_role'])) ?></span>
                    <span>Assignee: <?= !empty($item['assigned_staff_username']) ? '@' . trux_e((string)$item['assigned_staff_username']) : 'Unassigned' ?></span>
                  </div>
                  <div class="moderationActions">
                    <?php if ($subjectUrl !== null): ?>
                      <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL . $subjectUrl ?>">Open subject</a>
                    <?php endif; ?>
                    <a class="btn btn--small btn--ghost" href="<?= $buildUrl(['escalation' => (int)$item['id']]) ?>">Inspect</a>
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
              <h2 class="h2">Selected Escalation</h2>
              <p class="muted"><?= $selectedEscalation ? 'Review and resolve the selected escalation.' : 'Select an escalation from the queue to review it.' ?></p>
            </div>
          </div>

          <?php if (!$selectedEscalation): ?>
            <div class="moderationEmptyState">
              <strong>No escalation selected</strong>
              <p class="muted">Choose an escalation from the queue to inspect its details and update status.</p>
            </div>
          <?php else: ?>
            <?php $subjectUrl = trux_moderation_subject_url((string)$selectedEscalation['subject_type'], (int)$selectedEscalation['subject_id']); ?>
            <div class="reviewModal__metaList">
              <div class="reviewModal__metaRow">
                <span class="muted">Subject</span>
                <strong><?= trux_e(trux_moderation_subject_label((string)$selectedEscalation['subject_type'])) ?> #<?= (int)$selectedEscalation['subject_id'] ?></strong>
              </div>
              <div class="reviewModal__metaRow">
                <span class="muted">Queue</span>
                <strong><?= trux_e(trux_moderation_label($queueRoles, (string)$selectedEscalation['queue_role'])) ?></strong>
              </div>
              <div class="reviewModal__metaRow">
                <span class="muted">Assignee</span>
                <strong><?= !empty($selectedEscalation['assigned_staff_username']) ? '@' . trux_e((string)$selectedEscalation['assigned_staff_username']) : 'Unassigned' ?></strong>
              </div>
            </div>

            <p><?= trux_e((string)$selectedEscalation['summary']) ?></p>
            <?php if (!empty($selectedEscalation['resolution_notes'])): ?>
              <div class="reviewModal__note"><?= nl2br(trux_e((string)$selectedEscalation['resolution_notes'])) ?></div>
            <?php endif; ?>

            <div class="moderationActions">
              <?php if ($subjectUrl !== null): ?>
                <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL . $subjectUrl ?>">Open subject</a>
              <?php endif; ?>
              <form method="post" action="<?= $buildUrl(['escalation' => (int)$selectedEscalation['id']]) ?>" class="moderationInlineForm">
                <?= trux_csrf_field() ?>
                <input type="hidden" name="action" value="claim_escalation">
                <input type="hidden" name="escalation_id" value="<?= (int)$selectedEscalation['id'] ?>">
                <button class="btn btn--small btn--ghost" type="submit">Assign to me</button>
              </form>
            </div>

            <form class="reviewModal__section" method="post" action="<?= $buildUrl(['escalation' => (int)$selectedEscalation['id']]) ?>">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="assign_escalation">
              <input type="hidden" name="escalation_id" value="<?= (int)$selectedEscalation['id'] ?>">
              <label class="field">
                <span>Assignee</span>
                <select name="assigned_staff_user_id">
                  <option value="">Unassigned</option>
                  <?php foreach ($staffUsers as $staffUser): ?>
                    <option value="<?= (int)$staffUser['id'] ?>" <?= (int)($selectedEscalation['assigned_staff_user_id'] ?? 0) === (int)$staffUser['id'] ? 'selected' : '' ?>>@<?= trux_e((string)$staffUser['username']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button class="btn btn--small" type="submit">Save assignee</button>
            </form>

            <form class="reviewModal__section" method="post" action="<?= $buildUrl(['escalation' => (int)$selectedEscalation['id']]) ?>">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="escalation_id" value="<?= (int)$selectedEscalation['id'] ?>">
              <label class="field">
                <span>Status</span>
                <select name="status">
                  <?php foreach ($statuses as $statusKey => $statusLabel): ?>
                    <option value="<?= trux_e($statusKey) ?>" <?= (string)($selectedEscalation['status'] ?? '') === $statusKey ? 'selected' : '' ?>><?= trux_e($statusLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="field">
                <span>Resolution notes</span>
                <textarea name="resolution_notes" rows="5" maxlength="4000" placeholder="Document what was decided and any follow-up needed."><?= trux_e((string)($selectedEscalation['resolution_notes'] ?? '')) ?></textarea>
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
