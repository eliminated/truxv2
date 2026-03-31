<?php
declare(strict_types=1);

function trux_fetch_user_by_id(int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT id, username, email, display_name, bio, location, website_url, avatar_path, banner_path, created_at, staff_role
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function trux_direct_message_body_limit(): int {
    return 2000;
}

function trux_direct_message_default_fetch_limit(): int {
    return 60;
}

function trux_direct_message_edit_window_seconds(): int {
    return 15 * 60;
}

function trux_direct_message_unsend_window_seconds(): int {
    return 15 * 60;
}

function trux_direct_message_max_attachments(): int {
    return 10;
}

function trux_direct_message_attachment_allowed_mime_map(): array {
    return array_merge(TRUX_ALLOWED_IMAGE_MIME, [
        'application/pdf' => 'pdf',
    ]);
}

function trux_direct_message_is_image_mime(string $mimeType): bool {
    return array_key_exists($mimeType, TRUX_ALLOWED_IMAGE_MIME);
}

function trux_direct_message_attachment_storage_root(): string {
    return dirname(__DIR__) . '/storage';
}

function trux_direct_message_attachment_storage_dir(): string {
    return trux_direct_message_attachment_storage_root() . '/dm_attachments';
}

function trux_direct_message_attachment_relative_path(string $fileName): string {
    return 'dm_attachments/' . ltrim($fileName, '/');
}

function trux_direct_message_attachment_is_safe_relative_path(string $filePath): bool {
    return preg_match('#^dm_attachments/[A-Za-z0-9._-]+$#', trim($filePath)) === 1;
}

function trux_direct_message_attachment_absolute_path(string $filePath): string {
    return trux_direct_message_attachment_storage_root() . '/' . ltrim($filePath, '/');
}

function trux_direct_message_attachment_view_url(int $attachmentId, bool $download = false): string {
    $query = ['id' => $attachmentId];
    if ($download) {
        $query['download'] = '1';
    }

    return TRUX_BASE_URL . '/dm_attachment.php?' . http_build_query($query);
}

function trux_direct_message_attachment_ensure_storage_dir(): void {
    $dir = trux_direct_message_attachment_storage_dir();
    if (is_dir($dir)) {
        return;
    }

    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create DM attachment storage.');
    }
}

function trux_direct_message_delete_attachment_file(?string $filePath): void {
    $path = trim((string)$filePath);
    if ($path === '' || !trux_direct_message_attachment_is_safe_relative_path($path)) {
        return;
    }

    $absolute = trux_direct_message_attachment_absolute_path($path);
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function trux_direct_message_sanitize_original_name(string $name, string $fallbackBase = 'attachment'): string {
    $name = trim(str_replace('\\', '/', $name));
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
    $name = trim($name);

    if ($name === '') {
        $name = $fallbackBase;
    }

    return mb_substr($name, 0, 255);
}

function trux_direct_message_random_file_name(string $extension): string {
    return bin2hex(random_bytes(16)) . '.' . ltrim($extension, '.');
}

function trux_direct_message_pair(int $userA, int $userB): ?array {
    if ($userA <= 0 || $userB <= 0 || $userA === $userB) {
        return null;
    }

    return $userA < $userB
        ? [$userA, $userB]
        : [$userB, $userA];
}

function trux_fetch_direct_conversation_between(int $viewerId, int $otherUserId): ?array {
    $pair = trux_direct_message_pair($viewerId, $otherUserId);
    if (!$pair) {
        return null;
    }

    [$userOneId, $userTwoId] = $pair;
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT c.id, c.user_one_id, c.user_two_id, c.created_at, c.updated_at,
                u.id AS other_user_id, u.username AS other_username, u.display_name AS other_display_name,
                u.avatar_path AS other_avatar_path
         FROM direct_conversations c
         JOIN users u ON u.id = ?
         WHERE c.user_one_id = ? AND c.user_two_id = ?
         LIMIT 1'
    );
    $stmt->execute([$otherUserId, $userOneId, $userTwoId]);
    $conversation = $stmt->fetch();

    return $conversation ?: null;
}

function trux_fetch_direct_conversation_for_user(int $conversationId, int $viewerId): ?array {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return null;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT c.id, c.user_one_id, c.user_two_id, c.created_at, c.updated_at,
                u.id AS other_user_id, u.username AS other_username, u.display_name AS other_display_name,
                u.avatar_path AS other_avatar_path
         FROM direct_conversations c
         JOIN users u ON u.id = CASE
            WHEN c.user_one_id = :viewer_id THEN c.user_two_id
            ELSE c.user_one_id
         END
         WHERE c.id = :conversation_id
           AND (c.user_one_id = :viewer_one OR c.user_two_id = :viewer_two)
         LIMIT 1'
    );
    $stmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(':viewer_one', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':viewer_two', $viewerId, PDO::PARAM_INT);
    $stmt->execute();
    $conversation = $stmt->fetch();

    return $conversation ?: null;
}

function trux_get_or_create_direct_conversation(int $viewerId, int $otherUserId): int {
    $pair = trux_direct_message_pair($viewerId, $otherUserId);
    if (!$pair) {
        return 0;
    }

    [$userOneId, $userTwoId] = $pair;
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'INSERT INTO direct_conversations (user_one_id, user_two_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([$userOneId, $userTwoId]);

        return (int)$db->lastInsertId();
    } catch (PDOException) {
        return 0;
    }
}

function trux_direct_message_normalize_uploaded_files(array $files): array {
    if (!isset($files['name'])) {
        return [];
    }

    $normalized = [];
    $names = $files['name'];
    $types = $files['type'] ?? null;
    $tmpNames = $files['tmp_name'] ?? null;
    $errors = $files['error'] ?? null;
    $sizes = $files['size'] ?? null;

    if (is_array($names)) {
        foreach ($names as $index => $name) {
            $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;
            if (!is_int($error) || $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'name' => is_string($name) ? $name : '',
                'type' => is_array($types) && is_string($types[$index] ?? null) ? (string)$types[$index] : '',
                'tmp_name' => is_array($tmpNames) && is_string($tmpNames[$index] ?? null) ? (string)$tmpNames[$index] : '',
                'error' => $error,
                'size' => is_array($sizes) && is_numeric($sizes[$index] ?? null) ? (int)$sizes[$index] : 0,
            ];
        }

        return $normalized;
    }

    $error = is_int($errors) ? $errors : UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return [];
    }

    return [[
        'name' => is_string($names) ? $names : '',
        'type' => is_string($types) ? $types : '',
        'tmp_name' => is_string($tmpNames) ? $tmpNames : '',
        'error' => $error,
        'size' => is_numeric($sizes) ? (int)$sizes : 0,
    ]];
}

