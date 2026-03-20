<?php
declare(strict_types=1);
?>
</main>

<div id="commentDock" class="commentDock" hidden>
  <div class="commentDock__backdrop" data-comment-close="1"></div>
  <section class="commentDock__panel" role="dialog" aria-modal="true" aria-labelledby="commentDockTitle">
    <header class="commentDock__head">
      <h2 id="commentDockTitle">Post Viewer</h2>
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
              <button class="btn btn--small commentDock__submit" type="submit">Post comment</button>
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
      <h2 id="entityEditTitle" data-edit-title="1">Edit content</h2>
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
        <button class="btn btn--small btn--ghost" type="button" data-edit-cancel="1">Cancel</button>
        <button class="btn btn--small entityEditModal__submit" type="submit" data-edit-submit="1">Save changes</button>
      </div>
    </form>
  </section>
</div>

<footer class="footer">
  <div class="container footer__inner">
    <div class="muted">&copy; <?= date('Y') ?> <?= trux_e(TRUX_APP_NAME) ?> &middot; Built with PHP + MySQL</div>
  </div>
</footer>
</body>
</html>
