<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

$q          = trim((string)($_GET['q'] ?? ''));
$role       = (string)($_GET['role'] ?? '');
$type       = trim((string)($_GET['type'] ?? ''));
$relatedType = trim((string)($_GET['related_type'] ?? ''));
$dateFrom   = (string)($_GET['from'] ?? '');
$dateTo     = (string)($_GET['to'] ?? '');

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(u.name LIKE ? OR al.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if (in_array($role, ['student', 'faculty', 'admin'], true)) { $where[] = 'u.role = ?'; $params[] = $role; }
if ($type !== '') { $where[] = 'al.activity_type = ?'; $params[] = $type; }
if ($relatedType !== '') { $where[] = 'al.related_type = ?'; $params[] = $relatedType; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'al.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'al.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }

$perPage = 30; $page = current_page();
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al JOIN users u ON u.id = al.user_id WHERE " . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT al.*, u.name AS user_name, u.role AS user_role
     FROM activity_logs al JOIN users u ON u.id = al.user_id
     WHERE " . implode(' AND ', $where) . " ORDER BY al.created_at DESC LIMIT $perPage OFFSET " . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$activityTypes = $pdo->query('SELECT DISTINCT activity_type FROM activity_logs WHERE activity_type IS NOT NULL ORDER BY activity_type')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Activity Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .table thead th{color:var(--uiu-blue);font-size:12px;text-transform:uppercase;border-bottom:2px solid var(--border-color)}
        .table td{vertical-align:middle;font-size:13px}
        .table-responsive-wrap{overflow-x:auto}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Activity Logs (<?= $total ?>)</h2>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search user/description...">
            <select name="role" class="form-select form-select-sm" style="max-width:130px;"><option value="">All Roles</option><?php foreach (['student','faculty','admin'] as $r): ?><option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select>
            <select name="type" class="form-select form-select-sm" style="max-width:200px;"><option value="">All Activity Types</option><?php foreach ($activityTypes as $t): ?><option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
            <input type="date" name="from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm" style="max-width:150px;">
            <input type="date" name="to" value="<?= e($dateTo) ?>" class="form-control form-control-sm" style="max-width:150px;">
            <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
        </form>

        <div class="app-panel">
            <?php if (!$logs): ?>
                <div class="app-empty-state"><i class="bi bi-clock-history"></i><p>No activity found.</p></div>
            <?php else: ?>
            <div class="table-responsive-wrap">
            <table class="table table-hover">
                <thead><tr><th>User</th><th>Role</th><th>Activity</th><th>Description</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= e($l['user_name']) ?></td>
                        <td><?= e(ucfirst($l['user_role'])) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= e($l['activity_type'] ?: '—') ?></span></td>
                        <td><?= e($l['description']) ?></td>
                        <td class="text-muted"><?= e(time_ago($l['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
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
