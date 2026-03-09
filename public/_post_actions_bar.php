<?php
declare(strict_types=1);

$postId = isset($postId) ? (int)$postId : 0;
$stats = is_array($stats ?? null) ? $stats : [];
$likesCount = (int)($stats['likes'] ?? 0);
$commentsCount = (int)($stats['comments'] ?? 0);
$sharesCount = (int)($stats['shares'] ?? 0);
$liked = (bool)($stats['liked'] ?? false);
$shared = (bool)($stats['shared'] ?? false);
$bookmarked = (bool)($stats['bookmarked'] ?? false);
$isLoggedIn = (bool)$isLoggedIn;
?>
<div class="post__actionsBar" aria-label="Post actions">
  <?php if ($isLoggedIn): ?>
    <form class="postActForm" method="post" action="/like_post.php" data-ajax-action="1" data-action-kind="like" data-post-id="<?= $postId ?>" data-no-fx="1">
      <?= trux_csrf_field() ?>
      <input type="hidden" name="id" value="<?= $postId ?>">
      <button class="postAct<?= $liked ? ' is-active' : '' ?>" type="submit" aria-label="<?= $liked ? 'Unlike post' : 'Like post' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 20.2s-6.8-4.4-8.9-8.2c-1.7-3 .2-7 3.8-7 2 0 3.1 1.1 4.1 2.5 1-1.4 2.1-2.5 4.1-2.5 3.6 0 5.5 4 3.8 7-2.1 3.8-8.9 8.2-8.9 8.2Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Like</span>
        <span class="postAct__count" data-like-count-for="<?= $postId ?>"><?= $likesCount ?></span>
      </button>
    </form>
  <?php else: ?>
    <a class="postAct" href="/login.php" aria-label="Log in to like this post">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M12 20.2s-6.8-4.4-8.9-8.2c-1.7-3 .2-7 3.8-7 2 0 3.1 1.1 4.1 2.5 1-1.4 2.1-2.5 4.1-2.5 3.6 0 5.5 4 3.8 7-2.1 3.8-8.9 8.2-8.9 8.2Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Like</span>
      <span class="postAct__count" data-like-count-for="<?= $postId ?>"><?= $likesCount ?></span>
    </a>
  <?php endif; ?>

  <button
    class="postAct"
    type="button"
    data-comment-open="1"
    data-post-id="<?= $postId ?>"
    data-post-url="/post.php?id=<?= $postId ?>"
    aria-label="Open comments">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M20 14.6c0 2-1.8 3.6-4 3.6H9l-4 3V6.8c0-2 1.8-3.6 4-3.6h7c2.2 0 4 1.6 4 3.6v7.8Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span>Comment</span>
    <span class="postAct__count" data-comment-count-for="<?= $postId ?>"><?= $commentsCount ?></span>
  </button>

  <?php if ($isLoggedIn): ?>
    <form class="postActForm" method="post" action="/share_post.php" data-ajax-action="1" data-action-kind="share" data-post-id="<?= $postId ?>" data-no-fx="1">
      <?= trux_csrf_field() ?>
      <input type="hidden" name="id" value="<?= $postId ?>">
      <button class="postAct<?= $shared ? ' is-active' : '' ?>" type="submit" aria-label="<?= $shared ? 'Unshare post' : 'Share post' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M14 5h5v5M10 14 19 5M19 13v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Share</span>
        <span class="postAct__count" data-share-count-for="<?= $postId ?>"><?= $sharesCount ?></span>
      </button>
    </form>
  <?php else: ?>
    <a class="postAct" href="/login.php" aria-label="Log in to share this post">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M14 5h5v5M10 14 19 5M19 13v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span>Share</span>
      <span class="postAct__count" data-share-count-for="<?= $postId ?>"><?= $sharesCount ?></span>
    </a>
  <?php endif; ?>

  <?php if ($isLoggedIn): ?>
    <form class="postActForm" method="post" action="/bookmark_post.php" data-ajax-action="1" data-action-kind="bookmark" data-post-id="<?= $postId ?>" data-no-fx="1">
      <?= trux_csrf_field() ?>
      <input type="hidden" name="id" value="<?= $postId ?>">
      <button class="postAct<?= $bookmarked ? ' is-active' : '' ?>" type="submit" aria-label="<?= $bookmarked ? 'Remove bookmark' : 'Bookmark post' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M7 4.8h10a1 1 0 0 1 1 1V20l-6-3.8L6 20V5.8a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
        </svg>
        <span data-action-label="bookmark"><?= $bookmarked ? 'Saved' : 'Bookmark' ?></span>
      </button>
    </form>
  <?php else: ?>
    <a class="postAct" href="/login.php" aria-label="Log in to bookmark this post">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M7 4.8h10a1 1 0 0 1 1 1V20l-6-3.8L6 20V5.8a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
      </svg>
      <span>Bookmark</span>
    </a>
  <?php endif; ?>
</div>
