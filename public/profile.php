<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$username = trim(trux_str_param('u', ''));
if ($username === '') {
    http_response_code(404);
    trux_flash_set('error', 'User not found.');
    trux_redirect('/');
}

$profileUser = trux_fetch_user_by_username($username);
if (!$profileUser) {
    http_response_code(404);
    trux_flash_set('error', 'User not found.');
    trux_redirect('/');
}

$me = trux_current_user();
$isSelf = $me && (int)$me['id'] === (int)$profileUser['id'];
$followCounts = trux_follow_counts((int)$profileUser['id']);
$isFollowing = false;
$isMuted = false;
if ($me && !$isSelf) {
    $isFollowing = trux_is_following((int)$me['id'], (int)$profileUser['id']);
    $isMuted = trux_has_muted_user((int)$me['id'], (int)$profileUser['id']);
}

$showLikesTab = $isSelf || !isset($profileUser['show_likes_public']) || !empty($profileUser['show_likes_public']);
$showBookmarksTab = $isSelf || !isset($profileUser['show_bookmarks_public']) || !empty($profileUser['show_bookmarks_public']);
$requestedTab = trim(trux_str_param('tab', 'posts'));
$allowedTabs = ['posts', 'replies', 'about'];
if ($showLikesTab) {
    $allowedTabs[] = 'liked';
}
if ($showBookmarksTab) {
    $allowedTabs[] = 'bookmarks';
}

$blockedTab = null;
if ($requestedTab === 'liked' && !$showLikesTab) {
    $tab = 'liked';
    $blockedTab = 'liked';
} elseif ($requestedTab === 'bookmarks' && !$showBookmarksTab) {
    $tab = 'bookmarks';
    $blockedTab = 'bookmarks';
} elseif (in_array($requestedTab, $allowedTabs, true)) {
    $tab = $requestedTab;
} else {
    $tab = 'posts';
}

$before = trux_int_param('before', 0);
$repliesPage = max(1, trux_int_param('replies_page', 1));
$likedPostsPage = max(1, trux_int_param('liked_posts_page', 1));
$likedCommentsPage = max(1, trux_int_param('liked_comments_page', 1));
$bookmarkPostsPage = max(1, trux_int_param('bookmark_posts_page', 1));
$bookmarkCommentsPage = max(1, trux_int_param('bookmark_comments_page', 1));

$joinedDate = '-';
$joinedDateRaw = '';
$joinedDateExact = '';
if (!empty($profileUser['created_at']) && is_string($profileUser['created_at'])) {
    try {
        $joinedDateRaw = (string)$profileUser['created_at'];
        $joinedDate = trux_time_ago($joinedDateRaw);
        $joinedDateExact = trux_format_exact_time($joinedDateRaw);
    } catch (Exception) {
        $joinedDate = '-';
        $joinedDateRaw = '';
        $joinedDateExact = '';
    }
}

$displayName = trim((string)($profileUser['display_name'] ?? ''));
$bio = trim((string)($profileUser['bio'] ?? ''));
$aboutMe = trim((string)($profileUser['about_me'] ?? ''));
$location = trim((string)($profileUser['location'] ?? ''));
$avatarPath = trim((string)($profileUser['avatar_path'] ?? ''));
$bannerPath = trim((string)($profileUser['banner_path'] ?? ''));
$avatarUrl = $avatarPath !== '' ? trux_public_url($avatarPath) : '';
$bannerUrl = $bannerPath !== '' ? trux_public_url($bannerPath) : '';
$websiteUrl = trim((string)($profileUser['website_url'] ?? ''));
$websiteLabel = '';
if ($websiteUrl !== '') {
    $validatedWebsite = filter_var($websiteUrl, FILTER_VALIDATE_URL);
    if (is_string($validatedWebsite) && $validatedWebsite !== '') {
        $websiteUrl = $validatedWebsite;
        $websiteLabel = trux_profile_website_label($validatedWebsite);
    } else {
        $websiteUrl = '';
    }
}
$profileLinks = trux_profile_prepare_links_for_display(
    trux_profile_decode_links((string)($profileUser['profile_links_json'] ?? ''))
);

