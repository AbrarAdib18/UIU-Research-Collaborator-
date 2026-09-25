<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo      = db();
$adminId  = (int)$currentUser['id'];
$targetId = validate_id($_GET['id'] ?? $_POST['id'] ?? null);

if (!$targetId) {
    flash('error', 'Invalid user.');
    redirect('/admin/users.php');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();
if (!$target) {
    flash('error', 'User not found.');
    redirect('/admin/users.php');
}

$studentProfile = null;
$facultyProfile = null;
if ($target['role'] === 'student') {
    $sp = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ?');
    $sp->execute([$targetId]);
    $studentProfile = $sp->fetch();
} elseif ($target['role'] === 'faculty') {
    $fp = $pdo->prepare('SELECT * FROM faculty_profiles WHERE user_id = ?');
    $fp->execute([$targetId]);
    $facultyProfile = $fp->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/user-edit.php?id=' . $targetId);

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Name cannot be empty.');
        redirect('/admin/user-edit.php?id=' . $targetId);
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $targetId]);

        if ($studentProfile) {
            $pdo->prepare('UPDATE student_profiles SET department = ?, program = ?, semester = ? WHERE user_id = ?')
                ->execute([nullable_trim($_POST['department'] ?? ''), nullable_trim($_POST['program'] ?? ''), nullable_trim($_POST['semester'] ?? ''), $targetId]);
        } elseif ($facultyProfile) {
            $pdo->prepare('UPDATE faculty_profiles SET department = ?, designation = ? WHERE user_id = ?')
                ->execute([nullable_trim($_POST['department'] ?? ''), nullable_trim($_POST['designation'] ?? ''), $targetId]);
        }

        log_activity($pdo, $adminId, 'admin_user_edit', "Edited account details for {$name}", 'user', $targetId);
        $pdo->commit();
        flash('success', 'User details updated.');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        error_log('admin user-edit: ' . $ex->getMessage());
        flash('error', 'Could not save changes. Please try again.');
    }
    redirect('/admin/user-details.php?id=' . $targetId);
}

$pageTitle = 'Edit User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?= e($target['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>.app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:22px}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/admin/user-details.php?id=' . $targetId)) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to User</a>

        <div class="app-panel">
            <h2 style="color:var(--uiu-blue);font-size:20px;font-weight:700;">Edit Account Details</h2>
            <p class="text-muted small">Only safe administrative fields can be edited here — not password, role, or email.</p>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?= e($target['name']) ?>" required></div>
                <div class="mb-3"><label class="form-label">Email <small class="text-muted">(fixed — sign-in identity)</small></label><input type="email" class="form-control" value="<?= e($target['email']) ?>" disabled></div>
                <div class="mb-3"><label class="form-label">Role <small class="text-muted">(fixed — role changes are not permitted here)</small></label><input type="text" class="form-control" value="<?= e(ucfirst($target['role'])) ?>" disabled></div>

                <?php if ($studentProfile): ?>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= e($studentProfile['department'] ?? '') ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Program</label><input type="text" name="program" class="form-control" value="<?= e($studentProfile['program'] ?? '') ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Semester</label><input type="text" name="semester" class="form-control" value="<?= e($studentProfile['semester'] ?? '') ?>"></div>
                    </div>
                <?php elseif ($facultyProfile): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= e($facultyProfile['department'] ?? '') ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Designation</label><input type="text" name="designation" class="form-control" value="<?= e($facultyProfile['designation'] ?? '') ?>"></div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Save Changes</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
