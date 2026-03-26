<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'bookmarks';
$pageLayout = 'app';

trux_require_login();
$me = trux_current_user();
if (!$me) {
  trux_redirect('/login.php');
}

$bookmarkFilter = trux_str_param('filter', 'all');
if (!in_array($bookmarkFilter, ['all', 'posts', 'comments'], true)) {
  $bookmarkFilter = 'all';
}

$postsPage = max(1, trux_int_param('posts_page', 1));
$commentsPage = max(1, trux_int_param('comments_page', 1));
$postsPerPage = 12;
$commentsPerPage = 15;

$showPosts = $bookmarkFilter === 'all' || $bookmarkFilter === 'posts';
$showComments = $bookmarkFilter === 'all' || $bookmarkFilter === 'comments';

$bookmarkedPosts = [];
$bookmarkedComments = [];
$hasMorePosts = false;
$hasMoreComments = false;

if ($showPosts) {
  $bookmarkedPosts = trux_fetch_user_bookmarked_posts(
    (int)$me['id'],
    $postsPerPage + 1,
    ($postsPage - 1) * $postsPerPage
  );
  $hasMorePosts = count($bookmarkedPosts) > $postsPerPage;
  if ($hasMorePosts) {
    array_pop($bookmarkedPosts);
  }
}

if ($showComments) {
  $bookmarkedComments = trux_fetch_user_bookmarked_comments(
    (int)$me['id'],
    $commentsPerPage + 1,
    ($commentsPage - 1) * $commentsPerPage
  );
  $hasMoreComments = count($bookmarkedComments) > $commentsPerPage;
  if ($hasMoreComments) {
    array_pop($bookmarkedComments);
  }
}

$postInteractionMap = trux_fetch_post_interactions(
  trux_collect_post_ids($bookmarkedPosts),
  (int)$me['id']
);

$commentVoteMap = trux_fetch_comment_vote_stats(
  trux_collect_comment_ids($bookmarkedComments),
  (int)$me['id']
);

require_once __DIR__ . '/_header.php';

$bookmarkBaseParams = static function (array $overrides = []) use ($bookmarkFilter, $postsPage, $commentsPage): string {
  $params = [
    'filter' => $bookmarkFilter,
    'posts_page' => $postsPage,
    'comments_page' => $commentsPage,
  ];

  foreach ($overrides as $key => $value) {
    $params[$key] = $value;
  }

  return TRUX_BASE_URL . '/bookmarks.php?' . http_build_query($params);
};
?>

