<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo      = db();
$adminId  = (int)$currentUser['id'];
$targetId = validate_id($_GET['id'] ?? null);

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
$related = [];

if ($target['role'] === 'student') {
    $sp = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ?');
    $sp->execute([$targetId]);
    $studentProfile = $sp->fetch();

    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM opportunity_applications WHERE user_id = ?'); $stmt2->execute([$targetId]);
    $related['Applications'] = (int)$stmt2->fetchColumn();
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE user_id = ? AND status='Active'"); $stmt2->execute([$targetId]);
    $related['Teams'] = (int)$stmt2->fetchColumn();
    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM community_members WHERE user_id = ?'); $stmt2->execute([$targetId]);
    $related['Communities'] = (int)$stmt2->fetchColumn();
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM advisor_assignments WHERE student_user_id = ? AND status='active'"); $stmt2->execute([$targetId]);
    $related['Active Advisor Assignments'] = (int)$stmt2->fetchColumn();
} elseif ($target['role'] === 'faculty') {
    $fp = $pdo->prepare('SELECT * FROM faculty_profiles WHERE user_id = ?');
    $fp->execute([$targetId]);
    $facultyProfile = $fp->fetch();

    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM research_opportunities WHERE created_by = ?'); $stmt2->execute([$targetId]);
    $related['Opportunities Created'] = (int)$stmt2->fetchColumn();
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM advisor_assignments WHERE faculty_user_id = ? AND status='active'"); $stmt2->execute([$targetId]);
    $related['Active Advisor Assignments'] = (int)$stmt2->fetchColumn();
    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM research_resources WHERE uploaded_by = ?'); $stmt2->execute([$targetId]);
    $related['Resources Uploaded'] = (int)$stmt2->fetchColumn();

    $verStmt = $pdo->prepare('SELECT * FROM faculty_verifications WHERE faculty_user_id = ?');
    $verStmt->execute([$targetId]);
    $verification = $verStmt->fetch();
}

$activityStmt = $pdo->prepare('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 15');
$activityStmt->execute([$targetId]);
$activityRows = $activityStmt->fetchAll();

function admin_status_pill2(string $status): string
{
    return match ($status) {
        'active'    => 'badge bg-success-subtle text-success-emphasis',
        'inactive'  => 'badge bg-secondary-subtle text-secondary-emphasis',
        'suspended' => 'badge bg-danger-subtle text-danger-emphasis',
        default     => 'badge bg-secondary-subtle text-secondary-emphasis',
    };
}

