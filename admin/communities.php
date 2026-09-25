<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/communities.php');
    $id     = validate_id($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM communities WHERE id = ?');
    $stmt->execute([$id]);
    $community = $id ? $stmt->fetch() : null;

    if (!$community) {
        flash('error', 'Community not found.');
        redirect('/admin/communities.php');
    }

    if ($action === 'suspend') {
        $pdo->prepare("UPDATE communities SET status='Inactive' WHERE id=?")->execute([$id]);
        log_activity($pdo, $adminId, 'admin_community_suspend', 'Suspended community "' . $community['name'] . '"' . ($reason ? " — {$reason}" : ''), 'community', $id);
        create_notification($pdo, (int)$community['created_by'], 'community', 'Community Suspended', 'Your community "' . $community['name'] . '" was suspended by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'community', $id);
        flash('success', 'Community suspended.');
    } elseif ($action === 'restore') {
        $pdo->prepare("UPDATE communities SET status='Active' WHERE id=?")->execute([$id]);
        log_activity($pdo, $adminId, 'admin_community_restore', 'Restored community "' . $community['name'] . '"', 'community', $id);
        flash('success', 'Community restored.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM communities WHERE id = ?')->execute([$id]);
        log_activity($pdo, $adminId, 'admin_community_delete', 'Deleted community "' . $community['name'] . '"' . ($reason ? " — {$reason}" : ''), 'community', $id);
        flash('success', 'Community deleted.');
    }
    redirect('/admin/communities.php');
}

$privacyFilter = (string)($_GET['privacy'] ?? '');
$statusFilter  = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if (in_array($privacyFilter, ['Public', 'Private'], true)) { $where[] = 'c.privacy = ?'; $params[] = $privacyFilter; }
if (in_array($statusFilter, ['Active', 'Inactive'], true)) { $where[] = 'c.status = ?'; $params[] = $statusFilter; }
if ($q !== '') { $where[] = '(c.name LIKE ? OR u.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare(
    "SELECT c.*, u.name AS creator_name, d.name AS domain_name,
            (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id) AS member_count,
            (SELECT COUNT(*) FROM community_posts cp WHERE cp.community_id = c.id) AS post_count
     FROM communities c JOIN users u ON u.id = c.created_by LEFT JOIN research_domains d ON d.id = c.domain_id
     WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC"
);
$stmt->execute($params);
$communities = $stmt->fetchAll();

$pageTitle = 'Communities';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communities || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .type-tabs{display:flex;gap:8px;margin-bottom:16px}
        .type-tabs a{padding:8px 18px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none}
        .type-tabs a.active{background:var(--uiu-blue);color:#fff}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
        .pill-green{background:#d6f5e0;color:#0f8a3c} .pill-gray{background:#e9e9e9;color:#555} .pill-orange{background:#ffe8cc;color:#b35a00}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Communities (<?= count($communities) ?>)</h2>
        <div class="type-tabs">
            <a href="<?= e(url('/admin/communities.php')) ?>" class="active">All Communities</a>
            <a href="<?= e(url('/admin/community-moderation.php')) ?>">Moderation Queue</a>
        </div>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:220px;" placeholder="Search community/creator...">
            <select name="privacy" class="form-select" style="max-width:150px;"><option value="">Any Privacy</option><option value="Public" <?= $privacyFilter === 'Public' ? 'selected' : '' ?>>Public</option><option value="Private" <?= $privacyFilter === 'Private' ? 'selected' : '' ?>>Private</option></select>
            <select name="status" class="form-select" style="max-width:150px;"><option value="">Any Status</option><option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$communities): ?><div class="app-panel app-empty-state"><i class="bi bi-diagram-3"></i><p>No communities found.</p></div><?php endif; ?>
        <?php foreach ($communities as $c): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><a href="<?= e(url('/admin/community-details.php?id=' . $c['id'])) ?>" class="text-decoration-none"><?= e($c['name']) ?></a>
                        <span class="pill <?= $c['status'] === 'Active' ? 'pill-green' : 'pill-gray' ?>"><?= e($c['status']) ?></span>
                        <span class="pill <?= $c['privacy'] === 'Public' ? 'pill-green' : 'pill-orange' ?>"><?= e($c['privacy']) ?></span>
                    </div>
                    <div class="text-muted small">by <?= e($c['creator_name']) ?> &middot; <?= (int)$c['member_count'] ?> members &middot; <?= (int)$c['post_count'] ?> posts &middot; <?= e($c['domain_name'] ?: 'General') ?></div>
                </div>
                <a href="<?= e(url('/admin/community-details.php?id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary">Review</a>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
