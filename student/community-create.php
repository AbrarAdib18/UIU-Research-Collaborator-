<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/student/community-create.php');

    $name        = trim((string)($_POST['name'] ?? ''));
    $description = nullable_trim($_POST['description'] ?? '');
    $domainId    = ($_POST['domain_id'] ?? '') !== '' ? (int)$_POST['domain_id'] : null;
    $privacy     = $_POST['privacy'] ?? 'Public';

    $errors = [];

    if ($name === '') {
        $errors[] = 'Community name is required.';
    } elseif (mb_strlen($name) > 200) {
        $errors[] = 'Community name is too long (max 200 characters).';
    }

    if (!in_array($privacy, ['Public', 'Private'], true)) {
        $privacy = 'Public';
    }

    if ($domainId !== null) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM research_domains WHERE id = ?');
        $chk->execute([$domainId]);
        if (!$chk->fetchColumn()) {
            $domainId = null;
        }
    }

    // Case-insensitive duplicate check (the utf8mb4_general_ci collation on
    // `communities.name` already makes this comparison case-insensitive).
    if ($name !== '' && !$errors) {
        $dupChk = $pdo->prepare('SELECT id FROM communities WHERE name = ? LIMIT 1');
        $dupChk->execute([$name]);
        if ($dupChk->fetch()) {
            $errors[] = 'A community with that name already exists.';
        }
    }

    $coverFilename = null;
    $coverDir      = __DIR__ . '/../uploads/communities/';

    if (!$errors && isset($_FILES['cover_image']) && ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $result = validate_upload($_FILES['cover_image'], ['jpg', 'jpeg', 'png', 'webp'], 3 * 1024 * 1024);
        if (!$result['ok']) {
            $errors[] = $result['error'];
        } else {
            if (!is_dir($coverDir)) {
                mkdir($coverDir, 0755, true);
            }
            $coverFilename = safe_filename($_FILES['cover_image']['name']);
            if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $coverDir . $coverFilename)) {
                $errors[] = 'Could not upload the cover image. Please try again.';
                $coverFilename = null;
            }
        }
    }

    if ($errors) {
        if ($coverFilename && is_file($coverDir . $coverFilename)) {
            @unlink($coverDir . $coverFilename);
        }
        set_old($_POST);
        flash('error', implode(' ', $errors));
        redirect('/student/community-create.php');
    }

    try {
        $pdo->beginTransaction();

        $creationPolicy = get_platform_setting($pdo, 'community_creation_policy', 'open');
        $initialStatus  = $creationPolicy === 'admin_approval' ? 'Inactive' : 'Active';

        $ins = $pdo->prepare(
            "INSERT INTO communities (name, description, domain_id, created_by, cover_image, privacy, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([$name, $description, $domainId, $userId, $coverFilename, $privacy, $initialStatus]);
        $communityId = (int)$pdo->lastInsertId();

        $memberIns = $pdo->prepare(
            "INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'Admin')"
        );
        $memberIns->execute([$communityId, $userId]);

        $pdo->commit();

        log_activity($pdo, $userId, 'community_create', "Created the community \"{$name}\".", 'community', $communityId);

        flash('success', $initialStatus === 'Inactive'
            ? 'Community created and is pending administrator approval before it becomes visible to others. You are its admin.'
            : 'Community created successfully. You are its admin.');
        redirect('/student/community-details.php?id=' . $communityId);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($coverFilename && is_file($coverDir . $coverFilename)) {
            @unlink($coverDir . $coverFilename);
        }
        error_log('community-create.php: ' . $ex->getMessage());
        set_old($_POST);
        $msg = (($ex instanceof PDOException) && ($ex->errorInfo[1] ?? null) === 1062)
            ? 'A community with that name already exists.'
            : 'Something went wrong while creating the community. Please try again.';
        flash('error', $msg);
        redirect('/student/community-create.php');
    }
}

$domains = all_research_domains($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Community || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:22px;max-width:680px}
        .app-panel label{font-weight:600;font-size:13px;color:var(--text-dark);margin-bottom:4px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2>Create a Community</h2>
                <p>Start a new research community and invite others to join.</p>
            </div>
            <a href="<?= e(url('/student/communities.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Communities</a>
        </div>

        <div class="app-panel">
            <form action="<?= e(url('/student/community-create.php')) ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Community Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="200" required value="<?= e(old('name')) ?>">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="What is this community about?"><?= e(old('description')) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="domain_id" class="form-label">Research Domain</label>
                        <select class="form-select" id="domain_id" name="domain_id">
                            <option value="">— Not specified —</option>
                            <?php foreach ($domains as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= (string)old('domain_id') === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="privacy" class="form-label">Privacy</label>
                        <select class="form-select" id="privacy" name="privacy">
                            <option value="Public" <?= old('privacy', 'Public') === 'Public' ? 'selected' : '' ?>>Public — anyone can view and join</option>
                            <option value="Private" <?= old('privacy') === 'Private' ? 'selected' : '' ?>>Private — invitation only</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="cover_image" class="form-label">Cover Image (optional)</label>
                    <input type="file" class="form-control" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text">JPEG, PNG, or WebP — max 3MB. A default avatar is shown if you skip this.</div>
                </div>

                <button type="submit" class="btn btn-uiu"><i class="bi bi-check-lg"></i> Create Community</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
