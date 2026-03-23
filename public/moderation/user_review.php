<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$moderationActiveKey = 'user_review';
$userCaseStatuses = trux_moderation_user_case_statuses();
$userCasePriorities = trux_moderation_user_case_priorities();
$userCaseResolutionActions = trux_moderation_user_case_resolution_actions();
$staffUsers = trux_fetch_staff_users('developer');
$searchQuery = trim(trux_str_param('q', ''));
$selectedUserId = max(0, trux_int_param('user_id', 0));

$buildUserReviewPath = static function (array $overrides = []) use ($searchQuery, $selectedUserId): string {
    $params = array_merge([
        'q' => $searchQuery,
        'user_id' => $selectedUserId > 0 ? (string)$selectedUserId : '',
    ], $overrides);

    foreach ($params as $key => $value) {
        if (!is_string($key) || $value === null || $value === '') {
            unset($params[$key]);
        }
    }

    return '/moderation/user_review.php' . ($params ? '?' . http_build_query($params) : '');
};

$buildUserReviewUrl = static function (array $overrides = []) use ($buildUserReviewPath): string {
    return TRUX_BASE_URL . $buildUserReviewPath($overrides);
};

$currentQuery = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$defaultReturnPath = '/moderation/user_review.php' . ($currentQuery !== '' ? '?' . $currentQuery : '');

