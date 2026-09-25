<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/teams.php');
    $id     = validate_id($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
    $stmt->execute([$id]);
    $team = $id ? $stmt->fetch() : null;

    if (!$team) {
        flash('error', 'Team not found.');
        redirect('/admin/teams.php');
    }

    if ($action === 'archive') {
        $pdo->prepare("UPDATE research_teams SET status='Archived' WHERE id=?")->execute([$id]);
        log_activity($pdo, $adminId, 'admin_team_archive', 'Archived team "' . $team['name'] . '"' . ($reason ? " — {$reason}" : ''), 'team', $id);
        flash('success', 'Team archived.');
    } elseif ($action === 'restore') {
        $pdo->prepare("UPDATE research_teams SET status='Active' WHERE id=?")->execute([$id]);
        log_activity($pdo, $adminId, 'admin_team_restore', 'Restored team "' . $team['name'] . '"', 'team', $id);
        flash('success', 'Team restored.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM research_teams WHERE id = ?')->execute([$id]);
        log_activity($pdo, $adminId, 'admin_team_delete', 'Deleted team "' . $team['name'] . '"' . ($reason ? " — {$reason}" : ''), 'team', $id);
        flash('success', 'Team deleted.');
    } else {
        flash('error', 'Unknown action.');
    }
    redirect('/admin/teams.php');
}

$statusFilter = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if (in_array($statusFilter, ['Forming', 'Active', 'Completed', 'Archived'], true)) { $where[] = 'rt.status = ?'; $params[] = $statusFilter; }
if ($q !== '') { $where[] = '(rt.name LIKE ? OR u.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare(
    "SELECT rt.*, u.name AS creator_name, d.name AS domain_name,
            (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = rt.id AND tm.status='Active') AS member_count,
            (SELECT COUNT(*) FROM advisor_assignments aa WHERE aa.team_id = rt.id AND aa.status='active') AS has_advisor
     FROM research_teams rt JOIN users u ON u.id = rt.created_by LEFT JOIN research_domains d ON d.id = rt.research_domain_id
     WHERE " . implode(' AND ', $where) . " ORDER BY rt.created_at DESC"
);
$stmt->execute($params);
$teams = $stmt->fetchAll();

function team_pill(string $status): string
{
    return match ($status) { 'Active' => 'pill pill-green', 'Forming' => 'pill pill-blue', 'Completed' => 'pill pill-purple', 'Archived' => 'pill pill-gray', default => 'pill pill-gray' };
}
$pageTitle = 'Research Teams';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Teams || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
        .pill-green{background:#d6f5e0;color:#0f8a3c} .pill-blue{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)} .pill-purple{background:#ece1fb;color:#5b21a6} .pill-gray{background:#e9e9e9;color:#555}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Research Teams (<?= count($teams) ?>)</h2>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:240px;" placeholder="Search team/creator...">
            <select name="status" class="form-select" style="max-width:180px;"><option value="">All Statuses</option><?php foreach (['Forming','Active','Completed','Archived'] as $s): ?><option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$teams): ?><div class="app-panel app-empty-state"><i class="bi bi-people"></i><p>No teams found.</p></div><?php endif; ?>
        <?php foreach ($teams as $t): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><?= e($t['name']) ?> <span class="<?= team_pill($t['status']) ?>"><?= e($t['status']) ?></span> <?php if ($t['has_advisor']): ?><span class="pill pill-green">Has Advisor</span><?php endif; ?></div>
                    <div class="text-muted small">Led by <?= e($t['creator_name']) ?> &middot; <?= (int)$t['member_count'] ?>/<?= (int)$t['team_size_limit'] ?> members &middot; <?= e($t['domain_name'] ?: 'No domain') ?></div>
                </div>
                <a href="<?= e(url('/admin/team-details.php?id=' . $t['id'])) ?>" class="btn btn-sm btn-outline-primary">Review</a>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
