<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'profile';
$pageLayout = 'app';

$username = trim(trux_str_param('u', ''));
if ($username === '') {
  http_response_code(404);
  trux_flash_set('error', 'User not found.');
  trux_redirect('/');
}

$profileUser = trux_fetch_user_by_username($username);
if (!$profileUser || trux_is_report_system_user((string)($profileUser['username'] ?? ''))) {
  http_response_code(404);
  trux_flash_set('error', 'User not found.');
  trux_redirect('/');
}

$me = trux_current_user();
$isSelf = $me && (int)$me['id'] === (int)$profileUser['id'];
$followCounts = trux_follow_counts((int)$profileUser['id']);
$isFollowing = false;
$isMuted = false;
$isBlocked       = $me && !$isSelf && trux_has_blocked_user((int)$me['id'], (int)$profileUser['id']);
$isBlockedByThem = $me && !$isSelf && trux_has_blocked_user((int)$profileUser['id'], (int)$me['id']);
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
$hasMorePosts = false;
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
    $posts = trux_fetch_posts_by_user((int)$profileUser['id'], 21, $before > 0 ? $before : null);
    $hasMorePosts = count($posts) > 20;
    if ($hasMorePosts) {
      array_pop($posts);
    }
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
$profileMenuReturnPath = str_replace(TRUX_BASE_URL, '', $profileUrl());
$profileCanAssignRoles = $me && trux_can_manage_staff_roles((string)($me['staff_role'] ?? 'user'));
$profileCanModerate = $me && trux_has_staff_role((string)($me['staff_role'] ?? 'user'), 'developer');
$profileCanModerateWrite = $me && trux_can_moderation_write((string)($me['staff_role'] ?? 'user'));
$profileModerationCase = $profileCanModerate
  ? trux_moderation_fetch_user_case_by_user_id((int)$profileUser['id'])
  : null;

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
    $postRecord = $p;
    $postViewer = $viewer;
    $postInteractionStats = $interactionMap[(int)$p['id']] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false];
    if ($timeKey !== '' && !empty($p[$timeKey])) {
      $postContextTimeSource = (string)$p[$timeKey];
      $postContextTimePrefix = $timePrefix;
    }
    require __DIR__ . '/_post_card.php';
    unset($postContextTimeSource, $postContextTimePrefix);
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
            <span data-time-ago="1" data-time-source="<?= trux_e((string)$comment[$timeKey]) ?>" title="<?= trux_e(trux_format_exact_time((string)$comment[$timeKey])) ?>">
              <?= trux_e(trux_time_ago((string)$comment[$timeKey])) ?>
            </span>
          </span>
          <span>&middot;</span>
          <span>Score <?= $score ?></span>
        </div>
      </div>
      <div class="post__body"><?= trux_render_comment_body((string)$comment['body']) ?></div>
      <div class="profileActivityCard__context">
        <span class="muted">On <a href="<?= trux_e(trux_post_viewer_url((int)$comment['post_id'], $commentId)) ?>">post #<?= (int)$comment['post_id'] ?></a> by <a href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode((string)($comment['post_username'] ?? '')) ?>">@<?= trux_e((string)($comment['post_username'] ?? '')) ?></a></span>
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

<?php if ($isBlockedByThem): ?>
  <div class="pageFrame pageFrame--profile">
    <section class="bandSurface bandSurface--empty">
      <strong>@<?= trux_e((string)$profileUser['username']) ?> has blocked you</strong>
      <p class="muted">You are not able to view this profile.</p>
    </section>
  </div>
