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

// ---------------------------------------------------------------------
// Password reset ("Forgot Password?")
// ---------------------------------------------------------------------

/**
 * Creates a single-use, 1-hour password reset token for the given email
 * (if an account exists for it) and returns the raw token, or null if no
 * account matches. Only the token's hash is stored in the database.
 */
function create_password_reset_token(string $email): ?string
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $ins = db()->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $ins->execute([$user['id'], $tokenHash, $expiresAt]);

    return $token;
}

/**
 * Looks up an unexpired, unused reset token and returns the associated
 * user row, or null if the token is invalid/expired/already used.
 */
function find_valid_reset_token(string $token): ?array
{
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare(
        "SELECT prt.id AS token_id, u.*
         FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    return $stmt->fetch() ?: null;
}

/** Sets a new password for the user tied to a valid reset token and consumes it. */
function consume_password_reset_token(int $tokenId, int $userId, string $newPassword): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $upd->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        $used = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
        $used->execute([$tokenId]);

        // Invalidate any other outstanding tokens for this user.
        $invalidate = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
        $invalidate->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