$posts = [];
$nextBefore = null;
$postInteractionMap = [];
$replyItems = [];
$replyVoteMap = [];
$hasMoreReplies = false;
$likedPosts = [];
$likedComments = [];
$likedInteractionMap = [];
$likedCommentVoteMap = [];
$hasMoreLikedPosts = false;
$hasMoreLikedComments = false;
$bookmarkedPosts = [];
$bookmarkedComments = [];
$bookmarkInteractionMap = [];
$bookmarkCommentVoteMap = [];
$hasMoreBookmarkedPosts = false;
$hasMoreBookmarkedComments = false;

if ($blockedTab === null) {
    if ($tab === 'posts') {
        $posts = trux_fetch_posts_by_user((int)$profileUser['id'], 20, $before > 0 ? $before : null);
        if ($posts) {
            $nextBefore = (int)$posts[count($posts) - 1]['id'];
        }
        $postInteractionMap = trux_fetch_post_interactions(trux_collect_post_ids($posts), $me ? (int)$me['id'] : null);
    } elseif ($tab === 'replies') {
        $replyItems = trux_fetch_comments_by_user((int)$profileUser['id'], 16, ($repliesPage - 1) * 15);
        $hasMoreReplies = count($replyItems) > 15;
        if ($hasMoreReplies) {
            array_pop($replyItems);
        }
        $replyVoteMap = trux_fetch_comment_vote_stats(trux_collect_comment_ids($replyItems), $me ? (int)$me['id'] : null);
    } elseif ($tab === 'liked') {
        $likedPosts = trux_fetch_user_liked_posts((int)$profileUser['id'], 13, ($likedPostsPage - 1) * 12);
        $hasMoreLikedPosts = count($likedPosts) > 12;
        if ($hasMoreLikedPosts) {
            array_pop($likedPosts);
        }
        $likedComments = trux_fetch_user_liked_comments((int)$profileUser['id'], 16, ($likedCommentsPage - 1) * 15);
        $hasMoreLikedComments = count($likedComments) > 15;
        if ($hasMoreLikedComments) {
            array_pop($likedComments);
        }
        $likedInteractionMap = trux_fetch_post_interactions(trux_collect_post_ids($likedPosts), $me ? (int)$me['id'] : null);
        $likedCommentVoteMap = trux_fetch_comment_vote_stats(trux_collect_comment_ids($likedComments), $me ? (int)$me['id'] : null);
    } elseif ($tab === 'bookmarks') {
        $bookmarkedPosts = trux_fetch_user_bookmarked_posts((int)$profileUser['id'], 13, ($bookmarkPostsPage - 1) * 12);
        $hasMoreBookmarkedPosts = count($bookmarkedPosts) > 12;
        if ($hasMoreBookmarkedPosts) {
            array_pop($bookmarkedPosts);
        }
        $bookmarkedComments = trux_fetch_user_bookmarked_comments((int)$profileUser['id'], 16, ($bookmarkCommentsPage - 1) * 15);
        $hasMoreBookmarkedComments = count($bookmarkedComments) > 15;
        if ($hasMoreBookmarkedComments) {
            array_pop($bookmarkedComments);
        }
        $bookmarkInteractionMap = trux_fetch_post_interactions(trux_collect_post_ids($bookmarkedPosts), $me ? (int)$me['id'] : null);
        $bookmarkCommentVoteMap = trux_fetch_comment_vote_stats(trux_collect_comment_ids($bookmarkedComments), $me ? (int)$me['id'] : null);
    }
}

