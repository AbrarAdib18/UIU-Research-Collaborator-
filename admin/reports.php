<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

/** Strips a leading =, +, -, or @ from a CSV cell to guard against formula-injection when opened in a spreadsheet app. */
function csv_safe_cell(?string $value): string
{
    $value = (string)$value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

// ---------------------------------------------------------------------
// CSV export (admin-only, guarded above)
// ---------------------------------------------------------------------
$exportType = (string)($_GET['export'] ?? '');
if ($exportType !== '') {
    $exportMap = [
        'users'          => ['sql' => "SELECT id, name, email, role, status, created_at, last_login_at FROM users ORDER BY created_at DESC", 'headers' => ['ID', 'Name', 'Email', 'Role', 'Status', 'Joined', 'Last Login']],
        'opportunities'  => ['sql' => "SELECT o.id, o.title, u.name AS creator, o.status, o.deadline, o.created_at FROM research_opportunities o JOIN users u ON u.id=o.created_by ORDER BY o.created_at DESC", 'headers' => ['ID', 'Title', 'Creator', 'Status', 'Deadline', 'Created']],
        'applications'   => ['sql' => "SELECT oa.id, u.name AS applicant, o.title AS opportunity, oa.status, oa.applied_at FROM opportunity_applications oa JOIN users u ON u.id=oa.user_id JOIN research_opportunities o ON o.id=oa.opportunity_id ORDER BY oa.applied_at DESC", 'headers' => ['ID', 'Applicant', 'Opportunity', 'Status', 'Applied At']],
        'activity_logs'  => ['sql' => "SELECT al.id, u.name AS user, al.activity_type, al.description, al.created_at FROM activity_logs al JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 2000", 'headers' => ['ID', 'User', 'Type', 'Description', 'When']],
    ];
    if (!array_key_exists($exportType, $exportMap)) {
        flash('error', 'Unknown export type.');
        redirect('/admin/reports.php');
    }
    $rows = $pdo->query($exportMap[$exportType]['sql'])->fetchAll();
    log_activity($pdo, (int)$currentUser['id'], 'admin_report_export', "Exported {$exportType} report as CSV");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $exportType . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $exportMap[$exportType]['headers']);
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_safe_cell', array_values($row)));
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------------
// Report data
// ---------------------------------------------------------------------
$dateFrom = (string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo   = (string)($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');

function bucketed(PDO $pdo, string $table, string $col): array
{
    return $pdo->query("SELECT $col AS k, COUNT(*) c FROM $table GROUP BY $col ORDER BY c DESC")->fetchAll();
}

$usersByRole = bucketed($pdo, 'users', 'role');
$usersByStatus = bucketed($pdo, 'users', 'status');
$newRegStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?");
$newRegStmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
$newRegistrations = (int)$newRegStmt->fetchColumn();

$facultyVerificationStats = bucketed($pdo, 'faculty_verifications', 'status');
$opportunitiesByStatus = bucketed($pdo, 'research_opportunities', 'status');
$applicationsByStatus = bucketed($pdo, 'opportunity_applications', 'status');
$teamsByStatus = bucketed($pdo, 'research_teams', 'status');
$communitiesByPrivacy = bucketed($pdo, 'communities', 'privacy');
$communitiesByStatus = bucketed($pdo, 'communities', 'status');
$resourcesByType = bucketed($pdo, 'research_resources', 'resource_type');
$resourcesByStatus = bucketed($pdo, 'research_resources', 'status');
$advisorRequestsByStatus = bucketed($pdo, 'advisor_requests', 'status');
$advisorAssignmentsByStatus = bucketed($pdo, 'advisor_assignments', 'status');
$connectionsByStatus = bucketed($pdo, 'research_connections', 'status');

function report_bar_rows(array $rows, int $total): void
{
    foreach ($rows as $r) {
        $pct = $total > 0 ? round(((int)$r['c'] / $total) * 100) : 0;
        echo '<div class="d-flex justify-content-between small mb-1"><span>' . e(ucfirst(str_replace('_', ' ', (string)$r['k']))) . '</span><strong>' . (int)$r['c'] . '</strong></div>';
        echo '<div class="completion-progress mb-2"><div class="completion-progress-bar" style="width:' . $pct . '%;"></div></div>';
    }
}

$pageTitle = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>.app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}.app-panel h3{color:var(--uiu-blue);font-size:15px;font-weight:700;margin:0 0 12px}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Reports & Analytics</h2>

        <form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3">
            <div><label class="form-label small mb-0">From</label><input type="date" name="from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm"></div>
            <div><label class="form-label small mb-0">To</label><input type="date" name="to" value="<?= e($dateTo) ?>" class="form-control form-control-sm"></div>
            <button class="btn btn-sm btn-outline-primary">Apply</button>
        </form>

        <div class="app-panel">
            <h3>New Registrations (<?= e($dateFrom) ?> to <?= e($dateTo) ?>)</h3>
            <div class="mini-stat" style="background:#f5f8fc;border-radius:10px;padding:14px;display:inline-block;"><strong style="font-size:26px;color:var(--uiu-blue);"><?= $newRegistrations ?></strong> <span class="text-muted">new users</span></div>
        </div>

        <div class="app-panel">
            <h3>CSV Export</h3>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/admin/reports.php?export=users')) ?>"><i class="bi bi-download"></i> Users</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/admin/reports.php?export=opportunities')) ?>"><i class="bi bi-download"></i> Opportunities</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/admin/reports.php?export=applications')) ?>"><i class="bi bi-download"></i> Applications</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/admin/reports.php?export=activity_logs')) ?>"><i class="bi bi-download"></i> Activity Logs</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="app-panel"><h3>Users by Role</h3><?php report_bar_rows($usersByRole, array_sum(array_column($usersByRole, 'c'))); ?></div>
                <div class="app-panel"><h3>Users by Status</h3><?php report_bar_rows($usersByStatus, array_sum(array_column($usersByStatus, 'c'))); ?></div>
                <div class="app-panel"><h3>Faculty Verification</h3><?php report_bar_rows($facultyVerificationStats, array_sum(array_column($facultyVerificationStats, 'c'))); ?></div>
                <div class="app-panel"><h3>Opportunities by Status</h3><?php report_bar_rows($opportunitiesByStatus, array_sum(array_column($opportunitiesByStatus, 'c'))); ?></div>
                <div class="app-panel"><h3>Applications by Status</h3><?php report_bar_rows($applicationsByStatus, array_sum(array_column($applicationsByStatus, 'c'))); ?></div>
                <div class="app-panel"><h3>Teams by Status</h3><?php report_bar_rows($teamsByStatus, array_sum(array_column($teamsByStatus, 'c'))); ?></div>
            </div>
            <div class="col-md-6">
                <div class="app-panel"><h3>Communities by Privacy</h3><?php report_bar_rows($communitiesByPrivacy, array_sum(array_column($communitiesByPrivacy, 'c'))); ?></div>
                <div class="app-panel"><h3>Communities by Status</h3><?php report_bar_rows($communitiesByStatus, array_sum(array_column($communitiesByStatus, 'c'))); ?></div>
                <div class="app-panel"><h3>Repository Resources by Type</h3><?php report_bar_rows($resourcesByType, array_sum(array_column($resourcesByType, 'c'))); ?></div>
                <div class="app-panel"><h3>Repository Resources by Status</h3><?php report_bar_rows($resourcesByStatus, array_sum(array_column($resourcesByStatus, 'c'))); ?></div>
                <div class="app-panel"><h3>Advisor Requests by Status</h3><?php report_bar_rows($advisorRequestsByStatus, array_sum(array_column($advisorRequestsByStatus, 'c'))); ?></div>
                <div class="app-panel"><h3>Advisor Assignments by Status</h3><?php report_bar_rows($advisorAssignmentsByStatus, array_sum(array_column($advisorAssignmentsByStatus, 'c'))); ?></div>
                <div class="app-panel"><h3>Connections by Status</h3><?php report_bar_rows($connectionsByStatus, array_sum(array_column($connectionsByStatus, 'c'))); ?></div>
            </div>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