function trux_direct_message_validate_attachment_file(array $file): array {
    $error = $file['error'] ?? null;
    if (!is_int($error)) {
        return ['ok' => false, 'error' => 'Invalid upload payload.'];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Attachment upload failed.'];
    }

    $tmpName = $file['tmp_name'] ?? null;
    if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'error' => 'Invalid attachment upload.'];
    }

    $size = $file['size'] ?? null;
    if (!is_int($size) || $size <= 0) {
        return ['ok' => false, 'error' => 'Attachment size is invalid.'];
    }
    if ($size > TRUX_MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'Attachment is too large (max 4MB per file).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    if (!is_string($mime) || $mime === '') {
        return ['ok' => false, 'error' => 'Could not detect attachment type.'];
    }

    $allowedMime = trux_direct_message_attachment_allowed_mime_map();
    if (!array_key_exists($mime, $allowedMime)) {
        return ['ok' => false, 'error' => 'Only images and PDF attachments are allowed.'];
    }

    $originalName = trux_direct_message_sanitize_original_name(
        is_string($file['name'] ?? null) ? (string)$file['name'] : '',
        trux_direct_message_is_image_mime($mime) ? 'image' : 'document'
    );

    return [
        'ok' => true,
        'mime_type' => $mime,
        'file_size' => $size,
        'tmp_name' => $tmpName,
        'original_name' => $originalName,
        'extension' => (string)$allowedMime[$mime],
    ];
}

function trux_direct_message_store_pdf_attachment(array $validated): array {
    trux_direct_message_attachment_ensure_storage_dir();

    $fileName = trux_direct_message_random_file_name((string)$validated['extension']);
    $relativePath = trux_direct_message_attachment_relative_path($fileName);
    $absolutePath = trux_direct_message_attachment_absolute_path($relativePath);

    if (!move_uploaded_file((string)$validated['tmp_name'], $absolutePath)) {
        return ['ok' => false, 'error' => 'Could not save attachment.'];
    }

    @chmod($absolutePath, 0644);

    return [
        'ok' => true,
        'file_path' => $relativePath,
        'original_name' => (string)$validated['original_name'],
        'mime_type' => (string)$validated['mime_type'],
        'file_size' => (int)(filesize($absolutePath) ?: (int)$validated['file_size']),
        'image_width' => null,
        'image_height' => null,
    ];
}

function trux_direct_message_store_image_attachment(array $validated): array {
    $tmpName = (string)$validated['tmp_name'];
    $mime = (string)$validated['mime_type'];

    if (function_exists('getimagesize')) {
        $info = @getimagesize($tmpName);
        if ($info === false || !isset($info[0], $info[1])) {
            return ['ok' => false, 'error' => 'Invalid image attachment.'];
        }

        $width = (int)$info[0];
        $height = (int)$info[1];
        if ($width <= 0 || $height <= 0) {
            return ['ok' => false, 'error' => 'Invalid image attachment.'];
        }
        if ($width > TRUX_MAX_IMAGE_WIDTH || $height > TRUX_MAX_IMAGE_HEIGHT || ($width * $height) > TRUX_MAX_IMAGE_PIXELS) {
            return ['ok' => false, 'error' => 'Image attachment dimensions are too large.'];
        }
    } else {
        $width = null;
        $height = null;
    }

    trux_direct_message_attachment_ensure_storage_dir();

    if (!function_exists('imagecreatetruecolor')) {
        $fileName = trux_direct_message_random_file_name((string)$validated['extension']);
        $relativePath = trux_direct_message_attachment_relative_path($fileName);
        $absolutePath = trux_direct_message_attachment_absolute_path($relativePath);

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            return ['ok' => false, 'error' => 'Could not save image attachment.'];
        }

        @chmod($absolutePath, 0644);

        return [
            'ok' => true,
            'file_path' => $relativePath,
            'original_name' => (string)$validated['original_name'],
            'mime_type' => $mime,
            'file_size' => (int)(filesize($absolutePath) ?: (int)$validated['file_size']),
            'image_width' => $width,
            'image_height' => $height,
        ];
    }

    $source = null;
    $outputMime = $mime;

    switch ($mime) {
        case 'image/jpeg':
            $source = @imagecreatefromjpeg($tmpName);
            break;
        case 'image/png':
            $source = @imagecreatefrompng($tmpName);
            break;
        case 'image/gif':
            $source = @imagecreatefromgif($tmpName);
            $outputMime = 'image/png';
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                return ['ok' => false, 'error' => 'WebP attachments are not supported on this server.'];
            }
            $source = @imagecreatefromwebp($tmpName);
            break;
        default:
            return ['ok' => false, 'error' => 'Unsupported image attachment.'];
    }

    if (!$source) {
        return ['ok' => false, 'error' => 'Could not decode image attachment.'];
    }

    $outputExtension = (string)(trux_direct_message_attachment_allowed_mime_map()[$outputMime] ?? '');
    if ($outputExtension === '') {
        imagedestroy($source);
        return ['ok' => false, 'error' => 'Unsupported image attachment.'];
    }

    $fileName = trux_direct_message_random_file_name($outputExtension);
    $relativePath = trux_direct_message_attachment_relative_path($fileName);
    $absolutePath = trux_direct_message_attachment_absolute_path($relativePath);

    $saved = false;
    if ($outputMime === 'image/jpeg') {
        $saved = imagejpeg($source, $absolutePath, 85);
    } elseif ($outputMime === 'image/png') {
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $saved = imagepng($source, $absolutePath, 6);
    } elseif ($outputMime === 'image/webp') {
        if (!function_exists('imagewebp')) {
            imagedestroy($source);
            return ['ok' => false, 'error' => 'WebP attachments are not supported on this server.'];
        }
        $saved = imagewebp($source, $absolutePath, 80);
    }

    $outputWidth = imagesx($source);
    $outputHeight = imagesy($source);
    imagedestroy($source);

    if (!$saved) {
        return ['ok' => false, 'error' => 'Could not save image attachment.'];
    }

    @chmod($absolutePath, 0644);

    return [
        'ok' => true,
        'file_path' => $relativePath,
        'original_name' => (string)$validated['original_name'],
        'mime_type' => $outputMime,
        'file_size' => (int)(filesize($absolutePath) ?: (int)$validated['file_size']),
        'image_width' => $outputWidth > 0 ? $outputWidth : $width,
        'image_height' => $outputHeight > 0 ? $outputHeight : $height,
    ];
}

function trux_direct_message_store_attachment(array $file): array {
    $validated = trux_direct_message_validate_attachment_file($file);
    if (!($validated['ok'] ?? false)) {
        return $validated;
    }

    if (trux_direct_message_is_image_mime((string)$validated['mime_type'])) {
        return trux_direct_message_store_image_attachment($validated);
    }

    return trux_direct_message_store_pdf_attachment($validated);
}

