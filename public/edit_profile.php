<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

trux_require_login();
$me = trux_current_user();
if (!$me) {
    trux_redirect('/login.php');
}

$viewerId = (int)$me['id'];
$username = (string)$me['username'];
$currentAvatarPath = is_string($me['avatar_path'] ?? null) ? (string)$me['avatar_path'] : '';
$currentBannerPath = is_string($me['banner_path'] ?? null) ? (string)$me['banner_path'] : '';
$currentAvatarUrl = $currentAvatarPath !== '' ? trux_public_url($currentAvatarPath) : '';
$currentBannerUrl = $currentBannerPath !== '' ? trux_public_url($currentBannerPath) : '';

$form = [
    'display_name' => (string)($me['display_name'] ?? ''),
    'bio' => (string)($me['bio'] ?? ''),
    'location' => (string)($me['location'] ?? ''),
    'website_url' => (string)($me['website_url'] ?? ''),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'display_name' => trim((string)($_POST['display_name'] ?? '')),
        'bio' => trim((string)($_POST['bio'] ?? '')),
        'location' => trim((string)($_POST['location'] ?? '')),
        'website_url' => trim((string)($_POST['website_url'] ?? '')),
    ];

    $normalized = trux_profile_normalize_payload($form);
    $errors = is_array($normalized['errors'] ?? null) ? $normalized['errors'] : [];
    $normalizedData = is_array($normalized['data'] ?? null) ? $normalized['data'] : [];

    $nextAvatarPath = $currentAvatarPath !== '' ? $currentAvatarPath : null;
    $nextBannerPath = $currentBannerPath !== '' ? $currentBannerPath : null;
    $deleteOldAvatarPath = null;
    $deleteOldBannerPath = null;
    $newUploadedAvatarPath = null;
    $newUploadedBannerPath = null;

    $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';
    $removeBanner = isset($_POST['remove_banner']) && $_POST['remove_banner'] === '1';

    if ($removeAvatar && $currentAvatarPath !== '') {
        $nextAvatarPath = null;
        $deleteOldAvatarPath = $currentAvatarPath;
    }

    if ($removeBanner && $currentBannerPath !== '') {
        $nextBannerPath = null;
        $deleteOldBannerPath = $currentBannerPath;
    }

    if ($errors === []) {
        $avatarFile = $_FILES['avatar'] ?? null;
        if (is_array($avatarFile)) {
            $avatarError = $avatarFile['error'] ?? UPLOAD_ERR_NO_FILE;
            if (is_int($avatarError) && $avatarError !== UPLOAD_ERR_NO_FILE) {
                if (trux_profile_upload_is_animated_gif($avatarFile) && !trux_profile_user_has_premium($viewerId)) {
                    $errors[] = 'Animated profile photos are a Premium feature (coming soon).';
                } else {
                    $avatarUpload = trux_handle_image_upload($avatarFile, __DIR__ . '/uploads', '/uploads');
                    if (!($avatarUpload['ok'] ?? false)) {
                        $errors[] = (string)($avatarUpload['error'] ?? 'Avatar upload failed.');
                    } else {
                        $uploadedAvatarPath = is_string($avatarUpload['path'] ?? null) ? (string)$avatarUpload['path'] : '';
                        if ($uploadedAvatarPath !== '') {
                            $nextAvatarPath = $uploadedAvatarPath;
                            $newUploadedAvatarPath = $uploadedAvatarPath;
                            if ($currentAvatarPath !== '' && $currentAvatarPath !== $uploadedAvatarPath) {
                                $deleteOldAvatarPath = $currentAvatarPath;
                            }
                        }
                    }
                }
            }
        }

        $bannerFile = $_FILES['banner'] ?? null;
        if (is_array($bannerFile)) {
            $bannerError = $bannerFile['error'] ?? UPLOAD_ERR_NO_FILE;
            if (is_int($bannerError) && $bannerError !== UPLOAD_ERR_NO_FILE) {
                $bannerUpload = trux_handle_image_upload($bannerFile, __DIR__ . '/uploads', '/uploads');
                if (!($bannerUpload['ok'] ?? false)) {
                    $errors[] = (string)($bannerUpload['error'] ?? 'Banner upload failed.');
                } else {
                    $uploadedBannerPath = is_string($bannerUpload['path'] ?? null) ? (string)$bannerUpload['path'] : '';
                    if ($uploadedBannerPath !== '') {
                        $nextBannerPath = $uploadedBannerPath;
                        $newUploadedBannerPath = $uploadedBannerPath;
                        if ($currentBannerPath !== '' && $currentBannerPath !== $uploadedBannerPath) {
                            $deleteOldBannerPath = $currentBannerPath;
                        }
                    }
                }
            }
        }
    }

    if ($errors === []) {
        $saveOk = trux_update_user_profile($viewerId, [
            'display_name' => $normalizedData['display_name'] ?? null,
            'bio' => $normalizedData['bio'] ?? null,
            'location' => $normalizedData['location'] ?? null,
            'website_url' => $normalizedData['website_url'] ?? null,
            'avatar_path' => $nextAvatarPath,
            'banner_path' => $nextBannerPath,
        ]);

        if ($saveOk) {
            if (is_string($deleteOldAvatarPath) && $deleteOldAvatarPath !== '') {
                trux_profile_delete_uploaded_file($deleteOldAvatarPath);
            }
            if (is_string($deleteOldBannerPath) && $deleteOldBannerPath !== '') {
                trux_profile_delete_uploaded_file($deleteOldBannerPath);
            }

            trux_flash_set('success', 'Profile updated.');
            trux_redirect('/profile.php?u=' . urlencode($username));
        }

        if (is_string($newUploadedAvatarPath) && $newUploadedAvatarPath !== '') {
            trux_profile_delete_uploaded_file($newUploadedAvatarPath);
        }
        if (is_string($newUploadedBannerPath) && $newUploadedBannerPath !== '') {
            trux_profile_delete_uploaded_file($newUploadedBannerPath);
        }

        $errors[] = 'Could not update profile right now.';
    } else {
        if (is_string($newUploadedAvatarPath) && $newUploadedAvatarPath !== '') {
            trux_profile_delete_uploaded_file($newUploadedAvatarPath);
        }
        if (is_string($newUploadedBannerPath) && $newUploadedBannerPath !== '') {
            trux_profile_delete_uploaded_file($newUploadedBannerPath);
        }
    }
}

