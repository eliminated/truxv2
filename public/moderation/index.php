<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'moderation-dashboard';
$moderationActiveKey = 'dashboard';
$dashboard = trux_moderation_fetch_dashboard_data();
$metrics = is_array($dashboard['metrics'] ?? null) ? $dashboard['metrics'] : [];
$openReports = is_array($dashboard['open_reports'] ?? null) ? $dashboard['open_reports'] : [];
$openSuspicious = is_array($dashboard['open_suspicious'] ?? null) ? $dashboard['open_suspicious'] : [];
$openEscalations = is_array($dashboard['open_escalations'] ?? null) ? $dashboard['open_escalations'] : [];
$openAppeals = is_array($dashboard['open_appeals'] ?? null) ? $dashboard['open_appeals'] : [];
$recentAuditLogs = is_array($dashboard['recent_audit_logs'] ?? null) ? $dashboard['recent_audit_logs'] : [];
$badgeCounts = is_array($dashboard['badge_counts'] ?? null) ? $dashboard['badge_counts'] : [];

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>Moderation</h1>
  <p class="muted">Staff-only workspace for reports, suspicious activity, and audit review.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <section class="moderationMetricGrid">
      <?php foreach ($metrics as $metric): ?>
        <article class="card moderationMetricCard">
          <div class="card__body">
            <div class="moderationMetricCard__label muted"><?= trux_e((string)($metric['label'] ?? 'Metric')) ?></div>
            <div class="moderationMetricCard__value"><?= (int)($metric['value'] ?? 0) ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="moderationPanelGrid">
      <article class="card moderationPanel">
        <div class="card__body">
          <div class="moderationPanel__head">
            <div>
              <h2 class="h2">Needs Attention</h2>
              <p class="muted">Newest open reports.</p>
            </div>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/reports.php?status=open">Open queue</a>
          </div>

          <?php if (!$openReports): ?>
            <div class="moderationEmptyState">
              <strong>No open reports</strong>
              <p class="muted">The reports queue is currently empty.</p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($openReports as $report): ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong><?= trux_e(trim((string)($report['target_label'] ?? '')) !== ''
                        ? (string)$report['target_label']
                        : trux_moderation_target_label((string)$report['target_type'], (int)$report['target_id'])) ?></strong>
                    <div class="moderationBadgeRow">
                      <span class="moderationBadge <?= trux_moderation_priority_badge_class((string)$report['priority']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_report_priorities(), (string)$report['priority'])) ?></span>
                      <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$report['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_report_statuses(), (string)$report['status'])) ?></span>
                    </div>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <span>Reason: <?= trux_e(trux_moderation_reason_label((string)$report['reason_key'])) ?></span>
                    <span>Reporter: @<?= trux_e((string)$report['reporter_username']) ?></span>
                  </div>
                  <?php if (!empty($report['details'])): ?>
                    <p class="moderationListItem__summary"><?= trux_e((string)$report['details']) ?></p>
                  <?php endif; ?>
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
              <h2 class="h2">Suspicious Activity</h2>
              <p class="muted">Newest open signals.</p>
            </div>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/activity.php?status=open">Review activity</a>
          </div>

          <?php if (!$openSuspicious): ?>
            <div class="moderationEmptyState">
              <strong>No open suspicious events</strong>
              <p class="muted">Activity rules have not raised any open flags.</p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($openSuspicious as $event): ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong><?= trux_e(trux_moderation_rule_label((string)$event['rule_key'])) ?></strong>
                    <div class="moderationBadgeRow">
                      <span class="moderationBadge <?= trux_moderation_severity_badge_class((string)$event['severity']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_severity_options(), (string)$event['severity'])) ?></span>
                      <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$event['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_suspicious_statuses(), (string)$event['status'])) ?></span>
                    </div>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <span>
                      Actor:
                      <?php if (!empty($event['actor_username'])): ?>
                        <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$event['actor_username']) ?>">@<?= trux_e((string)$event['actor_username']) ?></a>
                      <?php else: ?>
                        Unknown
                      <?php endif; ?>
                    </span>
                    <span>Score: <?= (int)($event['score'] ?? 0) ?></span>
                  </div>
                  <p class="moderationListItem__summary"><?= trux_e((string)$event['summary']) ?></p>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </article>
    </section>

    <section class="moderationPanelGrid">
      <article class="card moderationPanel">
        <div class="card__body">
          <div class="moderationPanel__head">
            <div>
              <h2 class="h2">Recent Audit Log</h2>
              <p class="muted">Latest staff actions recorded.</p>
            </div>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/audit_logs.php">View all logs</a>
          </div>

          <?php if (!$recentAuditLogs): ?>
            <div class="moderationEmptyState">
              <strong>No audit entries yet</strong>
              <p class="muted">Staff changes will appear here once moderation actions occur.</p>
            </div>
          <?php else: ?>
            <div class="moderationList">
              <?php foreach ($recentAuditLogs as $log): ?>
                <article class="moderationListItem">
                  <div class="moderationListItem__top">
                    <strong><?= trux_e(trux_moderation_action_label((string)$log['action_type'])) ?></strong>
                    <span class="muted" title="<?= trux_e(trux_format_exact_time((string)$log['created_at'])) ?>">
                      <?= trux_e(trux_time_ago((string)$log['created_at'])) ?>
                    </span>
                  </div>
                  <div class="moderationListItem__meta muted">
                    <span>Actor: @<?= trux_e((string)$log['actor_username']) ?></span>
                    <span><?= trux_e(trux_moderation_subject_label((string)$log['subject_type'])) ?> #<?= (int)($log['subject_id'] ?? 0) ?></span>
                  </div>
                  <p class="moderationListItem__summary"><?= trux_e(trux_moderation_audit_log_summary($log)) ?></p>
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
              <h2 class="h2">Queues</h2>
              <p class="muted">Current handoff volume across moderation modules.</p>
            </div>
          </div>

          <div class="moderationList">
            <article class="moderationListItem">
              <div class="moderationListItem__top">
                <strong>Reports</strong>
                <span class="moderationBadge is-info"><?= (int)($badgeCounts['reports'] ?? 0) ?></span>
              </div>
              <p class="moderationListItem__summary">Active reports assigned to you or waiting to be claimed.</p>
            </article>
            <article class="moderationListItem">
              <div class="moderationListItem__top">
                <strong>User Review</strong>
                <span class="moderationBadge is-info"><?= (int)($badgeCounts['user_review'] ?? 0) ?></span>
              </div>
              <p class="moderationListItem__summary">Open or escalated user cases in your queue.</p>
            </article>
            <article class="moderationListItem">
              <div class="moderationListItem__top">
                <strong>Escalations</strong>
                <span class="moderationBadge is-info"><?= (int)($badgeCounts['escalations'] ?? 0) ?></span>
              </div>
              <p class="moderationListItem__summary">Admin-owner escalations assigned to you or still unclaimed.</p>
            </article>
            <article class="moderationListItem">
              <div class="moderationListItem__top">
                <strong>Appeals</strong>
                <span class="moderationBadge is-info"><?= (int)($badgeCounts['appeals'] ?? 0) ?></span>
              </div>
              <p class="moderationListItem__summary">Account-action appeals waiting for decision.</p>
            </article>
          </div>
        </div>
      </article>
    </section>

    <?php if ($openEscalations || $openAppeals): ?>
      <section class="moderationPanelGrid">
        <article class="card moderationPanel">
          <div class="card__body">
            <div class="moderationPanel__head">
              <div>
                <h2 class="h2">Open Escalations</h2>
                <p class="muted">Highest-level review queue.</p>
              </div>
              <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/escalations.php">Open queue</a>
            </div>
            <?php if (!$openEscalations): ?>
              <div class="moderationEmptyState">
                <strong>No open escalations</strong>
                <p class="muted">The escalation queue is currently clear.</p>
              </div>
            <?php else: ?>
              <div class="moderationList">
                <?php foreach ($openEscalations as $escalation): ?>
                  <article class="moderationListItem">
                    <div class="moderationListItem__top">
                      <strong>#<?= (int)$escalation['id'] ?> · <?= trux_e((string)$escalation['summary']) ?></strong>
                      <span class="moderationBadge <?= trux_moderation_priority_badge_class((string)$escalation['priority']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_user_case_priorities(), (string)$escalation['priority'])) ?></span>
                    </div>
                    <p class="moderationListItem__summary"><?= trux_e(trux_moderation_subject_label((string)$escalation['subject_type'])) ?> #<?= (int)$escalation['subject_id'] ?></p>
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
                <h2 class="h2">Open Appeals</h2>
                <p class="muted">Recent account-action appeals.</p>
              </div>
              <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/appeals.php">Open queue</a>
            </div>
            <?php if (!$openAppeals): ?>
              <div class="moderationEmptyState">
                <strong>No open appeals</strong>
                <p class="muted">No active appeals are waiting for review.</p>
              </div>
            <?php else: ?>
              <div class="moderationList">
                <?php foreach ($openAppeals as $appeal): ?>
                  <article class="moderationListItem">
                    <div class="moderationListItem__top">
                      <strong>#<?= (int)$appeal['id'] ?> · <?= trux_e(trux_moderation_resolution_action_label((string)$appeal['action_key'])) ?></strong>
                      <span class="moderationBadge <?= trux_moderation_status_badge_class((string)$appeal['status']) ?>"><?= trux_e(trux_moderation_label(trux_moderation_appeal_statuses(), (string)$appeal['status'])) ?></span>
                    </div>
                    <p class="moderationListItem__summary"><?= trux_e(trux_moderation_trimmed_excerpt((string)$appeal['submitter_reason'], 160)) ?></p>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
      </section>
    <?php endif; ?>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