function trux_send_direct_message_record(
    int $senderUserId,
    int $recipientUserId,
    string $body,
    array $uploadedFiles = [],
    int $replyToMessageId = 0
): array {
    $body = trim($body);
    $attachmentCount = count($uploadedFiles);

    if ($senderUserId <= 0 || $recipientUserId <= 0 || $senderUserId === $recipientUserId) {
        return ['ok' => false, 'error' => 'Invalid message recipient.'];
    }
    if ($body === '' && $attachmentCount === 0) {
        return ['ok' => false, 'error' => 'Message must contain text or attachments.'];
    }
    if (mb_strlen($body) > trux_direct_message_body_limit()) {
        return ['ok' => false, 'error' => 'Message must be 1-2000 characters or include attachments.'];
    }
    if ($attachmentCount > trux_direct_message_max_attachments()) {
        return ['ok' => false, 'error' => 'You can attach up to 10 files per message.'];
    }

    $existingConversation = trux_fetch_direct_conversation_between($senderUserId, $recipientUserId);
    $conversationId = $existingConversation
        ? (int)($existingConversation['id'] ?? 0)
        : trux_get_or_create_direct_conversation($senderUserId, $recipientUserId);
    if ($conversationId <= 0) {
        return ['ok' => false, 'error' => 'Could not start a conversation.'];
    }

    if ($replyToMessageId > 0) {
        $replyTarget = trux_fetch_direct_message_for_user($replyToMessageId, $senderUserId);
        if (!$replyTarget || (int)($replyTarget['conversation_id'] ?? 0) !== $conversationId) {
            return ['ok' => false, 'error' => 'Reply target not found.'];
        }
    } else {
        $replyToMessageId = 0;
    }

    $db = trux_db();
    $storedAttachments = [];

    try {
        $db->beginTransaction();

        $insert = $db->prepare(
            'INSERT INTO direct_messages (
                conversation_id,
                sender_user_id,
                body,
                reply_to_message_id,
                edit_window_expires_at,
                delete_window_expires_at
             )
             VALUES (
                ?,
                ?,
                ?,
                ?,
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE),
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)
             )'
        );
        $insert->execute([
            $conversationId,
            $senderUserId,
            $body !== '' ? $body : null,
            $replyToMessageId > 0 ? $replyToMessageId : null,
        ]);
        $messageId = (int)$db->lastInsertId();

        foreach ($uploadedFiles as $file) {
            $stored = trux_direct_message_store_attachment($file);
            if (!($stored['ok'] ?? false)) {
                throw new RuntimeException((string)($stored['error'] ?? 'Could not upload attachment.'));
            }

            $storedAttachments[] = $stored;
            $attachmentInsert = $db->prepare(
                'INSERT INTO direct_message_attachments (
                    message_id,
                    file_path,
                    original_name,
                    mime_type,
                    file_size,
                    image_width,
                    image_height
                 ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $attachmentInsert->execute([
                $messageId,
                (string)$stored['file_path'],
                (string)$stored['original_name'],
                (string)$stored['mime_type'],
                (int)$stored['file_size'],
                $stored['image_width'] !== null ? (int)$stored['image_width'] : null,
                $stored['image_height'] !== null ? (int)$stored['image_height'] : null,
            ]);
        }

        $update = $db->prepare(
            'UPDATE direct_conversations
             SET updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $update->execute([$conversationId]);

        $db->commit();

        return [
            'ok' => true,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'created_conversation' => $existingConversation === null,
        ];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        foreach ($storedAttachments as $storedAttachment) {
            trux_direct_message_delete_attachment_file((string)($storedAttachment['file_path'] ?? ''));
        }

        return [
            'ok' => false,
            'error' => $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Could not send message.',
        ];
    }
}

function trux_send_direct_message(
    int $senderUserId,
    int $recipientUserId,
    string $body,
    int $replyToMessageId = 0
): int {
    $result = trux_send_direct_message_record($senderUserId, $recipientUserId, $body, [], $replyToMessageId);
    if (!($result['ok'] ?? false)) {
        return 0;
    }

    return (int)($result['conversation_id'] ?? 0);
}

function trux_direct_message_fetch_raw_row_for_user(int $messageId, int $viewerId): ?array {
    if ($messageId <= 0 || $viewerId <= 0) {
        return null;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.reply_to_message_id, m.created_at, m.read_at,
                m.edited_at, m.edit_window_expires_at, m.deleted_for_everyone_at, m.delete_window_expires_at,
                u.username AS sender_username, u.display_name AS sender_display_name
         FROM direct_messages m
         JOIN direct_conversations c ON c.id = m.conversation_id
         JOIN users u ON u.id = m.sender_user_id
         WHERE m.id = ?
           AND (c.user_one_id = ? OR c.user_two_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$messageId, $viewerId, $viewerId]);
    $message = $stmt->fetch();

    return $message ?: null;
}

function trux_direct_message_fetch_raw_rows_by_ids(array $messageIds): array {
    $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn (int $id): bool => $id > 0)));
    if ($messageIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($messageIds), '?'));
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.reply_to_message_id, m.created_at, m.read_at,
                m.edited_at, m.edit_window_expires_at, m.deleted_for_everyone_at, m.delete_window_expires_at,
                u.username AS sender_username, u.display_name AS sender_display_name
         FROM direct_messages m
         JOIN users u ON u.id = m.sender_user_id
         WHERE m.id IN (' . $placeholders . ')
         ORDER BY m.id ASC'
    );
    $stmt->execute($messageIds);

    return $stmt->fetchAll();
}

function trux_direct_message_fetch_attachment_rows_by_message_ids(array $messageIds): array {
    $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn (int $id): bool => $id > 0)));
    if ($messageIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($messageIds), '?'));
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT id, message_id, file_path, original_name, mime_type, file_size, image_width, image_height, created_at
         FROM direct_message_attachments
         WHERE message_id IN (' . $placeholders . ')
         ORDER BY id ASC'
    );
    $stmt->execute($messageIds);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $messageId = (int)($row['message_id'] ?? 0);
        if ($messageId <= 0) {
            continue;
        }

        if (!isset($map[$messageId])) {
            $map[$messageId] = [];
        }

        $map[$messageId][] = $row;
    }

    return $map;
}

function trux_direct_message_attach_rows_with_attachments(array $rows): array {
    if ($rows === []) {
        return [];
    }

    $attachmentMap = trux_direct_message_fetch_attachment_rows_by_message_ids(array_column($rows, 'id'));
    foreach ($rows as &$row) {
        $messageId = (int)($row['id'] ?? 0);
        $row['attachments'] = $attachmentMap[$messageId] ?? [];
    }
    unset($row);

    return $rows;
}

function trux_direct_message_deleted_copy(): string {
    return 'Message deleted.';
}

function trux_direct_message_is_unsent(array $message): bool {
    return trim((string)($message['deleted_for_everyone_at'] ?? '')) !== '';
}