$pageTitle = 'User Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($target['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}
        .avatar-lg{width:64px;height:64px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:22px;font-weight:700;flex-shrink:0}
        .related-stat{background:#f5f8fc;border-radius:10px;padding:10px;text-align:center}
        .related-stat strong{display:block;font-size:18px;color:var(--uiu-blue)}
        .related-stat span{font-size:11px;color:var(--text-light)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/admin/users.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Users</a>

        <div class="detail-card d-flex align-items-center gap-3 flex-wrap">
            <span class="avatar-lg"><?= e(initials($target['name'])) ?></span>
            <div class="flex-grow-1">
                <h1 style="font-size:20px;color:var(--uiu-blue);font-weight:700;margin:0;"><?= e($target['name']) ?></h1>
                <p class="text-muted mb-1"><?= e($target['email']) ?></p>
                <span class="badge bg-light text-dark border"><?= e(ucfirst($target['role'])) ?></span>
                <span class="<?= admin_status_pill2($target['status']) ?>"><?= e(ucfirst($target['status'])) ?></span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= e(url('/admin/user-edit.php?id=' . $targetId)) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
                <?php if ($target['status'] !== 'active'): ?>
                    <form method="post" action="<?= e(url('/admin/user-status.php')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $targetId ?>"><input type="hidden" name="action" value="activate"><button class="btn btn-sm btn-success">Activate</button></form>
                <?php endif; ?>
                <?php if ($target['status'] === 'active'): ?>
                    <form method="post" action="<?= e(url('/admin/user-status.php')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $targetId ?>"><input type="hidden" name="action" value="deactivate"><button class="btn btn-sm btn-outline-secondary">Deactivate</button></form>
                <?php endif; ?>
                <?php if ($target['status'] !== 'suspended'): ?>
                    <form method="post" action="<?= e(url('/admin/user-status.php')) ?>" onsubmit="return confirm('Suspend this account? They will be unable to log in.');"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $targetId ?>"><input type="hidden" name="action" value="suspend"><button class="btn btn-sm btn-outline-danger">Suspend</button></form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/admin/user-status.php')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $targetId ?>"><input type="hidden" name="action" value="restore"><button class="btn btn-sm btn-success">Restore</button></form>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-card">
            <h2>Account Info</h2>
            <p class="mb-1"><strong>Joined:</strong> <?= format_date($target['created_at']) ?></p>
            <p class="mb-1"><strong>Last Login:</strong> <?= $target['last_login_at'] ? e(time_ago($target['last_login_at'])) : 'Never' ?></p>
            <p class="mb-0"><strong>Email Verified:</strong> <?= $target['email_verified_at'] ? format_date($target['email_verified_at']) : 'No' ?></p>
        </div>

        <?php if ($studentProfile): ?>
        <div class="detail-card">
            <h2>Student Profile</h2>
            <p class="mb-1"><strong>Student ID:</strong> <?= e($studentProfile['student_id'] ?: '—') ?></p>
            <p class="mb-1"><strong>Department:</strong> <?= e($studentProfile['department'] ?: '—') ?> &middot; <strong>Program:</strong> <?= e($studentProfile['program'] ?: '—') ?></p>
            <p class="mb-1"><strong>Semester:</strong> <?= e($studentProfile['semester'] ?: '—') ?> &middot; <strong>CGPA:</strong> <?= $studentProfile['cgpa'] !== null ? e((string)$studentProfile['cgpa']) : '—' ?></p>
            <p class="mb-0"><strong>Profile Completion:</strong> <?= (int)$studentProfile['profile_completion'] ?>%</p>
        </div>
        <?php endif; ?>

        <?php if ($facultyProfile): ?>
        <div class="detail-card">
            <h2>Faculty Profile</h2>
            <p class="mb-1"><strong>Faculty ID:</strong> <?= e($facultyProfile['faculty_id'] ?: '—') ?></p>
            <p class="mb-1"><strong>Department:</strong> <?= e($facultyProfile['department'] ?: '—') ?> &middot; <strong>Designation:</strong> <?= e($facultyProfile['designation'] ?: '—') ?></p>
            <p class="mb-1"><strong>Specialization:</strong> <?= e($facultyProfile['specialization'] ?: '—') ?></p>
            <?php if (isset($verification)): ?>
                <p class="mb-0"><strong>Verification Status:</strong>
                    <span class="badge <?= $verification['status'] === 'verified' ? 'bg-success-subtle text-success-emphasis' : ($verification['status'] === 'rejected' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis') ?>"><?= e(str_replace('_', ' ', $verification['status'])) ?></span>
                    <a href="<?= e(url('/admin/faculty-verification.php?id=' . $verification['id'])) ?>" class="ms-2 small">Review</a>
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($related): ?>
        <div class="detail-card">
            <h2>Related Activity</h2>
            <div class="row g-2">
                <?php foreach ($related as $label => $count): ?>
                    <div class="col-6 col-md-4"><div class="related-stat"><strong><?= $count ?></strong><span><?= e($label) ?></span></div></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="detail-card">
            <h2>Recent Activity Log</h2>
            <?php if (!$activityRows): ?><p class="text-muted mb-0">No activity recorded.</p><?php endif; ?>
            <?php foreach ($activityRows as $a): ?>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= e($a['description']) ?></span>
                    <span class="text-muted small"><?= e(time_ago($a['created_at'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
