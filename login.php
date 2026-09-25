<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Redirect an already-logged-in visitor straight to their own dashboard
// (avoids a redirect loop back through this page).
if (is_logged_in()) {
    switch ($_SESSION['role'] ?? '') {
        case 'student': redirect('/student/dashboard.php');
        case 'faculty': redirect('/faculty/dashboard.php');
        case 'admin':   redirect('/admin/dashboard.php');
        default:        redirect('/index.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/login.php');

    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        flash('error', 'Please enter your email and password.');
        set_old(['email' => $email]);
        redirect('/login.php');
    }

    $user = attempt_login($email, $password);
    if ($user) {
        flash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
        switch ($user['role']) {
            case 'student': redirect('/student/dashboard.php');
            case 'faculty': redirect('/faculty/dashboard.php');
            case 'admin':   redirect('/admin/dashboard.php');
            default:        redirect('/index.php');
        }
    }

    set_old(['email' => $email]);
    redirect('/login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN || UIU ResearchCollab</title>

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
                <h1>Welcome Back</h1>
                <p>Sign in to continue to <strong>UIU ResearchCollab</strong></p>
            </div>

            <?php render_flashes(); ?>

            <form class="auth-form" id="loginForm" action="login.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="auth-input-group">
                    <label for="login-email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <polyline points="3 7 12 13 21 7"/>
                        </svg>
                        <input
                            type="email"
                            id="login-email"
                            name="email"
                            value="<?= e(old('email')) ?>"
                            placeholder="Enter your UIU email"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <div class="auth-input-group">
                    <div class="auth-label-row">
                        <label for="login-password">Password</label>
                        <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="10" rx="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input
                            type="password"
                            id="login-password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="password-toggle" data-target="#login-password" id="passwordToggle" aria-label="Show password">
                            <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7 S2 12 2 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="auth-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="rememberMe">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="auth-submit-btn" id="loginSubmit">
                    <span>Login</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="M13 6l6 6-6 6"/>
                    </svg>
                    <span class="button-glow"></span>
                </button>
            </form>

            <div class="auth-divider"><span>OR</span></div>

            <div class="auth-bottom">
                <p>Don't have an account? <a href="signup.php">Create Account</a></p>
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
