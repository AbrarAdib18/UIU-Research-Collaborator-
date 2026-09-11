<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo       = db();
$userId    = (int)$currentUser['id'];
$profileId = (int)$studentProfile['id'];

// ---------------------------------------------------------------------
// POST handling
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/student/settings.php');

    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPassword, $currentUser['password'])) {
            flash('error', 'Current password is incorrect.');
            redirect('/student/settings.php');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirmation do not match.');
            redirect('/student/settings.php');
        }

        if (strlen($newPassword) < 8) {
            flash('error', 'New password must be at least 8 characters long.');
            redirect('/student/settings.php');
        }

        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$newHash, $userId]);
            log_activity($pdo, $userId, 'password_change', 'Changed account password');
            flash('success', 'Your password has been updated.');
        } catch (Throwable $e) {
            error_log('Password change failed: ' . $e->getMessage());
            flash('error', 'Something went wrong while updating your password. Please try again.');
        }

        redirect('/student/settings.php');
    }

    if ($action === 'update_visibility') {
        $contactVisibility     = isset($_POST['contact_visibility']) ? 1 : 0;
        $researchVisibility    = isset($_POST['research_visibility']) ? 1 : 0;
        $projectVisibility     = isset($_POST['project_visibility']) ? 1 : 0;
        $publicationVisibility = isset($_POST['publication_visibility']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO profile_visibility (profile_id, contact_visibility, research_visibility, project_visibility, publication_visibility)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    contact_visibility = VALUES(contact_visibility),
                    research_visibility = VALUES(research_visibility),
                    project_visibility = VALUES(project_visibility),
                    publication_visibility = VALUES(publication_visibility)'
            );
            $stmt->execute([$profileId, $contactVisibility, $researchVisibility, $projectVisibility, $publicationVisibility]);
            flash('success', 'Visibility settings updated.');
        } catch (Throwable $e) {
            error_log('Visibility update failed: ' . $e->getMessage());
            flash('error', 'Something went wrong while updating your visibility settings. Please try again.');
        }

        redirect('/student/settings.php');
    }

    redirect('/student/settings.php');
}

// ---------------------------------------------------------------------
// GET data fetching
// ---------------------------------------------------------------------
$visibility = get_profile_visibility($pdo, $profileId);

$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings || UIU ResearchCollab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="dashboard-content-grid">
            <section class="dashboard-center">

                <!-- ACCOUNT SUMMARY -->
                <section class="dashboard-section">
                    <div class="section-title-row">
                        <h2>Account Information</h2>
                    </div>
                    <div class="right-dashboard-card">
                        <dl class="row mb-3">
                            <dt class="col-sm-3 text-muted fw-normal">Name</dt>
                            <dd class="col-sm-9"><?= e($currentUser['name']) ?></dd>

                            <dt class="col-sm-3 text-muted fw-normal">Email</dt>
                            <dd class="col-sm-9"><?= e($currentUser['email']) ?></dd>

                            <dt class="col-sm-3 text-muted fw-normal">Member Since</dt>
                            <dd class="col-sm-9 mb-0"><?= format_date($currentUser['created_at']) ?></dd>
                        </dl>
                        <a href="<?= e(url('/student/profile.php')) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-person-gear"></i> Edit your full profile
                        </a>
                    </div>
                </section>

                <!-- CHANGE PASSWORD -->
                <section class="dashboard-section">
                    <div class="section-title-row">
                        <h2>Change Password</h2>
                    </div>
                    <div class="right-dashboard-card">
                        <form method="post" action="<?= e(url('/student/settings.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                                <div class="form-text">Must be at least 8 characters.</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-key-fill"></i> Update Password
                            </button>
                        </form>
                    </div>
                </section>

                <!-- VISIBILITY SETTINGS -->
                <section class="dashboard-section">
                    <div class="section-title-row">
                        <h2>Visibility Settings</h2>
                    </div>
                    <div class="right-dashboard-card">
                        <p class="text-muted small">Control which sections of your profile other students can see. This is separate from your overall profile visibility, which you can set on your <a href="<?= e(url('/student/profile.php')) ?>">profile page</a>.</p>
                        <form method="post" action="<?= e(url('/student/settings.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_visibility">

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="contact_visibility" name="contact_visibility" <?= $visibility['contact_visibility'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="contact_visibility">Show my contact info to other students</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="research_visibility" name="research_visibility" <?= $visibility['research_visibility'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="research_visibility">Show my research interests &amp; domains</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="project_visibility" name="project_visibility" <?= $visibility['project_visibility'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="project_visibility">Show my projects</label>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="publication_visibility" name="publication_visibility" <?= $visibility['publication_visibility'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="publication_visibility">Show my publications</label>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-eye-fill"></i> Save Visibility Settings
                            </button>
                        </form>
                    </div>
                </section>

            </section>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
