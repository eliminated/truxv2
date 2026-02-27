<?php
declare(strict_types=1);

function trux_e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function trux_redirect(string $path): void {
    $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
        ? $path
        : TRUX_BASE_URL . $path;
    header('Location: ' . $url);
    exit;
}

function trux_flash_set(string $key, string $message): void {
    $_SESSION['_flash'][$key] = $message;
}

function trux_flash_get(string $key): ?string {
    if (!isset($_SESSION['_flash'][$key])) return null;
    $msg = (string)$_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function trux_int_param(string $name, int $default = 0): int {
    $v = $_GET[$name] ?? null;
    if (!is_string($v) || $v === '') return $default;
    if (!preg_match('/^-?\d+$/', $v)) return $default;
    return (int)$v;
}

function trux_str_param(string $name, string $default = ''): string {
    $v = $_GET[$name] ?? null;
    if (!is_string($v)) return $default;
    return $v;
}

function trux_parse_datetime(string $dbTs): ?DateTimeImmutable {
    try {
        // MySQL TIMESTAMP typically returns "YYYY-MM-DD HH:MM:SS"
        return new DateTimeImmutable($dbTs);
    } catch (Throwable) {
        return null;
    }
}

function trux_format_exact_time(string $dbTs): string {
    $dt = trux_parse_datetime($dbTs);
    if (!$dt) return $dbTs;
    return $dt->format('Y-m-d H:i:s');
}

function trux_time_ago(string $dbTs): string {
    $dt = trux_parse_datetime($dbTs);
    if (!$dt) return $dbTs;

    $now = new DateTimeImmutable('now');
    $diff = $now->getTimestamp() - $dt->getTimestamp();

    if ($diff < 0) $diff = 0;

    if ($diff < 10) return 'just now';
    if ($diff < 60) return $diff . ' seconds ago';

    $mins = intdiv($diff, 60);
    if ($mins < 60) return $mins . ' minute' . ($mins === 1 ? '' : 's') . ' ago';

    $hours = intdiv($diff, 3600);
    if ($hours < 24) return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';

    $days = intdiv($diff, 86400);
    if ($days < 7) return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';

    // Fallback: show date for older items
    return $dt->format('Y-m-d');
}

function trux_like_escape(string $s): string {
    // Escape LIKE wildcards for safe searching
    // We'll use ESCAPE '\' in SQL.
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
}