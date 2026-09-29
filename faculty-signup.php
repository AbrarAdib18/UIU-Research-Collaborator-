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

// Faculty must register with a UIU address (any uiu.ac.bd subdomain, e.g. cse.uiu.ac.bd).
$facultyEmailPattern = '/^[a-zA-Z0-9._%+-]+@([a-zA-Z0-9-]+\.)*uiu\.ac\.bd$/i';

if (!$registrationEnabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('error', 'New registrations are temporarily disabled. Please contact an administrator.');
    redirect('/faculty-signup.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty-signup.php');

    $name        = trim(preg_replace('/\s+/', ' ', (string)($_POST['name'] ?? '')));
    $email       = trim((string)($_POST['email'] ?? ''));
    $department  = trim((string)($_POST['department'] ?? ''));
    $designation = trim((string)($_POST['designation'] ?? ''));
    $facultyCode = trim((string)($_POST['faculty_id'] ?? ''));
    $password    = (string)($_POST['password'] ?? '');
    $terms       = isset($_POST['terms']);

    $old = ['name' => $name, 'email' => $email, 'department' => $department, 'designation' => $designation, 'faculty_id' => $facultyCode];
    $errors = [];

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Please enter your full name (up to 100 characters).';
    }
    if (!preg_match($facultyEmailPattern, $email) || strlen($email) > 150) {
        $errors[] = 'Please use a valid @uiu.ac.bd university email.';
    }
    if ($department === '' || mb_strlen($department) > 150) {
        $errors[] = 'Please enter your department.';
    }
    if ($designation === '' || mb_strlen($designation) > 150) {
        $errors[] = 'Please enter your designation.';
    }
    if (mb_strlen($facultyCode) > 50) {
        $errors[] = 'Faculty ID must be at most 50 characters.';
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
        redirect('/faculty-signup.php');
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        flash('error', 'An account with that email already exists. Try logging in instead.');
        set_old($old);
        redirect('/faculty-signup.php');
    }

    if ($facultyCode !== '') {
        $checkFid = $pdo->prepare('SELECT id FROM faculty_profiles WHERE faculty_id = ? LIMIT 1');
        $checkFid->execute([$facultyCode]);
        if ($checkFid->fetch()) {
            flash('error', 'That faculty ID is already registered.');
            set_old($old);
            redirect('/faculty-signup.php');
        }
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, \'faculty\', \'active\')')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO faculty_profiles (user_id, faculty_id, department, designation) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $facultyCode !== '' ? $facultyCode : null, $department, $designation]);
        $profileId = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO faculty_preferences (faculty_profile_id) VALUES (?)')->execute([$profileId]);
        $pdo->prepare('INSERT INTO faculty_visibility (faculty_profile_id) VALUES (?)')->execute([$profileId]);
        $pdo->prepare('INSERT INTO faculty_verifications (faculty_user_id, status) VALUES (?, \'pending\')')->execute([$userId]);

        log_activity($pdo, $userId, 'signup', 'Created a faculty account');

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Faculty signup failed: ' . $e->getMessage());
        flash('error', 'Something went wrong creating your account. Please try again.');
        set_old($old);
        redirect('/faculty-signup.php');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role']    = 'faculty';
    $_SESSION['name']    = $name;
    $_SESSION['email']   = $email;

    flash('success', 'Welcome to UIU ResearchCollab! Your account is pending admin verification. Let\'s complete your profile.');
    redirect('/faculty/profile.php');
}

/** One text/email/password field in the same markup signup.php uses. */
function fs_field(string $id, string $name, string $label, string $type, string $placeholder, string $value = '', string $extra = ''): void
{
    ?>
                <div class="auth-input-group">
                    <label for="<?= e($id) ?>"><?= e($label) ?></label>
                    <div class="auth-input-wrapper">
                        <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>" <?= $extra ?>>
                    </div>
                </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FACULTY SIGNUP || UIU ResearchCollab</title>

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
    </div>

    <main class="auth-container">
        <section class="auth-card signup-card">
            <div class="auth-logo">
                <div class="auth-logo-circle">
                    <img src="IMAGES/logo.jpg" alt="UIU ResearchCollab Logo">
                </div>
            </div>

            <div class="auth-heading">
                <h1>Faculty Sign Up</h1>
                <p>Join <strong>UIU ResearchCollab</strong> to mentor and advise student researchers.</p>
            </div>

            <?php render_flashes(); ?>

            <form class="auth-form" id="signupForm" action="faculty-signup.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <?php
                fs_field('signup-name', 'name', 'Full Name', 'text', 'Enter your full name', old('name'), 'autocomplete="name" maxlength="100" required');
                fs_field('signup-email', 'email', 'University Email', 'email', 'yourname@uiu.ac.bd', old('email'), 'autocomplete="email" required');
                fs_field('faculty-id', 'faculty_id', 'Faculty ID (optional)', 'text', 'Your UIU faculty ID', old('faculty_id'), 'autocomplete="off" maxlength="50"');
                fs_field('department', 'department', 'Department', 'text', 'e.g. Computer Science & Engineering', old('department'), 'maxlength="150" required');
                fs_field('designation', 'designation', 'Designation', 'text', 'e.g. Assistant Professor', old('designation'), 'maxlength="150" required');
                fs_field('signup-password', 'password', 'Password', 'password', 'Create a password', '', 'autocomplete="new-password" minlength="8" required');
                ?>
                <small id="emailError" class="validation-message"></small>
                <small id="passwordError" class="validation-message"></small>

                <label class="auth-terms">
                    <input type="checkbox" id="terms" name="terms" required>
                    <span>I agree to the <a href="terms.php" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="privacy.php" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                </label>

                <button type="submit" class="auth-submit-btn" id="signupButton">
                    <span>Create Faculty Account</span>
                    <span class="button-glow"></span>
                </button>
            </form>

            <div class="auth-divider"><span>OR</span></div>

            <div class="auth-bottom">
                <p>Already have an account? <a href="login.php">Login</a></p>
                <p>Are you a student? <a href="signup.php">Student sign up</a></p>
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
        const form = document.getElementById("signupForm");
        const emailInput = document.getElementById("signup-email");
        const passwordInput = document.getElementById("signup-password");
        const emailError = document.getElementById("emailError");
        const passwordError = document.getElementById("passwordError");
        const emailPattern = /^[a-zA-Z0-9._%+-]+@([a-zA-Z0-9-]+\.)*uiu\.ac\.bd$/i;
        const passwordPattern = /^(?=.*[^A-Za-z0-9]).{8,}$/;

        form.addEventListener("submit", function (event) {
            emailError.textContent = "";
            passwordError.textContent = "";
            if (!emailPattern.test(emailInput.value.trim())) {
                event.preventDefault();
                emailError.textContent = "Please use a valid @uiu.ac.bd university email.";
                return;
            }
            if (!passwordPattern.test(passwordInput.value)) {
                event.preventDefault();
                passwordError.textContent = "Password must be at least 8 characters and include a special character.";
                return;
            }
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
    });
    </script>
</body>
</html>
