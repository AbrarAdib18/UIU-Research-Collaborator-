<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$verStmt = $pdo->prepare('SELECT * FROM faculty_verifications WHERE faculty_user_id = ?');
$verStmt->execute([$userId]);
$verification = $verStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/settings.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPassword, $currentUser['password'])) {
            flash('error', 'Current password is incorrect.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirmation do not match.');
        } elseif (strlen($newPassword) < 8) {
            flash('error', 'New password must be at least 8 characters long.');
        } else {
            try {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                log_activity($pdo, $userId, 'password_change', 'Changed account password');
                flash('success', 'Your password has been updated.');
            } catch (Throwable $e) {
                error_log('faculty settings change_password: ' . $e->getMessage());
                flash('error', 'Something went wrong while updating your password. Please try again.');
            }
        }
    }
    redirect('/faculty/settings.php');
}

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
    <style>.app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:22px;margin-bottom:18px}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;margin-bottom:14px;">Settings</h2>

        <div class="app-panel">
            <h3 style="font-size:16px;color:var(--uiu-blue);font-weight:700;">Account</h3>
            <p class="mb-1"><strong>Name:</strong> <?= e($currentUser['name']) ?></p>
            <p class="mb-1"><strong>Email:</strong> <?= e($currentUser['email']) ?></p>
            <p class="mb-0">
                <strong>Verification Status:</strong>
                <?php if (!$verification || $verification['status'] === 'pending'): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>
                    <span class="text-muted small d-block mt-1">Your account is awaiting administrator verification.</span>
                <?php elseif ($verification['status'] === 'verified'): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Verified</span>
                <?php elseif ($verification['status'] === 'rejected'): ?>
                    <span class="badge bg-danger-subtle text-danger-emphasis">Rejected</span>
                    <?php if ($verification['rejection_reason']): ?><span class="text-muted small d-block mt-1">Reason: <?= e($verification['rejection_reason']) ?></span><?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis">Update Needed</span>
                    <?php if ($verification['admin_notes']): ?><span class="text-muted small d-block mt-1">Note: <?= e($verification['admin_notes']) ?></span><?php endif; ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="app-panel">
            <h3 style="font-size:16px;color:var(--uiu-blue);font-weight:700;">Change Password</h3>
            <form method="post" style="max-width:420px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
                <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" minlength="8" required></div>
                <button type="submit" class="btn btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Update Password</button>
            </form>
        </div>

        <div class="app-panel">
            <h3 style="font-size:16px;color:var(--uiu-blue);font-weight:700;">Profile Visibility & Mentorship Preferences</h3>
            <p class="text-muted mb-2">Manage what students can see and your mentorship capacity from your profile page.</p>
            <a href="<?= e(url('/faculty/profile.php')) ?>" class="btn btn-outline-primary btn-sm">Go to My Profile</a>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
