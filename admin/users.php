<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$q       = trim((string)($_GET['q'] ?? ''));
$role    = (string)($_GET['role'] ?? '');
$status  = (string)($_GET['status'] ?? '');
$sort    = (string)($_GET['sort'] ?? 'newest');
if (!in_array($role, ['student', 'faculty', 'admin', ''], true)) $role = '';
if (!in_array($status, ['active', 'inactive', 'suspended', ''], true)) $status = '';
if (!in_array($sort, ['newest', 'oldest', 'name', 'last_login'], true)) $sort = 'newest';

$where  = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) = ? OR sp.department LIKE ? OR fp.department LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $q; $params[] = $like; $params[] = $like;
}
if ($role !== '') { $where[] = 'u.role = ?'; $params[] = $role; }
if ($status !== '') { $where[] = 'u.status = ?'; $params[] = $status; }

$orderBy = match ($sort) {
    'oldest'     => 'u.created_at ASC',
    'name'       => 'u.name ASC',
    'last_login' => 'u.last_login_at IS NULL, u.last_login_at DESC',
    default      => 'u.created_at DESC',
};

$perPage = 20;
$page    = current_page();

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM users u LEFT JOIN student_profiles sp ON sp.user_id = u.id LEFT JOIN faculty_profiles fp ON fp.user_id = u.id WHERE " . implode(' AND ', $where)
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT u.*, sp.department AS student_department, fp.department AS faculty_department
     FROM users u LEFT JOIN student_profiles sp ON sp.user_id = u.id LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
     WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy LIMIT $perPage OFFSET " . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$users = $stmt->fetchAll();

function admin_status_pill(string $status): string
{
    return match ($status) {
        'active'    => 'pill pill-green',
        'inactive'  => 'pill pill-gray',
        'suspended' => 'pill pill-red',
        default     => 'pill pill-gray',
    };
}

$pageTitle = 'User Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:14px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .table thead th{color:var(--uiu-blue);font-size:12px;text-transform:uppercase;border-bottom:2px solid var(--border-color)}
        .table td{vertical-align:middle;font-size:13px}
        .avatar-sm{width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:11px;font-weight:700;flex-shrink:0}
        .table-responsive-wrap{overflow-x:auto}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <div class="app-page-header"><h2>User Management</h2></div>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:260px;" placeholder="Search name, email, ID, department...">
            <select name="role" class="form-select" style="max-width:150px;">
                <option value="">All Roles</option>
                <?php foreach (['student', 'faculty', 'admin'] as $r): ?><option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
            </select>
            <select name="status" class="form-select" style="max-width:150px;">
                <option value="">All Statuses</option>
                <?php foreach (['active', 'inactive', 'suspended'] as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
            </select>
            <select name="sort" class="form-select" style="max-width:170px;">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                <option value="last_login" <?= $sort === 'last_login' ? 'selected' : '' ?>>Last Login</option>
            </select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <div class="app-panel">
            <?php if (!$users): ?>
                <div class="app-empty-state"><i class="bi bi-people"></i><p>No users found.</p></div>
            <?php else: ?>
            <div class="table-responsive-wrap">
            <table class="table table-hover">
                <thead><tr><th>User</th><th>Role</th><th>Department</th><th>Status</th><th>Last Login</th><th>Joined</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="d-flex align-items-center gap-2"><span class="avatar-sm"><?= e(initials($u['name'])) ?></span><div><div class="fw-semibold"><?= e($u['name']) ?></div><div class="text-muted small"><?= e($u['email']) ?></div></div></td>
                        <td><?= e(ucfirst($u['role'])) ?></td>
                        <td><?= e($u['student_department'] ?? $u['faculty_department'] ?? '—') ?></td>
                        <td><span class="<?= admin_status_pill($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                        <td><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) : '—' ?></td>
                        <td><?= format_date($u['created_at']) ?></td>
                        <td><a href="<?= e(url('/admin/user-details.php?id=' . $u['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="app-pagination" aria-label="Users pagination">
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
