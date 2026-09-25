<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/languages.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Language name is required.');
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM languages WHERE name = ?');
            $chk->execute([$name]);
            if ($chk->fetchColumn()) {
                flash('error', 'That language already exists.');
            } else {
                $pdo->prepare('INSERT INTO languages (name) VALUES (?)')->execute([$name]);
                log_activity($pdo, $adminId, 'admin_language_add', "Added language \"$name\"");
                flash('success', 'Language added.');
            }
        }
    } elseif ($action === 'edit') {
        $id = validate_id($_POST['id'] ?? null);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name !== '') {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM languages WHERE name = ? AND id != ?');
            $chk->execute([$name, $id]);
            if ($chk->fetchColumn()) {
                flash('error', 'That language already exists.');
            } else {
                $pdo->prepare('UPDATE languages SET name=? WHERE id=?')->execute([$name, $id]);
                log_activity($pdo, $adminId, 'admin_language_edit', "Edited language \"$name\"", 'language', $id);
                flash('success', 'Language updated.');
            }
        }
    } elseif ($action === 'delete') {
        $id = validate_id($_POST['id'] ?? null);
        if ($id) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM profile_languages WHERE language_id = ?');
            $c->execute([$id]);
            if ($c->fetchColumn() > 0) {
                flash('error', 'This language is still used in student profiles and cannot be deleted.');
            } else {
                $pdo->prepare('DELETE FROM languages WHERE id = ?')->execute([$id]);
                log_activity($pdo, $adminId, 'admin_language_delete', "Deleted language #$id");
                flash('success', 'Language deleted.');
            }
        }
    }
    redirect('/admin/languages.php');
}

$languages = $pdo->query('SELECT l.*, (SELECT COUNT(*) FROM profile_languages WHERE language_id = l.id) AS usage_count FROM languages l ORDER BY l.name')->fetchAll();

$pageTitle = 'Languages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Languages || UIU ResearchCollab</title>
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
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Languages</h2>
        <div class="type-tabs">
            <a href="<?= e(url('/admin/domains.php')) ?>">Domains</a>
            <a href="<?= e(url('/admin/skills.php')) ?>">Skills</a>
            <a href="<?= e(url('/admin/languages.php')) ?>" class="active">Languages</a>
        </div>

        <div class="app-panel">
            <h3 style="font-size:15px;color:var(--uiu-blue);font-weight:700;">Add Language</h3>
            <form method="post" class="row g-2 align-items-end">
                <?= csrf_field() ?><input type="hidden" name="action" value="add">
                <div class="col-md-6"><input type="text" name="name" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><button class="btn btn-sm btn-uiu w-100" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Add</button></div>
            </form>
        </div>

        <div class="app-panel">
            <table class="table table-hover">
                <thead><tr><th>Name</th><th>Used By</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($languages as $l): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($l['name']) ?></td>
                        <td><?= (int)$l['usage_count'] ?> profile(s)</td>
                        <td class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('edit<?= (int)$l['id'] ?>').classList.toggle('d-none')"><i class="bi bi-pencil"></i></button>
                            <form method="post" onsubmit="return confirm('Delete this language?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                    <tr id="edit<?= (int)$l['id'] ?>" class="d-none"><td colspan="3">
                        <form method="post" class="row g-2 align-items-end">
                            <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                            <div class="col-md-6"><input type="text" name="name" class="form-control form-control-sm" value="<?= e($l['name']) ?>" required></div>
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
