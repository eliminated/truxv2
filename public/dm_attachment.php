<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

$me = trux_current_user();
if (!$me) {
    http_response_code(401);
    echo 'Please log in to continue.';
    exit;
}

$attachmentId = (int)trux_int_param('id', 0);
if ($attachmentId <= 0) {
    http_response_code(400);
    echo 'Invalid attachment id.';
    exit;
}

$attachment = trux_fetch_direct_message_attachment_for_user($attachmentId, (int)$me['id']);
if (!$attachment) {
    http_response_code(404);
    echo 'Attachment not found.';
    exit;
}

$filePath = (string)($attachment['file_path'] ?? '');
$absolutePath = trux_direct_message_attachment_absolute_path($filePath);
if (!is_file($absolutePath)) {
    http_response_code(404);
    echo 'Attachment file not found.';
    exit;
}

$mimeType = trim((string)($attachment['mime_type'] ?? 'application/octet-stream'));
$download = trux_int_param('download', 0) === 1;
$isImage = trux_direct_message_is_image_mime($mimeType);
$safeName = trux_direct_message_sanitize_original_name((string)($attachment['original_name'] ?? 'attachment'));

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream'));
header('Content-Length: ' . (string)filesize($absolutePath));

$disposition = $download || !$isImage ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $safeName) . '"');

$stream = fopen($absolutePath, 'rb');
if ($stream === false) {
    http_response_code(500);
    echo 'Could not open attachment.';
    exit;
}

fpassthru($stream);
fclose($stream);
