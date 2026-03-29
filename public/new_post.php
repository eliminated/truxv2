<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'new-post';
$pageLayout = 'app';

trux_require_login();
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

$body = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = is_string($_POST['body'] ?? null) ? trim((string)$_POST['body']) : '';

  if ($body === '') {
    $error = 'Post text cannot be empty.';
  } elseif (mb_strlen($body) > 2000) {
    $error = 'Post is too long (max 2000 characters).';
  } else {
    $imagePath = null;

    if (isset($_FILES['image']) && is_array($_FILES['image'])) {
      $uploadsDirAbs = __DIR__ . '/uploads';
      $res = trux_handle_image_upload($_FILES['image'], $uploadsDirAbs, '/uploads');
      if (!($res['ok'] ?? false)) {
        $error = (string)($res['error'] ?? 'Image upload failed.');
      } else {
        $imagePath = $res['path'] ?? null;
      }
    }

    if ($error === null) {
      $postId = 0;
      try {
        $postId = trux_create_post((int)$user['id'], $body, $imagePath);
      } catch (Throwable) {
        if (is_string($imagePath) && $imagePath !== '') {
          trux_delete_uploaded_file($imagePath);
        }
        $error = 'Could not create post right now.';
      }

      if ($error === null && $postId <= 0) {
        if (is_string($imagePath) && $imagePath !== '') {
          trux_delete_uploaded_file($imagePath);
        }
        $error = 'Could not create post right now.';
      }

      if ($error === null) {
        trux_moderation_record_activity_event('post_created', (int)$user['id'], [
          'subject_type' => 'post',
          'subject_id' => $postId,
          'source_url' => trux_post_viewer_path($postId),
          'metadata' => [
            'has_image' => is_string($imagePath) && $imagePath !== '',
            'body_length' => mb_strlen($body),
            'link_count' => trux_moderation_link_count($body),
            'body_hash' => trux_moderation_text_fingerprint($body),
          ],
        ]);

        trux_flash_set('success', 'Posted!');
        if ($isJson) {
          $post = trux_fetch_post_by_id($postId);
          header('Content-Type: application/json; charset=utf-8');
          echo json_encode([
            'ok' => true,
            'post' => [
              'id' => $postId,
              'url' => trux_post_viewer_url($postId),
              'user_id' => (int)($post['user_id'] ?? $user['id']),
              'username' => (string)($post['username'] ?? $user['username']),
              'body' => (string)($post['body'] ?? $body),
              'body_html' => trux_render_post_body((string)($post['body'] ?? $body)),
              'image_path' => isset($post['image_path']) ? trux_public_url((string)$post['image_path']) : null,
              'created_at' => (string)($post['created_at'] ?? ''),
              'time_ago' => isset($post['created_at']) ? trux_time_ago((string)$post['created_at']) : 'just now',
            ],
            'message' => 'Posted!',
          ]);
          exit;
        }
        trux_redirect(trux_post_viewer_path($postId));
      }
    }
  }

  if ($isJson && $error !== null) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
  }
}

if ($isJson && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
  exit;
}

require_once __DIR__ . '/_header.php';
?>

<div class="pageFrame pageFrame--studio">
  <section class="pageBand pageBand--studio">
    <div class="pageBand__main">
      <span class="pageBand__eyebrow">Composer</span>
      <h2 class="pageBand__title">Create a new post</h2>
      <p class="pageBand__copy">Write, attach media, and publish into the stream without changing any existing validation or AJAX behavior.</p>
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
            <h3>Draft your update</h3>
            <details class="composeGuide">
              <summary class="composeGuide__trigger" aria-label="Open publish guide">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.7" />
                  <path d="M12 10.25v5.25M12 7.8h.01" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </summary>
              <div class="composeGuide__panel">
                <span class="composeGuide__eyebrow">Publish guide</span>
                <h4>Before you post</h4>
                <div class="composeGuide__stack">
                  <section class="utilityBand">
                    <div class="utilityBand__head"><h4>Formatting</h4></div>
                    <p class="muted">Mentions and links are rendered automatically after publish.</p>
                  </section>
                  <section class="utilityBand">
                    <div class="utilityBand__head"><h4>Media</h4></div>
                    <p class="muted">One image per post, processed with the same existing upload rules.</p>
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

      <form method="post" action="<?= TRUX_BASE_URL ?>/new_post.php" enctype="multipart/form-data" class="form" data-ajax-new-post="1" data-no-fx="1">
        <?= trux_csrf_field() ?>

        <label class="field">
          <span>What&apos;s happening?</span>
          <textarea name="body" rows="6" maxlength="2000" required data-mention-input="1"><?= trux_e($body) ?></textarea>
          <small class="muted">Up to 2000 characters. Hashtags like <code>#php</code> and <code>#release_notes</code> are supported.</small>
        </label>

        <label class="field">
          <span>Optional image (JPG/PNG/GIF/WebP, max 4MB, max 4096x4096)</span>
          <input type="file" name="image" accept="image/*">
          <small class="muted">GIFs are re-encoded as PNG for safety.</small>
        </label>

        <div class="editorStage__actions">
          <button class="shellButton shellButton--accent" type="submit">Post</button>
          <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/">Cancel</a>
        </div>
      </form>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
