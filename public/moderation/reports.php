<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$moderationActiveKey = 'reports';
$reportStatuses = trux_moderation_report_statuses();
$reportPriorities = trux_moderation_report_priorities();
$reportReasons = trux_moderation_report_reason_options();
$reportTargetTypes = trux_moderation_report_target_types();
$reportVoteOptions = trux_moderation_report_vote_options();
$userEnforcementActions = trux_moderation_user_enforcement_actions();
$staffUsers = trux_fetch_staff_users('developer');
$activeReviewId = max(0, trux_int_param('review', 0));
$currentQuery = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$returnPath = '/moderation/reports.php' . ($currentQuery !== '' ? '?' . $currentQuery : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!trux_can_moderation_write($moderationStaffRole)) {
        trux_flash_set('error', 'Your role is read-only in moderation.');
        trux_redirect($returnPath);
    }

    $reportIdRaw = $_POST['report_id'] ?? null;
    $reportId = is_string($reportIdRaw) && preg_match('/^\d+$/', $reportIdRaw) ? (int)$reportIdRaw : 0;
    $action = trim((string)($_POST['action'] ?? ''));

    if ($reportId <= 0) {
        trux_flash_set('error', 'Invalid report.');
        trux_redirect($returnPath);
    }

    $report = trux_moderation_fetch_report_by_id($reportId);
    if (!$report) {
        trux_flash_set('error', 'Report not found.');
        trux_redirect($returnPath);
    }

    $reportIsArchived = trux_moderation_is_report_archived_status((string)($report['status'] ?? ''));
    $actorIsOwner = trux_is_owner_staff_role((string)($moderationMe['staff_role'] ?? 'user'));

    if ($action === 'reopen_report') {
        if (!$reportIsArchived) {
            trux_flash_set('error', 'This report is already active.');
            trux_redirect($returnPath);
        }
        if (!$actorIsOwner) {
            trux_flash_set('error', 'Only the owner can reopen archived reports.');
            trux_redirect($returnPath);
        }

        $ok = trux_moderation_update_report_status($reportId, (int)$moderationMe['id'], 'open');
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Report returned to the active queue.' : 'Could not reopen the archived report.');
        trux_redirect($returnPath);
    }

    if ($reportIsArchived) {
        trux_flash_set('error', $actorIsOwner
            ? 'Archived reports must be reopened before they can be reviewed again.'
            : 'This report is archived. Only the owner can reopen it.');
        trux_redirect($returnPath);
    }

    if ($action === 'claim_head') {
        $ok = trux_moderation_assign_report($reportId, (int)$moderationMe['id'], (int)$moderationMe['id']);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'You are now the head reviewer.' : 'Could not claim head review.');
        trux_redirect($returnPath);
    }

    if ($action === 'set_head') {
        if (!trux_can_moderation_reassign($moderationStaffRole)) {
            trux_flash_set('error', 'Only admin and owner roles can change the head reviewer.');
            trux_redirect($returnPath);
        }

        $assignedRaw = $_POST['assigned_staff_user_id'] ?? '';
        $assignedStaffUserId = is_string($assignedRaw) && preg_match('/^\d+$/', $assignedRaw)
            ? (int)$assignedRaw
            : null;
        $ok = trux_moderation_assign_report($reportId, (int)$moderationMe['id'], $assignedStaffUserId);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Head reviewer updated.' : 'Could not update the head reviewer.');
        trux_redirect($returnPath);
    }

    if ($action === 'save_review_status') {
        $nextStatus = trim((string)($_POST['status'] ?? ''));
        if (!in_array($nextStatus, ['open', 'investigating'], true)) {
            trux_flash_set('error', 'Use the final decision controls to resolve or dismiss a report.');
            trux_redirect($returnPath);
        }

        $ok = trux_moderation_update_report_status($reportId, (int)$moderationMe['id'], $nextStatus);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Review status updated.' : 'Could not update review status.');
        trux_redirect($returnPath);
    }

    if ($action === 'add_discussion') {
        $discussionBody = (string)($_POST['discussion_body'] ?? '');
        $ok = trux_moderation_add_report_discussion_message($reportId, (int)$moderationMe['id'], $discussionBody);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Discussion line added.' : 'Could not add the discussion line.');
        trux_redirect($returnPath);
    }

    if ($action === 'cast_vote') {
        $voteValue = trim((string)($_POST['vote_value'] ?? ''));
        $ok = trux_moderation_set_report_vote($reportId, (int)$moderationMe['id'], $voteValue);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Vote recorded.' : 'Could not record the vote.');
        trux_redirect($returnPath);
    }

    if ($action === 'resolve_report') {
        if (!trux_moderation_can_finalize_report($report, $moderationMe)) {
            trux_flash_set('error', 'Only the head reviewer can finalize this report.');
            trux_redirect($returnPath);
        }

        $decision = [
            'content_action' => trim((string)($_POST['content_action'] ?? 'none')),
            'open_case' => !empty($_POST['open_case']),
            'enforcement_action' => trim((string)($_POST['enforcement_action'] ?? '')),
            'suspension_ends_at' => trim((string)($_POST['suspension_ends_at'] ?? '')),
            'resolution_notes' => trim((string)($_POST['resolution_notes'] ?? '')),
        ];
        $ok = trux_moderation_finalize_report_decision($reportId, (int)$moderationMe['id'], $decision);
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Report resolved.' : 'Could not complete the final action.');
        trux_redirect($returnPath);
    }

    if ($action === 'dismiss_review') {
        if (!trux_moderation_can_finalize_report($report, $moderationMe)) {
            trux_flash_set('error', 'Only the head reviewer can dismiss this report.');
            trux_redirect($returnPath);
        }

        $ok = trux_moderation_update_report_status($reportId, (int)$moderationMe['id'], 'dismissed');
        trux_flash_set($ok ? 'success' : 'error', $ok ? 'Report dismissed.' : 'Could not dismiss the report.');
        trux_redirect($returnPath);
    }

    if ($action === 'escalate_report') {
        $targetLabel = trim((string)($report['target_label'] ?? ''));
        if ($targetLabel === '') {
            $targetLabel = trux_moderation_target_label((string)($report['target_type'] ?? ''), (int)($report['target_id'] ?? 0));
        }
        $summary = 'Escalated report #' . $reportId . ' for ' . $targetLabel . ' (' . trux_moderation_reason_label((string)($report['reason_key'] ?? '')) . ').';
        $escalation = trux_moderation_create_or_get_escalation('report', $reportId, (int)$moderationMe['id'], $summary, 'admin', (string)($report['priority'] ?? 'high'));
        if ($escalation) {
            trux_redirect('/moderation/escalations.php?escalation=' . (int)$escalation['id']);
        }
        trux_flash_set('error', 'Could not escalate the report.');
        trux_redirect($returnPath);
    }

    trux_flash_set('error', 'Unknown moderation action.');
    trux_redirect($returnPath);
}

