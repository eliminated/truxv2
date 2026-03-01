<?php
declare(strict_types=1);

const TRUX_UI_COOKIE_REDUCE_MOTION = 'trux_ui_reduce_motion';
const TRUX_UI_COOKIE_CLASSIC_APPEARANCE = 'trux_ui_classic_appearance';

/**
 * @return array{reduce_motion: bool, classic_appearance: bool}
 */
function trux_get_ui_preferences(): array {
    $defaults = trux_ui_default_preferences();
    $userId = trux_ui_current_user_id();

    // Logged-in users use account-level settings from DB when columns exist.
    if ($userId > 0 && trux_ui_db_columns_available()) {
        try {
            $db = trux_db();
            $stmt = $db->prepare('SELECT ui_reduce_motion, ui_classic_appearance FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if (is_array($row)) {
                $prefs = [
                    'reduce_motion' => (int)($row['ui_reduce_motion'] ?? 0) === 1,
                    'classic_appearance' => (int)($row['ui_classic_appearance'] ?? 0) === 1,
                ];
                $_SESSION['ui_prefs'] = $prefs;
                return $prefs;
            }
        } catch (PDOException) {
            // Fall through to session/cookie fallback.
        }
    }

    $sessionPrefs = $_SESSION['ui_prefs'] ?? null;
    if (is_array($sessionPrefs)) {
        return [
            'reduce_motion' => !empty($sessionPrefs['reduce_motion']),
            'classic_appearance' => !empty($sessionPrefs['classic_appearance']),
        ] + $defaults;
    }

    $prefs = [
        'reduce_motion' => trux_cookie_to_bool($_COOKIE[TRUX_UI_COOKIE_REDUCE_MOTION] ?? null),
        'classic_appearance' => trux_cookie_to_bool($_COOKIE[TRUX_UI_COOKIE_CLASSIC_APPEARANCE] ?? null),
    ] + $defaults;

    $_SESSION['ui_prefs'] = $prefs;
    return $prefs;
}

function trux_set_ui_preferences(bool $reduceMotion, bool $classicAppearance): void {
    $prefs = [
        'reduce_motion' => $reduceMotion,
        'classic_appearance' => $classicAppearance,
    ];

    $_SESSION['ui_prefs'] = $prefs;
    $userId = trux_ui_current_user_id();

    if ($userId > 0 && trux_ui_db_columns_available()) {
        try {
            $db = trux_db();
            $stmt = $db->prepare('UPDATE users SET ui_reduce_motion = ?, ui_classic_appearance = ? WHERE id = ?');
            $stmt->execute([$reduceMotion ? 1 : 0, $classicAppearance ? 1 : 0, $userId]);
        } catch (PDOException) {
            // Keep session/cookie values even if DB write fails.
        }
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieOptions = [
        'expires' => time() + (60 * 60 * 24 * 365),
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie(TRUX_UI_COOKIE_REDUCE_MOTION, $reduceMotion ? '1' : '0', $cookieOptions);
    setcookie(TRUX_UI_COOKIE_CLASSIC_APPEARANCE, $classicAppearance ? '1' : '0', $cookieOptions);
}

function trux_cookie_to_bool(mixed $value): bool {
    if (!is_string($value)) return false;

    $value = strtolower(trim($value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

/**
 * @return array{reduce_motion: bool, classic_appearance: bool}
 */
function trux_ui_default_preferences(): array {
    return [
        'reduce_motion' => false,
        'classic_appearance' => false,
    ];
}

function trux_ui_current_user_id(): int {
    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        return 0;
    }
    return (int)$_SESSION['user_id'];
}

function trux_ui_db_columns_available(): bool {
    static $available = null;
    if (is_bool($available)) {
        return $available;
    }

    try {
        $db = trux_db();
        $hasReduce = (bool)$db->query("SHOW COLUMNS FROM users LIKE 'ui_reduce_motion'")->fetch();
        $hasClassic = (bool)$db->query("SHOW COLUMNS FROM users LIKE 'ui_classic_appearance'")->fetch();
        $available = $hasReduce && $hasClassic;
        return $available;
    } catch (PDOException) {
        $available = false;
        return false;
    }
}
