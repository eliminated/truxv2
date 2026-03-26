<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$pageKey = 'edit-profile';
$pageLayout = 'app';

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
$currentAvatarImgAttr = $currentAvatarUrl !== '' ? ' src="' . trux_e($currentAvatarUrl) . '"' : '';
$currentBannerImgAttr = $currentBannerUrl !== '' ? ' src="' . trux_e($currentBannerUrl) . '"' : '';

$form = [
    'display_name' => (string)($me['display_name'] ?? ''),
    'bio' => (string)($me['bio'] ?? ''),
    'about_me' => (string)($me['about_me'] ?? ''),
    'location' => (string)($me['location'] ?? ''),
    'website_url' => (string)($me['website_url'] ?? ''),
    'profile_links' => trux_profile_fill_link_slots(trux_profile_decode_links((string)($me['profile_links_json'] ?? ''))),
];
$errors = [];
$allowedEditorTabs = ['banner-profile', 'about-me', 'profile-media'];
$requestedEditorTab = trim((string)($_POST['editor_tab'] ?? trux_str_param('editor_tab', 'banner-profile')));
$editorTab = in_array($requestedEditorTab, $allowedEditorTabs, true) ? $requestedEditorTab : 'banner-profile';

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
                $avatarCropPayload = trux_parse_image_crop_payload($_POST['avatar_crop'] ?? null);
                if (!($avatarCropPayload['ok'] ?? false)) {
                    $errors[] = 'Profile photo crop selection is invalid.';
                } elseif (trux_profile_upload_is_animated_gif($avatarFile) && !trux_profile_user_has_premium($viewerId)) {
                    $errors[] = 'Animated profile photos are a Premium feature (coming soon).';
                } else {
                    $avatarCrop = is_array($avatarCropPayload['crop'] ?? null) ? $avatarCropPayload['crop'] : null;
                    $avatarUpload = trux_handle_image_upload($avatarFile, __DIR__ . '/uploads', '/uploads', $avatarCrop);
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
                $bannerCropPayload = trux_parse_image_crop_payload($_POST['banner_crop'] ?? null);
                if (!($bannerCropPayload['ok'] ?? false)) {
                    $errors[] = 'Profile banner crop selection is invalid.';
                } else {
                    $bannerCrop = is_array($bannerCropPayload['crop'] ?? null) ? $bannerCropPayload['crop'] : null;
                    $bannerUpload = trux_handle_image_upload($bannerFile, __DIR__ . '/uploads', '/uploads', $bannerCrop);
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

<div class="pageFrame pageFrame--studio">
  <section class="pageBand pageBand--studio">
    <div class="pageBand__main">
      <span class="pageBand__eyebrow">Profile studio</span>
      <h2 class="pageBand__title">Edit profile</h2>
      <p class="pageBand__copy">Update your public profile details, About Me section, links, and media.</p>
    </div>
    <div class="pageBand__aside">
      <div class="pageBand__meta">
        <span>@<?= trux_e($username) ?></span>
        <strong>Live identity editor</strong>
      </div>
    </div>
  </section>

  <?php if ($errors): ?>
    <section class="settingsSectionCard settingsSectionCard--studio">
      <div class="flash flash--error">
        <?php foreach ($errors as $error): ?>
          <div><?= trux_e((string)$error) ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <form class="form settingsForm" method="post" action="<?= TRUX_BASE_URL ?>/edit_profile.php?editor_tab=<?= urlencode($editorTab) ?>" enctype="multipart/form-data">
    <?= trux_csrf_field() ?>
    <input type="hidden" name="editor_tab" value="<?= trux_e($editorTab) ?>">

    <nav class="profileTabs" id="profile-editor-tabs" aria-label="Edit profile sections">
      <a
        class="profileTabs__item<?= $editorTab === 'banner-profile' ? ' is-active' : '' ?>"
        href="<?= TRUX_BASE_URL ?>/edit_profile.php?editor_tab=banner-profile#profile-editor-tabs"
        <?= $editorTab === 'banner-profile' ? 'aria-current="page"' : '' ?>>
        Banner Profile
      </a>
      <a
        class="profileTabs__item<?= $editorTab === 'about-me' ? ' is-active' : '' ?>"
        href="<?= TRUX_BASE_URL ?>/edit_profile.php?editor_tab=about-me#profile-editor-tabs"
        <?= $editorTab === 'about-me' ? 'aria-current="page"' : '' ?>>
        About Me
      </a>
      <a
        class="profileTabs__item<?= $editorTab === 'profile-media' ? ' is-active' : '' ?>"
        href="<?= TRUX_BASE_URL ?>/edit_profile.php?editor_tab=profile-media#profile-editor-tabs"
        <?= $editorTab === 'profile-media' ? 'aria-current="page"' : '' ?>>
        Profile Media
      </a>
    </nav>

    <section class="settingsSectionCard settingsSectionCard--studio" id="edit-profile-banner-section"<?= $editorTab !== 'banner-profile' ? ' hidden' : '' ?>>
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
    </section>

    <section class="settingsSectionCard settingsSectionCard--studio" id="edit-profile-about-section"<?= $editorTab !== 'about-me' ? ' hidden' : '' ?>>
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
    </section>

    <section class="settingsSectionCard settingsSectionCard--studio" id="edit-profile-media-section"<?= $editorTab !== 'profile-media' ? ' hidden' : '' ?>>
      <div class="settingSection">
        <div class="settingSection__head">
          <h2 class="h2">Profile Media</h2>
          <p class="muted">Upload and crop your profile photo and banner independently.</p>
        </div>

        <div class="profileMediaGrid">
          <div
            class="profileMediaCard"
            data-profile-media-card="avatar"
            data-profile-media-type="avatar"
            data-profile-media-label="Profile photo"
            data-profile-media-aspect="1"
            data-profile-original-src="<?= trux_e($currentAvatarUrl) ?>">
            <h3 class="h2">Profile Photo</h3>
            <div class="profileMediaPreviewWrap">
              <div class="profileMediaPreviewFrame profileMediaPreviewFrame--avatar" data-profile-media-preview-frame="1"<?= $currentAvatarUrl === '' ? ' hidden' : '' ?>>
                <img class="profileMediaPreview profileMediaPreview--avatar"<?= $currentAvatarImgAttr ?> alt="Current profile photo" loading="lazy" decoding="async" data-profile-media-preview-image="1"<?= $currentAvatarUrl === '' ? ' hidden' : '' ?>>
              </div>
              <div class="profileMediaEmpty muted" data-profile-media-empty="1"<?= $currentAvatarUrl !== '' ? ' hidden' : '' ?>>No profile photo uploaded.</div>
            </div>
            <label class="field">
              <span>Upload photo</span>
              <input type="hidden" name="avatar_crop" value="" data-profile-media-crop="1">
              <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" data-profile-media-input="1" data-profile-max-bytes="<?= TRUX_MAX_UPLOAD_BYTES ?>">
            </label>
            <p class="muted profileMediaHint">Choosing a new photo opens the crop tool right away.</p>
            <button class="btn btn--small btn--ghost profileMediaCropButton" type="button" data-profile-media-recrop="1" hidden>Edit crop</button>
            <div class="muted profileMediaStatus" data-profile-media-status="1" hidden></div>
            <label class="row profileMediaToggle">
              <input type="checkbox" name="remove_avatar" value="1" data-profile-media-remove="1">
              <span>Remove current photo</span>
            </label>
            <small class="muted">
              Animated GIF profile photos are reserved for Premium and are currently unavailable.
              <a href="<?= TRUX_BASE_URL ?>/premium.php">Learn more</a>.
            </small>
          </div>

          <div
            class="profileMediaCard"
            data-profile-media-card="banner"
            data-profile-media-type="banner"
            data-profile-media-label="Profile banner"
            data-profile-media-aspect="<?= trux_e((string)(16 / 6)) ?>"
            data-profile-original-src="<?= trux_e($currentBannerUrl) ?>">
            <h3 class="h2">Profile Banner</h3>
            <div class="profileMediaPreviewWrap">
              <div class="profileMediaPreviewFrame profileMediaPreviewFrame--banner" data-profile-media-preview-frame="1"<?= $currentBannerUrl === '' ? ' hidden' : '' ?>>
                <img class="profileMediaPreview profileMediaPreview--banner"<?= $currentBannerImgAttr ?> alt="Current profile banner" loading="lazy" decoding="async" data-profile-media-preview-image="1"<?= $currentBannerUrl === '' ? ' hidden' : '' ?>>
              </div>
              <div class="profileMediaEmpty muted" data-profile-media-empty="1"<?= $currentBannerUrl !== '' ? ' hidden' : '' ?>>No banner uploaded.</div>
            </div>
            <label class="field">
              <span>Upload banner</span>
              <input type="hidden" name="banner_crop" value="" data-profile-media-crop="1">
              <input type="file" name="banner" accept="image/jpeg,image/png,image/gif,image/webp" data-profile-media-input="1" data-profile-max-bytes="<?= TRUX_MAX_UPLOAD_BYTES ?>">
            </label>
            <p class="muted profileMediaHint">Choose a banner to crop it before saving.</p>
            <button class="btn btn--small btn--ghost profileMediaCropButton" type="button" data-profile-media-recrop="1" hidden>Edit crop</button>
            <div class="muted profileMediaStatus" data-profile-media-status="1" hidden></div>
            <label class="row profileMediaToggle">
              <input type="checkbox" name="remove_banner" value="1" data-profile-media-remove="1">
              <span>Remove current banner</span>
            </label>
          </div>
        </div>
      </div>
    </section>

    <section class="settingsSectionCard settingsSectionCard--studio">
      <div class="row">
        <button class="shellButton shellButton--accent" type="submit">Save profile</button>
        <a class="shellButton shellButton--ghost" href="<?= TRUX_BASE_URL ?>/profile.php?u=<?= urlencode($username) ?>">Cancel</a>
      </div>
    </section>
  </form>
</div>

<div id="profileCropperModal" class="profileCropperModal" hidden>
  <div class="profileCropperModal__backdrop" data-profile-crop-close="1"></div>
  <section class="profileCropperModal__panel" role="dialog" aria-modal="true" aria-labelledby="profileCropperTitle">
    <header class="profileCropperModal__head">
      <div class="profileCropperModal__titleWrap">
        <h2 id="profileCropperTitle" data-profile-crop-title="1">Crop image</h2>
        <p class="muted" data-profile-crop-subtitle="1">Drag to reposition and use zoom to tighten the crop.</p>
      </div>
      <button class="iconBtn profileCropperModal__close" type="button" aria-label="Close cropper" data-profile-crop-close="1">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </button>
    </header>

    <div class="profileCropper">
      <div class="profileCropper__workspace">
        <div class="profileCropper__stage">
          <div class="profileCropper__viewport" data-profile-crop-viewport="1">
            <img class="profileCropper__image" alt="" data-profile-crop-image="1">
            <div class="profileCropper__grid" aria-hidden="true"></div>
            <div class="profileCropper__avatarGuide" aria-hidden="true" data-profile-crop-avatar-guide="1"></div>
          </div>
        </div>

        <aside class="profileCropper__sidebar">
          <div class="profileCropper__previewCard">
            <span class="profileCropper__eyebrow">Preview</span>
            <div class="profileCropper__previewFrame" data-profile-crop-preview-frame="1">
              <img class="profileCropper__previewImage" alt="" data-profile-crop-preview-image="1">
            </div>
          </div>

          <label class="field profileCropper__field">
            <span>Zoom</span>
            <input type="range" min="100" max="300" step="1" value="100" data-profile-crop-zoom="1">
          </label>

          <button class="btn btn--small btn--ghost profileCropper__reset" type="button" data-profile-crop-reset="1">Reset position</button>
        </aside>
      </div>

      <div class="row profileCropper__actions">
        <button class="btn btn--small btn--ghost" type="button" data-profile-crop-cancel="1">Cancel</button>
        <button class="btn btn--small" type="button" data-profile-crop-apply="1">Apply crop</button>
      </div>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
