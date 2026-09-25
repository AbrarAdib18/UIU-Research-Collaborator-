<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$allowedTypes = ['Research Paper', 'Journal Article', 'Conference Paper', 'Dataset', 'Book', 'Thesis', 'Tutorial', 'Documentation', 'Other'];
$domains = all_research_domains($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/repository-resource-create.php');

    $title        = trim($_POST['title'] ?? '');
    $description  = nullable_trim($_POST['description'] ?? '');
    $resourceType = $_POST['resource_type'] ?? '';
    $author       = nullable_trim($_POST['author'] ?? '');
    $pubYearRaw   = trim($_POST['publication_year'] ?? '');
    $domainIdRaw  = trim($_POST['domain_id'] ?? '');
    $externalUrlRaw = trim($_POST['external_url'] ?? '');

    $errors = [];
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if (!in_array($resourceType, $allowedTypes, true)) {
        $errors[] = 'Please choose a valid resource type.';
    }
    $publicationYear = null;
    if ($pubYearRaw !== '') {
        if (!ctype_digit($pubYearRaw) || (int)$pubYearRaw < 1900 || (int)$pubYearRaw > (int)date('Y') + 1) {
            $errors[] = 'Please enter a valid publication year.';
        } else {
            $publicationYear = (int)$pubYearRaw;
        }
    }
    $domainId = null;
    if ($domainIdRaw !== '') {
        $validIds = array_map(fn($d) => (int)$d['id'], $domains);
        if (ctype_digit($domainIdRaw) && in_array((int)$domainIdRaw, $validIds, true)) {
            $domainId = (int)$domainIdRaw;
        } else {
            $errors[] = 'Please choose a valid research domain.';
        }
    }
    $externalUrl = null;
    if ($externalUrlRaw !== '') {
        if (!preg_match('~^https?://~i', $externalUrlRaw) || !filter_var($externalUrlRaw, FILTER_VALIDATE_URL)) {
            $errors[] = 'External URL must be a valid http(s) link.';
        } else {
            $externalUrl = $externalUrlRaw;
        }
    }

    $storedRelativePath = null;
    if (!empty($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = validate_upload($_FILES['file'], ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'zip'], 15 * 1024 * 1024);
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        } else {
            $storedName = safe_filename($_FILES['file']['name']);
            $destPath   = __DIR__ . '/../uploads/resources/' . $storedName;
            if (!is_dir(dirname($destPath))) {
                mkdir(dirname($destPath), 0755, true);
            }
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                error_log('repository-resource-create.php: failed to move uploaded file to ' . $destPath);
                $errors[] = 'Could not save the uploaded file. Please try again.';
            } else {
                $storedRelativePath = 'resources/' . $storedName;
            }
        }
    }

    if ($errors) {
        if ($storedRelativePath && is_file(__DIR__ . '/../uploads/' . $storedRelativePath)) {
            @unlink(__DIR__ . '/../uploads/' . $storedRelativePath);
        }
        flash('error', implode(' ', $errors));
    } else {
        try {
            $ins = $pdo->prepare(
                'INSERT INTO research_resources (uploaded_by, title, description, resource_type, author, publication_year, domain_id, file_path, external_url, visibility, status) VALUES (?,?,?,?,?,?,?,?,?,\'Public\',\'Published\')'
            );
            $ins->execute([$userId, $title, $description, $resourceType, $author, $publicationYear, $domainId, $storedRelativePath, $externalUrl]);
            log_activity($pdo, $userId, 'resource_added', 'Added a research resource "' . $title . '"', 'resource', (int)$pdo->lastInsertId());
            flash('success', 'Resource added to the repository.');
            redirect('/faculty/repository.php');
        } catch (Throwable $ex) {
            error_log('faculty repository-resource-create: ' . $ex->getMessage());
            flash('error', 'Could not save the resource. Please try again.');
        }
    }
}

$pageTitle = 'Add Repository Resource';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Resource || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>.app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:22px}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/faculty/repository.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Repository</a>
        <div class="app-panel">
            <h2 style="color:var(--uiu-blue);font-size:20px;font-weight:700;">Add Research Resource</h2>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Resource Type</label>
                        <select name="resource_type" class="form-select" required>
                            <?php foreach ($allowedTypes as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Research Domain</label>
                        <select name="domain_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($domains as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label">Author(s)</label><input type="text" name="author" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Publication Year</label><input type="number" name="publication_year" class="form-control" min="1900" max="<?= date('Y') + 1 ?>"></div>
                </div>
                <div class="mb-3"><label class="form-label">External URL</label><input type="url" name="external_url" class="form-control" placeholder="https://..."></div>
                <div class="mb-3"><label class="form-label">Upload File (optional)</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.zip"></div>
                <button type="submit" class="btn btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Add Resource</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
