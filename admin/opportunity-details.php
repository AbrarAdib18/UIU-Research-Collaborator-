<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo    = db();
$adminId = (int)$currentUser['id'];
$oppId  = validate_id($_GET['id'] ?? null);

if (!$oppId) {
    flash('error', 'Invalid opportunity.');
    redirect('/admin/opportunities.php');
}

$stmt = $pdo->prepare('SELECT o.*, u.name AS creator_name, u.id AS creator_id FROM research_opportunities o JOIN users u ON u.id = o.created_by WHERE o.id = ?');
$stmt->execute([$oppId]);
$opportunity = $stmt->fetch();

if (!$opportunity) {
    flash('error', 'Opportunity not found.');
    redirect('/admin/opportunities.php');
}

$domainStmt = $pdo->prepare('SELECT d.name FROM opportunity_domains od JOIN research_domains d ON d.id = od.domain_id WHERE od.opportunity_id = ?');
$domainStmt->execute([$oppId]);
$domainNames = $domainStmt->fetchAll(PDO::FETCH_COLUMN);

$appStmt = $pdo->prepare("SELECT oa.*, u.name AS applicant_name FROM opportunity_applications oa JOIN users u ON u.id = oa.user_id WHERE oa.opportunity_id = ? ORDER BY oa.applied_at DESC");
$appStmt->execute([$oppId]);
$applications = $appStmt->fetchAll();

$teamStmt = $pdo->prepare('SELECT id, name, status FROM research_teams WHERE opportunity_id = ?');
$teamStmt->execute([$oppId]);
$teams = $teamStmt->fetchAll();

$pageTitle = 'Opportunity Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($opportunity['title']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>.detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}.detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/admin/opportunities.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Opportunities</a>

        <div class="detail-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h1 style="font-size:22px;color:var(--uiu-blue);font-weight:700;"><?= e($opportunity['title']) ?></h1>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($opportunity['status']) ?></span>
                    <span class="badge bg-light text-dark border"><?= e($opportunity['visibility']) ?></span>
                </div>
                <a href="<?= e(url('/admin/user-details.php?id=' . $opportunity['creator_id'])) ?>" class="btn btn-sm btn-outline-secondary">View Creator</a>
            </div>
            <p class="text-muted small mt-2">Created by <?= e($opportunity['creator_name']) ?> &middot; Deadline <?= format_date($opportunity['deadline']) ?> &middot; Team <?= (int)$opportunity['team_size_min'] ?>-<?= (int)$opportunity['team_size_max'] ?></p>
            <?php if ($domainNames): ?><p><?php foreach ($domainNames as $n): ?><span class="badge bg-light text-dark border me-1"><?= e($n) ?></span><?php endforeach; ?></p><?php endif; ?>
            <p style="white-space:pre-line;"><?= e($opportunity['description']) ?></p>
        </div>

        <div class="detail-card">
            <h2>Moderation</h2>
            <form method="post" action="<?= e(url('/admin/opportunities.php')) ?>" class="mb-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $oppId ?>">
                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Reason (optional, notifies creator and is logged)">
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($opportunity['status'] === 'Open'): ?><button name="action" value="close" class="btn btn-sm btn-outline-warning">Close</button><?php endif; ?>
                    <?php if ($opportunity['status'] === 'Closed'): ?><button name="action" value="reopen" class="btn btn-sm btn-outline-success">Reopen</button><?php endif; ?>
                    <?php if ($opportunity['status'] !== 'Completed'): ?><button name="action" value="archive" class="btn btn-sm btn-outline-secondary">Archive</button><?php endif; ?>
                </div>
            </form>
            <form method="post" action="<?= e(url('/admin/opportunities.php')) ?>" onsubmit="return confirm('Delete this opportunity permanently? This cannot be undone.');">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $oppId ?>"><input type="hidden" name="action" value="delete">
                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Deletion reason (optional)">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete Opportunity</button>
            </form>
        </div>

        <?php if ($teams): ?>
        <div class="detail-card"><h2>Linked Teams</h2><?php foreach ($teams as $t): ?><p class="mb-1"><a href="<?= e(url('/admin/team-details.php?id=' . $t['id'])) ?>"><?= e($t['name']) ?></a> — <?= e($t['status']) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <div class="detail-card">
            <h2>Applicants (<?= count($applications) ?>)</h2>
            <?php if (!$applications): ?><p class="text-muted mb-0">No applications yet.</p><?php endif; ?>
            <?php foreach ($applications as $a): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div><?= e($a['applicant_name']) ?> <span class="text-muted small">applied <?= e(time_ago($a['applied_at'])) ?></span></div>
                    <span class="badge bg-light text-dark border"><?= e($a['status']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
