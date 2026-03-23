<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$moderationActiveKey = 'activity';
$suspiciousStatuses = trux_moderation_suspicious_statuses();
$severities = trux_moderation_severity_options();
$ruleLabels = trux_moderation_rule_labels();
$staffUsers = trux_fetch_staff_users('developer');
$currentQuery = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$returnPath = '/moderation/activity.php' . ($currentQuery !== '' ? '?' . $currentQuery : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!trux_can_moderation_write($moderationStaffRole)) {
        trux_flash_set('error', 'Your role is read-only in moderation.');
        trux_redirect($returnPath);
    }

    $eventIdRaw = $_POST['event_id'] ?? null;
    $eventId = is_string($eventIdRaw) && preg_match('/^\d+$/', $eventIdRaw) ? (int)$eventIdRaw : 0;
    $action = trim((string)($_POST['action'] ?? ''));
    $event = $eventId > 0 ? trux_moderation_fetch_suspicious_event_by_id($eventId) : null;

    if (in_array($action, ['mark_reviewed', 'mark_false_positive', 'reopen_event', 'claim_event', 'assign_event', 'open_or_create_case', 'escalate_event'], true) && !$event) {
        trux_flash_set('error', 'Suspicious event not found.');
        trux_redirect($returnPath);
    }

    if ($action === 'mark_reviewed' && $eventId > 0) {
        $ok = trux_moderation_update_suspicious_event_status($eventId, (int)$moderationMe['id'], 'reviewed');
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Suspicious event marked as reviewed.' : 'Could not update suspicious event.');
        trux_redirect($returnPath);
    }

    if ($action === 'mark_false_positive' && $eventId > 0) {
        $ok = trux_moderation_update_suspicious_event_status($eventId, (int)$moderationMe['id'], 'false_positive');
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Suspicious event marked as a false positive.' : 'Could not update suspicious event.');
        trux_redirect($returnPath);
    }

    if ($action === 'reopen_event' && $eventId > 0) {
        $ok = trux_moderation_update_suspicious_event_status($eventId, (int)$moderationMe['id'], 'open');
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Suspicious event reopened.' : 'Could not reopen suspicious event.');
        trux_redirect($returnPath);
    }

    if ($action === 'claim_event' && $eventId > 0) {
        $ok = trux_moderation_assign_suspicious_event($eventId, (int)$moderationMe['id'], (int)$moderationMe['id']);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Suspicious event assigned to you.' : 'Could not claim suspicious event.');
        trux_redirect($returnPath);
    }

    if ($action === 'assign_event' && $eventId > 0) {
        if (!trux_can_moderation_reassign($moderationStaffRole)) {
            trux_flash_set('error', 'Only admin and owner roles can change the event assignee.');
            trux_redirect($returnPath);
        }

        $assignedRaw = $_POST['assigned_staff_user_id'] ?? '';
        $assignedStaffUserId = is_string($assignedRaw) && preg_match('/^\d+$/', $assignedRaw) ? (int)$assignedRaw : null;
        $ok = trux_moderation_assign_suspicious_event($eventId, (int)$moderationMe['id'], $assignedStaffUserId);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Suspicious event assignee updated.' : 'Could not update event assignee.');
        trux_redirect($returnPath);
    }

    if ($action === 'open_or_create_case' && $event !== null) {
        $caseUserId = (int)($event['actor_user_id'] ?? $event['related_user_id'] ?? 0);
        if ($caseUserId <= 0) {
            trux_flash_set('error', 'This signal is not linked to a user account.');
            trux_redirect($returnPath);
        }

        $case = trux_moderation_ensure_user_case($caseUserId, (int)$moderationMe['id'], (int)($event['linked_report_id'] ?? 0));
        if ($case) {
            trux_moderation_reopen_user_case_if_closed($caseUserId, (int)$moderationMe['id'], 'Suspicious activity reopened.');
            trux_redirect('/moderation/user_review.php?user_id=' . $caseUserId);
        }

        trux_flash_set('error', 'Could not open or create the linked user case.');
        trux_redirect($returnPath);
    }

    if ($action === 'escalate_event' && $event !== null) {
        $summary = 'Escalated suspicious event #' . (int)$event['id'] . ': ' . (string)($event['summary'] ?? '');
        $escalation = trux_moderation_create_or_get_escalation('suspicious_event', (int)$event['id'], (int)$moderationMe['id'], $summary, 'admin', 'high');
        if ($escalation) {
            trux_redirect('/moderation/escalations.php?escalation=' . (int)$escalation['id']);
        }
        trux_flash_set('error', 'Could not escalate the suspicious event.');
        trux_redirect($returnPath);
    }

    trux_flash_set('error', 'Unknown moderation action.');
    trux_redirect($returnPath);
}