$profileUrl = static function (array $overrides = []) use (
    $username,
    $tab,
    $before,
    $repliesPage,
    $likedPostsPage,
    $likedCommentsPage,
    $bookmarkPostsPage,
    $bookmarkCommentsPage
): string {
    $params = [
        'u' => $username,
        'tab' => $tab,
        'before' => $before > 0 ? $before : null,
        'replies_page' => $repliesPage,
        'liked_posts_page' => $likedPostsPage,
        'liked_comments_page' => $likedCommentsPage,
        'bookmark_posts_page' => $bookmarkPostsPage,
        'bookmark_comments_page' => $bookmarkCommentsPage,
    ];
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    return TRUX_BASE_URL . '/profile.php?' . http_build_query($params);
};

$excerpt = static function (string $text, int $limit = 180): string {
    $value = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if ($value === '') {
        return '';
    }
    if (mb_strlen($value) <= $limit) {
        return $value;
    }
    return rtrim(mb_substr($value, 0, $limit - 3)) . '...';
};

$renderPosts = static function (array $items, array $interactionMap, ?array $viewer, string $timeKey = '', string $timePrefix = ''): void {
    foreach ($items as $p) {
        $editedAt = isset($p['edited_at']) && $p['edited_at'] !== null ? (string)$p['edited_at'] : '';
        $postAvatarPath = trim((string)($p['avatar_path'] ?? ''));
        $postAvatarUrl = $postAvatarPath !== '' ? trux_public_url($postAvatarPath) : '';
        $postImagePath = trim((string)($p['image_path'] ?? ''));
        $postImageUrl = $postImagePath !== '' ? trux_public_url($postImagePath) : '';
        ?>
        <article class="card post" data-post-id="<?= (int)$p['id'] ?>">
          <div class="card__body">
            <div class="post__head">
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
                  <?php if ($timeKey !== '' && !empty($p[$timeKey])): ?>
                    <span class="post__dot" aria-hidden="true">&bull;</span>
                    <span class="muted">
                      <?php if ($timePrefix !== ''): ?>
                        <?= trux_e($timePrefix) ?>
                      <?php endif; ?>
                      <span data-time-ago="1" data-time-source="<?= trux_e((string)$p[$timeKey]) ?>" title="<?= trux_e(trux_format_exact_time((string)$p[$timeKey])) ?>">
                        <?= trux_e(trux_time_ago((string)$p[$timeKey])) ?>
                      </span>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($viewer && (int)$p['user_id'] === (int)$viewer['id']): ?>
                <div class="post__actions">
                  <?php
                  $entityType = 'post';
                  $entityId = (int)$p['id'];
                  require __DIR__ . '/_owner_actions_menu.php';
                  ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="post__body"><?= trux_render_post_body((string)$p['body']) ?></div>
            <?php if ($postImageUrl !== ''): ?>
              <div class="post__image">
                <img src="<?= trux_e($postImageUrl) ?>" alt="Post image" loading="lazy" decoding="async">
              </div>
            <?php endif; ?>
            <?php
            $postId = (int)$p['id'];
            $stats = $interactionMap[$postId] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false];
            $isLoggedIn = (bool)$viewer;
            require __DIR__ . '/_post_actions_bar.php';
            ?>
          </div>
        </article>
        <?php
    }
};