function trux_direct_message_is_within_window(?string $expiresAt): bool {
    $expiresAt = trim((string)$expiresAt);
    if ($expiresAt === '') {
        return false;
    }

    $expires = trux_parse_datetime($expiresAt);
    if (!$expires) {
        return false;
    }

    return $expires->getTimestamp() >= time();
}

function trux_direct_message_day_key(string $timestamp): string {
    $dt = trux_parse_datetime($timestamp);
    if (!$dt) {
        return '';
    }

    return $dt->format('Y-m-d');
}

function trux_direct_message_day_label(string $timestamp): string {
    $dt = trux_parse_datetime($timestamp);
    if (!$dt) {
        return $timestamp;
    }

    return $dt->format('M j, Y');
}

function trux_direct_message_preview_from_message(array $message, int $limit = 90): string {
    if (trux_direct_message_is_unsent($message)) {
        return trux_direct_message_deleted_copy();
    }

    $body = trim((string)($message['body'] ?? ''));
    if ($body !== '') {
        return trux_direct_message_preview($body, $limit);
    }

    $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
    $count = count($attachments);
    if ($count <= 0) {
        return 'No messages yet.';
    }

    if ($count === 1) {
        $mimeType = trim((string)($attachments[0]['mime_type'] ?? ''));
        return trux_direct_message_is_image_mime($mimeType) ? 'Photo' : 'PDF attachment';
    }

    return $count . ' attachments';
}

function trux_direct_message_attach_rows_with_reply_context(array $rows): array {
    if ($rows === []) {
        return [];
    }

    $replyIds = array_values(array_unique(array_filter(
        array_map(static fn (array $row): int => (int)($row['reply_to_message_id'] ?? 0), $rows),
        static fn (int $id): bool => $id > 0
    )));

    $replyMap = [];
    if ($replyIds !== []) {
        $replyRows = trux_direct_message_fetch_raw_rows_by_ids($replyIds);
        $replyRows = trux_direct_message_attach_rows_with_attachments($replyRows);
        foreach ($replyRows as $replyRow) {
            $replyId = (int)($replyRow['id'] ?? 0);
            if ($replyId > 0) {
                $replyMap[$replyId] = $replyRow;
            }
        }
    }

    foreach ($rows as &$row) {
        $replyId = (int)($row['reply_to_message_id'] ?? 0);
        $row['reply_context'] = null;
        if ($replyId <= 0) {
            continue;
        }

        $replyRow = $replyMap[$replyId] ?? null;
        if (!$replyRow) {
            $row['reply_context'] = [
                'message_id' => $replyId,
                'sender_username' => '',
                'preview' => trux_direct_message_deleted_copy(),
                'is_deleted' => true,
                'is_missing' => true,
            ];
            continue;
        }

        $row['reply_context'] = [
            'message_id' => $replyId,
            'sender_username' => (string)($replyRow['sender_username'] ?? ''),
            'preview' => trux_direct_message_preview_from_message($replyRow, 60),
            'is_deleted' => trux_direct_message_is_unsent($replyRow),
            'is_missing' => false,
        ];
    }
    unset($row);

    return $rows;
}

function trux_direct_message_normalize_reaction(string $reaction): string {
    $normalized = trim(mb_strtolower($reaction));
    return $normalized === 'like' ? 'like' : '';
}

function trux_toggle_direct_message_reaction(int $messageId, int $viewerId, string $reaction): array {
    if ($messageId <= 0 || $viewerId <= 0) {
        return ['ok' => false, 'error' => 'Invalid message.'];
    }

    $normalizedReaction = trux_direct_message_normalize_reaction($reaction);
    if ($normalizedReaction === '') {
        return ['ok' => false, 'error' => 'Unsupported reaction.'];
    }

    $message = trux_fetch_direct_message_for_user($messageId, $viewerId);
    if (!$message) {
        return ['ok' => false, 'error' => 'Message not found.'];
    }
    if (!empty($message['is_unsent'])) {
        return ['ok' => false, 'error' => 'Deleted messages cannot receive reactions.'];
    }

    $db = trux_db();

    try {
        $db->beginTransaction();

        $existsStmt = $db->prepare(
            'SELECT 1
             FROM direct_message_reactions
             WHERE message_id = ?
               AND user_id = ?
               AND reaction = ?
             LIMIT 1'
        );
        $existsStmt->execute([$messageId, $viewerId, $normalizedReaction]);
        $viewerAlreadyReacted = (bool)$existsStmt->fetchColumn();

        if ($viewerAlreadyReacted) {
            $deleteStmt = $db->prepare(
                'DELETE FROM direct_message_reactions
                 WHERE message_id = ?
                   AND user_id = ?
                   AND reaction = ?'
            );
            $deleteStmt->execute([$messageId, $viewerId, $normalizedReaction]);
        } else {
            $insertStmt = $db->prepare(
                'INSERT INTO direct_message_reactions (message_id, user_id, reaction)
                 VALUES (?, ?, ?)'
            );
            $insertStmt->execute([$messageId, $viewerId, $normalizedReaction]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['ok' => false, 'error' => 'Could not update message reaction.'];
    }

    $updated = trux_fetch_direct_message_for_user($messageId, $viewerId);
    if (!$updated) {
        return ['ok' => false, 'error' => 'Could not load the updated message reaction.'];
    }

    $reactions = is_array($updated['reactions'] ?? null) ? $updated['reactions'] : [];

    return [
        'ok' => true,
        'message' => $updated,
        'message_id' => $messageId,
        'reaction' => $normalizedReaction,
        'viewer_liked' => !empty($reactions['viewer_liked']),
        'like_count' => (int)($reactions['like_count'] ?? 0),
        'action' => $viewerAlreadyReacted ? 'removed' : 'added',
    ];
}

function trux_direct_message_fetch_reaction_summary_map(array $messageIds, int $viewerId): array {
    $messageIds = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn (int $id): bool => $id > 0)));
    if ($messageIds === []) {
        return [];
    }

    $summary = [];
    foreach ($messageIds as $messageId) {
        $summary[$messageId] = [
            'like_count' => 0,
            'viewer_liked' => false,
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($messageIds), '?'));
    $db = trux_db();

    try {
        $countStmt = $db->prepare(
            'SELECT message_id, COUNT(*) AS like_count
             FROM direct_message_reactions
             WHERE reaction = ?
               AND message_id IN (' . $placeholders . ')
             GROUP BY message_id'
        );
        $countStmt->execute(array_merge(['like'], $messageIds));
        foreach ($countStmt->fetchAll() as $row) {
            $messageId = (int)($row['message_id'] ?? 0);
            if (!isset($summary[$messageId])) {
                continue;
            }
            $summary[$messageId]['like_count'] = (int)($row['like_count'] ?? 0);
        }

        if ($viewerId > 0) {
            $viewerStmt = $db->prepare(
                'SELECT message_id
                 FROM direct_message_reactions
                 WHERE reaction = ?
                   AND user_id = ?
                   AND message_id IN (' . $placeholders . ')'
            );
            $viewerStmt->execute(array_merge(['like', $viewerId], $messageIds));
            foreach ($viewerStmt->fetchAll() as $row) {
                $messageId = (int)($row['message_id'] ?? 0);
                if (isset($summary[$messageId])) {
                    $summary[$messageId]['viewer_liked'] = true;
                }
            }
        }
    } catch (PDOException) {
        return $summary;
    }

    return $summary;
}

