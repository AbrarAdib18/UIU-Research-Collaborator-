<?php
/** Session-based authentication helpers. Requires config/database.php + functions.php. */

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    static $cache = null;
    if (!is_logged_in()) {
        return null;
    }
    if ($cache === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([current_user_id()]);
        $cache = $stmt->fetch() ?: null;
        if (!$cache) {
            // Session points at a user that no longer exists.
            do_logout();
        }
    }
    return $cache;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('/login.php');
    }
}

function require_student(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'student') {
        flash('error', 'That area is only available to students.');
        redirect('/index.php');
    }
}

/**
 * Attempts to log a user in. Returns the user row on success, or null
 * with a flash message already set on failure.
 */
function attempt_login(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        flash('error', 'Incorrect email or password.');
        return null;
    }
    if ($user['status'] !== 'active') {
        flash('error', 'Your account is not active. Please contact support.');
        return null;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];

    $upd = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $upd->execute([$user['id']]);

    log_activity(db(), (int)$user['id'], 'login', 'Logged in');

    return $user;
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
