<?php
/** CSRF token helpers. Relies on a session already being started. */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Call at the top of every POST handler. Flashes an error and redirects on failure. */
function require_csrf(string $redirectTo = '/index.php'): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
        flash('error', 'Your session expired or the request was invalid. Please try again.');
        redirect($redirectTo);
    }
}
