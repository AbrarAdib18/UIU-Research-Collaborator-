<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/applications.php');
    $id     = validate_id($_POST['id'] ?? null);
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT oa.*, o.title FROM opportunity_applications oa JOIN research_opportunities o ON o.id = oa.opportunity_id WHERE oa.id = ?');
    $stmt->execute([$id]);
    $app = $id ? $stmt->fetch() : null;

    if (!$app) {
        flash('error', 'Application not found.');
    } else {
        $pdo->prepare("UPDATE opportunity_applications SET status='Withdrawn' WHERE id=?")->execute([$id]);
        log_activity($pdo, $adminId, 'admin_application_invalidate', 'Marked application to "' . $app['title'] . '" as withdrawn' . ($reason ? " — {$reason}" : ''), 'opportunity_application', $id);
        create_notification($pdo, (int)$app['user_id'], 'opportunity_application', 'Application Withdrawn by Admin', 'Your application to "' . $app['title'] . '" was marked invalid/withdrawn by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'opportunity', null);
        flash('success', 'Application marked as withdrawn.');
    }
    redirect('/admin/applications.php');
}

$statusFilter = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if (in_array($statusFilter, ['Pending', 'Shortlisted', 'Accepted', 'Rejected', 'Withdrawn'], true)) { $where[] = 'oa.status = ?'; $params[] = $statusFilter; }
if ($q !== '') { $where[] = '(u.name LIKE ? OR o.title LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$perPage = 20; $page = current_page();
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM opportunity_applications oa JOIN research_opportunities o ON o.id=oa.opportunity_id JOIN users u ON u.id=oa.user_id WHERE " . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT oa.*, o.title AS opportunity_title, u.name AS applicant_name, creator.name AS faculty_name
     FROM opportunity_applications oa
     JOIN research_opportunities o ON o.id = oa.opportunity_id
     JOIN users u ON u.id = oa.user_id
     JOIN users creator ON creator.id = o.created_by
     WHERE " . implode(' AND ', $where) . " ORDER BY oa.applied_at DESC LIMIT $perPage OFFSET " . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$applications = $stmt->fetchAll();

function app_pill(string $status): string
{
    return match ($status) { 'Accepted' => 'pill pill-green', 'Shortlisted' => 'pill pill-blue', 'Rejected' => 'pill pill-red', 'Withdrawn' => 'pill pill-gray', default => 'pill pill-orange' };
}
$pageTitle = 'Applications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
        .pill-green{background:#d6f5e0;color:#0f8a3c} .pill-blue{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)} .pill-red{background:#fde0e0;color:#c62828} .pill-gray{background:#e9e9e9;color:#555} .pill-orange{background:#ffe8cc;color:#b35a00}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Applications (<?= $total ?>)</h2>
        <p class="text-muted small">Platform-wide oversight. Accept/Reject/Shortlist decisions remain faculty-owned — admin can only mark an application invalid/withdrawn.</p>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:240px;" placeholder="Search applicant/opportunity...">
            <select name="status" class="form-select" style="max-width:180px;"><option value="">All Statuses</option><?php foreach (['Pending','Shortlisted','Accepted','Rejected','Withdrawn'] as $s): ?><option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$applications): ?><div class="app-panel app-empty-state"><i class="bi bi-inbox"></i><p>No applications found.</p></div><?php endif; ?>
        <?php foreach ($applications as $a): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><?= e($a['applicant_name']) ?></div>
                    <div class="text-muted small"><?= e($a['opportunity_title']) ?> &middot; owned by <?= e($a['faculty_name']) ?> &middot; <?= e(time_ago($a['applied_at'])) ?></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="<?= app_pill($a['status']) ?>"><?= e($a['status']) ?></span>
                    <?php if ($a['status'] !== 'Withdrawn'): ?>
                    <form method="post" onsubmit="return confirm('Mark this application invalid/withdrawn?');">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <input type="text" name="reason" class="form-control form-control-sm d-inline-block" style="width:160px;" placeholder="Reason (optional)">
                        <button class="btn btn-sm btn-outline-danger">Mark Invalid</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($totalPages > 1): ?>
            <nav class="app-pagination" aria-label="pagination">
                <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?><?php if ($p === $page): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= e(page_url($p)) ?>"><?= $p ?></a><?php endif; ?><?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
