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
$isMuted = false;
if ($me && !$isSelf) {
    $isFollowing = trux_is_following((int)$me['id'], (int)$profileUser['id']);
    $isMuted = trux_has_muted_user((int)$me['id'], (int)$profileUser['id']);
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

$displayName = trim((string)($profileUser['display_name'] ?? ''));
$bio = trim((string)($profileUser['bio'] ?? ''));
$location = trim((string)($profileUser['location'] ?? ''));
$avatarPath = trim((string)($profileUser['avatar_path'] ?? ''));
$bannerPath = trim((string)($profileUser['banner_path'] ?? ''));
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

require_once __DIR__ . '/_header.php';
?>

<div class="profile">
  <section class="profile__hero">
    <div class="profile__banner" aria-hidden="true">
      <?php if ($bannerPath !== ''): ?>
        <img class="profile__bannerImage" src="<?= trux_e($bannerPath) ?>" alt="" loading="lazy" decoding="async">
      <?php endif; ?>
    </div>
    <div class="profile__identity">
      <div class="profile__avatar<?= $avatarPath !== '' ? ' profile__avatar--image' : '' ?>" aria-hidden="true">
        <?php if ($avatarPath !== ''): ?>
          <img class="profile__avatarImage" src="<?= trux_e($avatarPath) ?>" alt="">
        <?php endif; ?>
      </div>
      <div class="profile__titleBlock">
        <h1 class="profile__username">
          <?= $displayName !== '' ? trux_e($displayName) : '@' . trux_e((string)$profileUser['username']) ?>
        </h1>
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
              <a class="profileMeta__item" href="<?= trux_e($websiteUrl) ?>" target="_blank" rel="noopener noreferrer">
                <?= trux_e($websiteLabel !== '' ? $websiteLabel : $websiteUrl) ?>
              </a>
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
          <a class="btn btn--neonFollow" href="/edit_profile.php">
            <span class="btn__icon btn__icon--edit" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" focusable="false">
                <path d="M12 20h9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
            <span class="btn__text">Edit Profile</span>
          </a>
        <?php elseif (!$me): ?>
          <button class="btn btn--neonFollow is-disabled" type="button" disabled>
            <span class="btn__icon" aria-hidden="true">+</span>
            <span class="btn__text">Log in to follow</span>
          </button>
        <?php else: ?>
          <div class="profileActions">
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

            <form class="profileMuteForm" method="post" action="/mute_user.php">
              <?= trux_csrf_field() ?>
              <input type="hidden" name="action" value="<?= $isMuted ? 'unmute' : 'mute' ?>">
              <input type="hidden" name="user_id" value="<?= (int)$profileUser['id'] ?>">
              <input type="hidden" name="user" value="<?= trux_e((string)$profileUser['username']) ?>">
              <button class="btn btn--small btn--ghost<?= $isMuted ? ' is-active' : '' ?>" type="submit">
                <?= $isMuted ? 'Muted' : 'Mute' ?>
              </button>
            </form>

            <a class="btn btn--small btn--ghost" href="/messages.php?with=<?= trux_e(rawurlencode((string)$profileUser['username'])) ?>">
              Message
            </a>
          </div>
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
        <?php $editedAt = isset($p['edited_at']) && $p['edited_at'] !== null ? (string)$p['edited_at'] : ''; ?>
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
                  <?php if ($editedAt !== ''): ?>
                    <span class="editedMeta" data-post-edited-for="<?= (int)$p['id'] ?>">
                      <span class="editedMeta__label">EDITED AT</span>
                      <span
                        class="editedMeta__time"
                        title="<?= trux_e(trux_format_exact_time($editedAt)) ?>"
                        data-time-ago="1"
                        data-time-source="<?= trux_e($editedAt) ?>">
                        <?= trux_e(trux_time_ago($editedAt)) ?>
                      </span>
                    </span>
                  <?php endif; ?>
                  <span class="post__dot" aria-hidden="true">&bull;</span>
                  <a class="post__id" href="/post.php?id=<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?></a>
                </div>
              </div>

              <?php if ($me && (int)$p['user_id'] === (int)$me['id']): ?>
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
