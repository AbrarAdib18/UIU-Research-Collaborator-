<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['Pending', 'Shortlisted', 'Accepted', 'Rejected', 'Withdrawn'], true)) {
    $statusFilter = '';
}
$q = trim((string)($_GET['q'] ?? ''));

$where  = ['o.created_by = ?'];
$params = [$userId];
if ($statusFilter !== '') {
    $where[] = 'oa.status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR o.title LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$perPage = 15;
$page    = current_page();

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM opportunity_applications oa
     JOIN research_opportunities o ON o.id = oa.opportunity_id
     JOIN users u ON u.id = oa.user_id
     WHERE " . implode(' AND ', $where)
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT oa.*, o.title AS opportunity_title, u.name AS applicant_name, sp.department, sp.profile_photo
     FROM opportunity_applications oa
     JOIN research_opportunities o ON o.id = oa.opportunity_id
     JOIN users u ON u.id = oa.user_id
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY oa.applied_at DESC LIMIT $perPage OFFSET " . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$applications = $stmt->fetchAll();

function fac_app_status_badge(string $status): string
{
    return match ($status) {
        'Accepted'    => 'bg-success-subtle text-success-emphasis',
        'Shortlisted' => 'bg-info-subtle text-info-emphasis',
        'Rejected'    => 'bg-danger-subtle text-danger-emphasis',
        'Withdrawn'   => 'bg-secondary-subtle text-secondary-emphasis',
        default       => 'bg-warning-subtle text-warning-emphasis',
    };
}

$pageTitle = 'Applications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .avatar-sm{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:13px;font-weight:700;flex-shrink:0;object-fit:cover}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <div class="app-page-header"><div><h2>Applications</h2><p class="text-muted mb-0">Review students who applied to your opportunities.</p></div></div>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:260px;" placeholder="Search applicant or opportunity...">
            <select name="status" class="form-select" style="max-width:180px;">
                <option value="">All Statuses</option>
                <?php foreach (['Pending', 'Shortlisted', 'Accepted', 'Rejected', 'Withdrawn'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$applications): ?>
            <div class="app-panel app-empty-state"><i class="bi bi-inbox"></i><p>No applications found.</p></div>
        <?php endif; ?>

        <?php foreach ($applications as $a): ?>
            <div class="app-panel d-flex align-items-center gap-3">
                <?php if (!empty($a['profile_photo'])): ?>
                    <img class="avatar-sm" src="<?= e(url('/uploads/avatars/' . $a['profile_photo'])) ?>" alt="<?= e($a['applicant_name']) ?>">
                <?php else: ?>
                    <span class="avatar-sm"><?= e(initials($a['applicant_name'])) ?></span>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= e($a['applicant_name']) ?></div>
                    <div class="text-muted small"><?= e($a['opportunity_title']) ?> &middot; <?= e($a['department'] ?: 'Student') ?> &middot; <?= e(time_ago($a['applied_at'])) ?></div>
                </div>
                <span class="badge rounded-pill <?= fac_app_status_badge($a['status']) ?>"><?= e($a['status']) ?></span>
                <a href="<?= e(url('/faculty/application-details.php?id=' . $a['id'])) ?>" class="btn btn-sm btn-outline-primary">Review</a>
            </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="app-pagination" aria-label="Applications pagination">
                <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p === $page): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= e(page_url($p)) ?>"><?= $p ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
