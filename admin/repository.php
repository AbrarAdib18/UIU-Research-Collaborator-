<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

$typeFilter = (string)($_GET['type'] ?? '');
$statusFilter = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if ($typeFilter !== '') { $where[] = 'r.resource_type = ?'; $params[] = $typeFilter; }
if (in_array($statusFilter, ['Published', 'Draft'], true)) { $where[] = 'r.status = ?'; $params[] = $statusFilter; }
if ($q !== '') { $where[] = '(r.title LIKE ? OR u.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare(
    "SELECT r.*, u.name AS uploader_name, d.name AS domain_name, (SELECT COUNT(*) FROM saved_resources sr WHERE sr.resource_id = r.id) AS save_count
     FROM research_resources r JOIN users u ON u.id = r.uploaded_by LEFT JOIN research_domains d ON d.id = r.domain_id
     WHERE " . implode(' AND ', $where) . " ORDER BY r.created_at DESC LIMIT 60"
);
$stmt->execute($params);
$resources = $stmt->fetchAll();

$types = $pdo->query('SELECT DISTINCT resource_type FROM research_resources ORDER BY resource_type')->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = 'Repository Resources';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repository Resources || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
        .pill-green{background:#d6f5e0;color:#0f8a3c} .pill-gray{background:#e9e9e9;color:#555}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Repository Resources (<?= count($resources) ?>)</h2>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:220px;" placeholder="Search title/uploader...">
            <select name="type" class="form-select" style="max-width:180px;"><option value="">All Types</option><?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
            <select name="status" class="form-select" style="max-width:150px;"><option value="">All Statuses</option><option value="Published" <?= $statusFilter === 'Published' ? 'selected' : '' ?>>Published</option><option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft (Hidden)</option></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$resources): ?><div class="app-panel app-empty-state"><i class="bi bi-database"></i><p>No resources found.</p></div><?php endif; ?>
        <?php foreach ($resources as $r): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><a href="<?= e(url('/admin/resource-details.php?id=' . $r['id'])) ?>" class="text-decoration-none"><?= e($r['title']) ?></a> <span class="pill <?= $r['status'] === 'Published' ? 'pill-green' : 'pill-gray' ?>"><?= e($r['status']) ?></span></div>
                    <div class="text-muted small"><?= e($r['resource_type']) ?> &middot; by <?= e($r['uploader_name']) ?> &middot; <?= (int)$r['save_count'] ?> save(s) &middot; <?= e($r['domain_name'] ?: 'General') ?></div>
                </div>
                <a href="<?= e(url('/admin/resource-details.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary">Review</a>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
