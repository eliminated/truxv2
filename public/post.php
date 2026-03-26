<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'post-viewer';
$pageLayout = 'app';

$id = trux_int_param('id', 0);
if ($id <= 0) {
  http_response_code(404);
  trux_flash_set('error', 'Post not found.');
  trux_redirect('/');
}

$post = trux_fetch_post_by_id($id);
if (!$post) {
  http_response_code(404);
  trux_flash_set('error', 'Post not found.');
  trux_redirect('/');
}

$me = trux_current_user();
$interactionMap = trux_fetch_post_interactions(
  [(int)$post['id']],
  $me ? (int)$me['id'] : null
);
$postStats = $interactionMap[(int)$post['id']] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'liked' => false, 'shared' => false, 'bookmarked' => false];

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--focus" hidden>
  <div class="focusStage" hidden>
    <div class="timeline">
      <?php
      $postRecord = $post;
      $postViewer = $me;
      $postInteractionStats = $postStats;
      $postCardClasses = 'post--single';
      $postViewerLinks = [];
      require __DIR__ . '/_post_card.php';
      ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