function trux_direct_message_attach_rows_with_reactions(array $rows, int $viewerId): array {
    if ($rows === []) {
        return [];
    }

    $summaryMap = trux_direct_message_fetch_reaction_summary_map(array_column($rows, 'id'), $viewerId);
    foreach ($rows as &$row) {
        $messageId = (int)($row['id'] ?? 0);
        $row['reactions'] = $summaryMap[$messageId] ?? [
            'like_count' => 0,
            'viewer_liked' => false,
        ];
    }
    unset($row);

    return $rows;
}

function trux_direct_message_enrich_rows(array $rows, int $viewerId): array {
    if ($rows === []) {
        return [];
    }

    $rows = trux_direct_message_attach_rows_with_attachments($rows);
    $rows = trux_direct_message_attach_rows_with_reply_context($rows);
    $rows = trux_direct_message_attach_rows_with_reactions($rows, $viewerId);

    return $rows;
}

function trux_serialize_direct_message_attachment(array $attachment): array {
    $attachmentId = (int)($attachment['id'] ?? 0);
    $mimeType = trim((string)($attachment['mime_type'] ?? ''));
    $isImage = trux_direct_message_is_image_mime($mimeType);

    return [
        'id' => $attachmentId,
        'message_id' => (int)($attachment['message_id'] ?? 0),
        'original_name' => (string)($attachment['original_name'] ?? ''),
        'mime_type' => $mimeType,
        'file_size' => (int)($attachment['file_size'] ?? 0),
        'is_image' => $isImage,
        'image_width' => isset($attachment['image_width']) ? (int)$attachment['image_width'] : null,
        'image_height' => isset($attachment['image_height']) ? (int)$attachment['image_height'] : null,
        'view_url' => $attachmentId > 0 ? trux_direct_message_attachment_view_url($attachmentId, false) : '',
        'download_url' => $attachmentId > 0 ? trux_direct_message_attachment_view_url($attachmentId, true) : '',
    ];
}

function trux_serialize_direct_message(array $message, int $viewerId): array {
    $createdAt = (string)($message['created_at'] ?? '');
    $isMine = (int)($message['sender_user_id'] ?? 0) === $viewerId;
    $isUnsent = trux_direct_message_is_unsent($message);
    $body = $isUnsent ? '' : trim((string)($message['body'] ?? ''));
    $rawAttachments = $isUnsent ? [] : (is_array($message['attachments'] ?? null) ? $message['attachments'] : []);
    $attachments = array_map('trux_serialize_direct_message_attachment', $rawAttachments);
    $replyContext = is_array($message['reply_context'] ?? null) ? $message['reply_context'] : null;
    $reactions = is_array($message['reactions'] ?? null) ? $message['reactions'] : [];

    return [
        'id' => (int)($message['id'] ?? 0),
        'conversation_id' => (int)($message['conversation_id'] ?? 0),
        'sender_user_id' => (int)($message['sender_user_id'] ?? 0),
        'sender_username' => (string)($message['sender_username'] ?? ''),
        'sender_display_name' => (string)($message['sender_display_name'] ?? ''),
        'is_mine' => $isMine,
        'body' => $body,
        'body_html' => $body !== '' ? trux_render_comment_body($body) : '',
        'reply_to_message_id' => (int)($message['reply_to_message_id'] ?? 0),
        'reply_context' => $replyContext ? [
            'message_id' => (int)($replyContext['message_id'] ?? 0),
            'sender_username' => (string)($replyContext['sender_username'] ?? ''),
            'preview' => (string)($replyContext['preview'] ?? ''),
            'is_deleted' => !empty($replyContext['is_deleted']),
            'is_missing' => !empty($replyContext['is_missing']),
        ] : null,
        'created_at' => $createdAt,
        'exact_time' => $createdAt !== '' ? trux_format_exact_time($createdAt) : '',
        'time_ago' => $createdAt !== '' ? trux_time_ago($createdAt) : '',
        'day_key' => $createdAt !== '' ? trux_direct_message_day_key($createdAt) : '',
        'day_label' => $createdAt !== '' ? trux_direct_message_day_label($createdAt) : '',
        'edited_at' => (string)($message['edited_at'] ?? ''),
        'is_edited' => !$isUnsent && trim((string)($message['edited_at'] ?? '')) !== '',
        'can_edit' => $isMine && !$isUnsent && trux_direct_message_is_within_window((string)($message['edit_window_expires_at'] ?? '')),
        'edit_window_expires_at' => (string)($message['edit_window_expires_at'] ?? ''),
        'deleted_for_everyone_at' => (string)($message['deleted_for_everyone_at'] ?? ''),
        'is_unsent' => $isUnsent,
        'can_unsend' => $isMine && !$isUnsent && trux_direct_message_is_within_window((string)($message['delete_window_expires_at'] ?? '')),
        'delete_window_expires_at' => (string)($message['delete_window_expires_at'] ?? ''),
        'attachments' => $attachments,
        'attachment_count' => count($attachments),
        'read_at' => (string)($message['read_at'] ?? ''),
        'is_read' => trim((string)($message['read_at'] ?? '')) !== '',
        'reactions' => [
            'like_count' => (int)($reactions['like_count'] ?? 0),
            'viewer_liked' => !empty($reactions['viewer_liked']),
        ],
    ];
}

function trux_fetch_direct_message_for_user(int $messageId, int $viewerId): ?array {
    $row = trux_direct_message_fetch_raw_row_for_user($messageId, $viewerId);
    if (!$row) {
        return null;
    }

    $rows = trux_direct_message_enrich_rows([$row], $viewerId);

    return trux_serialize_direct_message($rows[0], $viewerId);
}

function trux_fetch_direct_messages(int $conversationId, int $viewerId, int $limit = 100): array {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return [];
    }

    if (!trux_fetch_direct_conversation_for_user($conversationId, $viewerId)) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.reply_to_message_id, m.created_at, m.read_at,
                m.edited_at, m.edit_window_expires_at, m.deleted_for_everyone_at, m.delete_window_expires_at,
                u.username AS sender_username, u.display_name AS sender_display_name
         FROM direct_messages m
         JOIN users u ON u.id = m.sender_user_id
         WHERE m.conversation_id = ?
         ORDER BY m.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = array_reverse($stmt->fetchAll());
    $rows = trux_direct_message_enrich_rows($rows, $viewerId);

    return array_map(
        static fn (array $row): array => trux_serialize_direct_message($row, $viewerId),
        $rows
    );
}