<div class="pageFrame pageFrame--library">
  <section class="inlineHeader inlineHeader--library">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Saved workspace</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title">Bookmarks</h2>
        <p class="inlineHeader__copy">Posts, comments, and replies you want to revisit.</p>
      </div>
    </div>
    <div class="inlineHeader__aside">
      <nav class="segmented" aria-label="Bookmark filters">
        <a class="segmented__item<?= $bookmarkFilter === 'all' ? ' is-active' : '' ?>" href="<?= trux_e($bookmarkBaseParams(['filter' => 'all', 'posts_page' => 1, 'comments_page' => 1])) ?>" <?= $bookmarkFilter === 'all' ? 'aria-current="page"' : '' ?>>All</a>
        <a class="segmented__item<?= $bookmarkFilter === 'posts' ? ' is-active' : '' ?>" href="<?= trux_e($bookmarkBaseParams(['filter' => 'posts', 'posts_page' => 1, 'comments_page' => 1])) ?>" <?= $bookmarkFilter === 'posts' ? 'aria-current="page"' : '' ?>>Posts</a>
        <a class="segmented__item<?= $bookmarkFilter === 'comments' ? ' is-active' : '' ?>" href="<?= trux_e($bookmarkBaseParams(['filter' => 'comments', 'posts_page' => 1, 'comments_page' => 1])) ?>" <?= $bookmarkFilter === 'comments' ? 'aria-current="page"' : '' ?>>Comments</a>
      </nav>
      <div class="inlineHeader__meta">
        <span>@<?= trux_e((string)$me['username']) ?></span>
        <strong><?= count($bookmarkedPosts) + count($bookmarkedComments) ?> items visible</strong>
      </div>
    </div>
  </section>

  <div class="libraryScene">
    <?php if ($showPosts): ?>
      <section class="timelineFrame">
        <div class="timelineFrame__head">
          <div>
            <span class="timelineFrame__eyebrow">Posts</span>
            <h3>Saved posts</h3>
          </div>
          <span class="muted"><?= count($bookmarkedPosts) ?> loaded</span>
        </div>

        <?php if (!$bookmarkedPosts): ?>
          <section class="bandSurface bandSurface--empty">
            <strong>No saved posts</strong>
            <p class="muted"><?= $bookmarkFilter === 'posts' ? 'You have not bookmarked any posts yet.' : 'No bookmarked posts on this page.' ?></p>
          </section>
        <?php endif; ?>

        <div class="timeline" data-auto-pager-list="bookmarks-posts">
          <?php foreach ($bookmarkedPosts as $p): ?>
            <?php
            $postRecord = $p;
            $postViewer = $me;
            $postInteractionStats = $postInteractionMap[(int)$p['id']] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => true];
            $postContextTimeSource = (string)$p['bookmarked_at'];
            $postContextTimePrefix = 'Saved ';
            require __DIR__ . '/_post_card.php';
            unset($postContextTimeSource, $postContextTimePrefix);
            ?>
          <?php endforeach; ?>
        </div>

        <?php if ($hasMorePosts): ?>
          <div class="pager" data-auto-pager="bookmarks-posts">
            <a class="shellButton shellButton--ghost" data-no-fx="1" href="<?= trux_e($bookmarkBaseParams(['posts_page' => $postsPage + 1])) ?>">Load more posts</a>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($showComments): ?>
      <section class="bandSurface">
        <div class="bandSurface__head">
          <div>
            <span class="bandSurface__eyebrow">Comments</span>
            <h3>Saved comments and replies</h3>
          </div>
          <span class="bandSurface__meta"><?= count($bookmarkedComments) ?> loaded</span>
        </div>

        <?php if (!$bookmarkedComments): ?>
          <section class="bandSurface bandSurface--empty bandSurface--nested">
            <strong>No saved comments</strong>
            <p class="muted"><?= $bookmarkFilter === 'comments' ? 'You have not bookmarked any comments or replies yet.' : 'No bookmarked comments or replies on this page.' ?></p>
          </section>
        <?php else: ?>
          <div class="activityList">
            <?php foreach ($bookmarkedComments as $comment): ?>
              <?php
              $commentId = (int)$comment['id'];
              $vote = $commentVoteMap[$commentId] ?? ['score' => 0];
              $isReply = !empty($comment['parent_comment_id']);
              $postExcerpt = trim((string)($comment['post_body'] ?? ''));
              if (mb_strlen($postExcerpt) > 140) {
                $postExcerpt = mb_substr($postExcerpt, 0, 140) . '...';
              }
              ?>
              <article class="activityBand">
                <div class="activityBand__head">
                  <div>
                    <strong><?= $isReply ? 'Reply' : 'Comment' ?></strong>
                    <span class="muted">by <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$comment['username']) ?>">@<?= trux_e((string)$comment['username']) ?></a></span>
                  </div>
                  <span class="muted">Score <?= (int)$vote['score'] ?></span>
                </div>
                <div class="post__body"><?= trux_render_comment_body((string)$comment['body']) ?></div>
                <?php if ($postExcerpt !== ''): ?>
                  <div class="activityBand__context muted">Post context: <?= trux_e($postExcerpt) ?></div>
                <?php endif; ?>
                <div class="activityBand__actions">
                  <span class="muted">
                    Saved
                    <span data-time-ago="1" data-time-source="<?= trux_e((string)$comment['bookmarked_at']) ?>" title="<?= trux_e(trux_format_exact_time((string)$comment['bookmarked_at'])) ?>">
                      <?= trux_e(trux_time_ago((string)$comment['bookmarked_at'])) ?>
                    </span>
                  </span>
                  <div class="row">
                    <a class="shellButton shellButton--ghost" href="<?= trux_e(trux_post_viewer_url((int)$comment['post_id'], $commentId)) ?>">Open thread</a>
                    <form method="post" action="<?= TRUX_BASE_URL ?>/bookmark_comment.php" data-no-fx="1">
                      <?= trux_csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $commentId ?>">
                      <button class="shellButton shellButton--ghost" type="submit">Remove</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($hasMoreComments): ?>
          <div class="pager">
            <a class="shellButton shellButton--ghost" href="<?= trux_e($bookmarkBaseParams(['comments_page' => $commentsPage + 1])) ?>">Load more comments</a>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
