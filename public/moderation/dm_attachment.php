<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

$attachmentId = (int)trux_int_param('id', 0);
if ($attachmentId <= 0) {
    http_response_code(400);
    echo 'Invalid attachment id.';
    exit;
}

$attachment = trux_moderation_fetch_direct_message_attachment($attachmentId);
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

$download = trux_int_param('download', 0) === 1;
$mimeType = trim((string)($attachment['mime_type'] ?? 'application/octet-stream'));
$isImage = trux_direct_message_is_image_mime($mimeType);
$safeName = trux_direct_message_sanitize_original_name((string)($attachment['original_name'] ?? 'attachment'));

$moderationMe = trux_current_user();
$actorUserId = (int)($moderationMe['id'] ?? 0);
if ($actorUserId > 0) {
    $context = [
        'attachment_id' => $attachmentId,
        'conversation_id' => (int)($attachment['conversation_id'] ?? 0),
        'download' => $download,
        'mime_type' => $mimeType,
    ];
    trux_moderation_record_activity_event('moderation_dm_attachment_accessed', $actorUserId, [
        'subject_type' => 'message',
        'subject_id' => (int)($attachment['message_id'] ?? 0),
        'related_user_id' => (int)($attachment['sender_user_id'] ?? 0),
        'source_url' => '/moderation/dm_attachment.php?id=' . $attachmentId . ($download ? '&download=1' : ''),
        'metadata' => $context,
    ]);
    trux_moderation_write_audit_log(
        $actorUserId,
        'moderation_dm_attachment_accessed',
        'message',
        (int)($attachment['message_id'] ?? 0),
        $context
    );
}

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
