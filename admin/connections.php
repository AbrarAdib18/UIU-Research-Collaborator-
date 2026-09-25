<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/connections.php');
    $id     = validate_id($_POST['id'] ?? null);
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM research_connections WHERE id = ?');
    $stmt->execute([$id]);
    $conn = $id ? $stmt->fetch() : null;

    if (!$conn) {
        flash('error', 'Connection not found.');
    } else {
        $pdo->prepare("UPDATE research_connections SET status='blocked', responded_at=NOW() WHERE id=?")->execute([$id]);
        log_activity($pdo, $adminId, 'admin_connection_block', "Blocked connection #{$id}" . ($reason ? " — {$reason}" : ''), 'connection', $id);
        foreach ([$conn['requester_id'], $conn['recipient_id']] as $uid) {
            create_notification($pdo, (int)$uid, 'connection_request', 'Connection Blocked', 'A connection involving your account was blocked by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'connection_request', null);
        }
        flash('success', 'Connection blocked.');
    }
    redirect('/admin/connections.php');
}

$statusFilter = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
$validStatuses = ['pending','accepted','declined','cancelled','blocked'];
if (in_array($statusFilter, $validStatuses, true)) { $where[] = 'rc.status = ?'; $params[] = $statusFilter; }
if ($q !== '') { $where[] = '(req.name LIKE ? OR rec.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare(
    "SELECT rc.*, req.name AS requester_name, req.role AS requester_role, rec.name AS recipient_name, rec.role AS recipient_role
     FROM research_connections rc JOIN users req ON req.id = rc.requester_id JOIN users rec ON rec.id = rc.recipient_id
     WHERE " . implode(' AND ', $where) . " ORDER BY rc.created_at DESC LIMIT 60"
);
$stmt->execute($params);
$connections = $stmt->fetchAll();

function conn_pill(string $status): string
{
    return match ($status) { 'accepted' => 'pill pill-green', 'pending' => 'pill pill-orange', 'declined','blocked' => 'pill pill-red', default => 'pill pill-gray' };
}
$pageTitle = 'Connections';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connections || UIU ResearchCollab</title>
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
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Connections (<?= count($connections) ?>)</h2>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:220px;" placeholder="Search participants...">
            <select name="status" class="form-select" style="max-width:180px;"><option value="">All Statuses</option><?php foreach ($validStatuses as $s): ?><option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$connections): ?><div class="app-panel app-empty-state"><i class="bi bi-link-45deg"></i><p>No connections found.</p></div><?php endif; ?>
        <?php foreach ($connections as $c): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><?= e($c['requester_name']) ?> (<?= e(ucfirst($c['requester_role'])) ?>) ↔ <?= e($c['recipient_name']) ?> (<?= e(ucfirst($c['recipient_role'])) ?>)</div>
                    <div class="text-muted small"><?= e(time_ago($c['created_at'])) ?></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="<?= conn_pill($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span>
                    <?php if ($c['status'] !== 'blocked'): ?>
                    <form method="post" onsubmit="return confirm('Block this connection?');">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <input type="text" name="reason" class="form-control form-control-sm d-inline-block" style="width:140px;" placeholder="Reason">
                        <button class="btn btn-sm btn-outline-danger">Block</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
