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

$before = trux_int_param('before', 0);
$posts = trux_fetch_posts_by_user((int)$profileUser['id'], 20, $before > 0 ? $before : null);

$nextBefore = null;
if (count($posts) > 0) {
    $last = $posts[count($posts) - 1];
    $nextBefore = (int)$last['id'];
}

$me = trux_current_user();
$interactionMap = trux_fetch_post_interactions(
    trux_collect_post_ids($posts),
    $me ? (int)$me['id'] : null
);
$isSelf = $me && (int)$me['id'] === (int)$profileUser['id'];
$followCounts = trux_follow_counts((int)$profileUser['id']);
$isFollowing = false;
if ($me && !$isSelf) {
    $isFollowing = trux_is_following((int)$me['id'], (int)$profileUser['id']);
}

$joinedDate = '-';
$joinedDateRaw = '';
$joinedDateExact = '';
if (!empty($profileUser['created_at']) && is_string($profileUser['created_at'])) {
    try {
        $joinedDateRaw = (string)$profileUser['created_at'];
        $joinedDate = trux_time_ago($joinedDateRaw);
        $joinedDateExact = trux_format_exact_time($joinedDateRaw);
    } catch (Exception $e) {
        $joinedDate = '-';
        $joinedDateRaw = '';
        $joinedDateExact = '';
    }
}

require_once __DIR__ . '/_header.php';
?>

<div class="profile">
  <section class="profile__hero">
    <div class="profile__banner" aria-hidden="true"></div>
    <div class="profile__identity">
      <div class="profile__avatar" aria-hidden="true"></div>
      <div class="profile__titleBlock">
        <h1 class="profile__username">@<?= trux_e((string)$profileUser['username']) ?></h1>
        <div class="profile__subtitle">Cyber identity panel</div>
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
          <div
            class="profileStat__value"
            <?php if ($joinedDateRaw !== ''): ?>
              data-time-ago="1"
              data-time-source="<?= trux_e($joinedDateRaw) ?>"
              title="<?= trux_e($joinedDateExact) ?>"
            <?php endif; ?>>
            <?= trux_e($joinedDate) ?>
          </div>
          <div class="profileStat__label">Joined</div>
        </div>
      </div>
      <div class="profileStats__right">
        <?php if ($isSelf): ?>
          <button class="btn btn--neonFollow is-disabled" type="button" disabled>
            <span class="btn__icon" aria-hidden="true">&#8226;</span>
            <span class="btn__text">This is you</span>
          </button>
        <?php elseif (!$me): ?>
          <button class="btn btn--neonFollow is-disabled" type="button" disabled>
            <span class="btn__icon" aria-hidden="true">+</span>
            <span class="btn__text">Log in to follow</span>
          </button>
        <?php else: ?>
          <form class="profileFollowForm" method="post" action="/follow.php">
            <?= trux_csrf_field() ?>
            <input type="hidden" name="action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>">
            <input type="hidden" name="user_id" value="<?= (int)$profileUser['id'] ?>">
            <input type="hidden" name="user" value="<?= trux_e((string)$profileUser['username']) ?>">
            <button class="btn btn--neonFollow<?= $isFollowing ? ' is-following' : '' ?>" type="submit">
              <span class="btn__icon" aria-hidden="true"><?= $isFollowing ? '&#10003;' : '+' ?></span>
              <span class="btn__text"><?= $isFollowing ? 'Following' : 'Follow' ?></span>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="profile__feed">
    <div class="profile__feedHead">
      <h2 class="profile__feedTitle">Posts</h2>
    </div>

    <div class="profile__posts feed">
      <?php if (!$posts): ?>
        <div class="card">
          <div class="card__body">No posts yet.</div>
        </div>
      <?php endif; ?>

      <?php foreach ($posts as $p): ?>
        <article class="card post" data-post-id="<?= (int)$p['id'] ?>">
          <div class="card__body">
            <div class="post__head">
              <a class="post__avatar" href="/profile.php?u=<?= trux_e((string)$p['username']) ?>" aria-label="View @<?= trux_e((string)$p['username']) ?> profile"></a>

              <div class="post__meta">
                <div class="post__nameRow">
                  <a class="post__user" href="/profile.php?u=<?= trux_e((string)$p['username']) ?>">@<?= trux_e((string)$p['username']) ?></a>
                </div>
                <div class="post__subRow">
                  <span
                    class="post__time"
                    title="<?= trux_e(trux_format_exact_time((string)$p['created_at'])) ?>"
                    data-time-ago="1"
                    data-time-source="<?= trux_e((string)$p['created_at']) ?>">
                    <?= trux_e(trux_time_ago((string)$p['created_at'])) ?>
                  </span>
                  <span class="post__dot" aria-hidden="true">&bull;</span>
                  <a class="post__id" href="/post.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a>
                </div>
              </div>

              <?php if ($me && (int)$p['user_id'] === (int)$me['id']): ?>
                <div class="post__actions">
                  <form class="inline" method="post" action="/delete_post.php" data-confirm="Delete this post?">
                    <?= trux_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="iconBtn iconBtn--danger" type="submit" aria-label="Delete post" title="Delete">
                      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M4.75 7.5h14.5M9.5 7.5V5.75a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V7.5M7.5 7.5l.9 11.2a2 2 0 0 0 2 1.8h3.2a2 2 0 0 0 2-1.8l.9-11.2M10.25 11v6.25M13.75 11v6.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                      </svg>
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </div>

            <div class="post__body"><?= nl2br(trux_e((string)$p['body'])) ?></div>

            <?php if (!empty($p['image_path'])): ?>
              <div class="post__image">
                <img src="<?= trux_e((string)$p['image_path']) ?>" alt="Post image" loading="lazy" decoding="async">
              </div>
            <?php endif; ?>

            <?php
            $postId = (int)$p['id'];
            $stats = $interactionMap[$postId] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false];
            $isLoggedIn = (bool)$me;
            require __DIR__ . '/_post_actions_bar.php';
            ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($nextBefore): ?>
      <div class="pager">
        <a class="btn" href="/profile.php?u=<?= urlencode($username) ?>&before=<?= (int)$nextBefore ?>">Load more</a>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
