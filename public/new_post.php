<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();
$user = trux_current_user();

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
            $postId = trux_create_post((int)$user['id'], $body, $imagePath);
            trux_flash_set('success', 'Posted!');
            trux_redirect('/post.php?id=' . $postId);
        }
    }
}

require_once __DIR__ . '/_header.php';
?>

<section class="card">
  <div class="card__body">
    <h1>New Post</h1>

    <?php if ($error): ?>
      <div class="flash flash--error"><?= trux_e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/new_post.php" enctype="multipart/form-data" class="form">
      <?= trux_csrf_field() ?>

      <label class="field">
        <span>What’s happening?</span>
        <textarea name="body" rows="5" maxlength="2000" required><?= trux_e($body) ?></textarea>
        <small class="muted">Up to 2000 characters.</small>
      </label>

      <label class="field">
        <span>Optional image (JPG/PNG/GIF/WebP, max 4MB, max 4096×4096)</span>
        <input type="file" name="image" accept="image/*">
        <small class="muted">GIFs are re-encoded as PNG (animation removed) for safety.</small>
      </label>

      <div class="row">
        <button class="btn" type="submit">Post</button>
        <a class="muted" href="/">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>