<?php
declare(strict_types=1);

$isAppLayout = $pageLayout === 'app';
$isAuthLayout = $pageLayout === 'auth';
$isOpsLayout = $pageLayout === 'moderation';
$mobileDockHomeActive = $pageSlug === 'home' || $pageSlug === 'post-viewer';
$mobileDockInboxActive = $pageSlug === 'messages';
$mobileDockActivityActive = $pageSlug === 'notifications';
$mobileCreateHref = $user ? TRUX_BASE_URL . '/new_post.php' : TRUX_BASE_URL . '/register.php';
$mobileInboxHref = $user ? TRUX_BASE_URL . '/messages.php' : TRUX_BASE_URL . '/login.php';
$mobileActivityHref = $user ? TRUX_BASE_URL . '/notifications.php' : TRUX_BASE_URL . '/login.php';
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
    <nav class="mobileDock" aria-label="Primary mobile navigation">
      <a class="mobileDock__item<?= $mobileDockHomeActive ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/">
        <span class="mobileDock__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="M4 10.8 12 4l8 6.8M7 9.9V20h10V9.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span>Home</span>
      </a>

      <button class="mobileDock__item" type="button" data-shell-sheet-open="search">
        <span class="mobileDock__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path fill="currentColor" d="M10.5 4a6.5 6.5 0 1 0 4.02 11.61l4.43 4.43a1 1 0 0 0 1.41-1.41l-4.43-4.43A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9a4.5 4.5 0 0 1 0-9Z" />
          </svg>
        </span>
        <span>Search</span>
      </button>

      <a class="mobileDock__item mobileDock__item--accent<?= $pageSlug === 'new-post' ? ' is-active' : '' ?>" href="<?= $mobileCreateHref ?>">
        <span class="mobileDock__icon" aria-hidden="true">+</span>
        <span>Create</span>
      </a>

      <a class="mobileDock__item<?= $mobileDockInboxActive ? ' is-active' : '' ?>" href="<?= $mobileInboxHref ?>">
        <span class="mobileDock__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="M4.75 6.75h14.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H9.5l-4.75 3v-3H4.75a1.5 1.5 0 0 1-1.5-1.5v-7.5a1.5 1.5 0 0 1 1.5-1.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
          </svg>
        </span>
        <span>Inbox</span>
      </a>

      <a class="mobileDock__item<?= $mobileDockActivityActive ? ' is-active' : '' ?>" href="<?= $mobileActivityHref ?>">
        <span class="mobileDock__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path fill="currentColor" d="M12 3a5 5 0 0 0-5 5v1.2c0 .9-.28 1.78-.81 2.5l-1.1 1.54A2 2 0 0 0 6.72 17h10.56a2 2 0 0 0 1.63-3.16l-1.1-1.54A4.3 4.3 0 0 1 17 9.2V8a5 5 0 0 0-5-5Zm0 18a2.75 2.75 0 0 0 2.58-1.8.75.75 0 0 0-.7-1.02h-3.76a.75.75 0 0 0-.7 1.02A2.75 2.75 0 0 0 12 21Z" />
          </svg>
        </span>
        <span>Activity</span>
      </a>
    </nav>

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

    <div class="shellSheet" data-shell-sheet="account" hidden>
      <div class="shellSheet__backdrop" data-shell-sheet-close="1"></div>
      <section class="shellSheet__panel" role="dialog" aria-modal="true" aria-labelledby="shellAccountTitle">
        <header class="shellSheet__head">
          <div>
            <span class="shellSheet__eyebrow">Account</span>
            <h2 id="shellAccountTitle"><?= $user ? '@' . trux_e((string)$user['username']) : 'Guest mode' ?></h2>
          </div>
          <button class="iconBtn" type="button" aria-label="Close account sheet" data-shell-sheet-close="1">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </header>

        <?php if ($user): ?>
          <div class="shellSheet__stack">
            <a class="shellSheet__link" href="<?= $selfProfileUrl ?>">
              <span class="shellSheet__itemIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('profile') ?></svg>
              </span>
              <span class="shellSheet__itemMain">
                <strong>Profile</strong>
                <span>Open your public identity page</span>
              </span>
            </a>
            <a class="shellSheet__link" href="<?= $editProfileUrl ?>">
              <span class="shellSheet__itemIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('edit') ?></svg>
              </span>
              <span class="shellSheet__itemMain">
                <strong>Edit profile</strong>
                <span>Banner, avatar, links, and about</span>
              </span>
            </a>
            <a class="shellSheet__link" href="<?= TRUX_BASE_URL ?>/bookmarks.php">
              <span class="shellSheet__itemIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('bookmarks') ?></svg>
              </span>
              <span class="shellSheet__itemMain">
                <strong>Bookmarks</strong>
                <span>Saved posts, comments, and replies</span>
              </span>
            </a>
            <a class="shellSheet__link" href="<?= $settingsUrl ?>">
              <span class="shellSheet__itemIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('settings') ?></svg>
              </span>
              <span class="shellSheet__itemMain">
                <strong>Settings</strong>
                <span>Notifications, privacy, and interface center</span>
              </span>
            </a>
            <?php if ($showProfileMenuModeration): ?>
              <a class="shellSheet__link" href="<?= TRUX_BASE_URL ?>/moderation/">
                <span class="shellSheet__itemIcon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('moderation') ?></svg>
                </span>
                <span class="shellSheet__itemMain">
                  <strong>Moderation</strong>
                  <span><?= $moderationBadgeTotal > 0 ? $moderationBadgeTotal . ' items waiting' : 'Open staff workspace' ?></span>
                </span>
              </a>
            <?php endif; ?>
            <form class="shellSheet__logout" method="post" action="<?= TRUX_BASE_URL ?>/logout.php">
              <?= trux_csrf_field() ?>
              <button class="shellButton shellButton--ghost shellButton--danger shellSheet__logoutButton" type="submit">
                <span class="shellSheet__itemIcon shellSheet__itemIcon--danger" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('logout') ?></svg>
                </span>
                <span>Logout</span>
              </button>
            </form>
          </div>
        <?php else: ?>
          <div class="shellSheet__stack">
            <a class="shellSheet__link" href="<?= TRUX_BASE_URL ?>/login.php">
              <span class="shellSheet__itemIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('login') ?></svg>
              </span>
              <span class="shellSheet__itemMain">
                <strong>Login</strong>
                <span>Access messages, bookmarks, and posting</span>
              </span>
            </a>
            <a class="shellSheet__link" href="<?= TRUX_BASE_URL ?>/register.php">
              <span class="shellSheet__itemIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $menuIcon('register') ?></svg>
              </span>
              <span class="shellSheet__itemMain">
                <strong>Create account</strong>
                <span>Join TruX and build your workspace</span>
              </span>
            </a>
          </div>
        <?php endif; ?>
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