function trux_fetch_direct_message_with_attachment(int $messageId, int $viewerId): ?array {
    return trux_fetch_direct_message_for_user($messageId, $viewerId);
}

function trux_fetch_direct_messages_after(int $conversationId, int $viewerId, int $afterMessageId, int $limit = 50): array {
    if ($conversationId <= 0 || $viewerId <= 0 || $afterMessageId < 0) {
        return [];
    }

    if (!trux_fetch_direct_conversation_for_user($conversationId, $viewerId)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.reply_to_message_id, m.created_at, m.read_at,
                m.edited_at, m.edit_window_expires_at, m.deleted_for_everyone_at, m.delete_window_expires_at,
                u.username AS sender_username, u.display_name AS sender_display_name
         FROM direct_messages m
         JOIN users u ON u.id = m.sender_user_id
         WHERE m.conversation_id = ?
           AND m.id > ?
         ORDER BY m.id ASC
         LIMIT ?'
    );
    $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(2, $afterMessageId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = trux_direct_message_enrich_rows($stmt->fetchAll(), $viewerId);

    return array_map(
        static fn (array $row): array => trux_serialize_direct_message($row, $viewerId),
        $rows
    );
}

function trux_fetch_direct_messages_before(int $conversationId, int $viewerId, int $beforeMessageId, int $limit = 30): array {
    if ($conversationId <= 0 || $viewerId <= 0 || $beforeMessageId <= 0) {
        return ['messages' => [], 'has_more' => false];
    }

    if (!trux_fetch_direct_conversation_for_user($conversationId, $viewerId)) {
        return ['messages' => [], 'has_more' => false];
    }

    $limit = max(1, min(100, $limit));
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT m.id, m.conversation_id, m.sender_user_id, m.body, m.reply_to_message_id, m.created_at, m.read_at,
                m.edited_at, m.edit_window_expires_at, m.deleted_for_everyone_at, m.delete_window_expires_at,
                u.username AS sender_username, u.display_name AS sender_display_name
         FROM direct_messages m
         JOIN users u ON u.id = m.sender_user_id
         WHERE m.conversation_id = ?
           AND m.id < ?
         ORDER BY m.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(2, $beforeMessageId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit + 1, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        array_pop($rows);
    }

    $rows = array_reverse($rows);
    $rows = trux_direct_message_enrich_rows($rows, $viewerId);

    return [
        'messages' => array_map(
            static fn (array $row): array => trux_serialize_direct_message($row, $viewerId),
            $rows
        ),
        'has_more' => $hasMore,
    ];
}

function trux_direct_conversation_has_older_messages(int $conversationId, int $viewerId, int $oldestMessageId): bool {
    if ($conversationId <= 0 || $viewerId <= 0 || $oldestMessageId <= 0) {
        return false;
    }

    if (!trux_fetch_direct_conversation_for_user($conversationId, $viewerId)) {
        return false;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT 1
         FROM direct_messages
         WHERE conversation_id = ?
           AND id < ?
         LIMIT 1'
    );
    $stmt->execute([$conversationId, $oldestMessageId]);

    return (bool)$stmt->fetchColumn();
}

function trux_mark_direct_conversation_read(int $conversationId, int $viewerId): void {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return;
    }

    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'UPDATE direct_messages
             SET read_at = CURRENT_TIMESTAMP
             WHERE conversation_id = ?
               AND sender_user_id <> ?
               AND read_at IS NULL'
        );
        $stmt->execute([$conversationId, $viewerId]);
    } catch (PDOException) {
        // Ignore missing migrations and keep the rest of the app functional.
    }
}

function trux_fetch_sent_message_read_statuses(int $conversationId, int $viewerId, int $limit = 100): array {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return [];
    }

    $db = trux_db();
    try {
        $stmt = $db->prepare(
            'SELECT id, read_at IS NOT NULL AS is_read
             FROM direct_messages
             WHERE conversation_id = ?
               AND sender_user_id = ?
               AND deleted_for_everyone_at IS NULL
             ORDER BY id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute([$conversationId, $viewerId]);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['id']] = (bool)$row['is_read'];
        }
        return $result;
    } catch (PDOException) {
        return [];
    }
}

function trux_count_unread_direct_messages(int $viewerId): int {
    if ($viewerId <= 0) {
        return 0;
    }

    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM direct_messages m
             JOIN direct_conversations c ON c.id = m.conversation_id
             WHERE m.sender_user_id <> ?
               AND m.read_at IS NULL
               AND m.deleted_for_everyone_at IS NULL
               AND (c.user_one_id = ? OR c.user_two_id = ?)'
        );
        $stmt->execute([$viewerId, $viewerId, $viewerId]);

        return (int)$stmt->fetchColumn();
    } catch (PDOException) {
        return 0;
    }
}

function trux_direct_message_fetch_unread_count_map(array $conversationIds, int $viewerId): array {
    $conversationIds = array_values(array_unique(array_filter(array_map('intval', $conversationIds), static fn (int $id): bool => $id > 0)));
    if ($conversationIds === [] || $viewerId <= 0) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($conversationIds), '?'));
    $params = array_merge([$viewerId], $conversationIds);
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT conversation_id, COUNT(*) AS unread_count
         FROM direct_messages
         WHERE sender_user_id <> ?
           AND read_at IS NULL
           AND deleted_for_everyone_at IS NULL
           AND conversation_id IN (' . $placeholders . ')
         GROUP BY conversation_id'
    );
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int)$row['conversation_id']] = (int)$row['unread_count'];
    }

    return $map;
}

function trux_direct_message_fetch_latest_row_map(array $conversationIds): array {
    $conversationIds = array_values(array_unique(array_filter(array_map('intval', $conversationIds), static fn (int $id): bool => $id > 0)));
    if ($conversationIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($conversationIds), '?'));
    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT conversation_id, MAX(id) AS message_id
         FROM direct_messages
         WHERE conversation_id IN (' . $placeholders . ')
         GROUP BY conversation_id'
    );
    $stmt->execute($conversationIds);

    $conversationToMessage = [];
    foreach ($stmt->fetchAll() as $row) {
        $conversationId = (int)($row['conversation_id'] ?? 0);
        $messageId = (int)($row['message_id'] ?? 0);
        if ($conversationId > 0 && $messageId > 0) {
            $conversationToMessage[$conversationId] = $messageId;
        }
    }

    if ($conversationToMessage === []) {
        return [];
    }

    $messageRows = trux_direct_message_fetch_raw_rows_by_ids(array_values($conversationToMessage));
    $messageRows = trux_direct_message_attach_rows_with_attachments($messageRows);

    $rowById = [];
    foreach ($messageRows as $row) {
        $rowById[(int)($row['id'] ?? 0)] = $row;
    }

    $map = [];
    foreach ($conversationToMessage as $conversationId => $messageId) {
        if (isset($rowById[$messageId])) {
            $map[$conversationId] = $rowById[$messageId];
        }
    }

    return $map;
}

