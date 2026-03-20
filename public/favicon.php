<?php

declare(strict_types=1);

$faviconPath = dirname(__DIR__) . '/src/logo/trux_logo.png';

if (!is_file($faviconPath) || !is_readable($faviconPath)) {
    http_response_code(404);
    exit;
}

$lastModified = filemtime($faviconPath);
if ($lastModified !== false) {
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

header('Content-Type: image/png');
header('Content-Length: ' . (string) filesize($faviconPath));
header('Cache-Control: public, max-age=86400');

readfile($faviconPath);
