<?php

declare(strict_types=1);

$faviconPath = dirname(__DIR__) . '/src/logo/trux_logo.png';
$scriptPath = __FILE__;

if (!is_file($faviconPath) || !is_readable($faviconPath)) {
    http_response_code(404);
    exit;
}

$lastModified = max((int)(filemtime($faviconPath) ?: 0), (int)(filemtime($scriptPath) ?: 0));
if ($lastModified > 0) {
    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    if ($ifModifiedSince !== '') {
        $sinceTimestamp = strtotime($ifModifiedSince);
        if ($sinceTimestamp !== false && $sinceTimestamp >= $lastModified) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }
    }

    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
}

$streamOriginalPng = static function () use ($faviconPath): void {
    header('Content-Type: image/png');
    header('Content-Length: ' . (string) filesize($faviconPath));
    header('Cache-Control: public, max-age=86400');
    readfile($faviconPath);
    exit;
};

$gdAvailable =
    function_exists('imagecreatefrompng') &&
    function_exists('imagesx') &&
    function_exists('imagesy') &&
    function_exists('imagecreatetruecolor') &&
    function_exists('imagealphablending') &&
    function_exists('imagesavealpha') &&
    function_exists('imagecolorallocatealpha') &&
    function_exists('imagefilledrectangle') &&
    function_exists('imagecopyresampled') &&
    function_exists('imagepng');

if (!$gdAvailable) {
    $streamOriginalPng();
}

$source = @imagecreatefrompng($faviconPath);
if (!$source instanceof GdImage) {
    $streamOriginalPng();
}

$srcWidth = imagesx($source);
$srcHeight = imagesy($source);
$minX = $srcWidth;
$minY = $srcHeight;
$maxX = -1;
$maxY = -1;

for ($y = 0; $y < $srcHeight; $y++) {
    for ($x = 0; $x < $srcWidth; $x++) {
        $rgba = imagecolorat($source, $x, $y);
        $alpha = ($rgba >> 24) & 0x7F;
        if ($alpha < 120) {
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        }
    }
}

$outputSize = 32;
$outputPadding = 2;
$drawSize = $outputSize - ($outputPadding * 2);

if ($maxX < $minX || $maxY < $minY || $drawSize <= 0) {
    imagedestroy($source);
    $streamOriginalPng();
}

$contentWidth = $maxX - $minX + 1;
$contentHeight = $maxY - $minY + 1;
$contentSize = max($contentWidth, $contentHeight);
$sourceBleed = max(6, (int)ceil($contentSize * 0.03));
$cropSize = min(max($contentWidth, $contentHeight) + ($sourceBleed * 2), max($srcWidth, $srcHeight));
$centerX = ($minX + $maxX) / 2;
$centerY = ($minY + $maxY) / 2;
$cropX = (int)round($centerX - ($cropSize / 2));
$cropY = (int)round($centerY - ($cropSize / 2));
$cropX = max(0, min($cropX, max(0, $srcWidth - $cropSize)));
$cropY = max(0, min($cropY, max(0, $srcHeight - $cropSize)));
$cropWidth = min($cropSize, $srcWidth - $cropX);
$cropHeight = min($cropSize, $srcHeight - $cropY);

$output = imagecreatetruecolor($outputSize, $outputSize);
imagealphablending($output, false);
imagesavealpha($output, true);
$transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
imagefilledrectangle($output, 0, 0, $outputSize, $outputSize, $transparent);

imagecopyresampled(
    $output,
    $source,
    $outputPadding,
    $outputPadding,
    $cropX,
    $cropY,
    $drawSize,
    $drawSize,
    $cropWidth,
    $cropHeight
);

imagedestroy($source);

ob_start();
imagepng($output);
$pngData = (string)ob_get_clean();
imagedestroy($output);

header('Content-Type: image/png');
header('Content-Length: ' . (string)strlen($pngData));
header('Cache-Control: public, max-age=86400');
echo $pngData;
