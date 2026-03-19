<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

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
                if ($isJson) {
                    $post = trux_fetch_post_by_id($postId);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'ok' => true,
                        'post' => [
                            'id' => $postId,
                            'url' => TRUX_BASE_URL . '/post.php?id=' . $postId,
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
                trux_flash_set('success', 'Posted!');
                trux_redirect('/post.php?id=' . $postId);
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

<section class="card">
  <div class="card__body">
    <h1>New Post</h1>

    <?php if ($error): ?>
      <div class="flash flash--error"><?= trux_e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= TRUX_BASE_URL ?>/new_post.php" enctype="multipart/form-data" class="form" data-ajax-new-post="1" data-no-fx="1">
      <?= trux_csrf_field() ?>

      <label class="field">
        <span>What’s happening?</span>
        <textarea name="body" rows="5" maxlength="2000" required data-mention-input="1"><?= trux_e($body) ?></textarea>
        <small class="muted">Up to 2000 characters. Hashtags like <code>#php</code> and <code>#release_notes</code> are supported.</small>
      </label>

      <label class="field">
        <span>Optional image (JPG/PNG/GIF/WebP, max 4MB, max 4096×4096)</span>
        <input type="file" name="image" accept="image/*">
        <small class="muted">GIFs are re-encoded as PNG (animation removed) for safety.</small>
      </label>

      <div class="row">
        <button class="btn" type="submit">Post</button>
        <a class="muted" href="<?= TRUX_BASE_URL ?>/">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
