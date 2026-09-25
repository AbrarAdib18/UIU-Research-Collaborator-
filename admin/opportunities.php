<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/opportunities.php');
    $id     = validate_id($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM research_opportunities WHERE id = ?');
    $stmt->execute([$id]);
    $opp = $id ? $stmt->fetch() : null;

    if (!$opp) {
        flash('error', 'Opportunity not found.');
        redirect('/admin/opportunities.php');
    }

    $transitions = ['close' => 'Closed', 'reopen' => 'Open', 'archive' => 'Completed'];

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM research_opportunities WHERE id = ?')->execute([$id]);
        log_activity($pdo, $adminId, 'admin_opportunity_delete', 'Deleted opportunity "' . $opp['title'] . '"' . ($reason ? " — {$reason}" : ''), 'opportunity', $id);
        create_notification($pdo, (int)$opp['created_by'], 'opportunity', 'Opportunity Removed', 'Your opportunity "' . $opp['title'] . '" was removed by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'opportunity', null);
        flash('success', 'Opportunity deleted.');
    } elseif (isset($transitions[$action])) {
        $pdo->prepare('UPDATE research_opportunities SET status = ? WHERE id = ?')->execute([$transitions[$action], $id]);
        log_activity($pdo, $adminId, 'admin_opportunity_moderate', 'Set "' . $opp['title'] . '" to ' . $transitions[$action] . ($reason ? " — {$reason}" : ''), 'opportunity', $id);
        create_notification($pdo, (int)$opp['created_by'], 'opportunity', 'Opportunity Updated by Admin', 'Your opportunity "' . $opp['title'] . '" was set to ' . $transitions[$action] . ' by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'opportunity', $id);
        flash('success', 'Opportunity updated.');
    } else {
        flash('error', 'Unknown action.');
    }
    redirect('/admin/opportunities.php');
}

$statusFilter = (string)($_GET['status'] ?? '');
$domainFilter = validate_id($_GET['domain'] ?? null);
$q = trim((string)($_GET['q'] ?? ''));

$where = ['1=1']; $params = [];
if (in_array($statusFilter, ['Draft', 'Open', 'Closed', 'Completed'], true)) { $where[] = 'o.status = ?'; $params[] = $statusFilter; }
if ($domainFilter) { $where[] = 'EXISTS (SELECT 1 FROM opportunity_domains od WHERE od.opportunity_id = o.id AND od.domain_id = ?)'; $params[] = $domainFilter; }
if ($q !== '') { $where[] = '(o.title LIKE ? OR u.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$perPage = 15; $page = current_page();
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM research_opportunities o JOIN users u ON u.id = o.created_by WHERE " . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT o.*, u.name AS creator_name, (SELECT COUNT(*) FROM opportunity_applications a WHERE a.opportunity_id = o.id) AS applicant_count
     FROM research_opportunities o JOIN users u ON u.id = o.created_by
     WHERE " . implode(' AND ', $where) . " ORDER BY o.created_at DESC LIMIT $perPage OFFSET " . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$opportunities = $stmt->fetchAll();
$domains = all_research_domains($pdo);

function opp_status_pill2(string $status): string
{
    return match ($status) { 'Open' => 'pill pill-green', 'Closed' => 'pill pill-red', 'Draft' => 'pill pill-gray', 'Completed' => 'pill pill-purple', default => 'pill pill-gray' };
}
$pageTitle = 'Research Opportunities';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Opportunities || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
        .pill-green{background:#d6f5e0;color:#0f8a3c} .pill-red{background:#fde0e0;color:#c62828} .pill-gray{background:#e9e9e9;color:#555} .pill-purple{background:#ece1fb;color:#5b21a6}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Research Opportunities (<?= $total ?>)</h2>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:220px;" placeholder="Search title/creator...">
            <select name="status" class="form-select" style="max-width:150px;"><option value="">All Statuses</option><?php foreach (['Draft','Open','Closed','Completed'] as $s): ?><option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
            <select name="domain" class="form-select" style="max-width:200px;"><option value="">All Domains</option><?php foreach ($domains as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $domainFilter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$opportunities): ?><div class="app-panel app-empty-state"><i class="bi bi-briefcase"></i><p>No opportunities found.</p></div><?php endif; ?>
        <?php foreach ($opportunities as $o): ?>
            <div class="app-panel d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h3 style="font-size:15px;color:var(--uiu-blue);font-weight:700;margin:0;"><a href="<?= e(url('/admin/opportunity-details.php?id=' . $o['id'])) ?>" class="text-decoration-none"><?= e($o['title']) ?></a> <span class="<?= opp_status_pill2($o['status']) ?>"><?= e($o['status']) ?></span></h3>
                    <p class="text-muted small mb-0">by <?= e($o['creator_name']) ?> &middot; <?= (int)$o['applicant_count'] ?> applicant(s) &middot; Deadline <?= format_date($o['deadline']) ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= e(url('/admin/opportunity-details.php?id=' . $o['id'])) ?>" class="btn btn-sm btn-outline-primary">Review</a>
                </div>
            </div>
        <?php endforeach; ?>

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
