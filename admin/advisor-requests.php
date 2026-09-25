<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/advisor-requests.php');
    $id     = validate_id($_POST['id'] ?? null);
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM advisor_requests WHERE id = ?');
    $stmt->execute([$id]);
    $req = $id ? $stmt->fetch() : null;

    if (!$req) {
        flash('error', 'Request not found.');
    } elseif (!in_array($req['status'], ['pending', 'clarification_requested'], true)) {
        flash('error', 'Only pending requests can be marked invalid.');
    } else {
        $pdo->prepare("UPDATE advisor_requests SET status='cancelled', faculty_response=? WHERE id=?")
            ->execute([($req['faculty_response'] ? $req['faculty_response'] . ' | ' : '') . 'Marked invalid by admin' . ($reason ? ": {$reason}" : ''), $id]);
        log_activity($pdo, $adminId, 'admin_advisor_request_invalidate', "Marked advisor request #{$id} invalid" . ($reason ? " — {$reason}" : ''), 'advisor_request', $id);
        create_notification($pdo, (int)$req['requested_by_user_id'], 'advisor_request', 'Advisor Request Marked Invalid', 'Your advisor request was marked invalid by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'advisor_request', $id);
        flash('success', 'Request marked invalid.');
    }
    redirect('/admin/advisor-requests.php');
}

$statusFilter = (string)($_GET['status'] ?? '');
$typeFilter = (string)($_GET['type'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
$validStatuses = ['pending','accepted','declined','clarification_requested','cancelled','completed'];
if (in_array($statusFilter, $validStatuses, true)) { $where[] = 'ar.status = ?'; $params[] = $statusFilter; }
if (in_array($typeFilter, ['student', 'team'], true)) { $where[] = 'ar.requester_type = ?'; $params[] = $typeFilter; }
if ($q !== '') { $where[] = '(u.name LIKE ? OR f.name LIKE ? OR rt.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare(
    "SELECT ar.*, u.name AS requester_name, f.name AS faculty_name, rt.name AS team_name
     FROM advisor_requests ar
     JOIN users u ON u.id = ar.requested_by_user_id
     JOIN users f ON f.id = ar.faculty_user_id
     LEFT JOIN research_teams rt ON rt.id = ar.team_id
     WHERE " . implode(' AND ', $where) . " ORDER BY ar.created_at DESC LIMIT 60"
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

function ar_pill(string $status): string
{
    return match ($status) { 'pending','clarification_requested' => 'pill pill-orange', 'accepted','completed' => 'pill pill-green', 'declined','cancelled' => 'pill pill-red', default => 'pill pill-gray' };
}
$pageTitle = 'Advisor Requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisor Requests || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
        .pill-green{background:#d6f5e0;color:#0f8a3c} .pill-orange{background:#ffe8cc;color:#b35a00} .pill-red{background:#fde0e0;color:#c62828} .pill-gray{background:#e9e9e9;color:#555}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Advisor Requests (<?= count($requests) ?>)</h2>
        <p class="text-muted small">Platform-wide oversight. Accept/decline decisions remain faculty-owned — admin can only mark a request invalid.</p>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:220px;" placeholder="Search requester/faculty/team...">
            <select name="type" class="form-select" style="max-width:150px;"><option value="">Individual & Team</option><option value="student" <?= $typeFilter === 'student' ? 'selected' : '' ?>>Individual</option><option value="team" <?= $typeFilter === 'team' ? 'selected' : '' ?>>Team</option></select>
            <select name="status" class="form-select" style="max-width:200px;"><option value="">All Statuses</option><?php foreach ($validStatuses as $s): ?><option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$requests): ?><div class="app-panel app-empty-state"><i class="bi bi-person-check"></i><p>No advisor requests found.</p></div><?php endif; ?>
        <?php foreach ($requests as $r): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><?= $r['requester_type'] === 'team' ? e($r['team_name']) . ' (Team)' : e($r['requester_name']) ?> → <?= e($r['faculty_name']) ?></div>
                    <div class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $r['request_type']))) ?> &middot; <?= e(time_ago($r['created_at'])) ?></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="<?= ar_pill($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
                    <?php if (in_array($r['status'], ['pending', 'clarification_requested'], true)): ?>
                    <form method="post" onsubmit="return confirm('Mark this request invalid?');">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="text" name="reason" class="form-control form-control-sm d-inline-block" style="width:140px;" placeholder="Reason">
                        <button class="btn btn-sm btn-outline-danger">Mark Invalid</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
