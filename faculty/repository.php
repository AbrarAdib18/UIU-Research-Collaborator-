<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_resource') {
    require_csrf('/faculty/repository.php');
    $id = (int)($_POST['id'] ?? 0);
    $own = $pdo->prepare('SELECT * FROM research_resources WHERE id = ? AND uploaded_by = ?');
    $own->execute([$id, $userId]);
    $resource = $own->fetch();
    if ($resource) {
        $pdo->prepare('DELETE FROM research_resources WHERE id = ?')->execute([$id]);
        if ($resource['file_path'] && is_file(__DIR__ . '/../uploads/' . $resource['file_path'])) {
            @unlink(__DIR__ . '/../uploads/' . $resource['file_path']);
        }
        flash('success', 'Resource removed.');
    } else {
        flash('error', 'Resource not found.');
    }
    redirect('/faculty/repository.php');
}

$q        = trim((string)($_GET['q'] ?? ''));
$domainId = isset($_GET['domain_id']) && $_GET['domain_id'] !== '' ? (int)$_GET['domain_id'] : 0;
$mineOnly = isset($_GET['mine']);

$where  = ["r.status = 'Published'", "r.visibility <> 'Private'"];
$params = [];
if ($mineOnly) {
    $where = ["r.uploaded_by = ?"];
    $params[] = $userId;
}
if ($q !== '') {
    $where[] = '(r.title LIKE ? OR r.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($domainId > 0) {
    $where[] = 'r.domain_id = ?';
    $params[] = $domainId;
}

$stmt = $pdo->prepare(
    "SELECT r.*, u.name AS uploader_name, d.name AS domain_name FROM research_resources r
     JOIN users u ON u.id = r.uploaded_by LEFT JOIN research_domains d ON d.id = r.domain_id
     WHERE " . implode(' AND ', $where) . " ORDER BY r.created_at DESC LIMIT 40"
);
$stmt->execute($params);
$resources = $stmt->fetchAll();
$domains = all_research_domains($pdo);

$pageTitle = 'Research Repository';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Repository || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px}
        .res-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
        .res-card{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px}
        .res-card h3{font-size:15px;color:var(--uiu-blue);font-weight:700;margin-bottom:4px}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <div class="app-page-header">
            <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;margin:0;">Research Repository</h2>
            <a href="<?= e(url('/faculty/repository-resource-create.php')) ?>" class="btn btn-uiu"><i class="bi bi-plus-lg"></i> Add Resource</a>
        </div>

        <form method="get" class="d-flex flex-wrap gap-2 mb-3">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:240px;" placeholder="Search resources...">
            <select name="domain_id" class="form-select" style="max-width:200px;">
                <option value="">All Domains</option>
                <?php foreach ($domains as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $domainId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
            </select>
            <label class="d-flex align-items-center gap-1"><input type="checkbox" name="mine" value="1" <?= $mineOnly ? 'checked' : '' ?>> My uploads only</label>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$resources): ?>
            <div class="app-empty-state"><i class="bi bi-database"></i><p>No resources found.</p></div>
        <?php endif; ?>
        <div class="res-grid">
            <?php foreach ($resources as $r): ?>
                <div class="res-card">
                    <h3><?= e($r['title']) ?></h3>
                    <p class="text-muted small mb-1"><?= e($r['resource_type']) ?> &middot; <?= e($r['domain_name'] ?: 'General') ?><?= $r['publication_year'] ? ' &middot; ' . e((string)$r['publication_year']) : '' ?></p>
                    <p class="small"><?= e(mb_strimwidth((string)$r['description'], 0, 100, '...')) ?></p>
                    <p class="text-muted small mb-2">by <?= e($r['uploader_name']) ?></p>
                    <div class="d-flex gap-2">
                        <?php if ($r['file_path']): ?><a href="<?= e(url('/uploads/' . $r['file_path'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Download</a><?php endif; ?>
                        <?php if ($r['external_url']): ?><a href="<?= e($r['external_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Open Link</a><?php endif; ?>
                        <?php if ((int)$r['uploaded_by'] === $userId): ?>
                            <form method="post" onsubmit="return confirm('Remove this resource?');">
                                <?= csrf_field() ?><input type="hidden" name="action" value="remove_resource"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
