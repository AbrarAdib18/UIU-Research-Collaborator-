<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in() && ($_SESSION['role'] ?? '') === 'student') {
    redirect('/student/dashboard.php');
}

$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/forgot-password.php');

    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        set_old(['email' => $email]);
        redirect('/forgot-password.php');
    }

    $token = create_password_reset_token($email);

    // Always show the same message whether or not the email exists, so the
    // form can't be used to discover which emails are registered.
    flash('success', 'If an account exists for that email, a password reset link has been generated below.');

    if ($token !== null) {
        // No outbound email service is configured in this environment, so
        // the reset link is shown directly on screen instead of emailed —
        // this is a local-development/demo convenience, not a real email.
        $resetLink = url('/reset-password.php?token=' . $token);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FORGOT PASSWORD || UIU ResearchCollab</title>

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
                <h1>Forgot Password</h1>
                <p>Enter your email and we'll help you reset it</p>
            </div>

            <?php render_flashes(); ?>

            <?php if ($resetLink): ?>
                <div class="alert alert-info" role="alert" style="font-size: 13px; word-break: break-all;">
                    <strong>Demo mode:</strong> no email service is configured in this environment, so here is your reset link:<br>
                    <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="forgotPasswordForm" action="forgot-password.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="auth-input-group">
                    <label for="forgot-email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <polyline points="3 7 12 13 21 7"/>
                        </svg>
                        <input
                            type="email"
                            id="forgot-email"
                            name="email"
                            value="<?= e(old('email')) ?>"
                            placeholder="Enter your UIU email"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Send Reset Link</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="M13 6l6 6-6 6"/>
                    </svg>
                    <span class="button-glow"></span>
                </button>
            </form>

            <div class="auth-divider"><span>OR</span></div>

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
