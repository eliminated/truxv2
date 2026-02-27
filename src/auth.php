<?php
declare(strict_types=1);

function trux_is_logged_in(): bool {
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

function trux_current_user(): ?array {
    if (!trux_is_logged_in()) return null;

    $db = trux_db();
    $stmt = $db->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function trux_require_login(): void {
    if (trux_is_logged_in()) return;
    trux_flash_set('error', 'Please log in to continue.');
    trux_redirect('/login.php');
}

function trux_login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function trux_logout_user(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();
}

function trux_register_user(string $username, string $email, string $password): array {
    $username = trim($username);
    $email = trim($email);

    $errors = [];

    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $errors[] = 'Username must be 3–32 characters, letters/numbers/underscore only.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) return ['ok' => false, 'errors' => ['Could not hash password.']];

    $db = trux_db();
    try {
        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, $hash]);
        $userId = (int)$db->lastInsertId();
        return ['ok' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') {
            return ['ok' => false, 'errors' => ['Username or email is already in use.']];
        }
        return ['ok' => false, 'errors' => ['Database error.']];
    }
}

function trux_attempt_login(string $login, string $password): array {
    $login = trim($login);

    if ($login === '' || $password === '') {
        return ['ok' => false, 'error' => 'Please enter your username/email and password.'];
    }

    $db = trux_db();
    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $u = $stmt->fetch();

    if (!$u || !isset($u['password_hash'])) return ['ok' => false, 'error' => 'Invalid credentials.'];
    if (!password_verify($password, (string)$u['password_hash'])) return ['ok' => false, 'error' => 'Invalid credentials.'];

    trux_login_user((int)$u['id']);
    return ['ok' => true];
}