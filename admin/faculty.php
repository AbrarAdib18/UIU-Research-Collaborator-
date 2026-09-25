<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));

$where = ["u.role = 'faculty'"];
$params = [];
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR fp.department LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$perPage = 20;
$page = current_page();
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN faculty_profiles fp ON fp.user_id = u.id WHERE " . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.status, fp.id AS fp_id, fp.department, fp.designation,
            fv.status AS verification_status,
            (SELECT COUNT(*) FROM advisor_assignments aa WHERE aa.faculty_user_id = u.id AND aa.status='active') AS active_mentees
     FROM users u
     LEFT JOIN faculty_profiles fp ON fp.user_id = u.id
     LEFT JOIN faculty_verifications fv ON fv.faculty_user_id = u.id
     WHERE " . implode(' AND ', $where) . " ORDER BY u.created_at DESC LIMIT $perPage OFFSET " . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$faculty = $stmt->fetchAll();

function fv_pill(?string $status): string
{
    return match ($status) {
        'verified'     => 'pill pill-green',
        'rejected'     => 'pill pill-red',
        'needs_update' => 'pill pill-orange',
        'pending'      => 'pill pill-orange',
        default        => 'pill pill-gray',
    };
}

$pageTitle = 'Faculty Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:14px}
        .table thead th{color:var(--uiu-blue);font-size:12px;text-transform:uppercase;border-bottom:2px solid var(--border-color)}
        .table td{vertical-align:middle;font-size:13px}
        .table-responsive-wrap{overflow-x:auto}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-orange{background:#ffe8cc;color:#b35a00}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Faculty Management (<?= $total ?>)</h2>
        <form method="get" class="mb-3"><input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:280px;" placeholder="Search faculty..."></form>

        <div class="app-panel">
            <?php if (!$faculty): ?>
                <div class="app-empty-state"><i class="bi bi-person-workspace"></i><p>No faculty found.</p></div>
            <?php else: ?>
            <div class="table-responsive-wrap">
            <table class="table table-hover">
                <thead><tr><th>Name</th><th>Department</th><th>Designation</th><th>Active Mentees</th><th>Verification</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($faculty as $f): ?>
                    <tr>
                        <td><div class="fw-semibold"><?= e($f['name']) ?></div><div class="text-muted small"><?= e($f['email']) ?></div></td>
                        <td><?= e($f['department'] ?: '—') ?></td>
                        <td><?= e($f['designation'] ?: '—') ?></td>
                        <td><?= (int)$f['active_mentees'] ?></td>
                        <td><span class="<?= fv_pill($f['verification_status']) ?>"><?= e($f['verification_status'] ? str_replace('_', ' ', $f['verification_status']) : 'no record') ?></span></td>
                        <td><a href="<?= e(url('/admin/user-details.php?id=' . $f['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="app-pagination" aria-label="Faculty pagination">
                <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?><?php if ($p === $page): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= e(page_url($p)) ?>"><?= $p ?></a><?php endif; ?><?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