$filters = [
    'status' => trux_str_param('status', 'all'),
    'priority' => trux_str_param('priority', 'all'),
    'reason_key' => trux_str_param('reason_key', 'all'),
    'target_type' => trux_str_param('target_type', 'all'),
    'assignee' => trux_str_param('assignee', 'all'),
    'q' => trux_str_param('q', ''),
];
$page = max(1, trux_int_param('page', 1));
$reportPage = trux_moderation_fetch_reports($filters, $page, 25);
$reports = is_array($reportPage['items'] ?? null) ? $reportPage['items'] : [];
$matchedTotal = (int)($reportPage['total'] ?? 0);
$totalPages = max(1, (int)($reportPage['total_pages'] ?? 1));
$reviewState = trux_moderation_fetch_report_review_state(array_column($reports, 'id'), (int)($moderationMe['id'] ?? 0));
$defaultReviewState = [
    'discussion' => [],
    'votes' => [],
    'totals' => ['yay' => 0, 'nay' => 0],
    'viewer_vote' => '',
];
$getReportReviewState = static function (int $reportId) use ($reviewState, $defaultReviewState): array {
    return is_array($reviewState[$reportId] ?? null) ? $reviewState[$reportId] : $defaultReviewState;
};

$activeReports = [];
$archivedReports = [];
foreach ($reports as $report) {
    if (trux_moderation_is_report_archived_status((string)($report['status'] ?? ''))) {
        $archivedReports[] = $report;
        continue;
    }
    $activeReports[] = $report;
}

$activeReportCountOnPage = count($activeReports);
$archivedReportCountOnPage = count($archivedReports);
$canReopenArchivedReports = trux_is_owner_staff_role($moderationStaffRole);
$canWriteReview = trux_can_moderation_write($moderationStaffRole);

$buildReportsUrl = static function (array $overrides = []) use ($filters, $page): string {
    $params = array_merge($filters, ['page' => $page], $overrides);
    foreach ($params as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if ($value === null || $value === '' || $value === 'all' || ($key === 'page' && (int)$value <= 1)) {
            unset($params[$key]);
        }
    }

    $query = $params ? '?' . http_build_query($params) : '';
    return TRUX_BASE_URL . '/moderation/reports.php' . $query;
};

$profileUrl = static function (string $username): string {
    return TRUX_BASE_URL . '/profile.php?u=' . rawurlencode($username);
};

