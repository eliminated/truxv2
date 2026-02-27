<?php
declare(strict_types=1);

function trux_gd_required_or_error(): ?string {
    if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesize')) {
        return 'Server image processing is not available (GD missing).';
    }
    return null;
}

function trux_handle_image_upload(array $file, string $publicUploadsDirAbs, string $publicUploadsUrlPrefix): array {
    // Returns: ['ok' => bool, 'path' => ?string, 'error' => ?string]
    if (!isset($file['error']) || !is_int($file['error'])) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid upload payload.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Upload failed.'];
    }

    if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid upload file.'];
    }

    $size = $file['size'] ?? null;
    if (!is_int($size) || $size <= 0) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid file size.'];
    }

    if ($size > TRUX_MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'path' => null, 'error' => 'File too large (max 4MB).'];
    }

    if ($gdErr = trux_gd_required_or_error()) {
        return ['ok' => false, 'path' => null, 'error' => $gdErr];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!is_string($mime) || $mime === '') {
        return ['ok' => false, 'path' => null, 'error' => 'Could not detect file type.'];
    }

    if (!array_key_exists($mime, TRUX_ALLOWED_IMAGE_MIME)) {
        return ['ok' => false, 'path' => null, 'error' => 'Unsupported image type.'];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($info[0], $info[1])) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid image file.'];
    }

    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w <= 0 || $h <= 0) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid image dimensions.'];
    }

    if ($w > TRUX_MAX_IMAGE_WIDTH || $h > TRUX_MAX_IMAGE_HEIGHT || ($w * $h) > TRUX_MAX_IMAGE_PIXELS) {
        return ['ok' => false, 'path' => null, 'error' => 'Image dimensions too large (max 4096x4096).'];
    }

    // Decode using GD, then re-encode to strip metadata.
    $src = null;
    $outMime = $mime;

    switch ($mime) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            // Hardening tradeoff: we re-encode GIF as PNG (drops animation).
            $src = @imagecreatefromgif($file['tmp_name']);
            $outMime = 'image/png';
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                return ['ok' => false, 'path' => null, 'error' => 'WebP not supported by server GD build.'];
            }
            $src = @imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return ['ok' => false, 'path' => null, 'error' => 'Unsupported image type.'];
    }

    if (!$src) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not decode image.'];
    }

    // Decide output extension based on output mime
    $ext = TRUX_ALLOWED_IMAGE_MIME[$outMime] ?? null;
    if ($ext === null) {
        imagedestroy($src);
        return ['ok' => false, 'path' => null, 'error' => 'Unsupported output image type.'];
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!is_dir($publicUploadsDirAbs)) {
        if (!mkdir($publicUploadsDirAbs, 0755, true)) {
            imagedestroy($src);
            return ['ok' => false, 'path' => null, 'error' => 'Could not create upload directory.'];
        }
    }

    $destAbs = rtrim($publicUploadsDirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

    $ok = false;
    if ($outMime === 'image/jpeg') {
        $ok = imagejpeg($src, $destAbs, 85);
    } elseif ($outMime === 'image/png') {
        imagealphablending($src, false);
        imagesavealpha($src, true);
        $ok = imagepng($src, $destAbs, 6);
    } elseif ($outMime === 'image/webp') {
        if (!function_exists('imagewebp')) {
            imagedestroy($src);
            return ['ok' => false, 'path' => null, 'error' => 'WebP not supported by server GD build.'];
        }
        $ok = imagewebp($src, $destAbs, 80);
    }

    imagedestroy($src);

    if (!$ok) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not save processed image.'];
    }

    @chmod($destAbs, 0644);

    $publicPath = rtrim($publicUploadsUrlPrefix, '/') . '/' . $name;
    return ['ok' => true, 'path' => $publicPath, 'error' => null];
}