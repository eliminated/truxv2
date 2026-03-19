<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

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

    return '/bookmarks.php?' . http_build_query($params);
};
?>

<section class="hero">
  <h1>Bookmarks</h1>
  <p class="muted">Saved posts, comments, and replies you want to come back to.</p>

  <div class="feedSwitch" aria-label="Bookmark filters">
    <a
      class="feedSwitch__item<?= $bookmarkFilter === 'all' ? ' is-active' : '' ?>"
      href="<?= trux_e($bookmarkBaseParams(['filter' => 'all', 'posts_page' => 1, 'comments_page' => 1])) ?>"
      <?= $bookmarkFilter === 'all' ? 'aria-current="page"' : '' ?>>
      All
    </a>
    <a
      class="feedSwitch__item<?= $bookmarkFilter === 'posts' ? ' is-active' : '' ?>"
      href="<?= trux_e($bookmarkBaseParams(['filter' => 'posts', 'posts_page' => 1, 'comments_page' => 1])) ?>"
      <?= $bookmarkFilter === 'posts' ? 'aria-current="page"' : '' ?>>
      Posts
    </a>
    <a
      class="feedSwitch__item<?= $bookmarkFilter === 'comments' ? ' is-active' : '' ?>"
      href="<?= trux_e($bookmarkBaseParams(['filter' => 'comments', 'posts_page' => 1, 'comments_page' => 1])) ?>"
      <?= $bookmarkFilter === 'comments' ? 'aria-current="page"' : '' ?>>
      Comments
    </a>
  </div>
</section>

<section class="feed">
  <?php if ($showPosts): ?>
    <div class="card">
      <div class="card__body">
        <h2>Saved posts</h2>
        <?php if (!$bookmarkedPosts): ?>
          <p class="muted">
            <?= $bookmarkFilter === 'posts' ? 'No bookmarked posts yet.' : 'No bookmarked posts on this page.' ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ($bookmarkedPosts as $p): ?>
      <?php $editedAt = isset($p['edited_at']) && $p['edited_at'] !== null ? (string)$p['edited_at'] : ''; ?>
      <article class="card post" data-post-id="<?= (int)$p['id'] ?>">
        <div class="card__body">
          <div class="post__head">
            <?php
            $postAvatarPath = trim((string)($p['avatar_path'] ?? ''));
            $postAvatarUrl = $postAvatarPath !== '' ? trux_public_url($postAvatarPath) : '';
            ?>
            <a class="post__avatar<?= $postAvatarUrl !== '' ? ' post__avatar--image' : '' ?>" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$p['username']) ?>" aria-label="View @<?= trux_e((string)$p['username']) ?> profile">
              <?php if ($postAvatarUrl !== ''): ?>
                <img class="post__avatarImage" src="<?= trux_e($postAvatarUrl) ?>" alt="" loading="lazy" decoding="async">
              <?php endif; ?>
            </a>
            <div class="post__meta">
              <div class="post__nameRow">
                <a class="post__user" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$p['username']) ?>">@<?= trux_e((string)$p['username']) ?></a>
              </div>
              <div class="post__subRow">
                <span class="post__time" title="<?= trux_e(trux_format_exact_time((string)$p['created_at'])) ?>" data-time-ago="1" data-time-source="<?= trux_e((string)$p['created_at']) ?>">
                  <?= trux_e(trux_time_ago((string)$p['created_at'])) ?>
                </span>
                <?php if ($editedAt !== ''): ?>
                  <span class="editedMeta" data-post-edited-for="<?= (int)$p['id'] ?>">
                    <span class="editedMeta__label">EDITED AT</span>
                    <span class="editedMeta__time" title="<?= trux_e(trux_format_exact_time($editedAt)) ?>" data-time-ago="1" data-time-source="<?= trux_e($editedAt) ?>">
                      <?= trux_e(trux_time_ago($editedAt)) ?>
                    </span>
                  </span>
                <?php endif; ?>
                <span class="post__dot" aria-hidden="true">&bull;</span>
                <a class="post__id" href="<?= TRUX_BASE_URL ?>/post.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a>
                <span class="post__dot" aria-hidden="true">&bull;</span>
                <span class="muted">Saved <?= trux_e(trux_time_ago((string)$p['bookmarked_at'])) ?></span>
              </div>
            </div>
          </div>

          <div class="post__body"><?= trux_render_post_body((string)$p['body']) ?></div>

          <?php
          $postImagePath = trim((string)($p['image_path'] ?? ''));
          $postImageUrl = $postImagePath !== '' ? trux_public_url($postImagePath) : '';
          ?>
          <?php if ($postImageUrl !== ''): ?>
            <div class="post__image">
              <img src="<?= trux_e($postImageUrl) ?>" alt="Post image" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>

          <?php
          $postId = (int)$p['id'];
          $stats = $postInteractionMap[$postId] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => true];
          $isLoggedIn = true;
          require __DIR__ . '/_post_actions_bar.php';
          ?>
        </div>
      </article>
    <?php endforeach; ?>

    <?php if ($hasMorePosts): ?>
      <div class="pager">
        <a class="btn" href="<?= trux_e($bookmarkBaseParams(['posts_page' => $postsPage + 1])) ?>">Load more posts</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($showComments): ?>
    <div class="card">
      <div class="card__body">
        <h2>Saved comments and replies</h2>
        <?php if (!$bookmarkedComments): ?>
          <p class="muted">
            <?= $bookmarkFilter === 'comments' ? 'No bookmarked comments or replies yet.' : 'No bookmarked comments or replies on this page.' ?>
          </p>
        <?php else: ?>
          <div class="notificationList">
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
              <article class="notificationItem">
                <div class="notificationItem__body">
                  <div class="notificationItem__text">
                    <strong><?= $isReply ? 'Reply' : 'Comment' ?></strong>
                    by <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$comment['username']) ?>">@<?= trux_e((string)$comment['username']) ?></a>
                    on <a href="<?= TRUX_BASE_URL ?>/post.php?id=<?= (int)$comment['post_id'] ?>&comment_id=<?= $commentId ?>">post #<?= (int)$comment['post_id'] ?></a>
                  </div>
                  <div class="post__body"><?= trux_render_comment_body((string)$comment['body']) ?></div>
                  <?php if ($postExcerpt !== ''): ?>
                    <div class="muted">Post context: <?= trux_e($postExcerpt) ?></div>
                  <?php endif; ?>
                  <div class="row row--spaced">
                    <div class="muted">
                      Saved <?= trux_e(trux_time_ago((string)$comment['bookmarked_at'])) ?>
                      &middot; Score <?= (int)$vote['score'] ?>
                    </div>
                    <div class="row">
                      <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/post.php?id=<?= (int)$comment['post_id'] ?>&comment_id=<?= $commentId ?>">Open thread</a>
                      <form method="post" action="<?= TRUX_BASE_URL ?>/bookmark_comment.php" data-no-fx="1">
                        <?= trux_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $commentId ?>">
                        <button class="btn btn--small" type="submit">Remove bookmark</button>
                      </form>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($hasMoreComments): ?>
      <div class="pager">
        <a class="btn" href="<?= trux_e($bookmarkBaseParams(['comments_page' => $commentsPage + 1])) ?>">Load more comments</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!$showPosts && !$showComments): ?>
    <div class="card">
      <div class="card__body">
        <p class="muted">Nothing to show for this filter.</p>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
