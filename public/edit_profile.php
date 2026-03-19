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
    'about_me' => (string)($me['about_me'] ?? ''),
    'location' => (string)($me['location'] ?? ''),
    'website_url' => (string)($me['website_url'] ?? ''),
    'profile_links' => trux_profile_fill_link_slots(trux_profile_decode_links((string)($me['profile_links_json'] ?? ''))),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linkLabels = is_array($_POST['profile_link_label'] ?? null) ? $_POST['profile_link_label'] : [];
    $linkUrls = is_array($_POST['profile_link_url'] ?? null) ? $_POST['profile_link_url'] : [];
    $linkRows = [];
    $maxLinkRows = max(count($linkLabels), count($linkUrls), trux_profile_link_limit());

    for ($i = 0; $i < $maxLinkRows; $i++) {
        $linkRows[] = [
            'label' => trim((string)($linkLabels[$i] ?? '')),
            'url' => trim((string)($linkUrls[$i] ?? '')),
        ];
    }

    $form = [
        'display_name' => trim((string)($_POST['display_name'] ?? '')),
        'bio' => trim((string)($_POST['bio'] ?? '')),
        'about_me' => trim((string)($_POST['about_me'] ?? '')),
        'location' => trim((string)($_POST['location'] ?? '')),
        'website_url' => trim((string)($_POST['website_url'] ?? '')),
        'profile_links' => trux_profile_fill_link_slots($linkRows),
    ];

    $normalized = trux_profile_normalize_payload([
        'display_name' => $form['display_name'],
        'bio' => $form['bio'],
        'about_me' => $form['about_me'],
        'location' => $form['location'],
        'website_url' => $form['website_url'],
        'profile_links' => $linkRows,
    ]);
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
            'about_me' => $normalizedData['about_me'] ?? null,
            'location' => $normalizedData['location'] ?? null,
            'website_url' => $normalizedData['website_url'] ?? null,
            'profile_links_json' => $normalizedData['profile_links_json'] ?? null,
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
  <p class="muted">Update your public profile details, About Me section, links, and media.</p>
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

      <div class="settingSection">
        <div class="settingSection__head">
          <h2 class="h2">Banner Profile</h2>
          <p class="muted">These details stay near the top of your profile card.</p>
        </div>

        <label class="field">
          <span>Display name</span>
          <input type="text" name="display_name" maxlength="80" value="<?= trux_e($form['display_name']) ?>" placeholder="@<?= trux_e($username) ?>">
        </label>

        <label class="field">
          <span>Bio</span>
          <textarea name="bio" rows="4" maxlength="280" placeholder="Tell people who you are..."><?= trux_e($form['bio']) ?></textarea>
          <small class="muted">Up to 280 characters for the short banner bio.</small>
        </label>

        <label class="field">
          <span>Location</span>
          <input type="text" name="location" maxlength="100" value="<?= trux_e($form['location']) ?>" placeholder="Kuala Lumpur, MY">
        </label>

        <label class="field">
          <span>Website</span>
          <input type="text" name="website_url" maxlength="255" value="<?= trux_e($form['website_url']) ?>" placeholder="https://example.com">
        </label>
      </div>

      <div class="settingSection">
        <div class="settingSection__head">
          <h2 class="h2">About Me</h2>
          <p class="muted">This section appears as a longer profile description on its own tab.</p>
        </div>

        <label class="field">
          <span>Long description</span>
          <textarea name="about_me" rows="7" maxlength="<?= trux_profile_about_me_limit() ?>" placeholder="Share your story, interests, projects, communities, or anything you want people to know."><?= trux_e($form['about_me']) ?></textarea>
          <small class="muted">Up to <?= trux_profile_about_me_limit() ?> characters.</small>
        </label>

        <div class="profileLinkEditor">
          <div class="profileLinkEditor__head">
            <h3 class="h2">Affiliated Links</h3>
            <p class="muted">Add up to <?= trux_profile_link_limit() ?> websites or social profiles. Icons are generated automatically from the link when possible.</p>
          </div>

          <?php foreach ($form['profile_links'] as $index => $linkRow): ?>
            <?php
            $rowLabel = trim((string)($linkRow['label'] ?? ''));
            $rowUrl = trim((string)($linkRow['url'] ?? ''));
            $rowProvider = $rowUrl !== '' ? trux_profile_link_provider($rowUrl) : 'website';
            $rowPreviewLabel = $rowLabel !== '' ? $rowLabel : ($rowUrl !== '' ? trux_profile_website_label($rowUrl) : 'Auto preview');
            ?>
            <div class="profileLinkEditor__row" data-link-preview-row="1">
              <div class="profileLinkPreview">
                <span class="profileLinkPreview__icon profileLink__icon profileLink__icon--<?= trux_e($rowProvider) ?>" data-link-preview-icon="1" aria-hidden="true">
                  <?= trux_profile_link_icon_svg($rowProvider) ?>
                </span>
                <div class="profileLinkPreview__body">
                  <strong class="profileLinkPreview__label" data-link-preview-label="1"><?= trux_e($rowPreviewLabel) ?></strong>
                  <small class="muted" data-link-preview-provider="1"><?= trux_e(ucfirst($rowProvider)) ?></small>
                </div>
              </div>
              <label class="field">
                <span>Display text <?= $index + 1 ?></span>
                <input
                  type="text"
                  name="profile_link_label[]"
                  maxlength="<?= trux_profile_link_label_limit() ?>"
                  value="<?= trux_e((string)($linkRow['label'] ?? '')) ?>"
                  data-link-preview-label-input="1"
                  placeholder="TruX on Reddit">
              </label>
              <label class="field">
                <span>Link URL <?= $index + 1 ?></span>
                <input
                  type="text"
                  name="profile_link_url[]"
                  maxlength="255"
                  value="<?= trux_e((string)($linkRow['url'] ?? '')) ?>"
                  data-link-preview-url-input="1"
                  placeholder="https://www.reddit.com/">
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="settingSection">
        <div class="settingSection__head">
          <h2 class="h2">Profile Media</h2>
          <p class="muted">Upload a banner and avatar for your public profile.</p>
        </div>

        <div class="profileMediaGrid">
          <div class="profileMediaCard">
            <h3 class="h2">Profile Photo</h3>
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
            <h3 class="h2">Profile Banner</h3>
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
      </div>

      <div class="row">
        <button class="btn" type="submit">Save profile</button>
        <a class="muted" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode($username) ?>">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
