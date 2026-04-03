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
$author = $searchFilter === 'hashtags' ? '' : trux_normalize_mention_fragment(trux_str_param('author', ''));

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
$hasValidTextSearch = $searchFilter === 'hashtags'
  ? $hashtag !== ''
  : $term !== '' && mb_strlen($term) >= 2;
$hasValidAuthorFilter = $searchFilter !== 'hashtags' && $author !== '';
$hasValidSearch = $searchFilter === 'hashtags'
  ? $hashtag !== ''
  : ($hasValidTextSearch || $hasValidAuthorFilter);

if ($hasValidSearch) {
  if ($searchFilter === 'hashtags') {
    $posts = trux_search_posts_by_hashtag($hashtag, 20, $before > 0 ? $before : null);
  } else {
    $userLookupTerm = $term !== '' ? $term : $author;
    if ($userLookupTerm !== '') {
      $users = trux_search_users($userLookupTerm, 10);
    }
    $posts = trux_search_posts(
      $hasValidTextSearch ? $term : '',
      20,
      $before > 0 ? $before : null,
      $author !== '' ? $author : null
    );
  }

  if (count($posts) > 0) {
    $last = $posts[count($posts) - 1];
    $nextBefore = (int)$last['id'];
  }
}

$allFilterParams = ['q' => $q, 'filter' => 'all'];
if ($author !== '') {
  $allFilterParams['author'] = $author;
}
$hashtagFilterParams = ['q' => $q, 'filter' => 'hashtags'];
$clearAuthorParams = ['q' => $q, 'filter' => 'all'];
$pagerParams = ['q' => $q, 'filter' => $searchFilter, 'before' => (int)$nextBefore];
if ($searchFilter !== 'hashtags' && $author !== '') {
  $pagerParams['author'] = $author;
}
$resultsMeta = $q !== '' ? 'Query: ' . $q : 'No query yet';
if ($author !== '' && $searchFilter !== 'hashtags') {
  $resultsMeta .= ' · From @' . $author;
}
$postsHeading = $searchFilter === 'hashtags'
  ? 'Posts tagged #' . $hashtag
  : ($author !== '' ? 'Matching posts from @' . $author : 'Matching posts');
$emptyPostsCopy = $searchFilter === 'hashtags'
  ? 'No posts found for #' . $hashtag . '.'
  : ($author !== '' ? 'No matching posts from @' . $author . '.' : 'No matching posts.');

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
      <div class="commandReadoutGrid" aria-hidden="true">
        <div class="commandReadout">
          <span>Mode</span>
          <strong><?= $searchFilter === 'hashtags' ? 'Hashtag lock' : 'Network sweep' ?></strong>
        </div>
        <div class="commandReadout">
          <span>Query</span>
          <strong><?= $q !== '' ? trux_e($q) : 'Standby' ?></strong>
        </div>
      </div>
      <nav class="segmented" aria-label="Search filter">
        <a class="segmented__item<?= $searchFilter === 'all' ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/search.php?<?= http_build_query($allFilterParams) ?>" <?= $searchFilter === 'all' ? 'aria-current="page"' : '' ?>>All</a>
        <a class="segmented__item<?= $searchFilter === 'hashtags' ? ' is-active' : '' ?>" href="<?= TRUX_BASE_URL ?>/search.php?<?= http_build_query($hashtagFilterParams) ?>" <?= $searchFilter === 'hashtags' ? 'aria-current="page"' : '' ?>>Hashtags</a>
      </nav>
      <div class="inlineHeader__meta">
        <span><?= trux_e($resultsMeta) ?></span>
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
              Enter at least 2 characters to search, or apply an exact <code>@username</code> author filter.
            <?php endif; ?>
          </p>
        </section>
      <?php else: ?>
        <?php if ($searchFilter !== 'hashtags'): ?>
          <section class="bandSurface">
            <div class="bandSurface__head">
              <div>
                <span class="bandSurface__eyebrow">Post filter</span>
                <h3>From @username</h3>
              </div>
              <?php if ($author !== ''): ?>
                <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/search.php?<?= http_build_query($clearAuthorParams) ?>">Clear filter</a>
              <?php endif; ?>
            </div>

            <form method="get" action="<?= TRUX_BASE_URL ?>/search.php" class="settingSection__form">
              <input type="hidden" name="q" value="<?= trux_e($q) ?>">
              <input type="hidden" name="filter" value="all">
              <div class="settingRow">
                <span class="settingRow__label">
                  <strong>Limit post matches to one account</strong>
                  <small class="muted">Use an exact username to narrow post results without leaving search.</small>
                </span>
                <div class="searchAuthorFilter">
                  <input id="searchAuthor" name="author" value="<?= trux_e($author) ?>" placeholder="username" maxlength="32" autocomplete="off">
                  <button class="shellButton shellButton--accent" type="submit">Apply</button>
                </div>
              </div>
            </form>
          </section>
        <?php endif; ?>

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
                  <?php
                  $userSearchParams = ['filter' => 'all', 'author' => (string)$u['username']];
                  if ($q !== '') {
                    $userSearchParams['q'] = $q;
                  }
                  ?>
                  <div class="searchUserResult">
                    <a class="userBand userBand--link" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= trux_e((string)$u['username']) ?>">
                      <span class="userBand__signal" aria-hidden="true">USR</span>
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
                    <div class="searchUserResult__actions">
                      <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/search.php?<?= http_build_query($userSearchParams) ?>">Search posts from @<?= trux_e((string)$u['username']) ?></a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <section class="timelineFrame">
          <div class="timelineFrame__head">
            <div>
              <span class="timelineFrame__eyebrow"><?= $searchFilter === 'hashtags' ? 'Hashtag stream' : 'Post results' ?></span>
              <h3><?= trux_e($postsHeading) ?></h3>
            </div>
            <div class="timelineFrame__actions">
              <span class="muted"><?= count($posts) ?> loaded</span>
            </div>
          </div>

          <?php if (!$posts): ?>
            <section class="bandSurface bandSurface--empty">
              <strong>No posts matched</strong>
              <p class="muted"><?= trux_e($emptyPostsCopy) ?></p>
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
              <a class="shellButton shellButton--ghost" data-no-fx="1" href="<?= TRUX_BASE_URL ?>/search.php?<?= http_build_query($pagerParams) ?>">
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
              <div>
                <span class="utilityBand__eyebrow">Lookup syntax</span>
                <h4>Accounts</h4>
              </div>
            </div>
            <p class="muted">Use <code>@username</code> or part of a username to find people.</p>
          </section>
          <section class="utilityBand">
            <div class="utilityBand__head">
              <div>
                <span class="utilityBand__eyebrow">Topic syntax</span>
                <h4>Hashtags</h4>
              </div>
            </div>
            <p class="muted">Switch to hashtag mode to focus on exact tags such as <code>#updates</code>.</p>
          </section>
        </div>
      </section>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