$filters = [
    'status' => trux_str_param('status', 'open'),
    'severity' => trux_str_param('severity', ''),
    'rule_key' => trux_str_param('rule_key', ''),
    'q' => trux_str_param('q', ''),
];
$page = max(1, trux_int_param('page', 1));
$activityPage = trux_moderation_fetch_suspicious_events($filters, $page, 25);
$events = is_array($activityPage['items'] ?? null) ? $activityPage['items'] : [];
$totalPages = max(1, (int)($activityPage['total_pages'] ?? 1));

$buildActivityUrl = static function (array $overrides = []) use ($filters, $page): string {
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
    return TRUX_BASE_URL . '/moderation/activity.php' . $query;
};
$eventReportUrl = static function (array $event): ?string {
    $reportId = (int)($event['linked_report_id'] ?? 0);
    return $reportId > 0 ? TRUX_BASE_URL . '/moderation/reports.php?review=' . $reportId : null;
};
$eventCaseUrl = static function (array $event): ?string {
    $userId = (int)($event['actor_user_id'] ?? $event['related_user_id'] ?? 0);
    return $userId > 0 ? TRUX_BASE_URL . '/moderation/user_review.php?user_id=' . $userId : null;
};
$currentStaffId = (int)($moderationMe['id'] ?? 0);

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>Suspicious Activity</h1>
  <p class="muted">Open rule signals generated from login, content, message, and report patterns.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <section class="card moderationPanel">
      <div class="card__body">
        <form class="moderationFilters" method="get" action="<?= TRUX_BASE_URL ?>/moderation/activity.php">
          <label class="field">
            <span>Status</span>
            <select name="status">
              <option value="">All</option>
              <?php foreach ($suspiciousStatuses as $statusKey => $statusLabel): ?>
                <option value="<?= trux_e($statusKey) ?>" <?= $filters['status'] === $statusKey ? 'selected' : '' ?>><?= trux_e($statusLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Severity</span>
            <select name="severity">
              <option value="">All</option>
              <?php foreach ($severities as $severityKey => $severityLabel): ?>
                <option value="<?= trux_e($severityKey) ?>" <?= $filters['severity'] === $severityKey ? 'selected' : '' ?>><?= trux_e($severityLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Rule</span>
            <select name="rule_key">
              <option value="">All</option>
              <?php foreach ($ruleLabels as $ruleKey => $ruleLabel): ?>
                <option value="<?= trux_e($ruleKey) ?>" <?= $filters['rule_key'] === $ruleKey ? 'selected' : '' ?>><?= trux_e($ruleLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field moderationFilters__search">
            <span>Search</span>
            <input type="search" name="q" value="<?= trux_e((string)$filters['q']) ?>" placeholder="Actor username or summary">
          </label>

          <div class="moderationFilters__actions">
            <button class="btn btn--small" type="submit">Apply filters</button>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/activity.php">Reset</a>
          </div>
        </form>
      </div>
    </section>

    <section class="card moderationPanel">
      <div class="card__body">
        <div class="moderationPanel__head">
          <div>
            <h2 class="h2">Signals</h2>
            <p class="muted"><?= (int)($activityPage['total'] ?? 0) ?> suspicious event<?= (int)($activityPage['total'] ?? 0) === 1 ? '' : 's' ?> matched.</p>
          </div>
        </div>

        <?php if (!$events): ?>
          <div class="moderationEmptyState">
            <strong>No suspicious events matched</strong>
            <p class="muted">The current filters do not match any suspicious activity records.</p>
          </div>
        <?php else: ?>
          <div class="moderationTableWrap">
            <table class="moderationTable">
              <thead>
                <tr>
                  <th>Signal</th>
                  <th>Actor</th>
                  <th>Evidence</th>
                  <th>Review</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($events as $event): ?>
                  <?php
                  $metadataPreview = trux_moderation_metadata_preview(trux_moderation_json_decode((string)($event['metadata_json'] ?? '')));
                  $linkedReportUrl = $eventReportUrl($event);
                  $linkedCaseUrl = $eventCaseUrl($event);
                  $assignedEventUserId = isset($event['assigned_staff_user_id']) && $event['assigned_staff_user_id'] !== null ? (int)$event['assigned_staff_user_id'] : 0;
                  ?>
                  <tr>
                    <td>
                      <div class="moderationCellTitle"><?= trux_e(trux_moderation_rule_label((string)$event['rule_key'])) ?></div>
                      <div class="moderationBadgeRow">
                        <span class="moderationBadge <?= trux_moderation_severity_badge_class((string)$event['severity']) ?>"><?= trux_e(trux_moderation_label($severities, (string)$event['severity'])) ?></span>
                        <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$event['status']) ?>"><?= trux_e(trux_moderation_label($suspiciousStatuses, (string)$event['status'])) ?></span>
                      </div>
                      <div class="moderationTable__summary"><?= trux_e((string)$event['summary']) ?></div>
                    </td>
                    <td>
                      <?php if (!empty($event['actor_username'])): ?>
                        <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$event['actor_username']) ?>">@<?= trux_e((string)$event['actor_username']) ?></a>
                      <?php else: ?>
                        <span class="muted">Unknown</span>
                      <?php endif; ?>
                      <div class="muted">Score: <?= (int)($event['score'] ?? 0) ?></div>
                      <div class="muted">Assignee: <?= !empty($event['assigned_staff_username']) ? '@' . trux_e((string)$event['assigned_staff_username']) : 'Unassigned' ?></div>
                    </td>
                    <td>
                      <?php if (!$metadataPreview): ?>
                        <span class="muted">No extra evidence</span>
                      <?php else: ?>
                        <div class="moderationKeyValueList">
                          <?php foreach ($metadataPreview as $item): ?>
                            <div class="moderationKeyValue">
                              <span class="muted"><?= trux_e((string)$item['key']) ?></span>
                              <strong><?= trux_e((string)$item['value']) ?></strong>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="moderationReviewMeta">
                        <span title="<?= trux_e(trux_format_exact_time((string)$event['last_detected_at'])) ?>">
                          <?= trux_e(trux_time_ago((string)$event['last_detected_at'])) ?>
                        </span>
                        <?php if (!empty($event['reviewer_username'])): ?>
                          <span class="muted">Reviewed by @<?= trux_e((string)$event['reviewer_username']) ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="moderationActions">
                        <?php if ($linkedReportUrl !== null): ?>
                          <a class="btn btn--small btn--ghost" href="<?= $linkedReportUrl ?>">Open report</a>
                        <?php endif; ?>
                        <?php if ($linkedCaseUrl !== null): ?>
                          <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                            <?= trux_csrf_field() ?>
                            <input type="hidden" name="action" value="open_or_create_case">
                            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                            <button class="btn btn--small btn--ghost" type="submit"><?= $linkedCaseUrl !== null ? 'Open / create case' : 'Create case' ?></button>
                          </form>
                        <?php endif; ?>
                        <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                          <?= trux_csrf_field() ?>
                          <input type="hidden" name="action" value="escalate_event">
                          <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                          <button class="btn btn--small btn--ghost" type="submit">Escalate</button>
                        </form>
                        <?php if (trux_can_moderation_reassign($moderationStaffRole)): ?>
                          <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                            <?= trux_csrf_field() ?>
                            <input type="hidden" name="action" value="assign_event">
                            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                            <select name="assigned_staff_user_id">
                              <option value="">Unassigned</option>
                              <?php foreach ($staffUsers as $staffUser): ?>
                                <option value="<?= (int)$staffUser['id'] ?>" <?= $assignedEventUserId === (int)$staffUser['id'] ? 'selected' : '' ?>>@<?= trux_e((string)$staffUser['username']) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <button class="btn btn--small btn--ghost" type="submit">Save</button>
                          </form>
                        <?php elseif ($assignedEventUserId !== $currentStaffId): ?>
                          <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                            <?= trux_csrf_field() ?>
                            <input type="hidden" name="action" value="claim_event">
                            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                            <button class="btn btn--small btn--ghost" type="submit">Assign to me</button>
                          </form>
                        <?php endif; ?>
                        <?php if (trux_can_moderation_write($moderationStaffRole)): ?>
                          <?php if ((string)$event['status'] !== 'reviewed'): ?>
                            <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                              <?= trux_csrf_field() ?>
                              <input type="hidden" name="action" value="mark_reviewed">
                              <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                              <button class="btn btn--small btn--ghost" type="submit">Mark reviewed</button>
                            </form>
                          <?php endif; ?>
                          <?php if ((string)$event['status'] !== 'false_positive'): ?>
                            <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                              <?= trux_csrf_field() ?>
                              <input type="hidden" name="action" value="mark_false_positive">
                              <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                              <button class="btn btn--small btn--ghost" type="submit">False positive</button>
                            </form>
                          <?php endif; ?>
                          <?php if ((string)$event['status'] !== 'open'): ?>
                            <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                              <?= trux_csrf_field() ?>
                              <input type="hidden" name="action" value="reopen_event">
                              <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                              <button class="btn btn--small btn--ghost" type="submit">Reopen</button>
                            </form>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="moderationCards">
            <?php foreach ($events as $event): ?>
              <?php
              $metadataPreview = trux_moderation_metadata_preview(trux_moderation_json_decode((string)($event['metadata_json'] ?? '')));
              $linkedReportUrl = $eventReportUrl($event);
              $linkedCaseUrl = $eventCaseUrl($event);
              $assignedEventUserId = isset($event['assigned_staff_user_id']) && $event['assigned_staff_user_id'] !== null ? (int)$event['assigned_staff_user_id'] : 0;
              ?>
              <article class="moderationRecordCard">
                <div class="moderationRecordCard__head">
                  <strong><?= trux_e(trux_moderation_rule_label((string)$event['rule_key'])) ?></strong>
                  <div class="moderationBadgeRow">
                    <span class="moderationBadge <?= trux_moderation_severity_badge_class((string)$event['severity']) ?>"><?= trux_e(trux_moderation_label($severities, (string)$event['severity'])) ?></span>
                    <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$event['status']) ?>"><?= trux_e(trux_moderation_label($suspiciousStatuses, (string)$event['status'])) ?></span>
                  </div>
                </div>
                <div class="moderationRecordCard__meta muted">
                  <span>Actor: <?= !empty($event['actor_username']) ? '@' . trux_e((string)$event['actor_username']) : 'Unknown' ?></span>
                  <span>Score: <?= (int)($event['score'] ?? 0) ?></span>
                  <span>Assignee: <?= !empty($event['assigned_staff_username']) ? '@' . trux_e((string)$event['assigned_staff_username']) : 'Unassigned' ?></span>
                </div>
                <p class="moderationRecordCard__summary"><?= trux_e((string)$event['summary']) ?></p>
                <?php if ($metadataPreview): ?>
                  <div class="moderationKeyValueList">
                    <?php foreach ($metadataPreview as $item): ?>
                      <div class="moderationKeyValue">
                        <span class="muted"><?= trux_e((string)$item['key']) ?></span>
                        <strong><?= trux_e((string)$item['value']) ?></strong>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="moderationActions">
                  <?php if ($linkedReportUrl !== null): ?>
                    <a class="btn btn--small btn--ghost" href="<?= $linkedReportUrl ?>">Open report</a>
                  <?php endif; ?>
                  <?php if ($linkedCaseUrl !== null): ?>
                    <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                      <?= trux_csrf_field() ?>
                      <input type="hidden" name="action" value="open_or_create_case">
                      <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                      <button class="btn btn--small btn--ghost" type="submit">Open / create case</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="escalate_event">
                    <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                    <button class="btn btn--small btn--ghost" type="submit">Escalate</button>
                  </form>
                  <?php if (trux_can_moderation_reassign($moderationStaffRole)): ?>
                    <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                      <?= trux_csrf_field() ?>
                      <input type="hidden" name="action" value="assign_event">
                      <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                      <select name="assigned_staff_user_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($staffUsers as $staffUser): ?>
                          <option value="<?= (int)$staffUser['id'] ?>" <?= $assignedEventUserId === (int)$staffUser['id'] ? 'selected' : '' ?>>@<?= trux_e((string)$staffUser['username']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn--small btn--ghost" type="submit">Save</button>
                    </form>
                  <?php elseif ($assignedEventUserId !== $currentStaffId): ?>
                    <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                      <?= trux_csrf_field() ?>
                      <input type="hidden" name="action" value="claim_event">
                      <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                      <button class="btn btn--small btn--ghost" type="submit">Assign to me</button>
                    </form>
                  <?php endif; ?>
                  <?php if (trux_can_moderation_write($moderationStaffRole)): ?>
                    <?php if ((string)$event['status'] !== 'reviewed'): ?>
                      <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="action" value="mark_reviewed">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <button class="btn btn--small btn--ghost" type="submit">Mark reviewed</button>
                      </form>
                    <?php endif; ?>
                    <?php if ((string)$event['status'] !== 'false_positive'): ?>
                      <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="action" value="mark_false_positive">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <button class="btn btn--small btn--ghost" type="submit">False positive</button>
                      </form>
                    <?php endif; ?>
                    <?php if ((string)$event['status'] !== 'open'): ?>
                      <form method="post" action="<?= $buildActivityUrl() ?>" class="moderationInlineForm">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="action" value="reopen_event">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <button class="btn btn--small btn--ghost" type="submit">Reopen</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="moderationPagination">
            <?php if ($page > 1): ?>
              <a class="btn btn--small btn--ghost" href="<?= $buildActivityUrl(['page' => $page - 1]) ?>">Previous</a>
            <?php endif; ?>
            <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
              <a class="btn btn--small btn--ghost" href="<?= $buildActivityUrl(['page' => $page + 1]) ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