$renderCommentCards = static function (array $items, array $voteMap, string $mode) use ($excerpt): void {
    foreach ($items as $comment) {
        $commentId = (int)$comment['id'];
        $score = (int)(($voteMap[$commentId] ?? ['score' => 0])['score'] ?? 0);
        $isReply = !empty($comment['parent_comment_id']);
        $postExcerpt = $excerpt((string)($comment['post_body'] ?? ''));
        $title = match ($mode) {
            'liked' => $isReply ? 'Liked reply' : 'Liked comment',
            'bookmarks' => $isReply ? 'Saved reply' : 'Saved comment',
            default => $isReply ? 'Reply' : 'Comment',
        };
        $timeKey = match ($mode) {
            'liked' => 'liked_at',
            'bookmarks' => 'bookmarked_at',
            default => 'created_at',
        };
        $timePrefix = match ($mode) {
            'liked' => 'Liked ',
            'bookmarks' => 'Saved ',
            default => '',
        };
        ?>
        <article class="profileActivityCard">
          <div class="profileActivityCard__head">
            <div class="profileActivityCard__title">
              <strong><?= $title ?></strong>
              <?php if ($mode === 'replies' && $isReply && !empty($comment['reply_to_username'])): ?>
                <span class="muted">to @<?= trux_e((string)$comment['reply_to_username']) ?></span>
              <?php elseif ($mode !== 'replies'): ?>
                <span class="muted">by <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)$comment['username']) ?>">@<?= trux_e((string)$comment['username']) ?></a></span>
              <?php endif; ?>
            </div>
            <div class="profileActivityCard__meta muted">
              <span>
                <?php if ($timePrefix !== ''): ?>
                  <?= trux_e($timePrefix) ?>
                <?php endif; ?>
                <span
                  data-time-ago="1"
                  data-time-source="<?= trux_e((string)$comment[$timeKey]) ?>"
                  title="<?= trux_e(trux_format_exact_time((string)$comment[$timeKey])) ?>">
                  <?= trux_e(trux_time_ago((string)$comment[$timeKey])) ?>
                </span>
              </span>
              <span>&middot;</span>
              <span>Score <?= $score ?></span>
            </div>
          </div>
          <div class="post__body"><?= trux_render_comment_body((string)$comment['body']) ?></div>
          <div class="profileActivityCard__context">
            <span class="muted">On <a href="<?= TRUX_BASE_URL ?>/post.php?id=<?= (int)$comment['post_id'] ?>&comment_id=<?= $commentId ?>">post #<?= (int)$comment['post_id'] ?></a> by <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)($comment['post_username'] ?? '')) ?>">@<?= trux_e((string)($comment['post_username'] ?? '')) ?></a></span>
            <?php if ($postExcerpt !== ''): ?>
              <div class="profileActivityCard__excerpt"><?= trux_e($postExcerpt) ?></div>
            <?php endif; ?>
          </div>
        </article>
        <?php
    }
};

require_once __DIR__ . '/_header.php';
?>

