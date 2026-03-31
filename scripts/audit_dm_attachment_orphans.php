<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

ini_set('session.save_path', dirname(__DIR__) . '/storage');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/scripts/audit_dm_attachment_orphans.php';

require_once dirname(__DIR__) . '/public/_bootstrap.php';

$storageDir = trux_direct_message_attachment_storage_dir();
$db = trux_db();
$stmt = $db->query(
    'SELECT file_path, COUNT(*) AS reference_count
     FROM direct_message_attachments
     GROUP BY file_path
     ORDER BY file_path ASC'
);
$rows = $stmt ? $stmt->fetchAll() : [];

$dbPaths = [];
$unsafeDbPaths = [];
foreach ($rows as $row) {
    $filePath = trim((string)($row['file_path'] ?? ''));
    if ($filePath === '') {
        continue;
    }

    if (!trux_direct_message_attachment_is_safe_relative_path($filePath)) {
        $unsafeDbPaths[] = $filePath;
        continue;
    }

    $dbPaths[$filePath] = (int)($row['reference_count'] ?? 0);
}

$diskPaths = [];
if (is_dir($storageDir)) {
    $iterator = new FilesystemIterator($storageDir, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $basename = $fileInfo->getBasename();
        if ($basename === '.gitignore') {
            continue;
        }

        $relativePath = trux_direct_message_attachment_relative_path($basename);
        if (!trux_direct_message_attachment_is_safe_relative_path($relativePath)) {
            continue;
        }

        $diskPaths[$relativePath] = [
            'size' => (int)$fileInfo->getSize(),
            'modified_at' => date('Y-m-d H:i:s', (int)$fileInfo->getMTime()),
        ];
    }
}

$orphanedFiles = [];
foreach ($diskPaths as $relativePath => $details) {
    if (!array_key_exists($relativePath, $dbPaths)) {
        $orphanedFiles[$relativePath] = $details;
    }
}

$missingFiles = [];
foreach ($dbPaths as $relativePath => $referenceCount) {
    if (!array_key_exists($relativePath, $diskPaths)) {
        $missingFiles[$relativePath] = $referenceCount;
    }
}

echo "DM attachment audit\n";
echo "Storage directory: {$storageDir}\n";
echo "Referenced DB paths: " . count($dbPaths) . "\n";
echo "Files on disk: " . count($diskPaths) . "\n";
echo "Orphaned files on disk: " . count($orphanedFiles) . "\n";
echo "DB references missing files: " . count($missingFiles) . "\n";
echo "Unsafe DB paths: " . count($unsafeDbPaths) . "\n";
echo "No files were deleted.\n";

if ($orphanedFiles !== []) {
    echo "\nOrphaned files\n";
    foreach ($orphanedFiles as $relativePath => $details) {
        echo "- {$relativePath} | {$details['size']} bytes | modified {$details['modified_at']}\n";
    }
}

if ($missingFiles !== []) {
    echo "\nMissing files referenced by DB\n";
    foreach ($missingFiles as $relativePath => $referenceCount) {
        echo "- {$relativePath} | references {$referenceCount}\n";
    }
}

if ($unsafeDbPaths !== []) {
    echo "\nUnsafe DB paths\n";
    foreach ($unsafeDbPaths as $filePath) {
        echo "- {$filePath}\n";
    }
}