<?php else: ?>
  <div class="profileScene">
    <section class="identityBand">
      <div class="identityBand__backdrop">
        <?php if ($bannerUrl !== ''): ?>
          <img class="identityBand__bannerImage" src="<?= trux_e($bannerUrl) ?>" alt="" loading="lazy" decoding="async">
        <?php endif; ?>
      </div>

      <div class="identityBand__inner">
        <div class="identityBand__avatar<?= $avatarUrl !== '' ? ' identityBand__avatar--image' : '' ?>">
          <?php if ($avatarUrl !== ''): ?>
            <img class="identityBand__avatarImage" src="<?= trux_e($avatarUrl) ?>" alt="">
          <?php else: ?>
            <span><?= trux_e(strtoupper(substr((string)$profileUser['username'], 0, 1))) ?></span>
          <?php endif; ?>
        </div>

        <div class="identityBand__copy">
          <span class="identityBand__eyebrow">Identity surface</span>
          <div class="identityBand__nameRow">
            <h2><?= $displayName !== '' ? trux_e($displayName) : '@' . trux_e((string)$profileUser['username']) ?></h2>
            <span class="identityBand__handle">@<?= trux_e((string)$profileUser['username']) ?></span>
          </div>

          <?php if ($bio !== ''): ?>
            <p class="identityBand__bio"><?= nl2br(trux_e($bio)) ?></p>
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

          <div class="identityBand__telemetry" aria-hidden="true">
            <div class="identityBand__telemetryItem">
              <span>Mode</span>
              <strong><?= $isSelf ? 'Owner' : 'Public' ?></strong>
            </div>
            <div class="identityBand__telemetryItem">
              <span>Links</span>
              <strong><?= count($profileLinks) ?> mapped</strong>
            </div>
            <div class="identityBand__telemetryItem">
              <span>Tabs</span>
              <strong><?= count($allowedTabs) ?> visible</strong>
            </div>
          </div>
        </div>

        <div class="identityBand__actions">
          <?php if ($isSelf): ?>
            <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/edit_profile.php">Edit profile</a>
          <?php elseif (!$me): ?>
            <a class="shellButton shellButton--accent" href="<?= TRUX_BASE_URL ?>/login.php">Log in to follow</a>
          <?php else: ?>
            <div class="profileActions">
              <?php if (!$isBlocked): ?>
                <form class="profileFollowForm" method="post" action="<?= TRUX_BASE_URL ?>/follow.php">
                  <?= trux_csrf_field() ?>
                  <input type="hidden" name="action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$profileUser['id'] ?>">
                  <input type="hidden" name="user" value="<?= trux_e((string)$profileUser['username']) ?>">
                  <button class="shellButton shellButton--accent<?= $isFollowing ? ' is-active' : '' ?>" type="submit"><?= $isFollowing ? 'Following' : 'Follow' ?></button>
                </form>
              <?php endif; ?>

              <?php
              $profileMenuUsername = (string)$profileUser['username'];
              $profileMenuUserId = (int)$profileUser['id'];
              $profileMenuCanAssignRoles = $profileCanAssignRoles;
              $profileMenuIsBlocked = (bool)$isBlocked;
              $profileMenuIsMuted = (bool)$isMuted;
              $profileMenuCanModerate = (bool)$profileCanModerate;
              $profileMenuCanModerateWrite = (bool)$profileCanModerateWrite;
              $profileMenuHasUserCase = (bool)$profileModerationCase;
              $profileMenuIsWatchlisted = !empty($profileModerationCase['watchlisted']);
              $profileMenuUserCasePath = '/moderation/user_review.php?user_id=' . (int)$profileUser['id'];
              $profileMenuStaffAccessPath = '/moderation/staff.php?user_id=' . (int)$profileUser['id'];
              require __DIR__ . '/_profile_actions_menu.php';
              ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="identityBand__stats">
        <div class="identityStat">
          <strong><?= number_format((int)$followCounts['followers']) ?></strong>
          <span>Followers</span>
        </div>
        <div class="identityStat">
          <strong><?= number_format((int)$followCounts['following']) ?></strong>
          <span>Following</span>
        </div>
        <div class="identityStat">
          <strong <?= $joinedDateRaw !== '' ? 'data-time-ago="1" data-time-source="' . trux_e($joinedDateRaw) . '" title="' . trux_e($joinedDateExact) . '"' : '' ?>><?= trux_e($joinedDate) ?></strong>
          <span>Joined</span>
        </div>
      </div>
    </section>

    <div class="profileWorkspace">
      <div class="profileWorkspace__main">
        <nav class="profileTabs" aria-label="Profile sections">
          <?php foreach ([
            'posts' => 'Posts',
            'replies' => 'Replies',
            'liked' => 'Liked',
            'bookmarks' => 'Bookmarks',
            'about' => 'About',
          ] as $tabKey => $tabLabel): ?>
            <?php if (($tabKey === 'liked' && !$showLikesTab) || ($tabKey === 'bookmarks' && !$showBookmarksTab)) continue; ?>
            <a class="profileTabs__item<?= $tab === $tabKey ? ' is-active' : '' ?>" href="<?= trux_e($profileUrl(['tab' => $tabKey, 'before' => null, 'replies_page' => 1, 'liked_posts_page' => 1, 'liked_comments_page' => 1, 'bookmark_posts_page' => 1, 'bookmark_comments_page' => 1])) ?>" <?= $tab === $tabKey ? 'aria-current="page"' : '' ?>>
              <?= trux_e($tabLabel) ?>
            </a>
          <?php endforeach; ?>
        </nav>

        <?php if ($blockedTab !== null): ?>
          <section class="bandSurface bandSurface--empty">
            <strong><?= $blockedTab === 'liked' ? 'Likes hidden' : 'Bookmarks hidden' ?></strong>
            <p class="muted">@<?= trux_e((string)$profileUser['username']) ?> has chosen to keep this section private.</p>
          </section>
        <?php elseif ($tab === 'posts'): ?>
          <section class="timelineFrame">
            <div class="timelineFrame__head">
              <div>
                <span class="timelineFrame__eyebrow">Posts</span>
                <h3>Published by @<?= trux_e((string)$profileUser['username']) ?></h3>
              </div>
            </div>
            <div class="timeline" data-auto-pager-list="profile-posts">
              <?php if (!$posts && $before <= 0): ?>
                <section class="bandSurface bandSurface--empty">
                  <strong>No posts yet</strong>
                  <p class="muted">@<?= trux_e((string)$profileUser['username']) ?> has not posted yet.</p>
                </section>
              <?php else: ?>
                <?php $renderPosts($posts, $postInteractionMap, $me); ?>
              <?php endif; ?>
            </div>
            <?php if ($hasMorePosts && $nextBefore): ?>
              <div class="pager" data-auto-pager="profile-posts"><a class="shellButton shellButton--ghost" data-no-fx="1" href="<?= trux_e($profileUrl(['tab' => 'posts', 'before' => $nextBefore])) ?>">Load more</a></div>
            <?php endif; ?>
          </section>
        <?php elseif ($tab === 'replies'): ?>
          <section class="bandSurface">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">Replies</span>
                <h3>Comments and replies</h3>
              </div>
            </div>
            <?php if (!$replyItems): ?>
              <section class="bandSurface bandSurface--empty bandSurface--nested">
                <strong>No replies yet</strong>
                <p class="muted"><?= $isSelf ? 'You have not posted any comments or replies yet.' : '@' . trux_e((string)$profileUser['username']) . ' has not posted any comments or replies yet.' ?></p>
              </section>
            <?php else: ?>
              <div class="profileActivityList"><?php $renderCommentCards($replyItems, $replyVoteMap, 'replies'); ?></div>
            <?php endif; ?>
            <?php if ($hasMoreReplies): ?>
              <div class="pager"><a class="shellButton shellButton--ghost" href="<?= trux_e($profileUrl(['tab' => 'replies', 'replies_page' => $repliesPage + 1])) ?>">Load more replies</a></div>
            <?php endif; ?>
          </section>
        <?php elseif ($tab === 'liked'): ?>
          <section class="timelineFrame">
            <div class="timelineFrame__head">
              <div>
                <span class="timelineFrame__eyebrow">Liked posts</span>
                <h3>Recent likes</h3>
              </div>
            </div>
            <?php if (!$likedPosts): ?>
              <section class="bandSurface bandSurface--empty">
                <strong>No liked posts</strong>
                <p class="muted">No liked posts on this page.</p>
              </section>
            <?php endif; ?>
            <div class="timeline" data-auto-pager-list="profile-liked-posts">
              <?php $renderPosts($likedPosts, $likedInteractionMap, $me, 'liked_at', 'Liked '); ?>
            </div>
            <?php if ($hasMoreLikedPosts): ?>
              <div class="pager" data-auto-pager="profile-liked-posts"><a class="shellButton shellButton--ghost" data-no-fx="1" href="<?= trux_e($profileUrl(['tab' => 'liked', 'liked_posts_page' => $likedPostsPage + 1])) ?>">Load more liked posts</a></div>
            <?php endif; ?>
          </section>

          <section class="bandSurface">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">Liked comments</span>
                <h3>Comment and reply votes</h3>
              </div>
            </div>
            <?php if (!$likedComments): ?>
              <section class="bandSurface bandSurface--empty bandSurface--nested">
                <strong>No liked comments</strong>
                <p class="muted">No liked comments or replies on this page.</p>
              </section>
            <?php else: ?>
              <div class="profileActivityList"><?php $renderCommentCards($likedComments, $likedCommentVoteMap, 'liked'); ?></div>
            <?php endif; ?>
            <?php if ($hasMoreLikedComments): ?>
              <div class="pager"><a class="shellButton shellButton--ghost" href="<?= trux_e($profileUrl(['tab' => 'liked', 'liked_comments_page' => $likedCommentsPage + 1])) ?>">Load more liked comments</a></div>
            <?php endif; ?>
          </section>
        <?php elseif ($tab === 'bookmarks'): ?>
          <section class="timelineFrame">
            <div class="timelineFrame__head">
              <div>
                <span class="timelineFrame__eyebrow">Saved posts</span>
                <h3>Bookmarked content</h3>
              </div>
            </div>
            <?php if (!$bookmarkedPosts): ?>
              <section class="bandSurface bandSurface--empty">
                <strong>No saved posts</strong>
                <p class="muted">No bookmarked posts on this page.</p>
              </section>
            <?php endif; ?>
            <div class="timeline" data-auto-pager-list="profile-bookmarked-posts">
              <?php $renderPosts($bookmarkedPosts, $bookmarkInteractionMap, $me, 'bookmarked_at', 'Saved '); ?>
            </div>
            <?php if ($hasMoreBookmarkedPosts): ?>
              <div class="pager" data-auto-pager="profile-bookmarked-posts"><a class="shellButton shellButton--ghost" data-no-fx="1" href="<?= trux_e($profileUrl(['tab' => 'bookmarks', 'bookmark_posts_page' => $bookmarkPostsPage + 1])) ?>">Load more bookmarked posts</a></div>
            <?php endif; ?>
          </section>

          <section class="bandSurface">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">Saved comments</span>
                <h3>Bookmarked comment activity</h3>
              </div>
            </div>
            <?php if (!$bookmarkedComments): ?>
              <section class="bandSurface bandSurface--empty bandSurface--nested">
                <strong>No saved comments</strong>
                <p class="muted">No bookmarked comments or replies on this page.</p>
              </section>
            <?php else: ?>
              <div class="profileActivityList"><?php $renderCommentCards($bookmarkedComments, $bookmarkCommentVoteMap, 'bookmarks'); ?></div>
            <?php endif; ?>
            <?php if ($hasMoreBookmarkedComments): ?>
              <div class="pager"><a class="shellButton shellButton--ghost" href="<?= trux_e($profileUrl(['tab' => 'bookmarks', 'bookmark_comments_page' => $bookmarkCommentsPage + 1])) ?>">Load more bookmarked comments</a></div>
            <?php endif; ?>
          </section>
        <?php else: ?>
          <section class="bandSurface">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">About</span>
                <h3>Long-form profile</h3>
              </div>
            </div>
            <?php if ($aboutMe !== ''): ?>
              <div class="profileAbout__body"><?= trux_render_rich_text($aboutMe) ?></div>
            <?php else: ?>
              <section class="bandSurface bandSurface--empty bandSurface--nested">
                <strong>About is empty</strong>
                <p class="muted">
                  <?php if ($isSelf): ?>
                    Your About section is empty. <a href="<?= TRUX_BASE_URL ?>/edit_profile.php">Add a longer profile description</a>.
                  <?php else: ?>
                    @<?= trux_e((string)$profileUser['username']) ?> has not added an About section yet.
                  <?php endif; ?>
                </p>
              </section>
            <?php endif; ?>

            <div class="profileAbout__linksWrap">
              <h3>Affiliated links</h3>
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
          </section>
        <?php endif; ?>
      </div>

      <aside class="profileWorkspace__side">
        <section class="bandSurface bandSurface--side">
          <div class="bandSurface__head">
            <div>
              <span class="bandSurface__eyebrow">Profile facts</span>
              <h3>Identity summary</h3>
            </div>
          </div>

          <div class="profileFacts">
            <div class="profileFacts__row">
              <span class="muted">Followers</span>
              <strong><?= number_format((int)$followCounts['followers']) ?></strong>
            </div>
            <div class="profileFacts__row">
              <span class="muted">Following</span>
              <strong><?= number_format((int)$followCounts['following']) ?></strong>
            </div>
            <div class="profileFacts__row">
              <span class="muted">Joined</span>
              <strong <?= $joinedDateRaw !== '' ? 'data-time-ago="1" data-time-source="' . trux_e($joinedDateRaw) . '" title="' . trux_e($joinedDateExact) . '"' : '' ?>><?= trux_e($joinedDate) ?></strong>
            </div>
            <?php if ($location !== ''): ?>
              <div class="profileFacts__row">
                <span class="muted">Location</span>
                <strong><?= trux_e($location) ?></strong>
              </div>
            <?php endif; ?>
            <?php if ($websiteUrl !== ''): ?>
              <div class="profileFacts__row">
                <span class="muted">Website</span>
                <a href="<?= trux_e($websiteUrl) ?>" target="_blank" rel="noopener noreferrer"><?= trux_e($websiteLabel !== '' ? $websiteLabel : $websiteUrl) ?></a>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($aboutMe !== ''): ?>
          <section class="bandSurface bandSurface--side">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">About preview</span>
                <h3>Long-form bio</h3>
              </div>
            </div>
            <p class="muted"><?= trux_e($excerpt($aboutMe, 240)) ?></p>
            <?php if ($tab !== 'about'): ?>
              <a class="shellButton shellButton--ghost" href="<?= trux_e($profileUrl(['tab' => 'about'])) ?>">Open full about</a>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($profileLinks): ?>
          <section class="bandSurface bandSurface--side">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">Links</span>
                <h3>External profiles</h3>
              </div>
            </div>
            <div class="profileLinks">
              <?php foreach ($profileLinks as $link): ?>
                <a class="profileLink" href="<?= trux_e((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer">
                  <span class="profileLink__icon profileLink__icon--<?= trux_e((string)$link['provider']) ?>" aria-hidden="true"><?= $link['icon_svg'] ?></span>
                  <span class="profileLink__label"><?= trux_e((string)$link['label']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </aside>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
