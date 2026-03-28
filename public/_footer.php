<?php
declare(strict_types=1);

$isAppLayout = $pageLayout === 'app';
$isAuthLayout = $pageLayout === 'auth';
$isOpsLayout = $pageLayout === 'moderation';
?>
<?php if ($isAppLayout): ?>
          </div>
        </main>
      </div>
    </div>
  <?php elseif ($isOpsLayout): ?>
          </div>
        </main>
      </div>
    </div>
  <?php else: ?>
        </div>
      </main>

      <footer class="authFooter">
        <div class="authFooter__inner">
          <div class="authFooter__brand">
            <strong><?= trux_e(TRUX_APP_NAME) ?></strong>
            <span>&copy; <?= date('Y') ?> Secure access gateway</span>
          </div>
          <div class="authFooter__meta">Backend logic, session rules, and account flows remain unchanged.</div>
        </div>
      </footer>
    </div>
  <?php endif; ?>

  <?php if ($isAppLayout): ?>
    <div class="shellSheet" data-shell-sheet="search" hidden>
      <div class="shellSheet__backdrop" data-shell-sheet-close="1"></div>
      <section class="shellSheet__panel" role="dialog" aria-modal="true" aria-labelledby="shellSearchTitle">
        <header class="shellSheet__head">
          <div>
            <span class="shellSheet__eyebrow">Search</span>
            <h2 id="shellSearchTitle">Search TruX</h2>
          </div>
          <button class="iconBtn" type="button" aria-label="Close search" data-shell-sheet-close="1">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </header>

        <form class="shellSheet__form" method="get" action="<?= TRUX_BASE_URL ?>/search.php" role="search">
          <label class="field">
            <span>Query</span>
            <input name="q" value="<?= trux_e((string)$q) ?>" placeholder="Search users, posts, or #hashtags" maxlength="80" autofocus>
          </label>
          <div class="shellSheet__actions">
            <button class="shellButton shellButton--accent" type="submit">Search</button>
            <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/search.php">Open search page</a>
          </div>
        </form>
      </section>
    </div>

    <div class="shellNavDrawer" data-shell-nav="mobile" aria-hidden="true">
      <button class="shellNavDrawer__backdrop" type="button" data-shell-nav-backdrop data-shell-nav-target="mobile" aria-label="Close navigation menu"></button>
      <section class="shellNavDrawer__panel" id="<?= trux_e($shellNavMobilePanelId) ?>" data-shell-nav-panel role="dialog" aria-modal="true" aria-labelledby="shellNavMobileTitle">
        <header class="shellNavDrawer__head">
          <div class="shellBrand shellBrand--compact shellBrand--static" aria-label="<?= trux_e(TRUX_APP_NAME) ?> brand">
            <span class="shellBrand__mark">
              <img src="<?= TRUX_BASE_URL ?>/favicon.php?v=<?= $faviconVersion ?>" alt="" width="28" height="28" loading="eager" decoding="async">
            </span>
            <span class="shellBrand__copy shellBrand__copy--compact">
              <span class="shellNavDrawer__eyebrow">Navigation</span>
              <strong id="shellNavMobileTitle"><?= trux_e(TRUX_APP_NAME) ?></strong>
            </span>
          </div>

          <?php $renderShellNavToggle('mobile', $shellNavMobilePanelId, 'shellNavToggle--mobile shellNavToggle--drawer'); ?>
        </header>

        <div class="shellNavDrawer__body">
          <a class="railCompose<?= !empty($primaryRailAction['active']) ? ' is-active' : '' ?>" href="<?= trux_e((string)$primaryRailAction['href']) ?>" <?= !empty($primaryRailAction['active']) ? 'aria-current="page"' : '' ?>>
            <span class="railCompose__plus" aria-hidden="true">+</span>
            <span class="railCompose__copy">
              <strong><?= trux_e((string)$primaryRailAction['label']) ?></strong>
              <small><?= trux_e((string)$primaryRailAction['meta']) ?></small>
            </span>
          </a>

          <nav class="railNav railNav--panel railNav--drawer" aria-label="Secondary app links">
            <?php $renderAppRailItems($appRailItems, 'mobile'); ?>
          </nav>

          <?php $renderShellNavFooter('mobile'); ?>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <div id="commentDock" class="commentDock" hidden>
    <div class="commentDock__backdrop" data-comment-close="1"></div>
    <section class="commentDock__panel" role="dialog" aria-modal="true" aria-labelledby="commentDockTitle">
      <header class="commentDock__head">
        <div class="commentDock__titleWrap">
          <span class="commentDock__eyebrow">Post viewer</span>
          <h2 id="commentDockTitle">Thread</h2>
        </div>
        <div class="commentDock__headActions">
          <button class="iconBtn commentDock__close" type="button" aria-label="Close post viewer" data-comment-close="1">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </div>
      </header>

      <div class="commentDock__split">
        <div class="commentDock__left" data-comment-post></div>

        <div class="commentDock__right">
          <div class="commentDock__listWrap">
            <div class="commentDock__list" data-comment-list></div>
            <div class="commentDock__empty muted" data-comment-empty="1">No comments yet.</div>
          </div>

          <?php if (trux_is_logged_in()): ?>
            <form class="commentDock__form" method="post" action="<?= TRUX_BASE_URL ?>/comment_post.php" data-comment-form="1" data-no-fx="1">
              <input type="hidden" name="_csrf" value="<?= trux_e(trux_csrf_token()) ?>">
              <input type="hidden" name="id" value="" data-comment-post-id="1">
              <input type="hidden" name="parent_id" value="" data-comment-parent-id="1">
              <input type="hidden" name="reply_to_user_id" value="" data-comment-reply-user-id="1">
              <div class="commentDock__replying muted" data-comment-replying="1" hidden>
                Replying to <span data-comment-replying-user="1"></span>
                <button type="button" class="commentDock__replyCancel" data-comment-reply-cancel="1">Cancel</button>
              </div>
              <label class="field commentDock__field">
                <span class="commentDock__fieldLabel">Add a comment</span>
                <textarea class="commentDock__textarea" name="body" rows="3" maxlength="1000" required placeholder="Write your comment..." data-mention-input="1"></textarea>
              </label>
              <div class="row commentDock__formActions">
                <button class="shellButton shellButton--accent" type="submit">Post comment</button>
              </div>
            </form>
          <?php else: ?>
            <div class="commentDock__login muted">
              Log in to join the conversation. <a class="commentDock__loginLink" href="<?= TRUX_BASE_URL ?>/login.php">Go to login</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div id="entityEditModal" class="entityEditModal" hidden>
    <div class="entityEditModal__backdrop" data-edit-close="1"></div>
    <section class="entityEditModal__panel" role="dialog" aria-modal="true" aria-labelledby="entityEditTitle">
      <header class="entityEditModal__head">
        <div class="entityEditModal__titleWrap">
          <span class="entityEditModal__eyebrow">Edit</span>
          <h2 id="entityEditTitle" data-edit-title="1">Edit content</h2>
        </div>
        <button class="iconBtn entityEditModal__close" type="button" aria-label="Close editor" data-edit-close="1">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
      </header>

      <form class="entityEditModal__form" method="dialog" data-entity-edit-form="1" data-no-fx="1">
        <input type="hidden" value="" data-edit-type="1">
        <input type="hidden" value="" data-edit-id="1">

        <div class="flash flash--error entityEditModal__flash" data-edit-flash="1" hidden></div>

        <label class="field entityEditModal__field">
          <span class="entityEditModal__label" data-edit-label="1">Update your text</span>
          <textarea class="entityEditModal__textarea" name="body" rows="6" maxlength="2000" required data-mention-input="1"></textarea>
        </label>

        <div class="row entityEditModal__actions">
          <button class="shellButton shellButton--ghost" type="button" data-edit-cancel="1">Cancel</button>
          <button class="shellButton shellButton--accent" type="submit" data-edit-submit="1">Save changes</button>
        </div>
      </form>
    </section>
  </div>

  <?php if (trux_is_logged_in()): ?>
    <?php
    $footerUser = trux_current_user();
    $footerNotificationPrefs = $footerUser ? trux_fetch_notification_preferences((int)$footerUser['id']) : trux_notification_defaults();
    ?>
    <div id="postReportModal" class="reportModal" hidden>
      <div class="reportModal__backdrop" data-report-close="1"></div>
      <section class="reportModal__panel" role="dialog" aria-modal="true" aria-labelledby="postReportTitle">
        <header class="reportModal__head">
          <div>
            <div class="reportModal__eyebrow">Report</div>
            <h2 id="postReportTitle">Report content</h2>
          </div>
          <button class="iconBtn reportModal__close" type="button" aria-label="Close report form" data-report-close="1">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </header>

        <form class="reportModal__form" method="post" action="<?= TRUX_BASE_URL ?>/report.php" data-report-form="1" data-no-fx="1">
          <?= trux_csrf_field() ?>
          <input type="hidden" name="target_type" value="" data-report-target-type="1">
          <input type="hidden" name="target_id" value="" data-report-target-id="1">

          <div class="reportModal__summary">
            <div class="reportModal__summaryBody">
              <span class="reportModal__summaryLabel">Target</span>
              <strong data-report-target-text="1">Content</strong>
              <span class="muted">Choose the violation, then add any useful context for moderators, admins, and owners.</span>
            </div>
            <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/" target="_self" data-report-open-link="1">Open source</a>
          </div>

          <div class="flash flash--error reportModal__flash" data-report-flash="1" hidden></div>

          <label class="field reportModal__field">
            <span class="reportModal__label">Violation</span>
            <select class="reportModal__select" name="reason_key" required data-report-reason="1">
              <option value="">Choose a violation</option>
              <?php foreach (trux_moderation_report_reason_options() as $reasonKey => $reasonLabel): ?>
                <option value="<?= trux_e($reasonKey) ?>"><?= trux_e($reasonLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field reportModal__field">
            <span class="reportModal__label">Message for moderators, admins, and owners</span>
            <textarea
              class="reportModal__textarea"
              name="details"
              rows="5"
              maxlength="2000"
              placeholder="Optional: add context that will help moderators review this report."
              data-report-details="1"></textarea>
          </label>

          <label class="reportModal__checkbox">
            <input
              type="checkbox"
              name="wants_reporter_dm_updates"
              value="1"
              data-report-updates="1"
              <?= !empty($footerNotificationPrefs['notify_report_updates_default']) ? 'checked' : '' ?>>
            <span class="reportModal__checkboxText">
              <strong>Send future report updates to my DMs</strong>
              <small>Report System Updates will send you a private message after the moderation review is complete.</small>
            </span>
          </label>

          <div class="row reportModal__actions">
            <button class="shellButton shellButton--ghost" type="button" data-report-cancel="1">Cancel</button>
            <button class="shellButton shellButton--accent" type="submit" data-report-submit="1">Submit report</button>
          </div>
        </form>
      </section>
    </div>
  <?php endif; ?>
</body>
</html>
