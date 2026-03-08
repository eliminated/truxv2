<?php
declare(strict_types=1);
?>
</main>

<div id="commentDock" class="commentDock" hidden>
  <div class="commentDock__backdrop" data-comment-close="1"></div>
  <section class="commentDock__panel" role="dialog" aria-modal="true" aria-labelledby="commentDockTitle">
    <header class="commentDock__head">
      <h2 id="commentDockTitle">Post Comments</h2>
      <div class="commentDock__headActions">
        <a class="btn btn--small btn--ghost commentDock__openPost" href="/" target="_self" data-comment-open-post="1">Open post</a>
        <button class="iconBtn" type="button" aria-label="Close comments" data-comment-close="1">x</button>
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
          <form class="commentDock__form" method="post" action="/comment_post.php" data-comment-form="1" data-no-fx="1">
            <input type="hidden" name="_csrf" value="<?= trux_e(trux_csrf_token()) ?>">
            <input type="hidden" name="id" value="" data-comment-post-id="1">
            <input type="hidden" name="parent_id" value="" data-comment-parent-id="1">
            <input type="hidden" name="reply_to_user_id" value="" data-comment-reply-user-id="1">
            <div class="commentDock__replying muted" data-comment-replying="1" hidden>
              Replying to <span data-comment-replying-user="1"></span>
              <button type="button" class="commentDock__replyCancel" data-comment-reply-cancel="1">Cancel</button>
            </div>
            <label class="field">
              <span>Add a comment</span>
              <textarea name="body" rows="3" maxlength="1000" required placeholder="Write your comment..." data-mention-input="1"></textarea>
            </label>
            <div class="row">
              <button class="btn btn--small" type="submit">Comment</button>
            </div>
          </form>
        <?php else: ?>
          <div class="commentDock__login muted">
            Log in to comment. <a href="/login.php">Go to login</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<footer class="footer">
  <div class="container footer__inner">
    <div class="muted">&copy; <?= date('Y') ?> <?= trux_e(TRUX_APP_NAME) ?> &middot; Built with PHP + MySQL</div>
  </div>
</footer>
</body>
</html>
