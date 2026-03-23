<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$moderationActiveKey = 'audit_logs';
$auditActions = trux_moderation_audit_action_labels();
$subjectTypes = trux_moderation_subject_type_labels();
$staffUsers = trux_fetch_staff_users('developer');
$canViewFullAudit = trux_can_view_full_moderation_audit($moderationStaffRole);

$filters = [
    'action_type' => trux_str_param('action_type', ''),
    'subject_type' => trux_str_param('subject_type', ''),
    'actor' => trux_str_param('actor', ''),
    'q' => trux_str_param('q', ''),
];
$page = max(1, trux_int_param('page', 1));
$auditPage = trux_moderation_fetch_audit_logs($filters, $page, 30);
$logs = is_array($auditPage['items'] ?? null) ? $auditPage['items'] : [];
$totalPages = max(1, (int)($auditPage['total_pages'] ?? 1));

$buildAuditUrl = static function (array $overrides = []) use ($filters, $page): string {
    $params = array_merge($filters, ['page' => $page], $overrides);
    foreach ($params as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if ($value === null || $value === '' || ($key === 'page' && (int)$value <= 1)) {
            unset($params[$key]);
        }
    }

    $query = $params ? '?' . http_build_query($params) : '';
    return TRUX_BASE_URL . '/moderation/audit_logs.php' . $query;
};

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>Audit Logs</h1>
  <p class="muted">Immutable moderation history for report updates, assignments, and suspicious-event review.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <section class="card moderationPanel">
      <div class="card__body">
        <form class="moderationFilters moderationFilters--audit" method="get" action="<?= TRUX_BASE_URL ?>/moderation/audit_logs.php">
          <label class="field">
            <span>Action</span>
            <select name="action_type">
              <option value="">All</option>
              <?php foreach ($auditActions as $actionKey => $actionLabel): ?>
                <option value="<?= trux_e($actionKey) ?>" <?= $filters['action_type'] === $actionKey ? 'selected' : '' ?>><?= trux_e($actionLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Subject</span>
            <select name="subject_type">
              <option value="">All</option>
              <?php foreach ($subjectTypes as $subjectKey => $subjectLabel): ?>
                <option value="<?= trux_e($subjectKey) ?>" <?= $filters['subject_type'] === $subjectKey ? 'selected' : '' ?>><?= trux_e($subjectLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Actor</span>
            <select name="actor">
              <option value="">All</option>
              <?php foreach ($staffUsers as $staffUser): ?>
                <option value="<?= (int)$staffUser['id'] ?>" <?= (string)$filters['actor'] === (string)$staffUser['id'] ? 'selected' : '' ?>>
                  @<?= trux_e((string)$staffUser['username']) ?> (<?= trux_e(ucfirst((string)$staffUser['staff_role'])) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field moderationFilters__search">
            <span>Search</span>
            <input type="search" name="q" value="<?= trux_e((string)$filters['q']) ?>" placeholder="Actor, action, subject id">
          </label>

          <div class="moderationFilters__actions">
            <button class="btn btn--small" type="submit">Apply filters</button>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/audit_logs.php">Reset</a>
          </div>
        </form>
      </div>
    </section>

    <section class="card moderationPanel">
      <div class="card__body">
        <div class="moderationPanel__head">
          <div>
            <h2 class="h2">Staff History</h2>
            <p class="muted"><?= (int)($auditPage['total'] ?? 0) ?> audit entr<?= (int)($auditPage['total'] ?? 0) === 1 ? 'y' : 'ies' ?> matched.</p>
          </div>
        </div>

        <?php if (!$logs): ?>
          <div class="moderationEmptyState">
            <strong>No audit logs matched</strong>
            <p class="muted">Staff actions will appear here after moderation changes are made.</p>
          </div>
        <?php else: ?>
          <div class="moderationTableWrap">
            <table class="moderationTable">
              <thead>
                <tr>
                  <th>Action</th>
                  <th>Actor</th>
                  <th>Subject</th>
                  <th>Details</th>
                  <th>When</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <?php
                  $details = trux_moderation_json_decode((string)($log['details_json'] ?? ''));
                  $fullDetails = $details !== []
                      ? json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                      : '';
                  ?>
                  <tr>
                    <td>
                      <div class="moderationCellTitle"><?= trux_e(trux_moderation_action_label((string)$log['action_type'])) ?></div>
                      <div class="muted"><?= trux_e(trux_moderation_audit_log_summary($log)) ?></div>
                    </td>
                    <td>
                      <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$log['actor_username']) ?>">@<?= trux_e((string)$log['actor_username']) ?></a>
                      <div class="muted"><?= trux_e(ucfirst(trux_staff_role((string)($log['actor_staff_role'] ?? 'user')))) ?></div>
                    </td>
                    <td>
                      <div><?= trux_e(trux_moderation_subject_label((string)$log['subject_type'])) ?></div>
                      <div class="muted">#<?= (int)($log['subject_id'] ?? 0) ?></div>
                    </td>
                    <td>
                      <?php if ($canViewFullAudit && $fullDetails !== false && $fullDetails !== ''): ?>
                        <pre class="moderationJson"><?= trux_e((string)$fullDetails) ?></pre>
                      <?php else: ?>
                        <span class="muted"><?= trux_e(trux_moderation_audit_log_summary($log)) ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span title="<?= trux_e(trux_format_exact_time((string)$log['created_at'])) ?>">
                        <?= trux_e(trux_time_ago((string)$log['created_at'])) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="moderationCards">
            <?php foreach ($logs as $log): ?>
              <?php
              $details = trux_moderation_json_decode((string)($log['details_json'] ?? ''));
              $fullDetails = $details !== []
                  ? json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                  : '';
              ?>
              <article class="moderationRecordCard">
                <div class="moderationRecordCard__head">
                  <strong><?= trux_e(trux_moderation_action_label((string)$log['action_type'])) ?></strong>
                  <span class="muted" title="<?= trux_e(trux_format_exact_time((string)$log['created_at'])) ?>">
                    <?= trux_e(trux_time_ago((string)$log['created_at'])) ?>
                  </span>
                </div>
                <div class="moderationRecordCard__meta muted">
                  <span>Actor: @<?= trux_e((string)$log['actor_username']) ?></span>
                  <span><?= trux_e(trux_moderation_subject_label((string)$log['subject_type'])) ?> #<?= (int)($log['subject_id'] ?? 0) ?></span>
                </div>
                <?php if ($canViewFullAudit && $fullDetails !== false && $fullDetails !== ''): ?>
                  <pre class="moderationJson"><?= trux_e((string)$fullDetails) ?></pre>
                <?php else: ?>
                  <p class="moderationRecordCard__summary"><?= trux_e(trux_moderation_audit_log_summary($log)) ?></p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="moderationPagination">
            <?php if ($page > 1): ?>
              <a class="btn btn--small btn--ghost" href="<?= $buildAuditUrl(['page' => $page - 1]) ?>">Previous</a>
            <?php endif; ?>
            <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
              <a class="btn btn--small btn--ghost" href="<?= $buildAuditUrl(['page' => $page + 1]) ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
