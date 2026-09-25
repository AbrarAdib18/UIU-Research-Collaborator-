<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/domains.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = nullable_trim($_POST['description'] ?? '');
        $icon = nullable_trim($_POST['icon'] ?? '') ?: 'bi bi-diagram-3';
        if ($name === '') {
            flash('error', 'Domain name is required.');
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM research_domains WHERE name = ?');
            $chk->execute([$name]);
            if ($chk->fetchColumn()) {
                flash('error', 'A domain with that name already exists.');
            } else {
                try {
                    $pdo->prepare('INSERT INTO research_domains (name, description, icon) VALUES (?,?,?)')->execute([$name, $description, $icon]);
                    log_activity($pdo, $adminId, 'admin_domain_add', "Added research domain \"$name\"");
                    flash('success', 'Research domain added.');
                } catch (Throwable $ex) {
                    error_log('admin domains add: ' . $ex->getMessage());
                    flash('error', 'Could not add domain.');
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = validate_id($_POST['id'] ?? null);
        $name = trim($_POST['name'] ?? '');
        $description = nullable_trim($_POST['description'] ?? '');
        $icon = nullable_trim($_POST['icon'] ?? '') ?: 'bi bi-diagram-3';
        if (!$id || $name === '') {
            flash('error', 'Invalid domain.');
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM research_domains WHERE name = ? AND id != ?');
            $chk->execute([$name, $id]);
            if ($chk->fetchColumn()) {
                flash('error', 'A domain with that name already exists.');
            } else {
                $pdo->prepare('UPDATE research_domains SET name=?, description=?, icon=? WHERE id=?')->execute([$name, $description, $icon, $id]);
                log_activity($pdo, $adminId, 'admin_domain_edit', "Edited research domain \"$name\"", 'research_domain', $id);
                flash('success', 'Research domain updated.');
            }
        }
    } elseif ($action === 'delete') {
        $id = validate_id($_POST['id'] ?? null);
        if ($id) {
            $refs = [
                'profile_research_domains' => 'domain_id',
                'faculty_research_domains' => 'domain_id',
                'opportunity_domains'      => 'domain_id',
                'research_teams'           => 'research_domain_id',
                'communities'              => 'domain_id',
                'research_resources'       => 'domain_id',
            ];
            $inUse = false;
            foreach ($refs as $table => $col) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
                $c->execute([$id]);
                if ($c->fetchColumn() > 0) { $inUse = true; break; }
            }
            if ($inUse) {
                flash('error', 'This domain is still referenced by profiles, opportunities, teams, communities, or resources and cannot be deleted.');
            } else {
                $pdo->prepare('DELETE FROM research_domains WHERE id = ?')->execute([$id]);
                log_activity($pdo, $adminId, 'admin_domain_delete', "Deleted research domain #$id");
                flash('success', 'Research domain deleted.');
            }
        }
    }
    redirect('/admin/domains.php');
}

$domains = $pdo->query('SELECT * FROM research_domains ORDER BY name')->fetchAll();

$pageTitle = 'Research Domains';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Domains || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:14px}
        .type-tabs{display:flex;gap:8px;margin-bottom:16px}
        .type-tabs a{padding:8px 18px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none}
        .type-tabs a.active{background:var(--uiu-blue);color:#fff}
        .table thead th{color:var(--uiu-blue);font-size:12px;text-transform:uppercase;border-bottom:2px solid var(--border-color)}
        .table td{vertical-align:middle;font-size:13px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Research Domains</h2>
        <div class="type-tabs">
            <a href="<?= e(url('/admin/domains.php')) ?>" class="active">Domains</a>
            <a href="<?= e(url('/admin/skills.php')) ?>">Skills</a>
            <a href="<?= e(url('/admin/languages.php')) ?>">Languages</a>
        </div>

        <div class="app-panel">
            <h3 style="font-size:15px;color:var(--uiu-blue);font-weight:700;">Add Domain</h3>
            <form method="post" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-md-3"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                <div class="col-md-5"><label class="form-label small">Description</label><input type="text" name="description" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label small">Icon (Bootstrap Icons class)</label><input type="text" name="icon" class="form-control form-control-sm" placeholder="bi bi-cpu"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-uiu w-100" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Add</button></div>
            </form>
        </div>

        <div class="app-panel">
            <table class="table table-hover">
                <thead><tr><th>Icon</th><th>Name</th><th>Description</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($domains as $d): ?>
                    <tr>
                        <td><i class="<?= e($d['icon'] ?: 'bi bi-diagram-3') ?>"></i></td>
                        <td class="fw-semibold"><?= e($d['name']) ?></td>
                        <td class="text-muted small"><?= e(mb_strimwidth((string)$d['description'], 0, 80, '...')) ?></td>
                        <td class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('edit<?= (int)$d['id'] ?>').classList.toggle('d-none')"><i class="bi bi-pencil"></i></button>
                            <form method="post" onsubmit="return confirm('Delete this domain?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                    <tr id="edit<?= (int)$d['id'] ?>" class="d-none"><td colspan="4">
                        <form method="post" class="row g-2 align-items-end">
                            <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm" value="<?= e($d['name']) ?>" required></div>
                            <div class="col-md-5"><input type="text" name="description" class="form-control form-control-sm" value="<?= e($d['description'] ?? '') ?>"></div>
                            <div class="col-md-2"><input type="text" name="icon" class="form-control form-control-sm" value="<?= e($d['icon'] ?? '') ?>"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-success w-100">Save</button></div>
                        </form>
                    </td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