function trux_direct_message_enrich_conversation_rows(array $rows, int $viewerId): array {
    if ($rows === []) {
        return [];
    }

    $conversationIds = array_column($rows, 'id');
    $latestRows = trux_direct_message_fetch_latest_row_map($conversationIds);
    $unreadMap = trux_direct_message_fetch_unread_count_map($conversationIds, $viewerId);

    foreach ($rows as &$row) {
        $conversationId = (int)($row['id'] ?? 0);
        $latestRow = $latestRows[$conversationId] ?? null;
        if (is_array($latestRow)) {
            $row['last_message_id'] = (int)($latestRow['id'] ?? 0);
            $row['last_message_body'] = (string)($latestRow['body'] ?? '');
            $row['last_message_created_at'] = (string)($latestRow['created_at'] ?? '');
            $row['last_message_preview'] = trux_direct_message_preview_from_message($latestRow);
        } else {
            $row['last_message_id'] = 0;
            $row['last_message_body'] = '';
            $row['last_message_created_at'] = '';
            $row['last_message_preview'] = 'No messages yet.';
        }

        $row['unread_count'] = (int)($unreadMap[$conversationId] ?? 0);
    }
    unset($row);

    return $rows;
}

function trux_fetch_direct_conversations(int $viewerId, int $limit = 50): array {
    if ($viewerId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $db = trux_db();

    try {
        $stmt = $db->prepare(
            'SELECT c.id, c.user_one_id, c.user_two_id, c.created_at, c.updated_at,
                    u.id AS other_user_id, u.username AS other_username, u.display_name AS other_display_name,
                    u.avatar_path AS other_avatar_path
             FROM direct_conversations c
             JOIN users u ON u.id = CASE
                 WHEN c.user_one_id = :viewer_other THEN c.user_two_id
                 ELSE c.user_one_id
             END
             WHERE c.user_one_id = :viewer_one OR c.user_two_id = :viewer_two
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':viewer_other', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_one', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_two', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return trux_direct_message_enrich_conversation_rows($stmt->fetchAll(), $viewerId);
    } catch (PDOException) {
        return [];
    }
}

function trux_fetch_direct_conversation_summary(int $conversationId, int $viewerId): ?array {
    if ($conversationId <= 0 || $viewerId <= 0) {
        return null;
    }

    $conversation = trux_fetch_direct_conversation_for_user($conversationId, $viewerId);
    if (!$conversation) {
        return null;
    }

    $rows = trux_direct_message_enrich_conversation_rows([$conversation], $viewerId);
    return $rows[0] ?? null;
}

function trux_edit_direct_message(int $messageId, int $viewerId, string $body): array {
    $body = trim($body);
    if ($messageId <= 0 || $viewerId <= 0) {
        return ['ok' => false, 'error' => 'Invalid message.'];
    }
    if ($body === '' || mb_strlen($body) > trux_direct_message_body_limit()) {
        return ['ok' => false, 'error' => 'Message must be 1-2000 characters.'];
    }

    $message = trux_fetch_direct_message_for_user($messageId, $viewerId);
    if (!$message) {
        return ['ok' => false, 'error' => 'Message not found.'];
    }
    if ((int)($message['sender_user_id'] ?? 0) !== $viewerId) {
        return ['ok' => false, 'error' => 'You can only edit your own messages.'];
    }
    if (!empty($message['is_unsent'])) {
        return ['ok' => false, 'error' => 'Removed messages cannot be edited.'];
    }
    if (empty($message['can_edit'])) {
        return ['ok' => false, 'error' => 'The edit window has expired.'];
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'UPDATE direct_messages
         SET body = ?, edited_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND sender_user_id = ?
           AND deleted_for_everyone_at IS NULL
           AND edit_window_expires_at IS NOT NULL
           AND edit_window_expires_at >= CURRENT_TIMESTAMP'
    );
    $stmt->execute([$body, $messageId, $viewerId]);

    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'error' => 'The edit window has expired.'];
    }

    $updated = trux_fetch_direct_message_for_user($messageId, $viewerId);
    if (!$updated) {
        return ['ok' => false, 'error' => 'Could not load the edited message.'];
    }

    return ['ok' => true, 'message' => $updated];
}

function trux_unsend_direct_message(int $messageId, int $viewerId): array {
    if ($messageId <= 0 || $viewerId <= 0) {
        return ['ok' => false, 'error' => 'Invalid message.'];
    }

    $message = trux_fetch_direct_message_for_user($messageId, $viewerId);
    if (!$message) {
        return ['ok' => false, 'error' => 'Message not found.'];
    }
    if ((int)($message['sender_user_id'] ?? 0) !== $viewerId) {
        return ['ok' => false, 'error' => 'You can only delete your own messages.'];
    }
    if (!empty($message['is_unsent'])) {
        return ['ok' => false, 'error' => 'This message has already been deleted.'];
    }
    if (empty($message['can_unsend'])) {
        return ['ok' => false, 'error' => 'The delete window has expired.'];
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'UPDATE direct_messages
         SET body = NULL, deleted_for_everyone_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND sender_user_id = ?
           AND deleted_for_everyone_at IS NULL
           AND delete_window_expires_at IS NOT NULL
           AND delete_window_expires_at >= CURRENT_TIMESTAMP'
    );
    $stmt->execute([$messageId, $viewerId]);

    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'error' => 'The delete window has expired.'];
    }

    $updated = trux_fetch_direct_message_for_user($messageId, $viewerId);
    if (!$updated) {
        return ['ok' => false, 'error' => 'Could not load the deleted message.'];
    }

    return ['ok' => true, 'message' => $updated];
}

function trux_fetch_direct_message_attachment_for_user(int $attachmentId, int $viewerId): ?array {
    if ($attachmentId <= 0 || $viewerId <= 0) {
        return null;
    }

    $db = trux_db();
    $stmt = $db->prepare(
        'SELECT a.id, a.message_id, a.file_path, a.original_name, a.mime_type, a.file_size,
                a.image_width, a.image_height, a.created_at,
                m.conversation_id, m.deleted_for_everyone_at
         FROM direct_message_attachments a
         JOIN direct_messages m ON m.id = a.message_id
         JOIN direct_conversations c ON c.id = m.conversation_id
         WHERE a.id = ?
           AND (c.user_one_id = ? OR c.user_two_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$attachmentId, $viewerId, $viewerId]);
    $attachment = $stmt->fetch();

    if (!$attachment) {
        return null;
    }
    if (trux_direct_message_is_unsent($attachment)) {
        return null;
    }
    if (!trux_direct_message_attachment_is_safe_relative_path((string)($attachment['file_path'] ?? ''))) {
        return null;
    }

    return $attachment;
}