<div class="profile">
  <section class="profile__hero">
    <div class="profile__banner" aria-hidden="true">
      <?php if ($bannerUrl !== ''): ?>
        <img class="profile__bannerImage" src="<?= trux_e($bannerUrl) ?>" alt="" loading="lazy" decoding="async">
      <?php endif; ?>
    </div>
    <div class="profile__identity">
      <div class="profile__avatar<?= $avatarUrl !== '' ? ' profile__avatar--image' : '' ?>" aria-hidden="true">
        <?php if ($avatarUrl !== ''): ?>
          <img class="profile__avatarImage" src="<?= trux_e($avatarUrl) ?>" alt="">
        <?php endif; ?>
      </div>
      <div class="profile__titleBlock">
        <h1 class="profile__username"><?= $displayName !== '' ? trux_e($displayName) : '@' . trux_e((string)$profileUser['username']) ?></h1>
        <div class="profile__subtitle">@<?= trux_e((string)$profileUser['username']) ?></div>
        <?php if ($bio !== ''): ?>
          <p class="profile__bio"><?= nl2br(trux_e($bio)) ?></p>
        <?php endif; ?>
        <?php if ($location !== '' || $websiteUrl !== ''): ?>
          <div class="profileMeta">
            <?php if ($location !== ''): ?>
              <span class="profileMeta__item"><?= trux_e($location) ?></span>
            <?php endif; ?>
            <?php if ($websiteUrl !== ''): ?>
              <a class="profileMeta__item" href="<?= trux_e($websiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= trux_e($websiteLabel !== '' ? $websiteLabel : $websiteUrl) ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="profile__stats">
    <div class="profileStats">
      <div class="profileStats__left">
        <div class="profileStat">
          <div class="profileStat__value"><?= number_format((int)$followCounts['followers']) ?></div>
          <div class="profileStat__label">Followers</div>
        </div>
        <div class="profileStat">
          <div class="profileStat__value"><?= number_format((int)$followCounts['following']) ?></div>
          <div class="profileStat__label">Following</div>
        </div>
        <div class="profileStat">
          <div class="profileStat__value" <?= $joinedDateRaw !== '' ? 'data-time-ago="1" data-time-source="' . trux_e($joinedDateRaw) . '" title="' . trux_e($joinedDateExact) . '"' : '' ?>><?= trux_e($joinedDate) ?></div>
          <div class="profileStat__label">Joined</div>
        </div>
      </div>
      <div class="profileStats__right">
        <?php if ($isSelf): ?>
          <a class="btn btn--neonFollow" href="<?= TRUX_BASE_URL ?>/edit_profile.php"><span class="btn__text">Edit Profile</span></a>
        <?php elseif (!$me): ?>
          <button class="btn btn--neonFollow is-disabled" type="button" disabled><span class="btn__text">Log in to follow</span></button>
        <?php else: ?>
          <div class="profileActions">
            <form class="profileFollowForm" method="post" action="<?= TRUX_BASE_URL ?>/follow.php">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>">
              <input type="hidden" name="user_id" value="<?= (int)$profileUser['id'] ?>">
              <input type="hidden" name="user" value="<?= trux_e((string)$profileUser['username']) ?>">
              <button class="btn btn--neonFollow<?= $isFollowing ? ' is-following' : '' ?>" type="submit"><span class="btn__text"><?= $isFollowing ? 'Following' : 'Follow' ?></span></button>
            </form>
            <form class="profileMuteForm" method="post" action="<?= TRUX_BASE_URL ?>/mute_user.php">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="<?= $isMuted ? 'unmute' : 'mute' ?>">
              <input type="hidden" name="user_id" value="<?= (int)$profileUser['id'] ?>">
              <input type="hidden" name="user" value="<?= trux_e((string)$profileUser['username']) ?>">
              <button class="btn btn--small btn--ghost<?= $isMuted ? ' is-active' : '' ?>" type="submit"><?= $isMuted ? 'Muted' : 'Mute' ?></button>
            </form>
            <a class="btn btn--small btn--ghost" href="<?= TRUX_BASE_URL ?>/messages.php?with=<?= trux_e(rawurlencode((string)$profileUser['username'])) ?>">Message</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="card profile__tabs">
    <div class="card__body">
      <nav class="profileTabs" aria-label="Profile sections">
        <?php foreach ([
            'posts' => 'Posts',
            'replies' => 'Replies',
            'liked' => 'Liked',
            'bookmarks' => 'Bookmarks',
            'about' => 'About Me',
        ] as $tabKey => $tabLabel): ?>
          <?php if (($tabKey === 'liked' && !$showLikesTab) || ($tabKey === 'bookmarks' && !$showBookmarksTab)) continue; ?>
          <a
            class="profileTabs__item<?= $tab === $tabKey ? ' is-active' : '' ?>"
            href="<?= trux_e($profileUrl(['tab' => $tabKey, 'before' => null, 'replies_page' => 1, 'liked_posts_page' => 1, 'liked_comments_page' => 1, 'bookmark_posts_page' => 1, 'bookmark_comments_page' => 1])) ?>"
            <?= $tab === $tabKey ? 'aria-current="page"' : '' ?>>
            <?= trux_e($tabLabel) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </section>

  <?php if ($blockedTab !== null): ?>
    <section class="card profilePanelCard">
      <div class="card__body profileLockNote">
        <h2 class="h2"><?= $blockedTab === 'liked' ? 'Likes Hidden' : 'Bookmarks Hidden' ?></h2>
        <p class="muted">@<?= trux_e((string)$profileUser['username']) ?> has chosen to keep this section private.</p>
      </div>
    </section>
  <?php elseif ($tab === 'posts'): ?>
    <section class="profile__feed">
      <div class="profile__feedHead"><h2 class="profile__feedTitle">Posts</h2></div>
      <div class="profile__posts feed">
        <?php if (!$posts): ?>
          <div class="card"><div class="card__body">No posts yet.</div></div>
        <?php else: ?>
          <?php $renderPosts($posts, $postInteractionMap, $me); ?>
        <?php endif; ?>
      </div>
      <?php if ($nextBefore): ?>
        <div class="pager"><a class="btn" href="<?= trux_e($profileUrl(['tab' => 'posts', 'before' => $nextBefore])) ?>">Load more</a></div>
      <?php endif; ?>
    </section>
  <?php elseif ($tab === 'replies'): ?>
    <section class="card profilePanelCard">
      <div class="card__body">
        <div class="profilePanelHead">
          <h2 class="h2">Replies</h2>
          <p class="muted">Every comment and reply posted by @<?= trux_e((string)$profileUser['username']) ?>.</p>
        </div>
        <?php if (!$replyItems): ?>
          <div class="profileEmptyState"><?= $isSelf ? 'You have not posted any comments or replies yet.' : '@' . trux_e((string)$profileUser['username']) . ' has not posted any comments or replies yet.' ?></div>
        <?php else: ?>
          <div class="profileActivityList"><?php $renderCommentCards($replyItems, $replyVoteMap, 'replies'); ?></div>
        <?php endif; ?>
        <?php if ($hasMoreReplies): ?>
          <div class="pager"><a class="btn" href="<?= trux_e($profileUrl(['tab' => 'replies', 'replies_page' => $repliesPage + 1])) ?>">Load more replies</a></div>
        <?php endif; ?>
      </div>
    </section>
  <?php elseif ($tab === 'liked'): ?>
    <section class="profile__feed">
      <div class="profile__feedHead"><h2 class="profile__feedTitle">Liked</h2></div>
      <div class="card profilePanelCard">
        <div class="card__body">
          <div class="profilePanelHead">
            <h3 class="h2">Liked posts</h3>
            <p class="muted">Posts @<?= trux_e((string)$profileUser['username']) ?> has liked.</p>
          </div>
          <?php if (!$likedPosts): ?>
            <div class="profileEmptyState">No liked posts on this page.</div>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($likedPosts): ?>
        <div class="profile__posts feed">
          <?php $renderPosts($likedPosts, $likedInteractionMap, $me, 'liked_at', 'Liked '); ?>
        </div>
      <?php endif; ?>
      <?php if ($hasMoreLikedPosts): ?>
        <div class="pager"><a class="btn" href="<?= trux_e($profileUrl(['tab' => 'liked', 'liked_posts_page' => $likedPostsPage + 1])) ?>">Load more liked posts</a></div>
      <?php endif; ?>
      <section class="card profilePanelCard">
        <div class="card__body">
          <div class="profilePanelHead">
            <h3 class="h2">Liked comments and replies</h3>
            <p class="muted">Comments and replies @<?= trux_e((string)$profileUser['username']) ?> has upvoted.</p>
          </div>
          <?php if (!$likedComments): ?>
            <div class="profileEmptyState">No liked comments or replies on this page.</div>
          <?php else: ?>
            <div class="profileActivityList"><?php $renderCommentCards($likedComments, $likedCommentVoteMap, 'liked'); ?></div>
          <?php endif; ?>
          <?php if ($hasMoreLikedComments): ?>
            <div class="pager"><a class="btn" href="<?= trux_e($profileUrl(['tab' => 'liked', 'liked_comments_page' => $likedCommentsPage + 1])) ?>">Load more liked comments</a></div>
          <?php endif; ?>
        </div>
      </section>
    </section>
  <?php elseif ($tab === 'bookmarks'): ?>
    <section class="profile__feed">
      <div class="profile__feedHead"><h2 class="profile__feedTitle">Bookmarks</h2></div>
      <div class="card profilePanelCard">
        <div class="card__body">
          <div class="profilePanelHead">
            <h3 class="h2">Saved posts</h3>
            <p class="muted">Posts @<?= trux_e((string)$profileUser['username']) ?> has bookmarked.</p>
          </div>
          <?php if (!$bookmarkedPosts): ?>
            <div class="profileEmptyState">No bookmarked posts on this page.</div>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($bookmarkedPosts): ?>
        <div class="profile__posts feed">
          <?php $renderPosts($bookmarkedPosts, $bookmarkInteractionMap, $me, 'bookmarked_at', 'Saved '); ?>
        </div>
      <?php endif; ?>
      <?php if ($hasMoreBookmarkedPosts): ?>
        <div class="pager"><a class="btn" href="<?= trux_e($profileUrl(['tab' => 'bookmarks', 'bookmark_posts_page' => $bookmarkPostsPage + 1])) ?>">Load more bookmarked posts</a></div>
      <?php endif; ?>
      <section class="card profilePanelCard">
        <div class="card__body">
          <div class="profilePanelHead">
            <h3 class="h2">Saved comments and replies</h3>
            <p class="muted">Comments and replies @<?= trux_e((string)$profileUser['username']) ?> has bookmarked.</p>
          </div>
          <?php if (!$bookmarkedComments): ?>
            <div class="profileEmptyState">No bookmarked comments or replies on this page.</div>
          <?php else: ?>
            <div class="profileActivityList"><?php $renderCommentCards($bookmarkedComments, $bookmarkCommentVoteMap, 'bookmarks'); ?></div>
          <?php endif; ?>
          <?php if ($hasMoreBookmarkedComments): ?>
            <div class="pager"><a class="btn" href="<?= trux_e($profileUrl(['tab' => 'bookmarks', 'bookmark_comments_page' => $bookmarkCommentsPage + 1])) ?>">Load more bookmarked comments</a></div>
          <?php endif; ?>
        </div>
      </section>
    </section>
  <?php else: ?>
    <section class="card profilePanelCard">
      <div class="card__body">
        <div class="profilePanelHead">
          <h2 class="h2">About Me</h2>
          <p class="muted">Long-form profile details, affiliations, and off-platform links.</p>
        </div>
        <?php if ($aboutMe !== ''): ?>
          <div class="profileAbout__body"><?= trux_render_rich_text($aboutMe) ?></div>
        <?php else: ?>
          <div class="profileEmptyState">
            <?php if ($isSelf): ?>
              Your About Me section is empty. <a href="<?= TRUX_BASE_URL ?>/edit_profile.php">Add a longer profile description</a>.
            <?php else: ?>
              @<?= trux_e((string)$profileUser['username']) ?> has not added an About Me section yet.
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="profileAbout__linksWrap">
          <h3 class="h2">Affiliated Links</h3>
          <?php if (!$profileLinks): ?>
            <div class="profileEmptyState profileEmptyState--compact">
              <?php if ($isSelf): ?>
                No affiliated links added yet. <a href="<?= TRUX_BASE_URL ?>/edit_profile.php">Add up to <?= trux_profile_link_limit() ?> links</a>.
              <?php else: ?>
                No affiliated links shared yet.
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="profileLinks">
              <?php foreach ($profileLinks as $link): ?>
                <a class="profileLink" href="<?= trux_e((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer">
                  <span class="profileLink__icon profileLink__icon--<?= trux_e((string)$link['provider']) ?>" aria-hidden="true"><?= $link['icon_svg'] ?></span>
                  <span class="profileLink__label"><?= trux_e((string)$link['label']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
