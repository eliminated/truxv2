<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'search';
$pageLayout = 'app';

$q = trim(trux_str_param('q', ''));
$before = trux_int_param('before', 0);
$rawFilter = $_GET['filter'] ?? null;
$requestedFilter = is_string($rawFilter) ? trim($rawFilter) : '';
$searchFilter = $requestedFilter === 'hashtags' ? 'hashtags' : 'all';

$term = $q;
if (str_starts_with($term, '@')) {
  $term = ltrim($term, '@');
}

if ($requestedFilter === '' && str_starts_with($q, '#')) {
  $searchFilter = 'hashtags';
}

$users = [];
$posts = [];
$nextBefore = null;
$hashtag = $searchFilter === 'hashtags' ? trux_normalize_hashtag($term) : '';
$hasValidSearch = $searchFilter === 'hashtags'
  ? $hashtag !== ''
  : $term !== '' && mb_strlen($term) >= 2;

if ($hasValidSearch) {
  if ($searchFilter === 'hashtags') {
    $posts = trux_search_posts_by_hashtag($hashtag, 20, $before > 0 ? $before : null);
  } else {
    $users = trux_search_users($term, 10);
    $posts = trux_search_posts($term, 20, $before > 0 ? $before : null);
  }

  if (count($posts) > 0) {
    $last = $posts[count($posts) - 1];
    $nextBefore = (int)$last['id'];
  }
}

$me = trux_current_user();
$interactionMap = trux_fetch_post_interactions(
  trux_collect_post_ids($posts),
  $me ? (int)$me['id'] : null
);

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--search">
  <section class="inlineHeader inlineHeader--search">
    <div class="inlineHeader__main">
      <span class="inlineHeader__eyebrow">Search surface</span>
      <div class="inlineHeader__titleWrap">
        <h2 class="inlineHeader__title"><?= $searchFilter === 'hashtags' ? 'Hashtag explorer' : 'Network search' ?></h2>
        <p class="inlineHeader__copy">
        <?php if ($searchFilter === 'hashtags'): ?>
          Filter the network by exact hashtag matches such as <code>#php</code> or <code>#release_notes</code>.
        <?php else: ?>
          Find users and posts with one query. Use <code>@username</code>, keywords, or switch into hashtag mode.
        <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="inlineHeader__aside">
      <nav class="segmented" aria-label="Search filter">
        <a class="segmented__item<?= $searchFilter === 'all' ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode($q) ?>&filter=all" <?= $searchFilter === 'all' ? 'aria-current="page"' : '' ?>>All</a>
        <a class="segmented__item<?= $searchFilter === 'hashtags' ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode($q) ?>&filter=hashtags" <?= $searchFilter === 'hashtags' ? 'aria-current="page"' : '' ?>>Hashtags</a>
      </nav>
      <div class="inlineHeader__meta">
        <span><?= $q !== '' ? 'Query: ' . trux_e($q) : 'No query yet' ?></span>
        <strong><?= count($posts) ?> post<?= count($posts) === 1 ? '' : 's' ?></strong>
      </div>
    </div>
  </section>

  <div class="searchScene">
    <div class="searchScene__main">
      <?php if (!$hasValidSearch): ?>
        <section class="bandSurface bandSurface--empty">
          <strong>Enter a valid search</strong>
          <p class="muted">
            <?php if ($searchFilter === 'hashtags'): ?>
              Enter a hashtag using letters, numbers, or underscore. Examples: <code>#php</code>, <code>#release_notes</code>.
            <?php else: ?>
              Enter at least 2 characters to search.
            <?php endif; ?>
          </p>
        </section>
      <?php else: ?>
        <?php if ($searchFilter !== 'hashtags'): ?>
          <section class="bandSurface">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">Users</span>
                <h3>Matching accounts</h3>
              </div>
              <span class="bandSurface__meta"><?= count($users) ?> found</span>
            </div>

            <?php if (!$users): ?>
              <p class="muted">No matching users.</p>
            <?php else: ?>
              <div class="userStack">
                <?php foreach ($users as $u): ?>
                  <a class="userBand userBand--link" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$u['username']) ?>">
                    <div class="userBand__copy">
                      <div class="userBand__title">@<?= trux_e((string)$u['username']) ?></div>
                      <div class="userBand__meta muted">
                        Joined
                        <span title="<?= trux_e(trux_format_exact_time((string)$u['created_at'])) ?>" data-time-ago="1" data-time-source="<?= trux_e((string)$u['created_at']) ?>">
                          <?= trux_e(trux_time_ago((string)$u['created_at'])) ?>
                        </span>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <section class="timelineFrame">
          <div class="timelineFrame__head">
            <div>
              <span class="timelineFrame__eyebrow"><?= $searchFilter === 'hashtags' ? 'Hashtag stream' : 'Post results' ?></span>
              <h3><?= $searchFilter === 'hashtags' ? 'Posts tagged #' . trux_e($hashtag) : 'Matching posts' ?></h3>
            </div>
            <div class="timelineFrame__actions">
              <span class="muted"><?= count($posts) ?> loaded</span>
            </div>
          </div>

          <?php if (!$posts): ?>
            <section class="bandSurface bandSurface--empty">
              <strong>No posts matched</strong>
              <p class="muted">
                <?php if ($searchFilter === 'hashtags'): ?>
                  No posts found for #<?= trux_e($hashtag) ?>.
                <?php else: ?>
                  No matching posts.
                <?php endif; ?>
              </p>
            </section>
          <?php endif; ?>

          <div class="timeline" data-auto-pager-list="search-posts">
            <?php foreach ($posts as $p): ?>
              <?php
              $postRecord = $p;
              $postViewer = $me;
              $postInteractionStats = $interactionMap[(int)$p['id']] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false];
              require __DIR__ . '/_post_card.php';
              ?>
            <?php endforeach; ?>
          </div>

          <?php if ($nextBefore): ?>
            <div class="pager" data-auto-pager="search-posts">
              <a class="shellButton shellButton--ghost" data-no-fx="1" href="<?= TRUX_BASE_URL ?>/search.php?q=<?= urlencode($q) ?>&filter=<?= urlencode($searchFilter) ?>&before=<?= (int)$nextBefore ?>">
                Load more
              </a>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>

    <aside class="searchScene__rail">
      <section class="utilityPanel utilityPanel--compact">
        <div class="utilityPanel__head">
          <span class="utilityPanel__eyebrow">Tips</span>
          <h3>Search rules</h3>
        </div>
        <div class="utilityPanel__stack">
          <section class="utilityBand">
            <div class="utilityBand__head">
              <h4>Accounts</h4>
            </div>
            <p class="muted">Use <code>@username</code> or part of a username to find people.</p>
          </section>
          <section class="utilityBand">
            <div class="utilityBand__head">
              <h4>Hashtags</h4>
            </div>
            <p class="muted">Switch to hashtag mode to focus on exact tags such as <code>#updates</code>.</p>
          </section>
        </div>
      </section>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
