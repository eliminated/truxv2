<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'new-post';
$pageLayout = 'app';
$isJson = trux_str_param('format', '') === 'json';
$user = trux_current_user();

if (!$user) {
  if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in to continue.', 'login_url' => TRUX_BASE_URL . '/login.php']);
    exit;
  }

  trux_flash_set('error', 'Please log in to continue.');
  trux_redirect('/login.php');
}

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$rawOriginalId = $requestMethod === 'POST' ? ($_POST['original_post_id'] ?? null) : ($_GET['original_post_id'] ?? null);
$originalPostId = 0;
if (is_int($rawOriginalId)) {
  $originalPostId = $rawOriginalId > 0 ? $rawOriginalId : 0;
} elseif (is_string($rawOriginalId) && preg_match('/^\d+$/', trim($rawOriginalId))) {
  $originalPostId = (int)trim($rawOriginalId);
}

$rawReturnPath = $requestMethod === 'POST' ? ($_POST['return'] ?? null) : trux_str_param('return', '');
$defaultReturnPath = $originalPostId > 0 ? trux_post_viewer_path($originalPostId) : '/';
$returnPath = trux_safe_local_redirect_path($rawReturnPath, $defaultReturnPath);
$body = '';
$error = null;
$errorStatus = 400;
$originalPost = null;

if ($requestMethod === 'GET' && $isJson) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
  exit;
}

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
  if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
  }

  http_response_code(405);
  trux_redirect($returnPath);
}

if ($originalPostId <= 0) {
  if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid original post id.']);
    exit;
  }

  trux_flash_set('error', 'Invalid post id.');
  trux_redirect($returnPath);
}

$originalPost = trux_fetch_post_by_id($originalPostId);
if (!$originalPost) {
  if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Original post not found.']);
    exit;
  }

  trux_flash_set('error', 'Post not found.');
  trux_redirect($returnPath);
}

if ($requestMethod === 'POST') {
  $body = is_string($_POST['body'] ?? null) ? trim((string)$_POST['body']) : '';

  if ($body === '') {
    $error = 'Quote text cannot be empty.';
  } elseif (mb_strlen($body) > 2000) {
    $error = 'Quote text is too long (max 2000 characters).';
  } else {
    $quotePostId = trux_create_quote_post((int)$user['id'], $originalPostId, $body);
    if ($quotePostId <= 0) {
      $error = 'Could not create quote post.';
      $errorStatus = 500;
    } else {
      $originalAuthorId = (int)($originalPost['user_id'] ?? 0);
      if ($originalAuthorId > 0 && $originalAuthorId !== (int)$user['id']) {
        trux_notify_post_quote($originalAuthorId, (int)$user['id'], $quotePostId, $originalPostId);
      }

      trux_moderation_record_activity_event('post_created', (int)$user['id'], ['post_id' => $quotePostId]);

      if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
          'ok' => true,
          'post' => [
            'id' => $quotePostId,
            'url' => trux_post_viewer_url($quotePostId),
            'body' => $body,
            'body_html' => trux_render_post_body($body),
            'created_at' => date('Y-m-d H:i:s'),
            'time_ago' => 'just now',
          ],
          'message' => 'Quote posted!',
        ]);
        exit;
      }

      trux_flash_set('success', 'Quote posted!');
      trux_redirect(trux_post_viewer_path($quotePostId));
    }
  }

  if ($isJson && $error !== null) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($errorStatus);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
  }
}

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--studio">
  <section class="pageBand pageBand--studio">
    <div class="pageBand__main">
      <span class="pageBand__eyebrow">Composer</span>
      <h2 class="pageBand__title">Quote a post</h2>
      <p class="pageBand__copy">Add your take and publish it with the original post attached below the composer.</p>
    </div>
    <div class="pageBand__aside">
      <div class="pageBand__meta">
        <span>@<?= trux_e((string)$user['username']) ?></span>
        <strong>2000 character limit</strong>
      </div>
    </div>
  </section>

  <div class="editorStage">
    <section class="editorStage__main bandSurface">
      <div class="bandSurface__head">
        <div class="composePanel__head">
          <span class="bandSurface__eyebrow">Compose</span>
          <div class="composePanel__titleRow">
            <h3>Draft your quote</h3>
            <details class="composeGuide">
              <summary class="composeGuide__trigger" aria-label="Open publish guide">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.7" />
                  <path d="M12 10.25v5.25M12 7.8h.01" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </summary>
              <div class="composeGuide__panel">
                <span class="composeGuide__eyebrow">Publish guide</span>
                <h4>Before you quote</h4>
                <div class="composeGuide__stack">
                  <section class="utilityBand">
                    <div class="utilityBand__head"><h4>Formatting</h4></div>
                    <p class="muted">Mentions, hashtags, and links are rendered after publish using the existing post pipeline.</p>
                  </section>
                  <section class="utilityBand">
                    <div class="utilityBand__head"><h4>Attachment</h4></div>
                    <p class="muted">Quote posts stay text-only for now and always keep the original post preview attached below your comment.</p>
                  </section>
                </div>
              </div>
            </details>
          </div>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="flash flash--error"><?= trux_e($error) ?></div>
      <?php endif; ?>

      <form
        method="post"
        action="<?= TRUX_BASE_URL ?>/quote_post.php"
        class="form"
        data-ajax-compose="1"
        data-ajax-compose-error="Could not create quote post."
        data-no-fx="1">
        <?= trux_csrf_field() ?>
        <input type="hidden" name="original_post_id" value="<?= $originalPostId ?>">
        <input type="hidden" name="return" value="<?= trux_e($returnPath) ?>">

        <label class="field">
          <span>What&apos;s happening?</span>
          <textarea name="body" rows="6" maxlength="2000" required data-mention-input="1"><?= trux_e($body) ?></textarea>
          <div class="composeCounter" aria-live="polite">
            <span data-compose-char-count="1"><?= mb_strlen($body) ?></span>
            <span>/ 2000</span>
          </div>
          <small class="muted">Up to 2000 characters. Your quote appears above the attached original post preview.</small>
        </label>

        <section class="quoteComposer__quotedBlock" aria-label="Quoted original post">
          <div class="quoteComposer__quotedHeader">
            <span class="quoteComposer__quotedEyebrow">Original post</span>
            <a class="quoteComposer__quotedLink" href="<?= trux_e(trux_post_viewer_url($originalPostId)) ?>">Open viewer</a>
          </div>
          <?php
          $quotedPostRecord = $originalPost;
          $quotedPreviewDeleted = false;
          $quotedPreviewWrapperClass = 'quoteComposer__preview';
          require __DIR__ . '/_quoted_post_preview.php';
          ?>
        </section>

        <div class="editorStage__actions">
          <button class="shellButton shellButton--accent" type="submit">Quote post</button>
          <a class="shellButton shellButton--ghost" href="<?= trux_e(TRUX_BASE_URL . $returnPath) ?>">Cancel</a>
        </div>
      </form>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
