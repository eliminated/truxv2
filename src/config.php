<?php
declare(strict_types=1);

function trux_env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}

function trux_load_dotenv_if_present(string $path): void {
    if (!is_file($path) || !is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $k = trim(substr($line, 0, $pos));
        $v = trim(substr($line, $pos + 1));
        if ($k === '') continue;

        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }

        if (getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

$root = dirname(__DIR__);
trux_load_dotenv_if_present($root . DIRECTORY_SEPARATOR . '.env');

define('TRUX_APP_NAME', trux_env('TRUX_APP_NAME', 'TruX'));
define('TRUX_BASE_URL', rtrim((string)trux_env('TRUX_BASE_URL', 'http://localhost:8000'), '/'));
define('TRUX_TIMEZONE', (string)trux_env('TRUX_TIMEZONE', 'UTC'));

define('TRUX_DB_HOST', (string)trux_env('TRUX_DB_HOST', '127.0.0.1'));
define('TRUX_DB_PORT', (string)trux_env('TRUX_DB_PORT', '3306'));
define('TRUX_DB_NAME', (string)trux_env('TRUX_DB_NAME', 'trux'));
define('TRUX_DB_USER', (string)trux_env('TRUX_DB_USER', 'root'));
define('TRUX_DB_PASS', (string)trux_env('TRUX_DB_PASS', ''));
define('TRUX_DB_CHARSET', (string)trux_env('TRUX_DB_CHARSET', 'utf8mb4'));

define('TRUX_MAX_UPLOAD_BYTES', 4 * 1024 * 1024); // 4MB input limit
define('TRUX_MAX_IMAGE_WIDTH', 4096);
define('TRUX_MAX_IMAGE_HEIGHT', 4096);
define('TRUX_MAX_IMAGE_PIXELS', 4096 * 4096);

define('TRUX_ALLOWED_IMAGE_MIME', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
]);