<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in() && ($_SESSION['role'] ?? '') === 'student') {
    redirect('/student/dashboard.php');
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$resetUser = $token !== '' ? find_valid_reset_token($token) : null;

if ($token === '' || !$resetUser) {
    flash('error', 'This password reset link is invalid or has expired. Please request a new one.');
    redirect('/forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/reset-password.php?token=' . urlencode($token));

    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    if (strlen($password) < 8) {
        flash('error', 'Password must be at least 8 characters long.');
        redirect('/reset-password.php?token=' . urlencode($token));
    }
    if ($password !== $confirm) {
        flash('error', 'Passwords do not match.');
        redirect('/reset-password.php?token=' . urlencode($token));
    }

    consume_password_reset_token((int)$resetUser['token_id'], (int)$resetUser['id'], $password);

    flash('success', 'Your password has been reset. Please log in with your new password.');
    redirect('/login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESET PASSWORD || UIU ResearchCollab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="CSS/login.css">
</head>
<body>
    <div class="auth-background">
        <div class="auth-grid"></div>
        <div class="auth-orb auth-orb-1"></div>
        <div class="auth-orb auth-orb-2"></div>
        <div class="auth-orb auth-orb-3"></div>
        <div class="auth-particle particle-1"></div>
        <div class="auth-particle particle-2"></div>
        <div class="auth-particle particle-3"></div>
        <div class="auth-particle particle-4"></div>
        <div class="auth-particle particle-5"></div>
        <div class="auth-particle particle-6"></div>
    </div>

    <main class="auth-container">
        <section class="auth-card login-card">
            <div class="auth-logo">
                <div class="auth-logo-circle">
                    <img src="IMAGES/logo.jpg" alt="UIU ResearchCollab Logo">
                </div>
            </div>

            <div class="auth-heading">
                <h1>Reset Password</h1>
                <p>Choose a new password for <strong><?= e($resetUser['email']) ?></strong></p>
            </div>

            <?php render_flashes(); ?>

            <form class="auth-form" id="resetPasswordForm" action="reset-password.php?token=<?= e(urlencode($token)) ?>" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="auth-input-group">
                    <label for="reset-password">New Password</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="10" rx="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input
                            type="password"
                            id="reset-password"
                            name="password"
                            placeholder="At least 8 characters"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>

                <div class="auth-input-group">
                    <label for="reset-password-confirm">Confirm New Password</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="10" rx="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input
                            type="password"
                            id="reset-password-confirm"
                            name="password_confirm"
                            placeholder="Re-enter new password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Reset Password</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="M13 6l6 6-6 6"/>
                    </svg>
                    <span class="button-glow"></span>
                </button>
            </form>

            <div class="auth-bottom">
                <p><a href="login.php">Back to Login</a></p>
            </div>

            <div class="auth-footer">
                <span>UIU ResearchCollab</span>
                <span>&bull;</span>
                <span>Connecting Minds. Creating Research.</span>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="JS/app.js"></script>
</body>
</html>