function trux_render_direct_message_avatar(
    string $username,
    string $avatarUrl = '',
    string $className = 'dmAvatar',
    string $fallbackLabel = ''
): string {
    $seed = $username !== '' ? $username : $fallbackLabel;
    $initialSeed = $username !== '' ? $username : ($fallbackLabel !== '' ? $fallbackLabel : 'T');
    $initial = strtoupper(mb_substr($initialSeed, 0, 1));
    $theme = trux_direct_message_avatar_theme($seed);

    ob_start();
    ?>
    <span class="<?= trux_e($className) ?> dmAvatar dmAvatar--<?= trux_e($theme) ?><?= $avatarUrl !== '' ? ' ' . trux_e($className) . '--image dmAvatar--image' : '' ?>" aria-hidden="true">
        <?php if ($avatarUrl !== ''): ?>
            <img class="<?= trux_e($className) ?>__image dmAvatar__image" src="<?= trux_e($avatarUrl) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <span class="<?= trux_e($className) ?>__fallback dmAvatar__fallback"><?= trux_e($initial) ?></span>
        <?php endif; ?>
    </span>
    <?php

    return trim((string)ob_get_clean());
}

function trux_direct_message_avatar_theme(?string $seed): string {
    $seed = trim((string)$seed);
    if ($seed === '') {
        return 'accent';
    }

    $themes = ['accent', 'mint', 'warning', 'danger'];
    $hash = crc32(strtolower($seed));
    $index = (int)($hash % count($themes));

    return $themes[$index] ?? 'accent';
}

function trux_direct_message_actor_label(?string $username, ?string $displayName = null): string {
    $name = trim((string)$username);
    $display = trim((string)$displayName);

    if ($name === '') {
        return $display !== '' ? $display : 'Unknown';
    }

    if (trux_is_report_system_user($name)) {
        return $display !== '' ? $display : trux_report_system_display_name();
    }

    return '@' . $name;
}

function trux_direct_message_preview(?string $body, int $limit = 90): string {
    $body = trim((string)$body);
    if ($body === '') {
        return 'No messages yet.';
    }

    if (mb_strlen($body) <= $limit) {
        return $body;
    }

    return mb_substr($body, 0, max(1, $limit - 3)) . '...';
}

function trux_render_direct_message_attachments(array $message): string {
    $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
    if ($attachments === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="messageBubble__attachments">
        <?php foreach ($attachments as $attachment): ?>
            <?php
            $attachmentId = (int)($attachment['id'] ?? 0);
            if ($attachmentId <= 0) {
                continue;
            }
            $isImage = !empty($attachment['is_image']);
            $viewUrl = (string)($attachment['view_url'] ?? '');
            $downloadUrl = (string)($attachment['download_url'] ?? $viewUrl);
            $originalName = trim((string)($attachment['original_name'] ?? 'Attachment'));
            $mimeType = trim((string)($attachment['mime_type'] ?? 'application/octet-stream'));
            $fileSize = (int)($attachment['file_size'] ?? 0);
            ?>
            <?php if ($isImage): ?>
                <a class="messageBubble__attachment messageBubble__attachment--image" href="<?= trux_e($viewUrl) ?>" target="_blank" rel="noopener">
                    <img src="<?= trux_e($viewUrl) ?>" alt="<?= trux_e($originalName) ?>" loading="lazy" decoding="async">
                </a>
            <?php else: ?>
                <div class="messageBubble__attachment messageBubble__attachment--file">
                    <div class="messageBubble__fileMeta">
                        <strong><?= trux_e($originalName !== '' ? $originalName : 'PDF attachment') ?></strong>
                        <span class="muted"><?= trux_e(strtoupper($mimeType === 'application/pdf' ? 'pdf' : $mimeType)) ?><?php if ($fileSize > 0): ?> &middot; <?= trux_e((string)round($fileSize / 1024, 1)) ?> KB<?php endif; ?></span>
                    </div>
                    <div class="messageBubble__fileActions">
                        <a class="shellButton shellButton--ghost" href="<?= trux_e($viewUrl) ?>" target="_blank" rel="noopener">Open</a>
                        <a class="shellButton shellButton--ghost" href="<?= trux_e($downloadUrl) ?>">Download</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php

    return trim((string)ob_get_clean());
}

function trux_render_direct_message_bubble(array $message, int $viewerId, int $conversationId): string {
    $templatePath = dirname(__DIR__) . '/public/_dm_message_bubble.php';
    if (!is_file($templatePath)) {
        return '';
    }

    ob_start();
    require $templatePath;

    return trim((string)ob_get_clean());
}

function trux_render_direct_conversation_item(array $conversation, int $activeConversationId = 0): string {
    $templatePath = dirname(__DIR__) . '/public/_dm_conversation_item.php';
    if (!is_file($templatePath)) {
        return '';
    }

    ob_start();
    require $templatePath;

    return trim((string)ob_get_clean());
}

function trux_search_direct_message_recipients(int $viewerId, string $term, int $limit = 8): array {
    if ($viewerId <= 0) {
        return [];
    }

    $prefix = trux_normalize_mention_fragment($term);
    if ($prefix === '') {
        return [];
    }

    $limit = max(1, min(12, $limit));
    $db = trux_db();
    $like = trux_like_escape($prefix) . '%';
    $hiddenUsername = trux_report_system_username();

    try {
        $stmt = $db->prepare(
            'SELECT u.id, u.username, u.display_name, u.avatar_path, c.id AS conversation_id
             FROM users u
             LEFT JOIN direct_conversations c
               ON (
                    (c.user_one_id = :viewer_left AND c.user_two_id = u.id)
                    OR (c.user_one_id = u.id AND c.user_two_id = :viewer_right)
               )
             WHERE u.id <> :viewer_self
               AND u.username <> :hidden_username
               AND u.username LIKE :username_like ESCAPE \'\\\\\'
               AND NOT EXISTS (
                    SELECT 1
                    FROM blocked_users b
                    WHERE (b.user_id = :viewer_blocked AND b.blocked_user_id = u.id)
                       OR (b.user_id = u.id AND b.blocked_user_id = :viewer_blocker)
               )
             ORDER BY u.username ASC
             LIMIT :limit_rows'
        );
        $stmt->bindValue(':viewer_left', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_right', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_self', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':hidden_username', $hiddenUsername, PDO::PARAM_STR);
        $stmt->bindValue(':username_like', $like, PDO::PARAM_STR);
        $stmt->bindValue(':viewer_blocked', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_blocker', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException) {
        return [];
    }
}
