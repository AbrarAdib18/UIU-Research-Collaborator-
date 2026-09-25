<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/opportunities.php');
    $action = $_POST['action'] ?? '';
    $oppId  = (int)($_POST['opportunity_id'] ?? 0);

    $own = $pdo->prepare('SELECT * FROM research_opportunities WHERE id = ? AND created_by = ?');
    $own->execute([$oppId, $userId]);
    $opp = $own->fetch();

    if (!$opp) {
        flash('error', 'That opportunity does not exist or is not yours to manage.');
        redirect('/faculty/opportunities.php');
    }

    $validTransitions = [
        'publish' => 'Open',
        'close'   => 'Closed',
        'reopen'  => 'Open',
        'archive' => 'Completed',
    ];

    if ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM research_opportunities WHERE id = ? AND created_by = ?')->execute([$oppId, $userId]);
            log_activity($pdo, $userId, 'opportunity_deleted', 'Deleted opportunity "' . $opp['title'] . '"', 'opportunity', $oppId);
            flash('success', 'Opportunity deleted.');
        } catch (Throwable $ex) {
            error_log('faculty opportunities delete: ' . $ex->getMessage());
            flash('error', 'Could not delete opportunity.');
        }
    } elseif (isset($validTransitions[$action])) {
        try {
            $pdo->prepare('UPDATE research_opportunities SET status = ? WHERE id = ? AND created_by = ?')
                ->execute([$validTransitions[$action], $oppId, $userId]);
            log_activity($pdo, $userId, 'opportunity_status_change', 'Set "' . $opp['title'] . '" to ' . $validTransitions[$action], 'opportunity', $oppId);
            flash('success', 'Opportunity updated.');
        } catch (Throwable $ex) {
            error_log('faculty opportunities status change: ' . $ex->getMessage());
            flash('error', 'Could not update opportunity status.');
        }
    } else {
        flash('error', 'Unknown action.');
    }
    redirect('/faculty/opportunities.php');
}

$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['Draft', 'Open', 'Closed', 'Completed'], true)) {
    $statusFilter = '';
}
$q = trim((string)($_GET['q'] ?? ''));

$where  = ['created_by = ?'];
$params = [$userId];
if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = 'title LIKE ?';
    $params[] = '%' . $q . '%';
}

$perPage = 10;
$page    = current_page();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM research_opportunities WHERE ' . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    'SELECT o.*, (SELECT COUNT(*) FROM opportunity_applications a WHERE a.opportunity_id = o.id) AS applicant_count
     FROM research_opportunities o WHERE ' . implode(' AND ', $where) . '
     ORDER BY o.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . paginate_offset($page, $perPage)
);
$stmt->execute($params);
$opportunities = $stmt->fetchAll();

function opp_status_pill(string $status): string
{
    return match ($status) {
        'Open'      => 'pill pill-green',
        'Closed'    => 'pill pill-red',
        'Draft'     => 'pill pill-gray',
        'Completed' => 'pill pill-purple',
        default     => 'pill pill-gray',
    };
}

$pageTitle = 'Research Opportunities';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Opportunities || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .pill-purple{background:#ece1fb;color:#5b21a6}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:14px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
        .opp-row-actions form{display:inline}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2>Research Opportunities</h2>
                <p>Publish and manage the opportunities you're recruiting for.</p>
            </div>
            <a href="<?= e(url('/faculty/opportunity-create.php')) ?>" class="btn btn-uiu"><i class="bi bi-plus-lg"></i> Create Opportunity</a>
        </div>

        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:260px;" placeholder="Search by title...">
            <select name="status" class="form-select" style="max-width:180px;">
                <option value="">All Statuses</option>
                <?php foreach (['Draft', 'Open', 'Closed', 'Completed'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$opportunities): ?>
            <div class="app-panel app-empty-state">
                <i class="bi bi-briefcase"></i>
                <p>No opportunities yet. <a href="<?= e(url('/faculty/opportunity-create.php')) ?>">Create your first one</a>.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($opportunities as $o): ?>
            <div class="app-panel">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h3 style="color:var(--uiu-blue);font-size:16px;font-weight:700;margin-bottom:4px;">
                            <a href="<?= e(url('/faculty/opportunity-details.php?id=' . $o['id'])) ?>" class="text-decoration-none"><?= e($o['title']) ?></a>
                            <span class="<?= opp_status_pill($o['status']) ?>"><?= e($o['status']) ?></span>
                        </h3>
                        <p class="text-muted small mb-1"><?= e($o['project_type'] ?: 'Research') ?> &middot; Team <?= (int)$o['team_size_min'] ?>-<?= (int)$o['team_size_max'] ?> &middot; Deadline <?= format_date($o['deadline']) ?></p>
                        <p class="text-muted small mb-0"><i class="bi bi-people"></i> <?= (int)$o['applicant_count'] ?> applicant(s)</p>
                    </div>
                    <div class="opp-row-actions d-flex gap-2 flex-wrap">
                        <a href="<?= e(url('/faculty/opportunity-edit.php?id=' . $o['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
                        <?php if ($o['status'] === 'Draft'): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="opportunity_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-success">Publish</button></form>
                        <?php elseif ($o['status'] === 'Open'): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="opportunity_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-outline-warning">Close</button></form>
                        <?php elseif ($o['status'] === 'Closed'): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="opportunity_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-outline-success">Reopen</button></form>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="opportunity_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Archive</button></form>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('Delete this opportunity permanently?');">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="opportunity_id" value="<?= (int)$o['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="app-pagination" aria-label="Opportunities pagination">
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