$resolveReturnPath = static function (string $fallbackPath): string {
    $raw = trim((string)($_POST['redirect'] ?? ''));
    if ($raw === '') {
        return $fallbackPath;
    }

    if (str_starts_with($raw, TRUX_BASE_URL)) {
        $raw = str_replace(TRUX_BASE_URL, '', $raw);
    }

    $clean = trux_moderation_clean_path($raw);
    return $clean !== null && str_starts_with($clean, '/') ? $clean : $fallbackPath;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnPath = $resolveReturnPath($defaultReturnPath);

    if (!trux_can_moderation_write($moderationStaffRole)) {
        trux_flash_set('error', 'Your role is read-only in moderation.');
        trux_redirect($returnPath);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $userIdRaw = $_POST['user_id'] ?? null;
    $userId = is_string($userIdRaw) && preg_match('/^\d+$/', $userIdRaw) ? (int)$userIdRaw : 0;

    if ($userId <= 0) {
        trux_flash_set('error', 'Invalid user.');
        trux_redirect($returnPath);
    }

    if ($action === 'save_summary') {
        $summary = (string)($_POST['summary'] ?? '');
        $ok = trux_moderation_update_user_case_summary($userId, (int)$moderationMe['id'], $summary);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Case summary updated.' : 'Could not update the case summary.');
        trux_redirect($returnPath);
    }

    if ($action === 'save_watchlist' || $action === 'toggle_watchlist_inline') {
        $watchlisted = !empty($_POST['watchlisted']);
        $watchReason = (string)($_POST['watch_reason'] ?? '');
        $ok = trux_moderation_update_user_case_watchlist($userId, (int)$moderationMe['id'], $watchlisted, $watchReason);
        $message = $watchlisted ? 'User added to the watchlist.' : 'User removed from the watchlist.';
        trux_flash_set($ok ? 'success' : 'error', $ok ? $message : 'Could not update the watchlist state.');
        trux_redirect($returnPath);
    }

    if ($action === 'assign_case') {
        if (!trux_can_moderation_reassign($moderationStaffRole)) {
            trux_flash_set('error', 'Only admin and owner roles can change the case assignee.');
            trux_redirect($returnPath);
        }

        $assignedRaw = $_POST['assigned_staff_user_id'] ?? '';
        $assignedStaffUserId = is_string($assignedRaw) && preg_match('/^\d+$/', $assignedRaw)
            ? (int)$assignedRaw
            : null;
        $ok = trux_moderation_assign_user_case($userId, (int)$moderationMe['id'], $assignedStaffUserId);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Case assignee updated.' : 'Could not update the case assignee.');
        trux_redirect($returnPath);
    }

    if ($action === 'claim_case') {
        $ok = trux_moderation_assign_user_case($userId, (int)$moderationMe['id'], (int)$moderationMe['id']);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'You are now assigned to this case.' : 'Could not claim the case.');
        trux_redirect($returnPath);
    }

    if ($action === 'add_note') {
        $body = (string)($_POST['note_body'] ?? '');
        $linkedReportRaw = $_POST['linked_report_id'] ?? '';
        $linkedReportId = is_string($linkedReportRaw) && preg_match('/^\d+$/', $linkedReportRaw) ? (int)$linkedReportRaw : null;
        $ok = trux_moderation_add_user_case_note($userId, (int)$moderationMe['id'], $body, $linkedReportId);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Case note added.' : 'Could not add the case note.');
        trux_redirect($returnPath);
    }

    if ($action === 'save_workflow') {
        $status = trim((string)($_POST['case_status'] ?? 'open'));
        $priority = trim((string)($_POST['case_priority'] ?? 'normal'));
        $resolutionActionKey = trim((string)($_POST['resolution_action_key'] ?? ''));
        $resolutionNotes = (string)($_POST['resolution_notes'] ?? '');
        $ok = trux_moderation_update_user_case_workflow($userId, (int)$moderationMe['id'], $status, $priority, $resolutionActionKey !== '' ? $resolutionActionKey : null, $resolutionNotes);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Case workflow updated.' : 'Could not update the case workflow.');
        trux_redirect($returnPath);
    }

    if ($action === 'reopen_case') {
        $ok = trux_moderation_reopen_user_case_if_closed($userId, (int)$moderationMe['id'], 'Reopened from user review workspace.');
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Case reopened.' : 'Could not reopen the case.');
        trux_redirect($returnPath);
    }

    if ($action === 'escalate_case') {
        $case = trux_moderation_ensure_user_case($userId, (int)$moderationMe['id']);
        $summary = trim((string)($_POST['escalation_summary'] ?? ''));
        $priority = trim((string)($_POST['escalation_priority'] ?? 'high'));
        if (!$case || (int)($case['id'] ?? 0) <= 0) {
            trux_flash_set('error', 'Could not open the case for escalation.');
            trux_redirect($returnPath);
        }
        if ($summary === '') {
            $targetUser = trux_fetch_user_by_id($userId);
            $summary = 'Escalated from user review for @' . (string)($targetUser['username'] ?? 'user') . '.';
        }

        $escalation = trux_moderation_create_or_get_escalation('user_case', (int)$case['id'], (int)$moderationMe['id'], $summary, 'admin', $priority);
        trux_flash_set($escalation ? 'success' : 'error', $escalation ? 'Case escalated to the admin queue.' : 'Could not escalate the case.');
        trux_redirect($returnPath);
    }

    trux_flash_set('error', 'Unknown moderation action.');
    trux_redirect($returnPath);
}

$watchlistedCases = trux_moderation_fetch_watchlisted_user_cases(12);
$searchResults = trux_moderation_search_case_users($searchQuery, 12);
$selectedUser = $selectedUserId > 0 ? trux_fetch_user_by_id($selectedUserId) : null;
$selectedCase = $selectedUser ? trux_moderation_fetch_user_case_by_user_id($selectedUserId) : null;
$selectedCaseNotes = $selectedCase ? trux_moderation_fetch_user_case_notes((int)$selectedCase['id']) : [];
$selectedCaseEnforcements = $selectedCase ? trux_moderation_fetch_user_case_enforcements((int)$selectedCase['id']) : [];
$selectedCaseEscalations = $selectedCase ? trux_moderation_fetch_user_case_escalations((int)$selectedCase['id']) : [];
$selectedCaseAppeals = $selectedCase ? trux_moderation_fetch_user_case_appeals((int)$selectedCase['id']) : [];
$relatedReports = $selectedUser ? trux_moderation_fetch_user_case_related_reports($selectedUserId, 12) : [];
$relatedSuspicious = $selectedUser ? trux_moderation_fetch_user_case_related_suspicious_events($selectedUserId, 8) : [];
$selectedAssigneeId = isset($selectedCase['assigned_staff_user_id']) && $selectedCase['assigned_staff_user_id'] !== null
    ? (int)$selectedCase['assigned_staff_user_id']
    : 0;
