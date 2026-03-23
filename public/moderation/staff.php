<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

trux_require_staff_role('admin');

$moderationActiveKey = 'staff_access';
$manageableRoles = trux_manageable_staff_roles();
$roleFilterOptions = array_merge($manageableRoles, [
    'owner' => trux_staff_role_label('owner'),
]);

$normalizeRoleFilter = static function (string $value) use ($roleFilterOptions): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $normalized = trux_staff_role($value);
    return array_key_exists($normalized, $roleFilterOptions) ? $normalized : '';
};

$normalizeManageableRole = static function (string $value) use ($manageableRoles): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $normalized = trux_staff_role($value);
    return array_key_exists($normalized, $manageableRoles) ? $normalized : '';
};

$parsePositiveInt = static function ($value): int {
    return is_string($value) && preg_match('/^\d+$/', $value) ? (int)$value : 0;
};

$buildStaffPath = static function (array $params = []) use ($normalizeRoleFilter, $normalizeManageableRole): string {
    $query = trim((string)($params['q'] ?? ''));
    $currentRole = $normalizeRoleFilter((string)($params['current_role'] ?? ''));
    $userId = max(0, (int)($params['user_id'] ?? 0));
    $confirmRole = $normalizeManageableRole((string)($params['confirm_role'] ?? ''));

    $queryParams = [];
    if ($query !== '') {
        $queryParams['q'] = $query;
    }
    if ($currentRole !== '') {
        $queryParams['current_role'] = $currentRole;
    }
    if ($userId > 0) {
        $queryParams['user_id'] = (string)$userId;
    }
    if ($confirmRole !== '') {
        $queryParams['confirm_role'] = $confirmRole;
    }

    return '/moderation/staff.php' . ($queryParams ? '?' . http_build_query($queryParams) : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $returnParams = [
        'q' => trim((string)($_POST['return_q'] ?? '')),
        'current_role' => $normalizeRoleFilter((string)($_POST['return_current_role'] ?? '')),
        'user_id' => $parsePositiveInt($_POST['return_user_id'] ?? null),
        'confirm_role' => '',
    ];
    $buildReturnPath = static function (array $overrides = []) use ($buildStaffPath, $returnParams): string {
        return $buildStaffPath(array_merge($returnParams, $overrides));
    };

    if ($action === 'change_role') {
        $targetUserId = $parsePositiveInt($_POST['user_id'] ?? null);
        $nextRole = $normalizeManageableRole((string)($_POST['next_role'] ?? ''));
        $result = trux_assign_staff_role_result($targetUserId, (int)$moderationMe['id'], $nextRole, [
            'confirmation_username' => (string)($_POST['confirm_username'] ?? ''),
        ]);
        $targetUser = is_array($result['target_user'] ?? null)
            ? $result['target_user']
            : ($targetUserId > 0 ? trux_fetch_user_by_id($targetUserId) : null);

        if (!empty($result['ok'])) {
            $targetUsername = trim((string)($targetUser['username'] ?? ''));
            $toRole = trux_staff_role((string)($result['to_role'] ?? $nextRole));
            $message = empty($result['changed'])
                ? 'Staff role unchanged.'
                : ($toRole === 'user'
                    ? '@' . $targetUsername . ' no longer has staff access.'
                    : '@' . $targetUsername . ' is now assigned as ' . trux_staff_role_label($toRole) . '.');
            trux_flash_set('success', $message);
            trux_redirect($buildReturnPath([
                'user_id' => $targetUserId,
                'confirm_role' => '',
            ]));
        }

        trux_flash_set(
            'error',
            trux_moderation_staff_role_error_message(
                (string)($result['error'] ?? 'update_failed'),
                $targetUser,
                $nextRole
            )
        );
        trux_redirect($buildReturnPath([
            'user_id' => $targetUserId > 0 ? $targetUserId : (int)$returnParams['user_id'],
            'confirm_role' => $nextRole,
        ]));
    }

    trux_flash_set('error', 'Unknown staff action.');
    trux_redirect($buildReturnPath());
}

$searchQuery = trim(trux_str_param('q', ''));
$currentRoleFilter = $normalizeRoleFilter(trux_str_param('current_role', ''));
$selectedUserId = max(0, trux_int_param('user_id', 0));
$confirmRole = $normalizeManageableRole(trux_str_param('confirm_role', ''));

$buildStaffUrl = static function (array $overrides = []) use ($buildStaffPath, $searchQuery, $currentRoleFilter, $selectedUserId, $confirmRole): string {
    return TRUX_BASE_URL . $buildStaffPath(array_merge([
        'q' => $searchQuery,
        'current_role' => $currentRoleFilter,
        'user_id' => $selectedUserId,
        'confirm_role' => $confirmRole,
    ], $overrides));
};

$staffRoleBadgeClass = static function (string $role): string {
    return match (trux_staff_role($role)) {
        'owner' => 'is-danger',
        'admin' => 'is-warning',
        'moderator' => 'is-info',
        'developer' => 'is-open',
        default => 'is-muted',
    };
};

$staffList = trux_moderation_search_staff_access_users($searchQuery, $currentRoleFilter, 18);
$selectedUser = $selectedUserId > 0 ? trux_fetch_user_by_id($selectedUserId) : null;
$selectedUserRole = $selectedUser ? trux_staff_role((string)($selectedUser['staff_role'] ?? 'user')) : 'user';
$selectedLockReason = $selectedUser ? trux_moderation_staff_role_target_lock_reason($selectedUser, (int)$moderationMe['id']) : null;
$selectedRoleHistory = $selectedUser ? trux_moderation_fetch_user_staff_role_history((int)$selectedUser['id'], 12) : [];
$selectedAuditExcerpt = $selectedUser ? trux_moderation_fetch_user_audit_excerpt((int)$selectedUser['id'], 10) : [];
$selectedUsername = trim((string)($selectedUser['username'] ?? ''));
$selectedDisplayName = trim((string)($selectedUser['display_name'] ?? ''));
$selectedProfilePath = $selectedUser && $selectedUsername !== '' && !trux_is_report_system_user($selectedUsername)
    ? '/profile.php?u=' . rawurlencode($selectedUsername)
    : '';
$selectedAuditTrailPath = $selectedUser
    ? '/moderation/audit_logs.php?subject_type=user&q=' . urlencode((string)$selectedUser['id'])
    : '/moderation/audit_logs.php';
$selectedCreatedAt = (string)($selectedUser['created_at'] ?? '');
$selectedPendingRole = ($selectedUser && $confirmRole !== '' && $confirmRole !== $selectedUserRole && $selectedLockReason === null)
    ? $confirmRole
    : '';
$selectedPendingRoleIsDemotion = $selectedPendingRole !== ''
    ? trux_moderation_is_staff_role_demotion($selectedUserRole, $selectedPendingRole)
    : false;
$workspaceResetUrl = $buildStaffUrl([
    'user_id' => '',
    'confirm_role' => '',
]);

$renderAuditJson = static function (array $details): string {
    if ($details === []) {
        return '';
    }

    $encoded = json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : '';
};

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
  <h1>Staff Access</h1>
  <p class="muted">Admin-only account search, staff role management, role history, and user audit visibility.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <?php if ($workspaceFlashMessage !== '' && !$selectedUser): ?>
      <div class="flash flash--<?= $workspaceFlashType === 'error' ? 'error' : 'success' ?>"><?= trux_e($workspaceFlashMessage) ?></div>
    <?php endif; ?>

    <section class="moderationPanelGrid moderationPanelGrid--staffAccess">
      <article class="card moderationPanel">
        <div class="card__body">
          <div class="moderationPanel__head">
            <div>
              <h2 class="h2"><?= $searchQuery !== '' ? 'Search Results' : 'Current Staff Roster' ?></h2>
              <p class="muted">
                <?= $searchQuery !== ''
                    ? 'Search usernames, display names, emails, or exact user ids.'
                    : 'Search for any account, or browse current staff when no search is active.' ?>
              </p>
            </div>
          </div>

          <form class="moderationStaffSearchForm" method="get" action="<?= TRUX_BASE_URL ?>/moderation/staff.php">
            <label class="field">
              <span>Search</span>
              <input type="search" name="q" value="<?= trux_e($searchQuery) ?>" placeholder="Username, email, display name, or exact id">
            </label>

            <label class="field">
              <span>Current role</span>
              <select name="current_role">
                <option value="">All roles</option>
                <?php foreach ($roleFilterOptions as $roleKey => $roleLabel): ?>
                  <option value="<?= trux_e($roleKey) ?>" <?= $currentRoleFilter === $roleKey ? 'selected' : '' ?>><?= trux_e($roleLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <div class="moderationFilters__actions">
              <button class="btn btn--small" type="submit">Search</button>
              <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/staff.php">Reset</a>
            </div>
          </form>

          <?php if (!$staffList): ?>
            <div class="moderationEmptyState">
              <strong>No accounts matched</strong>
              <p class="muted">
                <?php if ($searchQuery !== ''): ?>
                  Try a different username, email, display name, or exact user id.
                <?php elseif ($currentRoleFilter === 'user'): ?>
                  Search to find regular users. The default roster view only lists current staff.
                <?php else: ?>
                  No staff accounts matched the current role filter.
                <?php endif; ?>
              </p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($staffList as $user): ?>
                <?php
                $resultUserId = (int)($user['id'] ?? 0);
                $resultRole = trux_staff_role((string)($user['staff_role'] ?? 'user'));
                $resultUsername = trim((string)($user['username'] ?? ''));
                $resultDisplayName = trim((string)($user['display_name'] ?? ''));
                $resultLockReason = trux_moderation_staff_role_target_lock_reason($user, (int)$moderationMe['id']);
                ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong>@<?= trux_e($resultUsername) ?></strong>
                    <div class="moderationBadgeRow">
                      <span class="moderationBadge <?= $staffRoleBadgeClass($resultRole) ?>"><?= trux_e(trux_staff_role_label($resultRole)) ?></span>
                      <?php if ($resultLockReason !== null): ?>
                        <span class="moderationBadge is-muted">Locked</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <?php if ($resultDisplayName !== ''): ?>
                      <span><?= trux_e($resultDisplayName) ?></span>
                    <?php endif; ?>
                    <span><?= trux_e((string)($user['email'] ?? '')) ?></span>
                  </div>
                  <div class="moderationActions">
                    <a class="btn btn--small btn--ghost" href="<?= $buildStaffUrl(['user_id' => $resultUserId, 'confirm_role' => '']) ?>">Open workspace</a>
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
              <h2 class="h2">Workspace Flow</h2>
              <p class="muted">Selecting an account opens the staff workspace in a popup without leaving the moderation route.</p>
            </div>
          </div>

          <div class="moderationList">
            <article class="moderationListItem">
              <div class="moderationListItem__top">
                <strong>Single overlay workspace</strong>
                <span class="moderationBadge is-info">Popup UI</span>
              </div>
              <p class="moderationListItem__summary">Role actions, audit visibility, and role history now live in one shared moderation popup shell.</p>
            </article>
            <article class="moderationListItem">
              <div class="moderationListItem__top">
                <strong>Safer confirmations</strong>
                <span class="moderationBadge is-warning">Guarded</span>
              </div>
              <p class="moderationListItem__summary">Promotions and demotions stay inside the same workspace, with exact-username confirmation for demotions.</p>
            </article>
            <article class="moderationListItem">
              <div class="moderationListItem__top">
                <strong>Shareable URLs</strong>
                <span class="moderationBadge is-open">Route-driven</span>
              </div>
              <p class="moderationListItem__summary">Deep links still use `user_id`, and closing the popup clears the selected workspace from the URL while keeping your filters.</p>
            </article>
          </div>
        </div>
      </article>
    </section>
  </div>

  <?php if ($selectedUser): ?>
    <div
      id="staff-workspace-modal-<?= (int)$selectedUser['id'] ?>"
      class="reviewModal"
      hidden
      data-review-modal="1"
      data-review-modal-autopen="1"
      data-review-modal-reset-url="<?= trux_e($workspaceResetUrl) ?>">
      <div class="reviewModal__backdrop" data-review-modal-close="1"></div>
      <section class="reviewModal__panel reviewModal__panel--workspace" role="dialog" aria-modal="true" aria-labelledby="staffWorkspaceTitle-<?= (int)$selectedUser['id'] ?>">
        <header class="reviewModal__head">
          <div>
            <div class="reviewModal__eyebrow">Staff Access</div>
            <h2 id="staffWorkspaceTitle-<?= (int)$selectedUser['id'] ?>">@<?= trux_e($selectedUsername) ?></h2>
            <div class="reviewModal__headMeta muted">
              <span><?= trux_e($selectedDisplayName !== '' ? $selectedDisplayName : 'No display name set') ?></span>
              <span>#<?= (int)$selectedUser['id'] ?></span>
              <span><?= trux_e(trux_staff_role_label($selectedUserRole)) ?></span>
              <?php if ($selectedCreatedAt !== ''): ?>
                <span title="<?= trux_e(trux_format_exact_time($selectedCreatedAt)) ?>"><?= trux_e(trux_time_ago($selectedCreatedAt)) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="reviewModal__linkRow">
            <?php if ($selectedProfilePath !== ''): ?>
              <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL . $selectedProfilePath ?>">Open profile</a>
            <?php endif; ?>
            <button class="iconBtn reviewModal__close" type="button" aria-label="Close staff workspace" data-review-modal-close="1">
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

          <div class="reviewModal__grid reviewModal__grid--staffWorkspace">
            <section class="reviewModal__card reviewModal__card--span-rows">
              <div class="reviewModal__sectionHead">
                <strong>Account Summary</strong>
                <div class="moderationBadgeRow">
                  <span class="moderationBadge <?= $staffRoleBadgeClass($selectedUserRole) ?>"><?= trux_e(trux_staff_role_label($selectedUserRole)) ?></span>
                  <?php if ($selectedLockReason !== null): ?>
                    <span class="moderationBadge is-muted">Locked target</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="reviewModal__metaList">
                <div class="reviewModal__metaRow">
                  <span class="muted">Email</span>
                  <strong><?= trux_e((string)($selectedUser['email'] ?? '')) ?></strong>
                </div>
                <div class="reviewModal__metaRow">
                  <span class="muted">User ID</span>
                  <strong>#<?= (int)$selectedUser['id'] ?></strong>
                </div>
                <div class="reviewModal__metaRow">
                  <span class="muted">Joined</span>
                  <strong title="<?= trux_e($selectedCreatedAt !== '' ? trux_format_exact_time($selectedCreatedAt) : '') ?>">
                    <?= trux_e($selectedCreatedAt !== '' ? trux_time_ago($selectedCreatedAt) : 'Unknown') ?>
                  </strong>
                </div>
              </div>

              <section class="reviewModal__section">
                <div class="reviewModal__sectionHead">
                  <strong>Role Actions</strong>
                  <span class="muted">One workspace, no nested confirmation popup.</span>
                </div>

                <?php if ($selectedLockReason !== null): ?>
                  <div class="reviewModal__note"><?= trux_e(trux_moderation_staff_role_error_message($selectedLockReason, $selectedUser)) ?></div>
                <?php else: ?>
                  <div class="reviewModal__linkRow">
                    <?php foreach ($manageableRoles as $roleKey => $roleLabel): ?>
                      <?php if ($roleKey === $selectedUserRole): ?>
                        <?php continue; ?>
                      <?php endif; ?>
                      <?php $isDemotion = trux_moderation_is_staff_role_demotion($selectedUserRole, $roleKey); ?>
                      <a
                        class="btn btn--small<?= $selectedPendingRole === $roleKey ? '' : ' btn--ghost' ?>"
                        href="<?= $buildStaffUrl(['user_id' => (int)$selectedUser['id'], 'confirm_role' => $roleKey]) ?>">
                        <?= trux_e($roleKey === 'user' ? 'Remove staff access' : 'Set as ' . $roleLabel) ?>
                        <?= $isDemotion ? ' *' : '' ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </section>

              <?php if ($selectedPendingRole !== ''): ?>
                <section class="reviewModal__section">
                  <div class="reviewModal__sectionHead">
                    <strong>Confirm Role Change</strong>
                    <span class="moderationBadge <?= $selectedPendingRoleIsDemotion ? 'is-warning' : 'is-info' ?>">
                      <?= $selectedPendingRoleIsDemotion ? 'Demotion' : 'Promotion' ?>
                    </span>
                  </div>

                  <div class="reviewModal__metaList">
                    <div class="reviewModal__metaRow">
                      <span class="muted">Current role</span>
                      <strong><?= trux_e(trux_staff_role_label($selectedUserRole)) ?></strong>
                    </div>
                    <div class="reviewModal__metaRow">
                      <span class="muted">New role</span>
                      <strong><?= trux_e(trux_staff_role_label($selectedPendingRole)) ?></strong>
                    </div>
                  </div>

                  <div class="reviewModal__note">
                    <?= trux_e($selectedPendingRoleIsDemotion
                        ? 'Type the exact username below before saving this demotion.'
                        : 'Confirm the new staff access level for this account.') ?>
                  </div>

                  <form class="moderationStaffConfirmForm" method="post" action="<?= TRUX_BASE_URL ?>/moderation/staff.php">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" value="<?= (int)$selectedUser['id'] ?>">
                    <input type="hidden" name="next_role" value="<?= trux_e($selectedPendingRole) ?>">
                    <input type="hidden" name="return_q" value="<?= trux_e($searchQuery) ?>">
                    <input type="hidden" name="return_current_role" value="<?= trux_e($currentRoleFilter) ?>">
                    <input type="hidden" name="return_user_id" value="<?= (int)$selectedUser['id'] ?>">

                    <?php if ($selectedPendingRoleIsDemotion): ?>
                      <label class="field">
                        <span>Type the exact username</span>
                        <input type="text" name="confirm_username" maxlength="32" required placeholder="<?= trux_e($selectedUsername) ?>">
                      </label>
                    <?php endif; ?>

                    <div class="moderationStaffConfirmForm__actions">
                      <a class="btn btn--small btn--ghost" href="<?= $buildStaffUrl(['user_id' => (int)$selectedUser['id'], 'confirm_role' => '']) ?>">Cancel</a>
                      <button class="btn btn--small<?= $selectedPendingRoleIsDemotion ? ' reviewModal__decisionBtn reviewModal__decisionBtn--danger' : '' ?>" type="submit">
                        <?= trux_e($selectedPendingRole === 'user' ? 'Confirm removal' : 'Confirm role change') ?>
                      </button>
                    </div>
                  </form>
                </section>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card reviewModal__card--compact">
              <div class="reviewModal__sectionHead">
                <strong>Role History</strong>
                <span class="muted"><?= count($selectedRoleHistory) ?> change<?= count($selectedRoleHistory) === 1 ? '' : 's' ?></span>
              </div>

              <?php if (!$selectedRoleHistory): ?>
                <div class="reviewModal__empty reviewModal__empty--compact">
                  <p class="muted">This account has no recorded staff role changes.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($selectedRoleHistory as $log): ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong><?= trux_e(trux_moderation_audit_log_summary($log)) ?></strong>
                        <span class="muted" title="<?= trux_e(trux_format_exact_time((string)($log['created_at'] ?? ''))) ?>">
                          <?= trux_e(trux_time_ago((string)($log['created_at'] ?? ''))) ?>
                        </span>
                      </div>
                      <div class="moderationListItem__meta muted">
                        <span>Actor: @<?= trux_e((string)($log['actor_username'] ?? 'unknown')) ?></span>
                        <span><?= trux_e(trux_staff_role_label((string)($log['actor_staff_role'] ?? 'user'))) ?></span>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="reviewModal__card">
              <div class="reviewModal__sectionHead">
                <strong>User Audit Excerpt</strong>
                <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL . $selectedAuditTrailPath ?>">Open full audit logs</a>
              </div>

              <?php if (!$selectedAuditExcerpt): ?>
                <div class="reviewModal__empty">
                  <p class="muted">Audit entries that target this user will appear here.</p>
                </div>
              <?php else: ?>
                <div class="moderationList">
                  <?php foreach ($selectedAuditExcerpt as $log): ?>
                    <?php
                    $details = trux_moderation_json_decode((string)($log['details_json'] ?? ''));
                    $fullDetails = $renderAuditJson($details);
                    ?>
                    <article class="moderationListItem">
                      <div class="moderationListItem__top">
                        <strong><?= trux_e(trux_moderation_action_label((string)($log['action_type'] ?? ''))) ?></strong>
                        <span class="muted" title="<?= trux_e(trux_format_exact_time((string)($log['created_at'] ?? ''))) ?>">
                          <?= trux_e(trux_time_ago((string)($log['created_at'] ?? ''))) ?>
                        </span>
                      </div>
                      <div class="moderationListItem__meta muted">
                        <span>Actor: @<?= trux_e((string)($log['actor_username'] ?? 'unknown')) ?></span>
                        <span><?= trux_e(trux_moderation_audit_log_summary($log)) ?></span>
                      </div>
                      <?php if ($fullDetails !== ''): ?>
                        <pre class="moderationJson"><?= trux_e($fullDetails) ?></pre>
                      <?php endif; ?>
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
