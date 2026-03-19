<?php
declare(strict_types=1);

function trux_delete_uploaded_file(?string $publicPath): void
{
    if (!is_string($publicPath) || $publicPath === '') {
        return;
    }

    if (!preg_match('#^/uploads/[A-Za-z0-9._-]+$#', $publicPath)) {
        return;
    }

    $abs = dirname(__DIR__) . '/public' . $publicPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

function trux_store_uploaded_image_raw(array $file, string $mime, string $publicUploadsDirAbs, string $publicUploadsUrlPrefix): array
{
    $ext = TRUX_ALLOWED_IMAGE_MIME[$mime] ?? null;
    if ($ext === null) {
        return ['ok' => false, 'path' => null, 'error' => 'Unsupported image type.'];
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!is_dir($publicUploadsDirAbs)) {
        if (!mkdir($publicUploadsDirAbs, 0755, true)) {
            return ['ok' => false, 'path' => null, 'error' => 'Could not create upload directory.'];
        }
    }

    $destAbs = rtrim($publicUploadsDirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $destAbs)) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not save uploaded image.'];
    }

    if (!@chmod($destAbs, 0644)) {
        @chmod($destAbs, 0664);
    }
    $publicPath = rtrim($publicUploadsUrlPrefix, '/') . '/' . $name;
    return ['ok' => true, 'path' => $publicPath, 'error' => null];
}

function trux_parse_image_crop_payload(mixed $raw): array
{
    if (!is_string($raw)) {
        return ['ok' => true, 'crop' => null, 'error' => null];
    }

    $raw = trim($raw);
    if ($raw === '') {
        return ['ok' => true, 'crop' => null, 'error' => null];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'crop' => null, 'error' => 'Invalid crop selection.'];
    }

    $crop = [];
    foreach (['x', 'y', 'width', 'height'] as $field) {
        $value = $decoded[$field] ?? null;
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            return ['ok' => false, 'crop' => null, 'error' => 'Invalid crop selection.'];
        }

        $number = (int) round((float) $value);
        if (($field === 'width' || $field === 'height') && $number <= 0) {
            return ['ok' => false, 'crop' => null, 'error' => 'Invalid crop selection.'];
        }
        if (($field === 'x' || $field === 'y') && $number < 0) {
            return ['ok' => false, 'crop' => null, 'error' => 'Invalid crop selection.'];
        }

        $crop[$field] = $number;
    }

    return ['ok' => true, 'crop' => $crop, 'error' => null];
}

function trux_crop_decoded_image($src, array $crop): array
{
    $srcWidth = imagesx($src);
    $srcHeight = imagesy($src);
    if ($srcWidth <= 0 || $srcHeight <= 0) {
        return ['ok' => false, 'image' => null, 'error' => 'Invalid crop source image.'];
    }

    $x = min(max(0, (int) ($crop['x'] ?? 0)), max(0, $srcWidth - 1));
    $y = min(max(0, (int) ($crop['y'] ?? 0)), max(0, $srcHeight - 1));
    $width = min(max(1, (int) ($crop['width'] ?? 1)), $srcWidth - $x);
    $height = min(max(1, (int) ($crop['height'] ?? 1)), $srcHeight - $y);

    if ($width <= 0 || $height <= 0) {
        return ['ok' => false, 'image' => null, 'error' => 'Invalid crop dimensions.'];
    }

    $dest = imagecreatetruecolor($width, $height);
    if (!$dest) {
        return ['ok' => false, 'image' => null, 'error' => 'Could not prepare cropped image.'];
    }

    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
    imagefill($dest, 0, 0, $transparent);

    $copied = imagecopyresampled($dest, $src, 0, 0, $x, $y, $width, $height, $width, $height);
    if (!$copied) {
        imagedestroy($dest);
        return ['ok' => false, 'image' => null, 'error' => 'Could not crop selected image.'];
    }

    return ['ok' => true, 'image' => $dest, 'error' => null];
}

function trux_handle_image_upload(array $file, string $publicUploadsDirAbs, string $publicUploadsUrlPrefix, ?array $crop = null): array
{
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

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!is_string($mime) || $mime === '') {
        return ['ok' => false, 'path' => null, 'error' => 'Could not detect file type.'];
    }

    if (!array_key_exists($mime, TRUX_ALLOWED_IMAGE_MIME)) {
        return ['ok' => false, 'path' => null, 'error' => 'Unsupported image type.'];
    }

    if (function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !isset($info[0], $info[1])) {
            return ['ok' => false, 'path' => null, 'error' => 'Invalid image file.'];
        }

        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w <= 0 || $h <= 0) {
            return ['ok' => false, 'path' => null, 'error' => 'Invalid image dimensions.'];
        }

        if ($w > TRUX_MAX_IMAGE_WIDTH || $h > TRUX_MAX_IMAGE_HEIGHT || ($w * $h) > TRUX_MAX_IMAGE_PIXELS) {
            return ['ok' => false, 'path' => null, 'error' => 'Image dimensions too large (max 4096x4096).'];
        }
    }

    $hasCrop = is_array($crop) && $crop !== [];

    // If GD is unavailable, keep uploads working by storing the validated file as-is.
    if (!function_exists('imagecreatetruecolor')) {
        if ($hasCrop) {
            return ['ok' => false, 'path' => null, 'error' => 'Image cropping is unavailable right now.'];
        }
        return trux_store_uploaded_image_raw($file, $mime, $publicUploadsDirAbs, $publicUploadsUrlPrefix);
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

    if ($hasCrop) {
        $cropped = trux_crop_decoded_image($src, $crop);
        if (!($cropped['ok'] ?? false)) {
            imagedestroy($src);
            return [
                'ok' => false,
                'path' => null,
                'error' => (string) ($cropped['error'] ?? 'Could not crop selected image.'),
            ];
        }

        $croppedImage = $cropped['image'] ?? null;
        if (!$croppedImage) {
            imagedestroy($src);
            return ['ok' => false, 'path' => null, 'error' => 'Could not crop selected image.'];
        }

        imagedestroy($src);
        $src = $croppedImage;
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

    if (!@chmod($destAbs, 0644)) {
        @chmod($destAbs, 0664);
    }

    $publicPath = rtrim($publicUploadsUrlPrefix, '/') . '/' . $name;
    return ['ok' => true, 'path' => $publicPath, 'error' => null];
}