$selectedUsername = trim((string)($selectedUser['username'] ?? ''));
$selectedDisplayName = trim((string)($selectedUser['display_name'] ?? ''));
$selectedProfilePath = $selectedUser && $selectedUsername !== ''
    ? '/profile.php?u=' . rawurlencode($selectedUsername)
    : '/profile.php';
$selectedCreatedAt = (string)($selectedUser['created_at'] ?? '');
$workspaceResetUrl = $buildUserReviewUrl([
    'user_id' => '',
]);
$workspaceActionPath = $buildUserReviewPath([
    'user_id' => $selectedUserId,
]);

$error = trux_flash_get('error');
$success = trux_flash_get('success');
$errorMessage = is_string($error) ? trim($error) : '';
$successMessage = is_string($success) ? trim($success) : '';
$renderPageFlash = false;
$workspaceFlashType = $errorMessage !== '' ? 'error' : ($successMessage !== '' ? 'success' : '');
$workspaceFlashMessage = $errorMessage !== '' ? $errorMessage : $successMessage;

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>User Review</h1>
  <p class="muted">Staff-only user cases, watchlist state, account notes, and linked moderation context.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <?php if ($workspaceFlashMessage !== '' && !$selectedUser): ?>
      <div class="flash flash--<?= $workspaceFlashType === 'error' ? 'error' : 'success' ?>"><?= trux_e($workspaceFlashMessage) ?></div>
    <?php endif; ?>

    <section class="moderationPanelGrid">
      <article class="card moderationPanel">
        <div class="card__body">
          <div class="moderationPanel__head">
            <div>
              <h2 class="h2">Find A User</h2>
              <p class="muted">Search usernames, display names, or locations to open a moderation case workspace.</p>
            </div>
          </div>

          <form class="moderationFilters" method="get" action="<?= TRUX_BASE_URL ?>/moderation/user_review.php">
            <label class="field moderationFilters__search">
              <span>Search</span>
              <input type="search" name="q" value="<?= trux_e($searchQuery) ?>" placeholder="Search users or case owners">
            </label>
            <div class="moderationFilters__actions">
              <button class="btn btn--small" type="submit">Search</button>
              <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/user_review.php">Reset</a>
            </div>
          </form>

          <?php if (!$searchResults): ?>
            <div class="moderationEmptyState">
              <strong>No users matched</strong>
              <p class="muted">Try a different username or open a user directly from a report or profile shortcut.</p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($searchResults as $result): ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong>@<?= trux_e((string)$result['username']) ?></strong>
                    <div class="moderationBadgeRow">
                      <?php if (!empty($result['watchlisted'])): ?>
                        <span class="moderationBadge is-warning">Watchlisted</span>
                      <?php endif; ?>
                      <?php if (!empty($result['user_case_id'])): ?>
                        <span class="moderationBadge is-info">Case</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <span><?= trux_e((string)($result['display_name'] ?? 'No display name')) ?></span>
                  </div>
                  <div class="moderationActions">
                    <a class="btn btn--small btn--ghost" href="<?= $buildUserReviewUrl(['user_id' => (int)$result['id']]) ?>">Open workspace</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </article>

      <article class="card moderationPanel">
        <div class="card__body">
          <div class="moderationPanel__head">
            <div>
              <h2 class="h2">Watchlist</h2>
              <p class="muted">Accounts currently flagged for follow-up.</p>
            </div>
          </div>

          <?php if (!$watchlistedCases): ?>
            <div class="moderationEmptyState">
              <strong>No watchlisted users</strong>
              <p class="muted">Add users from the profile shortcut or this workspace when they need ongoing attention.</p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($watchlistedCases as $case): ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong>@<?= trux_e((string)$case['target_username']) ?></strong>
                    <span class="moderationBadge is-warning">Watchlisted</span>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <span><?= !empty($case['assigned_staff_username']) ? 'Assignee: @' . trux_e((string)$case['assigned_staff_username']) : 'Unassigned' ?></span>
                  </div>
                  <?php if (!empty($case['watch_reason'])): ?>
                    <p class="moderationListItem__summary"><?= trux_e((string)$case['watch_reason']) ?></p>
                  <?php endif; ?>
                  <div class="moderationActions">
                    <a class="btn btn--small btn--ghost" href="<?= $buildUserReviewUrl(['user_id' => (int)$case['user_id']]) ?>">Open workspace</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </article>
    </section>

    <section class="card moderationPanel">
      <div class="card__body">
        <div class="moderationPanel__head">
          <div>
            <h2 class="h2">Workspace Flow</h2>
            <p class="muted">Selecting a user keeps the list context in place and opens the full case workspace in a popup.</p>
          </div>
        </div>
        <div class="moderationEmptyState">
          <strong>Select a user</strong>
          <p class="muted">Choose a user from search results, the watchlist, a profile shortcut, or a report review link.</p>
        </div>
      </div>
    </section>
  </div>

  <?php if ($selectedUser): ?>
    <div
      id="user-review-modal-<?= (int)$selectedUser['id'] ?>"
      class="reviewModal"
      hidden
      data-review-modal="1"
      data-review-modal-autopen="1"
      data-review-modal-reset-url="<?= trux_e($workspaceResetUrl) ?>">
      <div class="reviewModal__backdrop" data-review-modal-close="1"></div>
      <section class="reviewModal__panel reviewModal__panel--workspace" role="dialog" aria-modal="true" aria-labelledby="userReviewTitle-<?= (int)$selectedUser['id'] ?>">
        <header class="reviewModal__head">
          <div>
            <div class="reviewModal__eyebrow">User Review</div>
            <h2 id="userReviewTitle-<?= (int)$selectedUser['id'] ?>">@<?= trux_e($selectedUsername) ?></h2>
            <div class="reviewModal__headMeta muted">
              <span><?= trux_e($selectedDisplayName !== '' ? $selectedDisplayName : 'No display name set') ?></span>
              <span><?= $selectedCase ? trux_moderation_label($userCaseStatuses, (string)($selectedCase['status'] ?? 'open')) : 'No case yet' ?></span>
              <span><?= !empty($selectedCase['watchlisted']) ? 'Watchlisted' : 'Not watchlisted' ?></span>
              <?php if ($selectedCreatedAt !== ''): ?>
                <span title="<?= trux_e(trux_format_exact_time($selectedCreatedAt)) ?>"><?= trux_e(trux_time_ago($selectedCreatedAt)) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="reviewModal__linkRow">
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL . $selectedProfilePath ?>">Open public profile</a>
            <button class="iconBtn reviewModal__close" type="button" aria-label="Close user review" data-review-modal-close="1">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
        </header>

        <div class="reviewModal__body">
          <?php if ($workspaceFlashMessage !== ''): ?>
            <div class="flash flash--<?= $workspaceFlashType === 'error' ? 'error' : 'success' ?> reviewModal__flash"><?= trux_e($workspaceFlashMessage) ?></div>
          <?php endif; ?>

          <div class="reviewModal__grid reviewModal__grid--userReviewWorkspace">
            <section class="reviewModal__card reviewModal__card--compact">
              <div class="reviewModal__sectionHead">
                <strong>Case Snapshot</strong>
                <div class="moderationBadgeRow">
                  <span class="moderationBadge <?= !empty($selectedCase['watchlisted']) ? 'is-warning' : 'is-muted' ?>">
                    <?= !empty($selectedCase['watchlisted']) ? 'Watchlisted' : 'Not watchlisted' ?>
                  </span>
                  <?php if ($selectedCase): ?>
                    <span class="moderationBadge <?= trux_moderation_status_badge_class((string)($selectedCase['status'] ?? 'open')) ?>"><?= trux_e(trux_moderation_label($userCaseStatuses, (string)($selectedCase['status'] ?? 'open'))) ?></span>
                    <span class="moderationBadge <?= trux_moderation_priority_badge_class((string)($selectedCase['priority'] ?? 'normal')) ?>"><?= trux_e(trux_moderation_label($userCasePriorities, (string)($selectedCase['priority'] ?? 'normal'))) ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="reviewModal__metaList">
                <div class="reviewModal__metaRow">
                  <span class="muted">Case status</span>
                  <strong><?= $selectedCase ? trux_e(trux_moderation_label($userCaseStatuses, (string)($selectedCase['status'] ?? 'open'))) : 'No case yet' ?></strong>
                </div>
                <div class="reviewModal__metaRow">
                  <span class="muted">Priority</span>
                  <strong><?= $selectedCase ? trux_e(trux_moderation_label($userCasePriorities, (string)($selectedCase['priority'] ?? 'normal'))) : 'Normal' ?></strong>
                </div>
                <div class="reviewModal__metaRow">
                  <span class="muted">Assignee</span>
                  <strong><?= !empty($selectedCase['assigned_staff_username']) ? '@' . trux_e((string)$selectedCase['assigned_staff_username']) : 'Unassigned' ?></strong>
                </div>
                <?php if (!empty($selectedCase['resolution_action_key'])): ?>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Resolution</span>
                    <strong><?= trux_e(trux_moderation_label($userCaseResolutionActions, (string)$selectedCase['resolution_action_key'])) ?></strong>
                  </div>
                <?php endif; ?>
                <?php if (!empty($selectedCase['current_escalation_id'])): ?>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Current escalation</span>
                    <strong>#<?= (int)$selectedCase['current_escalation_id'] ?></strong>
                  </div>
                <?php endif; ?>
                <?php if (!empty($selectedCase['closed_at'])): ?>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Closed</span>
                    <strong title="<?= trux_e(trux_format_exact_time((string)$selectedCase['closed_at'])) ?>"><?= trux_e(trux_time_ago((string)$selectedCase['closed_at'])) ?></strong>
                  </div>
                <?php endif; ?>
                <div class="reviewModal__metaRow">
                  <span class="muted">Joined</span>
                  <strong title="<?= trux_e($selectedCreatedAt !== '' ? trux_format_exact_time($selectedCreatedAt) : '') ?>">
                    <?= trux_e($selectedCreatedAt !== '' ? trux_time_ago($selectedCreatedAt) : 'Unknown') ?>
                  </strong>
                </div>
              </div>

              <?php if (trux_can_moderation_write($moderationStaffRole)): ?>
                <form class="reviewModal__section" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="action" value="save_watchlist">
                  <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                  <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                  <label class="field">
                    <span>Watchlist</span>
                    <select name="watchlisted">
                      <option value="0" <?= empty($selectedCase['watchlisted']) ? 'selected' : '' ?>>Off</option>
                      <option value="1" <?= !empty($selectedCase['watchlisted']) ? 'selected' : '' ?>>On</option>
                    </select>
                  </label>
                  <label class="field">
                    <span>Watchlist reason</span>
                    <input type="text" name="watch_reason" maxlength="280" value="<?= trux_e((string)($selectedCase['watch_reason'] ?? '')) ?>" placeholder="Optional reason for ongoing attention">
                  </label>
                  <div class="reviewModal__linkRow">
                    <button class="btn btn--small" type="submit">Save watchlist</button>
                  </div>
                </form>

                <form class="reviewModal__section" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="action" value="save_summary">
                  <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                  <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                  <label class="field">
                    <span>Case summary</span>
                    <textarea name="summary" rows="5" maxlength="4000" placeholder="Summarize the account context, risks, and follow-up plan."><?= trux_e((string)($selectedCase['summary'] ?? '')) ?></textarea>
                  </label>
                  <div class="reviewModal__linkRow">
                    <button class="btn btn--small" type="submit">Save summary</button>
                  </div>
                </form>

                <form class="reviewModal__section" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="action" value="save_workflow">
                  <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                  <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                  <label class="field">
                    <span>Case status</span>
                    <select name="case_status">
                      <?php foreach ($userCaseStatuses as $statusKey => $statusLabel): ?>
                        <option value="<?= trux_e($statusKey) ?>" <?= (string)($selectedCase['status'] ?? 'open') === $statusKey ? 'selected' : '' ?>><?= trux_e($statusLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label class="field">
                    <span>Priority</span>
                    <select name="case_priority">
                      <?php foreach ($userCasePriorities as $priorityKey => $priorityLabel): ?>
                        <option value="<?= trux_e($priorityKey) ?>" <?= (string)($selectedCase['priority'] ?? 'normal') === $priorityKey ? 'selected' : '' ?>><?= trux_e($priorityLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label class="field">
                    <span>Resolution</span>
                    <select name="resolution_action_key">
                      <option value="">No resolution selected</option>
                      <?php foreach ($userCaseResolutionActions as $actionKey => $actionLabel): ?>
                        <option value="<?= trux_e($actionKey) ?>" <?= (string)($selectedCase['resolution_action_key'] ?? '') === $actionKey ? 'selected' : '' ?>><?= trux_e($actionLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label class="field">
                    <span>Resolution notes</span>
                    <textarea name="resolution_notes" rows="4" maxlength="4000" placeholder="Describe the current disposition, next checks, or closure reasoning."><?= trux_e((string)($selectedCase['resolution_notes'] ?? '')) ?></textarea>
                  </label>
                  <div class="reviewModal__linkRow">
                    <button class="btn btn--small" type="submit">Save workflow</button>
                  </div>
                </form>

                <?php if (trux_can_moderation_reassign($moderationStaffRole)): ?>
                  <form class="reviewModal__section" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="assign_case">
                    <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                    <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                    <label class="field">
                      <span>Assignee</span>
                      <select name="assigned_staff_user_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($staffUsers as $staffUser): ?>
                          <option value="<?= (int)$staffUser['id'] ?>" <?= $selectedAssigneeId === (int)$staffUser['id'] ? 'selected' : '' ?>>
                            @<?= trux_e((string)$staffUser['username']) ?> (<?= trux_e(ucfirst((string)$staffUser['staff_role'])) ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <div class="reviewModal__linkRow">
                      <button class="btn btn--small" type="submit">Save assignee</button>
                    </div>
                  </form>
                <?php elseif ($selectedAssigneeId !== (int)$moderationMe['id']): ?>
                  <form class="reviewModal__inlineAction" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="claim_case">
                    <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                    <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                    <button class="btn btn--small btn--ghost" type="submit">Assign case to me</button>
                  </form>
                <?php endif; ?>

                <form class="reviewModal__section" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="action" value="escalate_case">
                  <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                  <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                  <label class="field">
                    <span>Escalation summary</span>
                    <textarea name="escalation_summary" rows="3" maxlength="280" placeholder="Explain what needs admin review and why."></textarea>
                  </label>
                  <label class="field">
                    <span>Escalation priority</span>
                    <select name="escalation_priority">
                      <?php foreach ($userCasePriorities as $priorityKey => $priorityLabel): ?>
                        <option value="<?= trux_e($priorityKey) ?>" <?= $priorityKey === 'high' ? 'selected' : '' ?>><?= trux_e($priorityLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <div class="reviewModal__linkRow">
                    <button class="btn btn--small btn--ghost" type="submit">Escalate case</button>
                  </div>
                </form>
                <?php if ((string)($selectedCase['status'] ?? '') === 'closed'): ?>
                  <form class="reviewModal__inlineAction" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="reopen_case">
                    <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                    <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                    <button class="btn btn--small btn--ghost" type="submit">Reopen case</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <?php if (!empty($selectedCase['watch_reason'])): ?>
                  <div class="reviewModal__note"><?= trux_e((string)$selectedCase['watch_reason']) ?></div>
                <?php endif; ?>
                <?php if (!empty($selectedCase['summary'])): ?>
                  <div class="reviewModal__note"><?= trux_e((string)$selectedCase['summary']) ?></div>
                <?php endif; ?>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card">
              <div class="reviewModal__sectionHead">
                <strong>Case Notes</strong>
                <span class="muted"><?= count($selectedCaseNotes) ?> note<?= count($selectedCaseNotes) === 1 ? '' : 's' ?></span>
              </div>

              <?php if (!$selectedCaseNotes): ?>
                <div class="reviewModal__empty reviewModal__empty--compact">
                  <p class="muted">Notes added here become the shared internal timeline for this account.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($selectedCaseNotes as $note): ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong>@<?= trux_e((string)$note['author_username']) ?></strong>
                        <span class="muted" title="<?= trux_e(trux_format_exact_time((string)$note['created_at'])) ?>"><?= trux_e(trux_time_ago((string)$note['created_at'])) ?></span>
                      </div>
                      <?php if (!empty($note['linked_report_id'])): ?>
                        <div class="moderationListItem__meta muted">
                          <span>Linked report #<?= (int)$note['linked_report_id'] ?></span>
                        </div>
                      <?php endif; ?>
                      <p class="moderationListItem__summary"><?= nl2br(trux_e((string)$note['body'])) ?></p>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (trux_can_moderation_write($moderationStaffRole)): ?>
                <form class="reviewModal__section" method="post" action="<?= TRUX_BASE_URL . $workspaceActionPath ?>">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="action" value="add_note">
                  <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                  <input type="hidden" name="redirect" value="<?= trux_e($workspaceActionPath) ?>">
                  <label class="field">
                    <span>Linked report</span>
                    <select name="linked_report_id">
                      <option value="">None</option>
                      <?php foreach ($relatedReports as $report): ?>
                        <option value="<?= (int)$report['id'] ?>">#<?= (int)$report['id'] ?> - <?= trux_e((string)$report['target_label']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label class="field">
                    <span>Note</span>
                    <textarea name="note_body" rows="5" maxlength="1000" required placeholder="Add an internal note for the moderation team..."></textarea>
                  </label>
                  <div class="reviewModal__linkRow">
                    <button class="btn btn--small" type="submit">Add note</button>
                  </div>
                </form>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card reviewModal__card--compact">
              <div class="reviewModal__sectionHead">
                <strong>Enforcements</strong>
                <span class="muted"><?= count($selectedCaseEnforcements) ?> action<?= count($selectedCaseEnforcements) === 1 ? '' : 's' ?></span>
              </div>

              <?php if (!$selectedCaseEnforcements): ?>
                <div class="reviewModal__empty reviewModal__empty--compact">
                  <p class="muted">No account-level enforcement history is linked to this case yet.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($selectedCaseEnforcements as $enforcement): ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong><?= trux_e(trux_moderation_resolution_action_label((string)$enforcement['action_key'])) ?></strong>
                        <div class="moderationBadgeRow">
                          <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$enforcement['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_user_enforcement_statuses(), (string)$enforcement['status'])) ?></span>
                        </div>
                      </div>
                      <div class="moderationListItem__meta muted">
                        <span>Created by @<?= trux_e((string)$enforcement['created_by_staff_username']) ?></span>
                        <?php if (!empty($enforcement['ends_at'])): ?>
                          <span>Ends <?= trux_e((string)$enforcement['ends_at']) ?></span>
                        <?php endif; ?>
                      </div>
                      <?php if (!empty($enforcement['reason_summary'])): ?>
                        <p class="moderationListItem__summary"><?= trux_e((string)$enforcement['reason_summary']) ?></p>
                      <?php endif; ?>
                      <?php if ($appealPath = trux_moderation_public_appeal_url($enforcement)): ?>
                        <div class="reviewModal__linkRow">
                          <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL . $appealPath ?>">Open appeal link</a>
                        </div>
                      <?php endif; ?>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card reviewModal__card--compact">
              <div class="reviewModal__sectionHead">
                <strong>Escalations</strong>
                <span class="muted"><?= count($selectedCaseEscalations) ?> linked</span>
              </div>

              <?php if (!$selectedCaseEscalations): ?>
                <div class="reviewModal__empty reviewModal__empty--compact">
                  <p class="muted">No escalations are linked to this case.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($selectedCaseEscalations as $escalation): ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong>#<?= (int)$escalation['id'] ?></strong>
                        <div class="moderationBadgeRow">
                          <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$escalation['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_escalation_statuses(), (string)$escalation['status'])) ?></span>
                          <span class="moderationBadge <?= trux_moderation_priority_badge_class((string)$escalation['priority']) ?>"><?= trux_e(trux_moderation_label($userCasePriorities, (string)$escalation['priority'])) ?></span>
                        </div>
                      </div>
                      <p class="moderationListItem__summary"><?= trux_e((string)$escalation['summary']) ?></p>
                      <div class="reviewModal__linkRow">
                        <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/escalations.php?escalation=<?= (int)$escalation['id'] ?>">Open escalation</a>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card reviewModal__card--compact">
              <div class="reviewModal__sectionHead">
                <strong>Appeals</strong>
                <span class="muted"><?= count($selectedCaseAppeals) ?> linked</span>
              </div>

              <?php if (!$selectedCaseAppeals): ?>
                <div class="reviewModal__empty reviewModal__empty--compact">
                  <p class="muted">No appeals are linked to this case.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($selectedCaseAppeals as $appeal): ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong>#<?= (int)$appeal['id'] ?></strong>
                        <div class="moderationBadgeRow">
                          <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$appeal['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_appeal_statuses(), (string)$appeal['status'])) ?></span>
                        </div>
                      </div>
                      <p class="moderationListItem__summary"><?= trux_e(trux_moderation_trimmed_excerpt((string)$appeal['submitter_reason'], 240)) ?></p>
                      <div class="reviewModal__linkRow">
                        <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/appeals.php?appeal=<?= (int)$appeal['id'] ?>">Open appeal</a>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card reviewModal__card--compact">
              <div class="reviewModal__sectionHead">
                <strong>Related Reports</strong>
                <span class="muted"><?= count($relatedReports) ?> match<?= count($relatedReports) === 1 ? '' : 'es' ?></span>
              </div>

              <?php if (!$relatedReports): ?>
                <div class="reviewModal__empty reviewModal__empty--compact">
                  <p class="muted">Reports against this user will appear here.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($relatedReports as $report): ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong><?= trux_e((string)$report['target_label']) ?></strong>
                        <div class="moderationBadgeRow">
                          <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$report['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_report_statuses(), (string)$report['status'])) ?></span>
                        </div>
                      </div>
                      <div class="moderationListItem__meta muted">
                        <span>Reason: <?= trux_e(trux_moderation_reason_label((string)$report['reason_key'])) ?></span>
                        <span>Reporter: @<?= trux_e((string)$report['reporter_username']) ?></span>
                      </div>
                      <div class="reviewModal__linkRow">
                        <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/reports.php?review=<?= (int)$report['id'] ?>">Open report</a>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card reviewModal__card--compact">
              <div class="reviewModal__sectionHead">
                <strong>Related Suspicious Activity</strong>
                <span class="muted"><?= count($relatedSuspicious) ?> signal<?= count($relatedSuspicious) === 1 ? '' : 's' ?></span>
              </div>

              <?php if (!$relatedSuspicious): ?>
                <div class="reviewModal__empty reviewModal__empty--compact">
                  <p class="muted">Automated signals for this account will appear here.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($relatedSuspicious as $event): ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong><?= trux_e(trux_moderation_rule_label((string)$event['rule_key'])) ?></strong>
                        <div class="moderationBadgeRow">
                          <span class="moderationBadge <?= trux_moderation_severity_badge_class((string)$event['severity']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_severity_options(), (string)$event['severity'])) ?></span>
                          <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$event['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_suspicious_statuses(), (string)$event['status'])) ?></span>
                        </div>
                      </div>
                      <p class="moderationListItem__summary"><?= trux_e((string)$event['summary']) ?></p>
                      <div class="reviewModal__linkRow">
                        <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/activity.php?q=<?= urlencode((string)$event['id']) ?>">Open signal</a>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>
          </div>
        </div>
      </section>
    </div>
  <?php endif; ?>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
