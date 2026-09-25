<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['pending', 'accepted', 'declined', 'clarification_requested', 'cancelled', 'completed'], true)) {
    $statusFilter = '';
}
$typeFilter = (string)($_GET['type'] ?? '');
if (!in_array($typeFilter, ['student', 'team'], true)) {
    $typeFilter = '';
}
$q = trim((string)($_GET['q'] ?? ''));

$where  = ['ar.faculty_user_id = ?'];
$params = [$userId];
if ($statusFilter !== '') {
    $where[] = 'ar.status = ?';
    $params[] = $statusFilter;
}
if ($typeFilter !== '') {
    $where[] = 'ar.requester_type = ?';
    $params[] = $typeFilter;
}
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR rt.name LIKE ? OR ar.title LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$perPage = 15;
$page    = current_page();

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM advisor_requests ar
     JOIN users u ON u.id = ar.requested_by_user_id
     LEFT JOIN research_teams rt ON rt.id = ar.team_id
     WHERE " . implode(' AND ', $where)
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT ar.*, u.name AS requester_name, rt.name AS team_name
     FROM advisor_requests ar
     JOIN users u ON u.id = ar.requested_by_user_id
     LEFT JOIN research_teams rt ON rt.id = ar.team_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY FIELD(ar.status,'pending','clarification_requested','accepted','declined','cancelled','completed'), ar.created_at DESC
     LIMIT $perPage OFFSET " . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

function mr_status_pill(string $status): string
{
    return match ($status) {
        'pending', 'clarification_requested' => 'pill pill-orange',
        'accepted', 'completed'              => 'pill pill-green',
        'declined', 'cancelled'              => 'pill pill-red',
        default                              => 'pill pill-gray',
    };
}

$pageTitle = 'Mentor / Advisor Requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor / Advisor Requests || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-orange{background:#ffe8cc;color:#b35a00}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Mentor / Advisor Requests</h2>
        <p class="text-muted">Requests from students and teams asking you to be their advisor.</p>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:240px;" placeholder="Search requester...">
            <select name="type" class="form-select" style="max-width:160px;">
                <option value="">Individual & Team</option>
                <option value="student" <?= $typeFilter === 'student' ? 'selected' : '' ?>>Individual</option>
                <option value="team" <?= $typeFilter === 'team' ? 'selected' : '' ?>>Team</option>
            </select>
            <select name="status" class="form-select" style="max-width:200px;">
                <option value="">All Statuses</option>
                <?php foreach (['pending' => 'Pending', 'clarification_requested' => 'Clarification Requested', 'accepted' => 'Accepted', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$requests): ?>
            <div class="app-panel app-empty-state"><i class="bi bi-person-check"></i><p>No mentorship requests found.</p></div>
        <?php endif; ?>

        <?php foreach ($requests as $r): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">
                        <?= $r['requester_type'] === 'team' ? '<i class="bi bi-people-fill"></i> ' . e($r['team_name']) : '<i class="bi bi-person-fill"></i> ' . e($r['requester_name']) ?>
                        <span class="pill pill-gray"><?= e(ucwords(str_replace('_', ' ', $r['request_type']))) ?></span>
                    </div>
                    <div class="text-muted small"><?= e($r['title'] ?: 'No title') ?> &middot; <?= e(time_ago($r['created_at'])) ?></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="<?= mr_status_pill($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
                    <a href="<?= e(url('/faculty/mentorship-request-details.php?id=' . $r['id'])) ?>" class="btn btn-sm btn-outline-primary">Review</a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="app-pagination" aria-label="Requests pagination">
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
