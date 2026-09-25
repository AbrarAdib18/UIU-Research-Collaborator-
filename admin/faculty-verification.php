<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/faculty-verification.php');
    $verId  = validate_id($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    $notes  = nullable_trim($_POST['admin_notes'] ?? '');
    $reason = nullable_trim($_POST['rejection_reason'] ?? '');

    if (!$verId || !in_array($action, ['approve', 'reject', 'request_update'], true)) {
        flash('error', 'Invalid request.');
        redirect('/admin/faculty-verification.php');
    }

    $stmt = $pdo->prepare('SELECT * FROM faculty_verifications WHERE id = ?');
    $stmt->execute([$verId]);
    $verification = $stmt->fetch();

    if (!$verification) {
        flash('error', 'Verification record not found.');
        redirect('/admin/faculty-verification.php');
    }

    $facultyUserId = (int)$verification['faculty_user_id'];
    $newStatus = match ($action) {
        'approve'        => 'verified',
        'reject'          => 'rejected',
        'request_update'  => 'needs_update',
    };

    if ($action === 'reject' && $reason === null) {
        flash('error', 'Please provide a rejection reason.');
        redirect('/admin/faculty-verification.php?id=' . $verId);
    }

    try {
        $pdo->beginTransaction();

        if ($action === 'approve') {
            $pdo->prepare("UPDATE faculty_verifications SET status='verified', admin_notes=?, rejection_reason=NULL, verified_by=?, verified_at=NOW() WHERE id=?")
                ->execute([$notes, $adminId, $verId]);
            $message = 'Your faculty account has been verified by an administrator.';
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE faculty_verifications SET status='rejected', admin_notes=?, rejection_reason=?, verified_by=?, verified_at=NOW() WHERE id=?")
                ->execute([$notes, $reason, $adminId, $verId]);
            $message = 'Your faculty verification was rejected. Reason: ' . $reason;
        } else {
            $pdo->prepare("UPDATE faculty_verifications SET status='needs_update', admin_notes=?, verified_by=?, verified_at=NOW() WHERE id=?")
                ->execute([$notes, $adminId, $verId]);
            $message = 'Please update your faculty profile — an administrator requested changes.' . ($notes ? ' Note: ' . $notes : '');
        }

        create_notification($pdo, $facultyUserId, 'faculty_verification', 'Faculty Verification Update', $message, 'faculty_verification', $verId);
        log_activity($pdo, $adminId, 'admin_faculty_verification', "Set faculty verification #{$verId} to {$newStatus}", 'faculty_verification', $verId);

        $pdo->commit();
        flash('success', 'Verification status updated.');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        error_log('admin faculty-verification: ' . $ex->getMessage());
        flash('error', 'Could not update verification status. Please try again.');
    }
    redirect('/admin/faculty-verification.php');
}

$statusFilter = (string)($_GET['status'] ?? 'pending');
if (!in_array($statusFilter, ['pending', 'verified', 'rejected', 'needs_update', ''], true)) {
    $statusFilter = 'pending';
}

$where = ['1=1'];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'fv.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare(
    "SELECT fv.*, u.name, u.email, fp.department, fp.designation, fp.specialization, fp.faculty_id AS faculty_code, verifier.name AS verifier_name
     FROM faculty_verifications fv
     JOIN users u ON u.id = fv.faculty_user_id
     LEFT JOIN faculty_profiles fp ON fp.user_id = fv.faculty_user_id
     LEFT JOIN users verifier ON verifier.id = fv.verified_by
     WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(fv.status,'pending','needs_update','rejected','verified'), fv.created_at DESC"
);
$stmt->execute($params);
$verifications = $stmt->fetchAll();

function fv_status_badge(string $status): string
{
    return match ($status) {
        'verified'     => 'badge bg-success-subtle text-success-emphasis',
        'rejected'     => 'badge bg-danger-subtle text-danger-emphasis',
        'needs_update' => 'badge bg-warning-subtle text-warning-emphasis',
        default        => 'badge bg-warning-subtle text-warning-emphasis',
    };
}

$pageTitle = 'Faculty Verification';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Verification || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:14px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Faculty Verification</h2>

        <form method="get" class="filter-bar">
            <select name="status" class="form-select" style="max-width:200px;">
                <?php foreach (['pending' => 'Pending', 'needs_update' => 'Needs Update', 'verified' => 'Verified', 'rejected' => 'Rejected', '' => 'All'] as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$verifications): ?>
            <div class="app-panel app-empty-state"><i class="bi bi-patch-check"></i><p>No verification records found.</p></div>
        <?php endif; ?>

        <?php foreach ($verifications as $v): ?>
            <div class="app-panel">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h3 style="font-size:16px;font-weight:700;color:var(--uiu-blue);margin:0;"><?= e($v['name']) ?></h3>
                        <p class="text-muted small mb-1"><?= e($v['email']) ?> &middot; Faculty ID: <?= e($v['faculty_code'] ?: '—') ?></p>
                        <p class="mb-1"><strong>Department:</strong> <?= e($v['department'] ?: '—') ?> &middot; <strong>Designation:</strong> <?= e($v['designation'] ?: '—') ?></p>
                        <p class="mb-1"><strong>Specialization:</strong> <?= e($v['specialization'] ?: '—') ?></p>
                        <?php if ($v['admin_notes']): ?><p class="mb-1 text-muted small"><strong>Admin notes:</strong> <?= e($v['admin_notes']) ?></p><?php endif; ?>
                        <?php if ($v['rejection_reason']): ?><p class="mb-1 text-danger small"><strong>Rejection reason:</strong> <?= e($v['rejection_reason']) ?></p><?php endif; ?>
                        <?php if ($v['verified_by']): ?><p class="mb-0 text-muted small">Last reviewed by <?= e($v['verifier_name']) ?> on <?= format_date($v['verified_at']) ?></p><?php endif; ?>
                    </div>
                    <span class="<?= fv_status_badge($v['status']) ?>"><?= e(str_replace('_', ' ', $v['status'])) ?></span>
                </div>

                <?php if (in_array($v['status'], ['pending', 'needs_update'], true)): ?>
                <form method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                    <div class="mb-2"><textarea name="admin_notes" class="form-control form-control-sm" rows="2" placeholder="Admin notes (optional for approve/request update)"></textarea></div>
                    <div class="mb-2"><input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Rejection reason (required only if rejecting)"></div>
                    <div class="d-flex gap-2">
                        <button name="action" value="approve" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Approve</button>
                        <button name="action" value="reject" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                        <button name="action" value="request_update" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Request Update</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