require_once __DIR__ . '/_header.php';
?>

<section class="hero">
  <h1>Edit Profile</h1>
  <p class="muted">Update your public profile details and media.</p>
</section>

<section class="card settingsCard">
  <div class="card__body">
    <?php if ($errors): ?>
      <div class="flash flash--error">
        <?php foreach ($errors as $error): ?>
          <div><?= trux_e((string)$error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/edit_profile.php" enctype="multipart/form-data">
      <?= trux_csrf_field() ?>

      <label class="field">
        <span>Display name</span>
        <input type="text" name="display_name" maxlength="80" value="<?= trux_e($form['display_name']) ?>" placeholder="@<?= trux_e($username) ?>">
      </label>

      <label class="field">
        <span>Bio</span>
        <textarea name="bio" rows="4" maxlength="280" placeholder="Tell people who you are..."><?= trux_e($form['bio']) ?></textarea>
        <small class="muted">Up to 280 characters.</small>
      </label>

      <label class="field">
        <span>Location</span>
        <input type="text" name="location" maxlength="100" value="<?= trux_e($form['location']) ?>" placeholder="Kuala Lumpur, MY">
      </label>

      <label class="field">
        <span>Website</span>
        <input type="text" name="website_url" maxlength="255" value="<?= trux_e($form['website_url']) ?>" placeholder="https://example.com">
      </label>

      <div class="profileMediaGrid">
        <div class="profileMediaCard">
          <h2 class="h2">Profile Photo</h2>
          <?php if ($currentAvatarUrl !== ''): ?>
            <img class="profileMediaPreview profileMediaPreview--avatar" src="<?= trux_e($currentAvatarUrl) ?>" alt="Current profile photo" loading="lazy" decoding="async">
          <?php else: ?>
            <div class="profileMediaEmpty muted">No profile photo uploaded.</div>
          <?php endif; ?>
          <label class="field">
            <span>Upload photo</span>
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
          </label>
          <label class="row profileMediaToggle">
            <input type="checkbox" name="remove_avatar" value="1">
            <span>Remove current photo</span>
          </label>
          <small class="muted">
            Animated GIF profile photos are reserved for Premium and are currently unavailable.
            <a href="<?= TRUX_BASE_URL ?>/premium.php">Learn more</a>.
          </small>
        </div>

        <div class="profileMediaCard">
          <h2 class="h2">Profile Banner</h2>
          <?php if ($currentBannerUrl !== ''): ?>
            <img class="profileMediaPreview profileMediaPreview--banner" src="<?= trux_e($currentBannerUrl) ?>" alt="Current profile banner" loading="lazy" decoding="async">
          <?php else: ?>
            <div class="profileMediaEmpty muted">No banner uploaded.</div>
          <?php endif; ?>
          <label class="field">
            <span>Upload banner</span>
            <input type="file" name="banner" accept="image/jpeg,image/png,image/gif,image/webp">
          </label>
          <label class="row profileMediaToggle">
            <input type="checkbox" name="remove_banner" value="1">
            <span>Remove current banner</span>
          </label>
        </div>
      </div>

      <div class="row">
        <button class="btn" type="submit">Save profile</button>
        <a class="muted" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode($username) ?>">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
