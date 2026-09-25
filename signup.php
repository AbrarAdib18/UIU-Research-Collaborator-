<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Redirect an already-logged-in visitor straight to their own dashboard.
if (is_logged_in()) {
    switch ($_SESSION['role'] ?? '') {
        case 'student': redirect('/student/dashboard.php');
        case 'faculty': redirect('/faculty/dashboard.php');
        case 'admin':   redirect('/admin/dashboard.php');
        default:        redirect('/index.php');
    }
}

$pdo = db();
$registrationEnabled = get_platform_setting($pdo, 'public_registration_enabled', '1') === '1';
$emailDomain = get_platform_setting($pdo, 'student_email_domain', 'bscse.uiu.ac.bd');
$emailDomainPattern = '/^[a-zA-Z0-9._%+-]+@' . preg_quote($emailDomain, '/') . '$/';

if (!$registrationEnabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('error', 'New registrations are temporarily disabled. Please contact an administrator.');
    redirect('/signup.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/signup.php');

    $name      = trim((string)($_POST['name'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));
    $studentId = trim((string)($_POST['student_id'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $terms     = isset($_POST['terms']);

    $old = ['name' => $name, 'email' => $email, 'student_id' => $studentId];
    $errors = [];

    if ($name === '') {
        $errors[] = 'Please enter your full name.';
    }
    if (!preg_match($emailDomainPattern, $email)) {
        $errors[] = 'Please use a valid @' . $emailDomain . ' university email.';
    }
    if ($studentId === '') {
        $errors[] = 'Please enter your student ID.';
    }
    if (strlen($password) < 8 || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 characters long and include a special character.';
    }
    if (!$terms) {
        $errors[] = 'You must agree to the Terms & Conditions and Privacy Policy.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        set_old($old);
        redirect('/signup.php');
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        flash('error', 'An account with that email already exists. Try logging in instead.');
        set_old($old);
        redirect('/signup.php');
    }

    $checkSid = $pdo->prepare('SELECT id FROM student_profiles WHERE student_id = ? LIMIT 1');
    $checkSid->execute([$studentId]);
    if ($checkSid->fetch()) {
        flash('error', 'That student ID is already registered.');
        set_old($old);
        redirect('/signup.php');
    }

    try {
        $pdo->beginTransaction();

        $insertUser = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, \'student\', \'active\')'
        );
        $insertUser->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();

        $insertProfile = $pdo->prepare(
            'INSERT INTO student_profiles (user_id, student_id) VALUES (?, ?)'
        );
        $insertProfile->execute([$userId, $studentId]);
        $profileId = (int)$pdo->lastInsertId();

        $defaultVisibility = get_platform_setting($pdo, 'default_profile_visibility', 'Students Only');
        if (!in_array($defaultVisibility, ['Public', 'Students Only', 'Private'], true)) {
            $defaultVisibility = 'Students Only';
        }
        $insertVisibility = $pdo->prepare(
            'INSERT INTO profile_visibility (profile_id, profile_visibility) VALUES (?, ?)'
        );
        $insertVisibility->execute([$profileId, $defaultVisibility]);

        $insertPrefs = $pdo->prepare(
            'INSERT INTO research_preferences (profile_id) VALUES (?)'
        );
        $insertPrefs->execute([$profileId]);

        log_activity($pdo, $userId, 'signup', 'Created a student account');

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Signup failed: ' . $e->getMessage());
        flash('error', 'Something went wrong creating your account. Please try again.');
        set_old($old);
        redirect('/signup.php');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role']    = 'student';
    $_SESSION['name']    = $name;
    $_SESSION['email']   = $email;

    flash('success', 'Welcome to UIU ResearchCollab! Let\'s complete your profile.');
    redirect('/student/profile.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGNUP || UIU ResearchCollab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="CSS/signup.css">
</head>
<body class="auth-body">

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
        <section class="auth-card signup-card">
            <div class="auth-logo">
                <div class="auth-logo-circle">
                    <img src="IMAGES/logo.jpg" alt="UIU ResearchCollab Logo">
                </div>
            </div>

            <div class="auth-heading">
                <h1>Create Your Account</h1>
                <p>Join <strong>UIU ResearchCollab</strong> and start connecting with researchers.</p>
            </div>

            <?php render_flashes(); ?>

            <form class="auth-form" id="signupForm" action="signup.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="auth-input-group">
                    <label for="signup-name">Full Name</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/>
                        </svg>
                        <input type="text" id="signup-name" name="name" value="<?= e(old('name')) ?>" placeholder="Enter your full name" autocomplete="name" required>
                    </div>
                </div>

                <div class="auth-input-group">
                    <label for="signup-email">University Email</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <polyline points="3 7 12 13 21 7"/>
                        </svg>
                        <input type="email" id="signup-email" name="email" value="<?= e(old('email')) ?>" placeholder="yourname@bscse.uiu.ac.bd" autocomplete="email" required>
                    </div>
                    <small id="emailError" class="validation-message"></small>
                </div>

                <div class="auth-input-group">
                    <label for="student-id">Student ID</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="17" rx="2"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <input type="text" id="student-id" name="student_id" value="<?= e(old('student_id')) ?>" placeholder="Enter your student ID" autocomplete="off" required>
                    </div>
                </div>

                <div class="auth-input-group">
                    <label for="signup-password">Password</label>
                    <div class="auth-input-wrapper">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="10" rx="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input type="password" id="signup-password" name="password" placeholder="Create a password" autocomplete="new-password" minlength="8" required>
                        <button type="button" class="password-toggle" data-target="#signup-password" id="passwordToggle" aria-label="Show password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7 S2 12 2 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <small id="passwordError" class="validation-message"></small>
                </div>

                <label class="auth-terms">
                    <input type="checkbox" id="terms" name="terms" required>
                    <span>I agree to the <a href="terms.php" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="privacy.php" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                </label>

                <button type="submit" class="auth-submit-btn" id="signupButton">
                    <span>Create Account</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 12h14"/>
                        <path d="M13 6l6 6-6 6"/>
                    </svg>
                    <span class="button-glow"></span>
                </button>
            </form>

            <div class="auth-divider"><span>OR</span></div>

            <div class="auth-bottom">
                <p>Already have an account? <a href="login.php">Login</a></p>
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

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const signupForm = document.getElementById("signupForm");
        const emailInput = document.getElementById("signup-email");
        const passwordInput = document.getElementById("signup-password");
        const emailError = document.getElementById("emailError");
        const passwordError = document.getElementById("passwordError");

        const universityEmailPattern = /^[a-zA-Z0-9._%+-]+@bscse\.uiu\.ac\.bd$/;
        const passwordPattern = /^(?=.*[^A-Za-z0-9]).{8,}$/;

        emailInput.addEventListener("input", function () {
            const email = emailInput.value.trim();
            if (email === "") { emailError.textContent = ""; emailInput.setCustomValidity(""); return; }
            if (!universityEmailPattern.test(email)) {
                emailError.textContent = "Use your university email ending with @bscse.uiu.ac.bd.";
                emailInput.setCustomValidity("Invalid university email.");
            } else {
                emailError.textContent = ""; emailInput.setCustomValidity("");
            }
        });

        passwordInput.addEventListener("input", function () {
            const password = passwordInput.value;
            if (password === "") { passwordError.textContent = ""; passwordInput.setCustomValidity(""); return; }
            if (password.length < 8) {
                passwordError.textContent = "Password must contain at least 8 characters.";
                passwordInput.setCustomValidity("Too short.");
                return;
            }
            if (!passwordPattern.test(password)) {
                passwordError.textContent = "Password must contain at least 1 special character.";
                passwordInput.setCustomValidity("Missing special character.");
                return;
            }
            passwordError.textContent = ""; passwordInput.setCustomValidity("");
        });

        signupForm.addEventListener("submit", function (event) {
            const email = emailInput.value.trim();
            const password = passwordInput.value;

            if (!universityEmailPattern.test(email)) {
                event.preventDefault();
                emailError.textContent = "Please use a valid @bscse.uiu.ac.bd university email.";
                emailInput.reportValidity();
                return;
            }
            if (!passwordPattern.test(password)) {
                event.preventDefault();
                passwordError.textContent = "Password must be at least 8 characters and include a special character.";
                passwordInput.reportValidity();
                return;
            }
            if (!signupForm.checkValidity()) {
                event.preventDefault();
                signupForm.classList.add("was-validated");
            }
        });
    });
    </script>
</body>
</html>
