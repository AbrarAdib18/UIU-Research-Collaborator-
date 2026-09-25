<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/advisor-assignments.php');
    $id     = validate_id($_POST['id'] ?? null);
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT aa.*, u.name AS faculty_name FROM advisor_assignments aa JOIN users u ON u.id = aa.faculty_user_id WHERE aa.id = ?');
    $stmt->execute([$id]);
    $assignment = $id ? $stmt->fetch() : null;

    if (!$assignment || $assignment['status'] !== 'active') {
        flash('error', 'Assignment not found or already ended.');
        redirect('/admin/advisor-assignments.php');
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE advisor_assignments SET status='cancelled', ended_at=NOW(), notes=? WHERE id=?")
            ->execute([($reason ? 'Ended by admin: ' . $reason : 'Ended by admin'), $id]);
        if ($assignment['advisor_request_id']) {
            $pdo->prepare("UPDATE advisor_requests SET status='cancelled' WHERE id=?")->execute([$assignment['advisor_request_id']]);
        }

        create_notification($pdo, (int)$assignment['faculty_user_id'], 'advisor_assignment', 'Advisor Assignment Ended', 'An administrator ended one of your advisor assignments.' . ($reason ? ' Reason: ' . $reason : ''), 'advisor_assignment', $id);
        if ($assignment['student_user_id']) {
            create_notification($pdo, (int)$assignment['student_user_id'], 'advisor_assignment', 'Advisor Assignment Ended', 'Your advisor assignment with ' . $assignment['faculty_name'] . ' was ended by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'advisor_assignment', $id);
        }
        if ($assignment['team_id']) {
            $members = $pdo->prepare("SELECT user_id FROM team_members WHERE team_id = ? AND status='Active'");
            $members->execute([$assignment['team_id']]);
            foreach ($members->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
                create_notification($pdo, (int)$memberId, 'advisor_assignment', 'Advisor Assignment Ended', 'Your team\'s advisor assignment with ' . $assignment['faculty_name'] . ' was ended by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'team', (int)$assignment['team_id']);
            }
        }

        log_activity($pdo, $adminId, 'admin_advisor_assignment_end', "Ended advisor assignment #{$id}" . ($reason ? " — {$reason}" : ''), 'advisor_assignment', $id);
        $pdo->commit();
        flash('success', 'Assignment ended.');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        error_log('admin advisor-assignments end: ' . $ex->getMessage());
        flash('error', 'Could not end the assignment. Please try again.');
    }
    redirect('/admin/advisor-assignments.php');
}

$statusFilter = (string)($_GET['status'] ?? 'active');
$where = ['1=1']; $params = [];
if (in_array($statusFilter, ['active', 'completed', 'cancelled'], true)) { $where[] = 'aa.status = ?'; $params[] = $statusFilter; }

$stmt = $pdo->prepare(
    "SELECT aa.*, f.name AS faculty_name, s.name AS student_name, rt.name AS team_name
     FROM advisor_assignments aa
     JOIN users f ON f.id = aa.faculty_user_id
     LEFT JOIN users s ON s.id = aa.student_user_id
     LEFT JOIN research_teams rt ON rt.id = aa.team_id
     WHERE " . implode(' AND ', $where) . " ORDER BY aa.assigned_at DESC LIMIT 60"
);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Faculty capacity flags
$capacityRows = $pdo->query(
    "SELECT u.id, u.name, fp.id AS fp_id, pr.max_active_mentees,
            (SELECT COUNT(*) FROM advisor_assignments a2 WHERE a2.faculty_user_id = u.id AND a2.status='active') AS active_count
     FROM users u
     JOIN faculty_profiles fp ON fp.user_id = u.id
     LEFT JOIN faculty_preferences pr ON pr.faculty_profile_id = fp.id
     WHERE u.role = 'faculty'"
)->fetchAll();
$overloaded = array_filter($capacityRows, fn($r) => $r['max_active_mentees'] !== null && (int)$r['active_count'] > (int)$r['max_active_mentees']);

$pageTitle = 'Advisor Assignments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisor Assignments || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Advisor Assignments</h2>

        <?php if ($overloaded): ?>
        <div class="app-panel" style="border-color:#ffc107;">
            <h3 style="font-size:15px;color:#b35a00;font-weight:700;"><i class="bi bi-exclamation-triangle-fill"></i> Faculty Over Capacity</h3>
            <?php foreach ($overloaded as $o): ?>
                <p class="mb-1"><a href="<?= e(url('/admin/user-details.php?id=' . $o['id'])) ?>"><?= e($o['name']) ?></a>: <?= (int)$o['active_count'] ?> active / <?= (int)$o['max_active_mentees'] ?> capacity</p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="get" class="filter-bar">
            <select name="status" class="form-select" style="max-width:180px;"><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option><option value="completed" <?= $statusFilter==='completed'?'selected':'' ?>>Completed</option><option value="cancelled" <?= $statusFilter==='cancelled'?'selected':'' ?>>Cancelled</option></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$assignments): ?><div class="app-panel app-empty-state"><i class="bi bi-mortarboard"></i><p>No assignments found.</p></div><?php endif; ?>
        <?php foreach ($assignments as $a): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><?= e($a['faculty_name']) ?> ↔ <?= $a['team_name'] ? e($a['team_name']) . ' (Team)' : e($a['student_name']) ?></div>
                    <div class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $a['assignment_type']))) ?> &middot; Since <?= format_date($a['assigned_at']) ?></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-<?= $a['status']==='active'?'success':'secondary' ?>-subtle text-<?= $a['status']==='active'?'success':'secondary' ?>-emphasis"><?= e(ucfirst($a['status'])) ?></span>
                    <?php if ($a['status'] === 'active'): ?>
                    <form method="post" onsubmit="return confirm('End this advisor assignment?');">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <input type="text" name="reason" class="form-control form-control-sm d-inline-block" style="width:140px;" placeholder="Reason">
                        <button class="btn btn-sm btn-outline-danger">End</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
