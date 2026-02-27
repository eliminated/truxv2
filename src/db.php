<?php
declare(strict_types=1);

function trux_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        TRUX_DB_HOST,
        TRUX_DB_PORT,
        TRUX_DB_NAME,
        TRUX_DB_CHARSET
    );

    $pdo = new PDO($dsn, TRUX_DB_USER, TRUX_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}