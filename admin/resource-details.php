<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo       = db();
$adminId   = (int)$currentUser['id'];
$resourceId = validate_id($_GET['id'] ?? null);

if (!$resourceId) {
    flash('error', 'Invalid resource.');
    redirect('/admin/repository.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/resource-details.php?id=' . $resourceId);
    $action = $_POST['action'] ?? '';
    $reason = nullable_trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM research_resources WHERE id = ?');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch();

    if (!$resource) {
        flash('error', 'Resource not found.');
        redirect('/admin/repository.php');
    }

    if ($action === 'publish') {
        $pdo->prepare("UPDATE research_resources SET status='Published' WHERE id=?")->execute([$resourceId]);
        log_activity($pdo, $adminId, 'admin_resource_publish', 'Published resource "' . $resource['title'] . '"', 'resource', $resourceId);
        create_notification($pdo, (int)$resource['uploaded_by'], 'resource', 'Resource Published', 'Your resource "' . $resource['title'] . '" is now published.', 'resource', $resourceId);
        flash('success', 'Resource published.');
    } elseif ($action === 'hide') {
        $pdo->prepare("UPDATE research_resources SET status='Draft' WHERE id=?")->execute([$resourceId]);
        log_activity($pdo, $adminId, 'admin_resource_hide', 'Hid resource "' . $resource['title'] . '"' . ($reason ? " — {$reason}" : ''), 'resource', $resourceId);
        create_notification($pdo, (int)$resource['uploaded_by'], 'resource', 'Resource Hidden', 'Your resource "' . $resource['title'] . '" was hidden by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'resource', $resourceId);
        flash('success', 'Resource hidden.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM research_resources WHERE id = ?')->execute([$resourceId]);
        log_activity($pdo, $adminId, 'admin_resource_delete', 'Deleted resource "' . $resource['title'] . '"' . ($reason ? " — {$reason}" : ''), 'resource', $resourceId);
        flash('success', 'Resource deleted.');
        redirect('/admin/repository.php');
    }
    redirect('/admin/resource-details.php?id=' . $resourceId);
}

$stmt = $pdo->prepare('SELECT r.*, u.name AS uploader_name, u.id AS uploader_id, d.name AS domain_name FROM research_resources r JOIN users u ON u.id = r.uploaded_by LEFT JOIN research_domains d ON d.id = r.domain_id WHERE r.id = ?');
$stmt->execute([$resourceId]);
$resource = $stmt->fetch();
if (!$resource) {
    flash('error', 'Resource not found.');
    redirect('/admin/repository.php');
}

$saveCountStmt = $pdo->prepare('SELECT COUNT(*) FROM saved_resources WHERE resource_id = ?');
$saveCountStmt->execute([$resourceId]);
$saveCount = (int)$saveCountStmt->fetchColumn();

$pageTitle = 'Resource Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($resource['title']) ?> || UIU ResearchCollab</title>
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
        <a href="<?= e(url('/admin/repository.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Repository</a>

        <div class="detail-card">
            <h1 style="font-size:20px;color:var(--uiu-blue);font-weight:700;"><?= e($resource['title']) ?> <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($resource['status']) ?></span></h1>
            <p class="text-muted small">
                <?= e($resource['resource_type']) ?> &middot; by <a href="<?= e(url('/admin/user-details.php?id=' . $resource['uploader_id'])) ?>"><?= e($resource['uploader_name']) ?></a>
                &middot; <?= e($resource['domain_name'] ?: 'General') ?><?= $resource['publication_year'] ? ' · ' . e((string)$resource['publication_year']) : '' ?> &middot; <?= $saveCount ?> save(s)
            </p>
            <?php if ($resource['author']): ?><p class="mb-1"><strong>Author:</strong> <?= e($resource['author']) ?></p><?php endif; ?>
            <p><?= nl2br(e($resource['description'] ?: 'No description.')) ?></p>
            <?php if ($resource['external_url']): ?><p><a href="<?= e($resource['external_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> External Link</a></p><?php endif; ?>
            <?php if ($resource['file_path']): ?><p><a href="<?= e(url('/uploads/' . $resource['file_path'])) ?>" target="_blank"><i class="bi bi-file-earmark"></i> Uploaded File</a></p><?php endif; ?>
        </div>

        <div class="detail-card">
            <h2>Moderation</h2>
            <form method="post" class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <?= csrf_field() ?>
                <input type="text" name="reason" class="form-control form-control-sm" style="max-width:220px;" placeholder="Reason (optional)">
                <?php if ($resource['status'] === 'Draft'): ?><button name="action" value="publish" class="btn btn-sm btn-success">Publish</button><?php else: ?><button name="action" value="hide" class="btn btn-sm btn-outline-warning">Hide</button><?php endif; ?>
            </form>
            <form method="post" onsubmit="return confirm('Delete this resource permanently? This cannot be undone.');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Deletion reason (optional)">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete Resource</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