$reportLabel = static function (array $report): string {
    $label = trim((string)($report['target_label'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    return trux_moderation_target_label((string)($report['target_type'] ?? ''), (int)($report['target_id'] ?? 0));
};

$reportOwnerUsername = static function (array $report): string {
    $username = trim((string)($report['owner_username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    return trim((string)($report['target_owner_username'] ?? ''));
};

$reportUserCaseUrl = static function (array $report): ?string {
    $targetType = trim((string)($report['target_type'] ?? ''));
    $userId = $targetType === 'user'
        ? (int)($report['target_id'] ?? 0)
        : (int)($report['owner_user_id'] ?? 0);

    return $userId > 0 ? TRUX_BASE_URL . '/moderation/user_review.php?user_id=' . $userId : null;
};

$archivedReportMessage = static function (array $report): string {
    return trux_moderation_archived_report_message(
        (string)($report['status'] ?? ''),
        is_string($report['resolution_action_key'] ?? null) ? (string)$report['resolution_action_key'] : null,
        (string)($report['target_type'] ?? '')
    );
};

$renderReportPreview = static function (array $report, ?string $userCaseUrl) use ($reportLabel): string {
    $targetType = trim((string)($report['target_type'] ?? ''));
    $snapshot = is_array($report['snapshot'] ?? null) ? $report['snapshot'] : [];
    $liveContext = is_array($report['live_context'] ?? null) ? $report['live_context'] : [];
    $targetAvailable = !empty($report['target_available']);
    $sourceUrl = trux_public_url((string)($report['source_url'] ?? ''));

    ob_start();
    ?>
    <div class="reviewModalPost">
      <?php if (!$targetAvailable && $snapshot !== []): ?>
        <div class="reviewModal__note">Live target unavailable. This preview is using the captured report snapshot.</div>
      <?php elseif (!$targetAvailable): ?>
        <div class="reviewModal__empty reviewModal__empty--compact">
          <p class="muted">The reported target is no longer available and no snapshot was captured.</p>
        </div>
      <?php endif; ?>

      <?php if ($targetType === 'post'): ?>
        <?php
        $post = is_array($liveContext['post'] ?? null) ? $liveContext['post'] : [];
        $body = trim((string)($post['body'] ?? ($snapshot['body'] ?? '')));
        $imagePath = trim((string)($post['image_path'] ?? ($snapshot['image_path'] ?? '')));
        $imageUrl = trux_public_url($imagePath);
        $authorUsername = trim((string)($post['author_username'] ?? ($snapshot['author_username'] ?? '')));
        $createdAt = trim((string)($post['created_at'] ?? ($snapshot['created_at'] ?? '')));
        ?>
        <div class="reviewModalPost__meta muted">
          <span>Post #<?= (int)($report['target_id'] ?? 0) ?></span>
          <?php if ($authorUsername !== ''): ?>
            <span>Author: @<?= trux_e($authorUsername) ?></span>
          <?php endif; ?>
          <?php if ($createdAt !== ''): ?>
            <span title="<?= trux_e(trux_format_exact_time($createdAt)) ?>"><?= trux_e(trux_time_ago($createdAt)) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($body !== ''): ?>
          <div class="reviewModalPost__body"><?= trux_render_post_body($body) ?></div>
        <?php endif; ?>
        <?php if ($imageUrl !== ''): ?>
          <div class="reviewModalPost__image">
            <img src="<?= trux_e($imageUrl) ?>" alt="" loading="lazy" decoding="async">
          </div>
        <?php endif; ?>
        <?php if ($body === '' && $imageUrl === ''): ?>
          <div class="reviewModal__empty reviewModal__empty--compact">
            <p class="muted">No post body or image was available.</p>
          </div>
        <?php endif; ?>
      <?php elseif ($targetType === 'comment'): ?>
        <?php
        $comment = is_array($liveContext['comment'] ?? null) ? $liveContext['comment'] : [];
        $post = is_array($liveContext['post'] ?? null) ? $liveContext['post'] : [];
        $body = trim((string)($comment['body'] ?? ($snapshot['body'] ?? '')));
        $authorUsername = trim((string)($comment['author_username'] ?? ($snapshot['author_username'] ?? '')));
        $replyToUsername = trim((string)($comment['reply_to_username'] ?? ($snapshot['reply_to_username'] ?? '')));
        $postId = (int)($comment['post_id'] ?? ($snapshot['post_id'] ?? 0));
        $postExcerpt = trim((string)($post['excerpt'] ?? ($snapshot['post_excerpt'] ?? '')));
        $postUsername = trim((string)($post['author_username'] ?? ($snapshot['post_username'] ?? '')));
        $createdAt = trim((string)($comment['created_at'] ?? ($snapshot['created_at'] ?? '')));
        ?>
        <div class="reviewModalPost__meta muted">
          <span>Comment #<?= (int)($report['target_id'] ?? 0) ?></span>
          <?php if ($authorUsername !== ''): ?>
            <span>Author: @<?= trux_e($authorUsername) ?></span>
          <?php endif; ?>
          <?php if ($replyToUsername !== ''): ?>
            <span>Replying to @<?= trux_e($replyToUsername) ?></span>
          <?php endif; ?>
          <?php if ($createdAt !== ''): ?>
            <span title="<?= trux_e(trux_format_exact_time($createdAt)) ?>"><?= trux_e(trux_time_ago($createdAt)) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($body !== ''): ?>
          <div class="reviewModalPost__body"><?= trux_render_comment_body($body) ?></div>
        <?php else: ?>
          <div class="reviewModal__empty reviewModal__empty--compact">
            <p class="muted">No comment body was available.</p>
          </div>
        <?php endif; ?>
        <?php if ($postId > 0 || $postExcerpt !== ''): ?>
          <div class="reviewModal__note">
            <strong>Linked post<?= $postId > 0 ? ' #' . $postId : '' ?></strong><br>
            <?php if ($postUsername !== ''): ?>
              by @<?= trux_e($postUsername) ?><br>
            <?php endif; ?>
            <?= $postExcerpt !== '' ? trux_render_rich_text($postExcerpt) : 'No post excerpt available.' ?>
          </div>
        <?php endif; ?>
      <?php elseif ($targetType === 'message'): ?>
        <?php
        $message = is_array($liveContext['message'] ?? null) ? $liveContext['message'] : [];
        $conversationMessages = is_array($liveContext['conversation_messages'] ?? null) ? $liveContext['conversation_messages'] : [];
        $body = trim((string)($message['body'] ?? ($snapshot['body'] ?? '')));
        $senderUsername = trim((string)($message['sender_username'] ?? ($snapshot['sender_username'] ?? '')));
        $recipientUsername = trim((string)($message['recipient_username'] ?? ($snapshot['recipient_username'] ?? '')));
        $conversationId = (int)($message['conversation_id'] ?? ($snapshot['conversation_id'] ?? 0));
        $createdAt = trim((string)($message['created_at'] ?? ($snapshot['created_at'] ?? '')));
        ?>
        <div class="reviewModalPost__meta muted">
          <span>Message #<?= (int)($report['target_id'] ?? 0) ?></span>
          <?php if ($senderUsername !== ''): ?>
            <span>From @<?= trux_e($senderUsername) ?></span>
          <?php endif; ?>
          <?php if ($recipientUsername !== ''): ?>
            <span>To @<?= trux_e($recipientUsername) ?></span>
          <?php endif; ?>
          <?php if ($conversationId > 0): ?>
            <span>Conversation #<?= $conversationId ?></span>
          <?php endif; ?>
          <?php if ($createdAt !== ''): ?>
            <span title="<?= trux_e(trux_format_exact_time($createdAt)) ?>"><?= trux_e(trux_time_ago($createdAt)) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($body !== ''): ?>
          <div class="reviewModalPost__body"><?= trux_render_rich_text($body) ?></div>
        <?php else: ?>
          <div class="reviewModal__empty reviewModal__empty--compact">
            <p class="muted">No message body was available.</p>
          </div>
        <?php endif; ?>
        <?php if ($conversationMessages !== []): ?>
          <div class="reviewModal__sectionHead">
            <strong>Nearby Conversation Context</strong>
            <span class="muted"><?= count($conversationMessages) ?> message<?= count($conversationMessages) === 1 ? '' : 's' ?></span>
          </div>
          <div class="reviewModalDiscussion">
            <?php foreach ($conversationMessages as $conversationMessage): ?>
              <?php
              $conversationBody = trim((string)($conversationMessage['body'] ?? ''));
              $conversationUsername = trim((string)($conversationMessage['sender_username'] ?? ''));
              $conversationCreatedAt = trim((string)($conversationMessage['created_at'] ?? ''));
              $isReportedTarget = !empty($conversationMessage['is_reported_target']);
              ?>
              <article class="reviewModalDiscussion__item<?= $isReportedTarget ? ' reviewModalDiscussion__item--target' : '' ?>">
                <div class="reviewModalDiscussion__meta">
                  <strong>@<?= trux_e($conversationUsername !== '' ? $conversationUsername : 'unknown') ?></strong>
                  <?php if ($isReportedTarget): ?>
                    <span class="moderationBadge is-danger">Reported message</span>
                  <?php endif; ?>
                  <?php if ($conversationCreatedAt !== ''): ?>
                    <span class="muted" title="<?= trux_e(trux_format_exact_time($conversationCreatedAt)) ?>"><?= trux_e(trux_time_ago($conversationCreatedAt)) ?></span>
                  <?php endif; ?>
                </div>
                <div class="reviewModalDiscussion__body">
                  <?= $conversationBody !== '' ? trux_render_rich_text($conversationBody) : '<span class="muted">No message body available.</span>' ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php elseif ($targetType === 'user'): ?>
        <?php
        $user = is_array($liveContext['user'] ?? null) ? $liveContext['user'] : [];
        $username = trim((string)($user['username'] ?? ($snapshot['username'] ?? '')));
        $displayName = trim((string)($user['display_name'] ?? ($snapshot['display_name'] ?? '')));
        $bio = trim((string)($user['bio'] ?? ($snapshot['bio'] ?? '')));
        $location = trim((string)($user['location'] ?? ($snapshot['location'] ?? '')));
        $websiteUrl = trim((string)($user['website_url'] ?? ($snapshot['website_url'] ?? '')));
        $avatarUrl = trux_public_url((string)($user['avatar_path'] ?? ($snapshot['avatar_path'] ?? '')));
        $bannerUrl = trux_public_url((string)($user['banner_path'] ?? ($snapshot['banner_path'] ?? '')));
        $createdAt = trim((string)($user['created_at'] ?? ($snapshot['created_at'] ?? '')));
        $staffRole = trim((string)($user['staff_role'] ?? ($snapshot['staff_role'] ?? 'user')));
        ?>
        <div class="reviewModalPost__meta muted">
          <span><?= $username !== '' ? '@' . trux_e($username) : trux_e($reportLabel($report)) ?></span>
          <?php if ($displayName !== ''): ?>
            <span><?= trux_e($displayName) ?></span>
          <?php endif; ?>
          <?php if ($createdAt !== ''): ?>
            <span title="<?= trux_e(trux_format_exact_time($createdAt)) ?>">Joined <?= trux_e(trux_time_ago($createdAt)) ?></span>
          <?php endif; ?>
        </div>
        <div class="reviewModal__metaList">
          <div class="reviewModal__metaRow">
            <span class="muted">Location</span>
            <strong><?= trux_e($location !== '' ? $location : 'Not set') ?></strong>
          </div>
          <div class="reviewModal__metaRow">
            <span class="muted">Website</span>
            <?php if ($websiteUrl !== ''): ?>
              <a href="<?= trux_e($websiteUrl) ?>"><?= trux_e($websiteUrl) ?></a>
            <?php else: ?>
              <span class="muted">Not set</span>
            <?php endif; ?>
          </div>
          <div class="reviewModal__metaRow">
            <span class="muted">Staff role</span>
            <strong><?= trux_e(ucfirst(trux_staff_role($staffRole))) ?></strong>
          </div>
        </div>
        <?php if ($bio !== ''): ?>
          <div class="reviewModalPost__body"><?= trux_render_rich_text($bio) ?></div>
        <?php else: ?>
          <div class="reviewModal__note">No bio was set on the reported profile.</div>
        <?php endif; ?>
        <div class="reviewModal__linkRow">
          <?php if ($sourceUrl !== ''): ?>
            <a class="btn btn--small btn--ghost" href="<?= trux_e($sourceUrl) ?>">Open public profile</a>
          <?php endif; ?>
          <?php if ($userCaseUrl !== null): ?>
            <a class="btn btn--small btn--ghost" href="<?= trux_e($userCaseUrl) ?>">Open user case</a>
          <?php endif; ?>
          <?php if ($avatarUrl !== ''): ?>
            <a class="btn btn--small btn--ghost" href="<?= trux_e($avatarUrl) ?>">Avatar asset</a>
          <?php endif; ?>
          <?php if ($bannerUrl !== ''): ?>
            <a class="btn btn--small btn--ghost" href="<?= trux_e($bannerUrl) ?>">Banner asset</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="reviewModal__empty">
          <strong>Unknown target type</strong>
          <p class="muted">No preview renderer is available for this report target.</p>
        </div>
      <?php endif; ?>
    </div>
    <?php

    return trim((string)ob_get_clean());
};

require_once dirname(__DIR__) . '/_header.php';
?>

<section class="hero">
  <h1>Reports</h1>
  <p class="muted">Unified review queue for profile, post, comment, and direct-message reporting.</p>
</section>

<section class="moderationLayout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="moderationContent">
    <section class="card moderationPanel">
      <div class="card__body">
        <form class="moderationFilters" method="get" action="<?= TRUX_BASE_URL ?>/moderation/reports.php">
          <label class="field">
            <span>Status</span>
            <select name="status">
              <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All</option>
              <?php foreach ($reportStatuses as $statusKey => $statusLabel): ?>
                <option value="<?= trux_e($statusKey) ?>" <?= $filters['status'] === $statusKey ? 'selected' : '' ?>><?= trux_e($statusLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Priority</span>
            <select name="priority">
              <option value="all" <?= $filters['priority'] === 'all' ? 'selected' : '' ?>>All</option>
              <?php foreach ($reportPriorities as $priorityKey => $priorityLabel): ?>
                <option value="<?= trux_e($priorityKey) ?>" <?= $filters['priority'] === $priorityKey ? 'selected' : '' ?>><?= trux_e($priorityLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Reason</span>
            <select name="reason_key">
              <option value="all" <?= $filters['reason_key'] === 'all' ? 'selected' : '' ?>>All</option>
              <?php foreach ($reportReasons as $reasonKey => $reasonLabel): ?>
                <option value="<?= trux_e($reasonKey) ?>" <?= $filters['reason_key'] === $reasonKey ? 'selected' : '' ?>><?= trux_e($reasonLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Target Type</span>
            <select name="target_type">
              <option value="all" <?= $filters['target_type'] === 'all' ? 'selected' : '' ?>>All</option>
              <?php foreach ($reportTargetTypes as $targetTypeKey => $targetTypeLabel): ?>
                <option value="<?= trux_e($targetTypeKey) ?>" <?= $filters['target_type'] === $targetTypeKey ? 'selected' : '' ?>><?= trux_e($targetTypeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Head Reviewer</span>
            <select name="assignee">
              <option value="all" <?= $filters['assignee'] === 'all' ? 'selected' : '' ?>>All</option>
              <option value="unassigned" <?= $filters['assignee'] === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
              <?php foreach ($staffUsers as $staffUser): ?>
                <option value="<?= (int)$staffUser['id'] ?>" <?= (string)$filters['assignee'] === (string)$staffUser['id'] ? 'selected' : '' ?>>
                  @<?= trux_e((string)$staffUser['username']) ?> (<?= trux_e(ucfirst((string)$staffUser['staff_role'])) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field moderationFilters__search">
            <span>Search</span>
            <input type="search" name="q" value="<?= trux_e((string)$filters['q']) ?>" placeholder="Reporter, reviewer, target id, notes">
          </label>

          <div class="moderationFilters__actions">
            <button class="btn btn--small" type="submit">Apply filters</button>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/reports.php">Reset</a>
          </div>
        </form>
      </div>
    </section>

    <section class="card moderationPanel">
      <div class="card__body">
        <div class="moderationPanel__head">
          <div>
            <h2 class="h2">Active Queue</h2>
            <p class="muted">
              <?= $matchedTotal ?> report<?= $matchedTotal === 1 ? '' : 's' ?> matched.
              <?= $activeReportCountOnPage ?> active on this page<?= $archivedReportCountOnPage > 0 ? ', ' . $archivedReportCountOnPage . ' archived' : '' ?>.
            </p>
          </div>
        </div>

        <?php if (!$activeReports): ?>
          <div class="moderationEmptyState">
            <strong>No active reports on this page</strong>
            <p class="muted"><?= $archivedReportCountOnPage > 0 ? 'Archived reports are available below.' : 'New reports will appear here when they need review.' ?></p>
          </div>
        <?php else: ?>
          <div class="moderationList">
            <?php foreach ($activeReports as $report): ?>
              <?php
              $reportId = (int)($report['id'] ?? 0);
              $reportReviewState = $getReportReviewState($reportId);
              $voteTotals = is_array($reportReviewState['totals'] ?? null) ? $reportReviewState['totals'] : ['yay' => 0, 'nay' => 0];
              $ownerUsername = $reportOwnerUsername($report);
              $sourceUrl = trux_public_url((string)($report['source_url'] ?? ''));
              $userCaseUrl = $reportUserCaseUrl($report);
              ?>
              <article class="moderationListItem">
                <div class="moderationListItem__top">
                  <strong><?= trux_e($reportLabel($report)) ?></strong>
                  <div class="moderationBadgeRow">
                    <span class="moderationBadge <?= trux_moderation_priority_badge_class((string)($report['priority'] ?? '')) ?>"><?= trux_e(trux_moderation_label($reportPriorities, (string)($report['priority'] ?? ''))) ?></span>
                    <span class="moderationBadge <?= trux_moderation_status_badge_class((string)($report['status'] ?? '')) ?>"><?= trux_e(trux_moderation_label($reportStatuses, (string)($report['status'] ?? ''))) ?></span>
                    <span class="moderationBadge is-muted"><?= trux_e(trux_moderation_label($reportTargetTypes, (string)($report['target_type'] ?? ''))) ?></span>
                  </div>
                </div>
                <div class="moderationListItem__meta muted">
                  <span>Reason: <?= trux_e(trux_moderation_reason_label((string)($report['reason_key'] ?? ''))) ?></span>
                  <span>Reporter: <a href="<?= $profileUrl((string)$report['reporter_username']) ?>">@<?= trux_e((string)$report['reporter_username']) ?></a></span>
                  <span>
                    Owner:
                    <?php if ($ownerUsername !== ''): ?>
                      <a href="<?= $profileUrl($ownerUsername) ?>">@<?= trux_e($ownerUsername) ?></a>
                    <?php else: ?>
                      Unknown
                    <?php endif; ?>
                  </span>
                  <span>
                    Head reviewer:
                    <?= !empty($report['assigned_staff_username']) ? '@' . trux_e((string)$report['assigned_staff_username']) : 'Unassigned' ?>
                  </span>
                </div>
                <div class="moderationListItem__meta muted">
                  <span>Discussion: <?= count((array)($reportReviewState['discussion'] ?? [])) ?> line<?= count((array)($reportReviewState['discussion'] ?? [])) === 1 ? '' : 's' ?></span>
                  <span>Votes: Yay <?= (int)($voteTotals['yay'] ?? 0) ?> / Nay <?= (int)($voteTotals['nay'] ?? 0) ?></span>
                  <span title="<?= trux_e(trux_format_exact_time((string)($report['updated_at'] ?? ''))) ?>"><?= trux_e(trux_time_ago((string)($report['updated_at'] ?? ''))) ?></span>
                </div>
                <?php if (!empty($report['wants_reporter_dm_updates'])): ?>
                  <p class="moderationListItem__summary">Reporter requested moderation DM updates.</p>
                <?php endif; ?>
                <?php if (!empty($report['details'])): ?>
                  <p class="moderationListItem__summary"><?= trux_e((string)$report['details']) ?></p>
                <?php endif; ?>
                <div class="moderationActions">
                  <button class="btn btn--small btn--ghost" type="button" data-review-modal-open="report-review-modal-<?= $reportId ?>">Open review</button>
                  <?php if ($sourceUrl !== ''): ?>
                    <a class="btn btn--small btn--ghost" href="<?= trux_e($sourceUrl) ?>">Open source</a>
                  <?php endif; ?>
                  <?php if ($userCaseUrl !== null): ?>
                    <a class="btn btn--small btn--ghost" href="<?= trux_e($userCaseUrl) ?>">Open user case</a>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($archivedReports): ?>
          <details class="moderationArchive" <?= !$activeReports ? 'open' : '' ?>>
            <summary class="moderationArchive__summary">
              <div>
                <strong>Archived Reports</strong>
                <p class="muted">Resolved and dismissed reports remain here for audit and potential reopen by the owner.</p>
              </div>
              <div class="moderationArchive__summaryMeta">
                <span class="moderationBadge is-muted"><?= $archivedReportCountOnPage ?> on this page</span>
                <span class="moderationArchive__chevron" aria-hidden="true"></span>
              </div>
            </summary>

            <div class="moderationArchive__body">
              <div class="moderationList">
                <?php foreach ($archivedReports as $report): ?>
                  <?php
                  $reportId = (int)($report['id'] ?? 0);
                  $sourceUrl = trux_public_url((string)($report['source_url'] ?? ''));
                  $userCaseUrl = $reportUserCaseUrl($report);
                  $resolutionActionLabel = trim((string)($report['resolution_action_label'] ?? ''));
                  ?>
                  <article class="moderationRecordCard moderationRecordCard--archived">
                    <div class="moderationRecordCard__head">
                      <strong><?= trux_e($reportLabel($report)) ?></strong>
                      <div class="moderationBadgeRow">
                        <span class="moderationBadge <?= trux_moderation_status_badge_class((string)($report['status'] ?? '')) ?>"><?= trux_e(trux_moderation_label($reportStatuses, (string)($report['status'] ?? ''))) ?></span>
                        <span class="moderationBadge is-muted"><?= trux_e(trux_moderation_label($reportTargetTypes, (string)($report['target_type'] ?? ''))) ?></span>
                        <?php if ($resolutionActionLabel !== ''): ?>
                          <span class="moderationBadge is-info"><?= trux_e($resolutionActionLabel) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="moderationRecordCard__meta muted">
                      <span>Reason: <?= trux_e(trux_moderation_reason_label((string)($report['reason_key'] ?? ''))) ?></span>
                      <span>Reporter: @<?= trux_e((string)$report['reporter_username']) ?></span>
                      <span title="<?= trux_e(trux_format_exact_time((string)($report['updated_at'] ?? ''))) ?>"><?= trux_e(trux_time_ago((string)($report['updated_at'] ?? ''))) ?></span>
                    </div>
                    <p class="moderationRecordCard__summary"><?= trux_e($archivedReportMessage($report)) ?></p>
                    <?php if (!empty($report['details'])): ?>
                      <p class="moderationRecordCard__summary"><?= trux_e((string)$report['details']) ?></p>
                    <?php endif; ?>
                    <div class="moderationActions">
                      <button class="btn btn--small btn--ghost" type="button" data-review-modal-open="report-review-modal-<?= $reportId ?>">Open review</button>
                      <?php if ($sourceUrl !== ''): ?>
                        <a class="btn btn--small btn--ghost" href="<?= trux_e($sourceUrl) ?>">Open source</a>
                      <?php endif; ?>
                      <?php if ($userCaseUrl !== null): ?>
                        <a class="btn btn--small btn--ghost" href="<?= trux_e($userCaseUrl) ?>">Open user case</a>
                      <?php endif; ?>
                      <?php if ($canReopenArchivedReports): ?>
                        <form class="moderationInlineForm" method="post" action="<?= $buildReportsUrl(['review' => $reportId]) ?>">
                          <?= trux_csrf_field() ?>
                          <input type="hidden" name="action" value="reopen_report">
                          <input type="hidden" name="report_id" value="<?= $reportId ?>">
                          <button class="btn btn--small" type="submit">Reopen</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          </details>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
          <div class="moderationPagination">
            <?php if ($page > 1): ?>
              <a class="btn btn--small btn--ghost" href="<?= $buildReportsUrl(['page' => $page - 1]) ?>">Previous</a>
            <?php endif; ?>
            <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
              <a class="btn btn--small btn--ghost" href="<?= $buildReportsUrl(['page' => $page + 1]) ?>">Next</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <?php foreach ($reports as $report): ?>
      <?php
      $reportId = (int)($report['id'] ?? 0);
      $reportReviewState = $getReportReviewState($reportId);
      $discussionLines = is_array($reportReviewState['discussion'] ?? null) ? $reportReviewState['discussion'] : [];
      $votes = is_array($reportReviewState['votes'] ?? null) ? $reportReviewState['votes'] : [];
      $voteTotals = is_array($reportReviewState['totals'] ?? null) ? $reportReviewState['totals'] : ['yay' => 0, 'nay' => 0];
      $viewerVote = trim((string)($reportReviewState['viewer_vote'] ?? ''));
      $ownerUsername = $reportOwnerUsername($report);
      $userCaseUrl = $reportUserCaseUrl($report);
      $sourceUrl = trux_public_url((string)($report['source_url'] ?? ''));
      $reviewActionUrl = $buildReportsUrl(['review' => $reportId]);
      $reportIsArchived = trux_moderation_is_report_archived_status((string)($report['status'] ?? ''));
      $canFinalizeReview = trux_moderation_can_finalize_report($report, $moderationMe);
      $canManageHeadReviewer = $canWriteReview && trux_can_moderation_reassign($moderationStaffRole);
      $assigneeUserId = isset($report['assigned_staff_user_id']) && $report['assigned_staff_user_id'] !== null
          ? (int)$report['assigned_staff_user_id']
          : 0;
      $resolutionActionLabel = trim((string)($report['resolution_action_label'] ?? ''));
      ?>
      <div
        id="report-review-modal-<?= $reportId ?>"
        class="reviewModal"
        hidden
        data-review-modal="1"
        <?= $activeReviewId === $reportId ? 'data-review-modal-autopen="1"' : '' ?>>
        <div class="reviewModal__backdrop" data-review-modal-close="1"></div>
        <section class="reviewModal__panel" role="dialog" aria-modal="true" aria-labelledby="reportReviewTitle-<?= $reportId ?>">
          <header class="reviewModal__head">
            <div>
              <div class="reviewModal__eyebrow">Report Review</div>
              <h2 id="reportReviewTitle-<?= $reportId ?>"><?= trux_e($reportLabel($report)) ?></h2>
              <div class="reviewModal__headMeta muted">
                <span>Report #<?= $reportId ?></span>
                <span><?= trux_e(trux_moderation_reason_label((string)($report['reason_key'] ?? ''))) ?></span>
                <span><?= trux_e(trux_moderation_label($reportTargetTypes, (string)($report['target_type'] ?? ''))) ?></span>
              </div>
            </div>
            <button class="iconBtn reviewModal__close" type="button" aria-label="Close review" data-review-modal-close="1">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
          </header>

          <div class="reviewModal__body">
            <div class="reviewModal__grid">
              <section class="reviewModal__card reviewModal__card--compact">
                <div class="reviewModal__sectionHead">
                  <strong>Report Snapshot</strong>
                </div>
                <div class="reviewModal__metaList">
                  <div class="reviewModal__metaRow">
                    <span class="muted">Status</span>
                    <span class="moderationBadge <?= trux_moderation_status_badge_class((string)($report['status'] ?? '')) ?>"><?= trux_e(trux_moderation_label($reportStatuses, (string)($report['status'] ?? ''))) ?></span>
                  </div>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Priority</span>
                    <span class="moderationBadge <?= trux_moderation_priority_badge_class((string)($report['priority'] ?? '')) ?>"><?= trux_e(trux_moderation_label($reportPriorities, (string)($report['priority'] ?? ''))) ?></span>
                  </div>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Target</span>
                    <strong><?= trux_e($reportLabel($report)) ?></strong>
                  </div>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Reporter DM updates</span>
                    <strong><?= !empty($report['wants_reporter_dm_updates']) ? 'Requested' : 'Off' ?></strong>
                  </div>
                  <?php if ($resolutionActionLabel !== ''): ?>
                    <div class="reviewModal__metaRow">
                      <span class="muted">Resolution action</span>
                      <strong><?= trux_e($resolutionActionLabel) ?></strong>
                    </div>
                  <?php endif; ?>
                </div>
                <?php if (!empty($report['details'])): ?>
                  <div class="reviewModal__note"><?= nl2br(trux_e((string)$report['details'])) ?></div>
                <?php else: ?>
                  <div class="muted">No extra reporter note.</div>
                <?php endif; ?>
              </section>

              <section class="reviewModal__card reviewModal__card--compact">
                <div class="reviewModal__sectionHead">
                  <strong>People And Context</strong>
                </div>
                <div class="reviewModal__metaList">
                  <div class="reviewModal__metaRow">
                    <span class="muted">Reporter</span>
                    <a href="<?= $profileUrl((string)$report['reporter_username']) ?>">@<?= trux_e((string)$report['reporter_username']) ?></a>
                  </div>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Target owner</span>
                    <?php if ($ownerUsername !== ''): ?>
                      <a href="<?= $profileUrl($ownerUsername) ?>">@<?= trux_e($ownerUsername) ?></a>
                    <?php else: ?>
                      <span class="muted">Unknown</span>
                    <?php endif; ?>
                  </div>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Head reviewer</span>
                    <?php if (!empty($report['assigned_staff_username'])): ?>
                      <strong>@<?= trux_e((string)$report['assigned_staff_username']) ?></strong>
                    <?php else: ?>
                      <span class="muted">Unassigned</span>
                    <?php endif; ?>
                  </div>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Opened</span>
                    <span title="<?= trux_e(trux_format_exact_time((string)($report['created_at'] ?? ''))) ?>"><?= trux_e(trux_time_ago((string)($report['created_at'] ?? ''))) ?></span>
                  </div>
                  <div class="reviewModal__metaRow">
                    <span class="muted">Last updated</span>
                    <span title="<?= trux_e(trux_format_exact_time((string)($report['updated_at'] ?? ''))) ?>"><?= trux_e(trux_time_ago((string)($report['updated_at'] ?? ''))) ?></span>
                  </div>
                </div>
                <div class="reviewModal__linkRow">
                  <?php if ($sourceUrl !== ''): ?>
                    <a class="btn btn--small btn--ghost" href="<?= trux_e($sourceUrl) ?>">Open source</a>
                  <?php endif; ?>
                  <?php if ($userCaseUrl !== null): ?>
                    <a class="btn btn--small btn--ghost" href="<?= trux_e($userCaseUrl) ?>">Open user case</a>
                  <?php endif; ?>
                  <form method="post" action="<?= $reviewActionUrl ?>" class="inline">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="action" value="escalate_report">
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <button class="btn btn--small btn--ghost" type="submit">Escalate</button>
                  </form>
                  <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/moderation/audit_logs.php?q=<?= urlencode((string)$reportId) ?>">Audit trail</a>
                </div>
              </section>

              <section class="reviewModal__card reviewModal__card--post">
                <div class="reviewModal__sectionHead">
                  <strong>Reported Target</strong>
                  <?php if (empty($report['target_available'])): ?>
                    <span class="muted">Snapshot fallback</span>
                  <?php endif; ?>
                </div>
                <?= $renderReportPreview($report, $userCaseUrl) ?>
              </section>

              <section class="reviewModal__card reviewModal__card--workspace">
                <div class="reviewModalWorkspace">
                  <section class="reviewModal__section">
                    <div class="reviewModal__sectionHead">
                      <strong>Review Controls</strong>
                      <?php if ($reportIsArchived): ?>
                        <span class="muted">Archived</span>
                      <?php elseif (!$canWriteReview): ?>
                        <span class="muted">Read only</span>
                      <?php endif; ?>
                    </div>

                    <?php if ($reportIsArchived): ?>
                      <div class="reviewModal__note"><?= trux_e($archivedReportMessage($report)) ?></div>
                      <?php if ($canReopenArchivedReports): ?>
                        <form class="reviewModal__inlineAction" method="post" action="<?= $reviewActionUrl ?>">
                          <?= trux_csrf_field() ?>
                          <input type="hidden" name="action" value="reopen_report">
                          <input type="hidden" name="report_id" value="<?= $reportId ?>">
                          <button class="btn btn--small" type="submit">Reopen report</button>
                        </form>
                      <?php endif; ?>
                    <?php elseif ($canWriteReview): ?>
                      <form class="reviewModal__formRow" method="post" action="<?= $reviewActionUrl ?>">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="action" value="save_review_status">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <label class="field">
                          <span>Status</span>
                          <select name="status">
                            <?php foreach (['open', 'investigating'] as $statusKey): ?>
                              <option value="<?= trux_e($statusKey) ?>" <?= (string)($report['status'] ?? '') === $statusKey ? 'selected' : '' ?>><?= trux_e(trux_moderation_label($reportStatuses, $statusKey)) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                        <button class="btn btn--small btn--ghost" type="submit">Save status</button>
                      </form>

                      <?php if ($canManageHeadReviewer): ?>
                        <form class="reviewModal__formRow" method="post" action="<?= $reviewActionUrl ?>">
                          <?= trux_csrf_field() ?>
                          <input type="hidden" name="action" value="set_head">
                          <input type="hidden" name="report_id" value="<?= $reportId ?>">
                          <label class="field">
                            <span>Head reviewer</span>
                            <select name="assigned_staff_user_id">
                              <option value="">Unassigned</option>
                              <?php foreach ($staffUsers as $staffUser): ?>
                                <option value="<?= (int)$staffUser['id'] ?>" <?= $assigneeUserId === (int)$staffUser['id'] ? 'selected' : '' ?>>
                                  @<?= trux_e((string)$staffUser['username']) ?> (<?= trux_e(ucfirst((string)$staffUser['staff_role'])) ?>)
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </label>
                          <button class="btn btn--small btn--ghost" type="submit">Save head</button>
                        </form>
                      <?php elseif ($assigneeUserId !== (int)$moderationMe['id']): ?>
                        <form class="reviewModal__inlineAction" method="post" action="<?= $reviewActionUrl ?>">
                          <?= trux_csrf_field() ?>
                          <input type="hidden" name="action" value="claim_head">
                          <input type="hidden" name="report_id" value="<?= $reportId ?>">
                          <button class="btn btn--small btn--ghost" type="submit">Make me head reviewer</button>
                        </form>
                      <?php endif; ?>
                    <?php else: ?>
                      <div class="muted">Developers can inspect reports here, but only moderator roles and above can change the review.</div>
                    <?php endif; ?>
                  </section>

                  <section class="reviewModal__section">
                    <div class="reviewModal__sectionHead">
                      <strong>Votes</strong>
                      <span class="muted">Yay <?= (int)($voteTotals['yay'] ?? 0) ?> / Nay <?= (int)($voteTotals['nay'] ?? 0) ?></span>
                    </div>

                    <?php if (!$reportIsArchived && $canWriteReview): ?>
                      <div class="reviewModal__voteRow">
                        <?php foreach ($reportVoteOptions as $voteKey => $voteLabel): ?>
                          <form method="post" action="<?= $reviewActionUrl ?>">
                            <?= trux_csrf_field() ?>
                            <input type="hidden" name="action" value="cast_vote">
                            <input type="hidden" name="report_id" value="<?= $reportId ?>">
                            <input type="hidden" name="vote_value" value="<?= trux_e($voteKey) ?>">
                            <button class="btn btn--small btn--ghost<?= $viewerVote === $voteKey ? ' is-active' : '' ?>" type="submit"><?= trux_e($voteLabel) ?></button>
                          </form>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

                    <?php if (!$votes): ?>
                      <div class="muted">No votes yet.</div>
                    <?php else: ?>
                      <div class="reviewModalVoteList">
                        <?php foreach ($votes as $vote): ?>
                          <?php $voteValue = trim((string)($vote['vote_value'] ?? '')); ?>
                          <div class="reviewModalVoteList__item">
                            <strong>@<?= trux_e((string)$vote['staff_username']) ?></strong>
                            <span class="moderationBadge <?= $voteValue === 'yay' ? 'is-success' : 'is-muted' ?>"><?= trux_e(trux_moderation_label($reportVoteOptions, $voteValue)) ?></span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </section>

                  <section class="reviewModal__section reviewModal__section--fill">
                    <div class="reviewModal__sectionHead">
                      <strong>Moderator Discussion</strong>
                      <span class="muted"><?= count($discussionLines) ?> line<?= count($discussionLines) === 1 ? '' : 's' ?></span>
                    </div>

                    <div class="reviewModalDiscussion">
                      <?php if (!$discussionLines): ?>
                        <div class="reviewModal__empty reviewModal__empty--compact">
                          <p class="muted">No moderator discussion yet.</p>
                        </div>
                      <?php else: ?>
                        <?php foreach ($discussionLines as $line): ?>
                          <article class="reviewModalDiscussion__item">
                            <div class="reviewModalDiscussion__meta">
                              <strong>@<?= trux_e((string)$line['author_username']) ?></strong>
                              <span class="muted" title="<?= trux_e(trux_format_exact_time((string)$line['created_at'])) ?>"><?= trux_e(trux_time_ago((string)$line['created_at'])) ?></span>
                            </div>
                            <div class="reviewModalDiscussion__body"><?= trux_e((string)$line['body']) ?></div>
                          </article>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>

                    <?php if (!$reportIsArchived && $canWriteReview): ?>
                      <form class="reviewModal__discussionForm" method="post" action="<?= $reviewActionUrl ?>">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="action" value="add_discussion">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <input type="text" name="discussion_body" maxlength="280" required placeholder="Add a short discussion line for the review team...">
                        <button class="btn btn--small btn--ghost" type="submit">Post line</button>
                      </form>
                    <?php endif; ?>
                  </section>

                  <section class="reviewModal__section">
                    <div class="reviewModal__sectionHead">
                      <strong>Final Decision</strong>
                      <?php if ($reportIsArchived): ?>
                        <span class="muted">Already finalized</span>
                      <?php elseif ($canFinalizeReview): ?>
                        <span class="muted">Head reviewer controls</span>
                      <?php else: ?>
                        <span class="muted">Head reviewer required</span>
                      <?php endif; ?>
                    </div>

                    <?php if ($reportIsArchived): ?>
                      <div class="reviewModal__note"><?= trux_e($archivedReportMessage($report)) ?></div>
                    <?php elseif ($canFinalizeReview): ?>
                      <?php
                      $supportsContentRemoval = trux_moderation_report_supports_content_removal($report);
                      $supportsUserCase = trux_moderation_report_supports_user_case($report);
                      ?>
                      <form class="reviewModal__section" method="post" action="<?= $reviewActionUrl ?>">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="action" value="resolve_report">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">

                        <?php if ($supportsContentRemoval): ?>
                          <label class="field">
                            <span>Content outcome</span>
                            <select name="content_action">
                              <option value="none">No content action</option>
                              <option value="content_removed">Remove content</option>
                              <option value="content_already_unavailable" <?= empty($report['target_available']) ? 'selected' : '' ?>>Content already unavailable</option>
                            </select>
                          </label>
                        <?php else: ?>
                          <input type="hidden" name="content_action" value="none">
                        <?php endif; ?>

                        <?php if ($supportsUserCase): ?>
                          <label class="field" style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="open_case" value="1" checked>
                            <span>Open or update the linked user case</span>
                          </label>

                          <label class="field">
                            <span>Account action</span>
                            <select name="enforcement_action">
                              <option value="">No account action</option>
                              <?php foreach ($userEnforcementActions as $actionKey => $actionLabel): ?>
                                <option value="<?= trux_e($actionKey) ?>"><?= trux_e($actionLabel) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </label>

                          <label class="field">
                            <span>Suspend until</span>
                            <input type="datetime-local" name="suspension_ends_at" value="">
                          </label>
                        <?php else: ?>
                          <input type="hidden" name="enforcement_action" value="">
                        <?php endif; ?>

                        <label class="field">
                          <span>Resolution notes</span>
                          <textarea name="resolution_notes" rows="4" maxlength="4000" placeholder="Optional internal notes for the enforcement, case, or appeal trail."></textarea>
                        </label>

                        <div class="reviewModal__decisionRow">
                          <button class="btn btn--small reviewModal__decisionBtn reviewModal__decisionBtn--danger" type="submit">Resolve report</button>
                        </div>
                      </form>

                      <form method="post" action="<?= $reviewActionUrl ?>">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="action" value="dismiss_review">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <button class="btn btn--small reviewModal__decisionBtn" type="submit">Dismiss</button>
                      </form>

                      <?php if ($supportsContentRemoval && !empty($report['target_available'])): ?>
                        <p class="muted">Use "content already unavailable" only when the reported target is already gone and review is proceeding from the stored snapshot.</p>
                      <?php endif; ?>
                    <?php else: ?>
                      <p class="muted">Only the current head reviewer can finalize this report.</p>
                    <?php endif; ?>
                  </section>
                </div>
              </section>
            </div>
          </div>
        </section>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
