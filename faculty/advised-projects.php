<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/advised-projects.php');
    $action = $_POST['action'] ?? '';
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);

    $own = $pdo->prepare('SELECT * FROM advisor_assignments WHERE id = ? AND faculty_user_id = ?');
    $own->execute([$assignmentId, $userId]);
    $assignment = $own->fetch();

    if (!$assignment) {
        flash('error', 'Assignment not found.');
    } elseif ($action === 'complete_assignment' && $assignment['status'] === 'active') {
        try {
            $pdo->prepare("UPDATE advisor_assignments SET status='completed', ended_at=NOW() WHERE id=?")->execute([$assignmentId]);
            if ($assignment['advisor_request_id']) {
                $pdo->prepare("UPDATE advisor_requests SET status='completed' WHERE id=?")->execute([$assignment['advisor_request_id']]);
            }
            if ($assignment['student_user_id']) {
                create_notification($pdo, (int)$assignment['student_user_id'], 'advisor_assignment', 'Advisor Relationship Completed', $currentUser['name'] . ' marked your advisor relationship as completed.', 'advisor_assignment', $assignmentId);
            }
            log_activity($pdo, $userId, 'advisor_assignment_completed', 'Completed an advisor assignment', 'advisor_assignment', $assignmentId);
            flash('success', 'Assignment marked as completed.');
        } catch (Throwable $ex) {
            error_log('advised-projects complete: ' . $ex->getMessage());
            flash('error', 'Could not update assignment.');
        }
    } elseif ($action === 'cancel_assignment' && $assignment['status'] === 'active') {
        try {
            $pdo->prepare("UPDATE advisor_assignments SET status='cancelled', ended_at=NOW() WHERE id=?")->execute([$assignmentId]);
            log_activity($pdo, $userId, 'advisor_assignment_cancelled', 'Cancelled an advisor assignment', 'advisor_assignment', $assignmentId);
            flash('success', 'Assignment cancelled.');
        } catch (Throwable $ex) {
            error_log('advised-projects cancel: ' . $ex->getMessage());
            flash('error', 'Could not update assignment.');
        }
    }
    redirect('/faculty/advised-projects.php');
}

$stmt = $pdo->prepare(
    "SELECT aa.*, u.name AS student_name, rt.name AS team_name, p.title AS project_title, o.title AS opportunity_title
     FROM advisor_assignments aa
     LEFT JOIN users u ON u.id = aa.student_user_id
     LEFT JOIN research_teams rt ON rt.id = aa.team_id
     LEFT JOIN projects p ON p.id = aa.project_id
     LEFT JOIN research_opportunities o ON o.id = aa.opportunity_id
     WHERE aa.faculty_user_id = ? AND aa.assignment_type IN ('paper_advisor','project_mentor','fydp_supervisor','research_advisor')
     ORDER BY aa.status = 'active' DESC, aa.assigned_at DESC"
);
$stmt->execute([$userId]);
$assignments = $stmt->fetchAll();

$pageTitle = 'Guided Projects & Papers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guided Projects & Papers || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px 18px;margin-bottom:12px}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Guided Projects & Papers</h2>
        <p class="text-muted">Individual and team advisory relationships around a specific project, paper, or FYDP.</p>

        <?php if (!$assignments): ?>
            <div class="app-panel app-empty-state"><i class="bi bi-kanban"></i><p>No guided projects or papers yet.</p></div>
        <?php endif; ?>

        <?php foreach ($assignments as $a): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">
                        <?= $a['project_title'] ? e($a['project_title']) : ($a['opportunity_title'] ? e($a['opportunity_title']) : e(ucwords(str_replace('_', ' ', $a['assignment_type'])))) ?>
                    </div>
                    <div class="text-muted small">
                        <?= $a['team_name'] ? 'Team: ' . e($a['team_name']) : 'Advisee: ' . e($a['student_name']) ?>
                        &middot; <?= e(ucwords(str_replace('_', ' ', $a['assignment_type']))) ?> &middot; Since <?= format_date($a['assigned_at']) ?>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-<?= $a['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $a['status'] === 'active' ? 'success' : 'secondary' ?>-emphasis"><?= e(ucfirst($a['status'])) ?></span>
                    <?php if ($a['status'] === 'active'): ?>
                        <form method="post" onsubmit="return confirm('Mark this assignment as completed?');">
                            <?= csrf_field() ?><input type="hidden" name="action" value="complete_assignment"><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-outline-dark">Complete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
