<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

/**
 * Research Repository — resource details, with owner-only inline edit/delete.
 * Non-owner access is only allowed for Published + Public resources.
 */

$pdo    = db();
$userId = (int)$currentUser['id'];
$id     = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Resource not found.');
    redirect('/student/repository.php');
}

$allowedTypes = [
    'Research Paper', 'Journal Article', 'Conference Paper', 'Dataset',
    'Book', 'Thesis', 'Tutorial', 'Documentation', 'Other',
];

$typeIcons = [
    'Research Paper'   => 'bi-file-earmark-text',
    'Journal Article'  => 'bi-journal-text',
    'Conference Paper' => 'bi-file-earmark-richtext',
    'Dataset'          => 'bi-database',
    'Book'             => 'bi-journal-bookmark',
    'Thesis'           => 'bi-mortarboard',
    'Tutorial'         => 'bi-play-btn',
    'Documentation'    => 'bi-file-earmark-code',
    'Other'            => 'bi-file-earmark',
];

function repo_load_resource(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, d.name AS domain_name, u.name AS uploader_name
         FROM research_resources r
         LEFT JOIN research_domains d ON d.id = r.domain_id
         JOIN users u ON u.id = r.uploaded_by
         WHERE r.id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$resource = repo_load_resource($pdo, $id);
if (!$resource) {
    flash('error', 'Resource not found.');
    redirect('/student/repository.php');
}

$isOwner  = ((int)$resource['uploaded_by'] === $userId);
$isPublic = ($resource['status'] === 'Published' && $resource['visibility'] === 'Public');

if (!$isPublic && !$isOwner) {
    flash('error', 'That resource is not available.');
    redirect('/student/repository.php');
}

// ---------------------------------------------------------------------
// Handle edit (owner-only, re-verified server-side)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    require_csrf('/student/repository-details.php?id=' . $id);

    if (!$isOwner) {
        flash('error', 'You can only edit resources you uploaded.');
        redirect('/student/repository-details.php?id=' . $id);
    }

    $title        = trim($_POST['title'] ?? '');
    $description  = nullable_trim($_POST['description'] ?? '');
    $resourceType = $_POST['resource_type'] ?? '';
    $author       = nullable_trim($_POST['author'] ?? '');
    $pubYearRaw   = trim($_POST['publication_year'] ?? '');
    $domainIdRaw  = trim($_POST['domain_id'] ?? '');
    $externalUrl  = trim($_POST['external_url'] ?? '');

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
        $validDomainIds = array_map(fn($d) => (int)$d['id'], all_research_domains($pdo));
        if (ctype_digit($domainIdRaw) && in_array((int)$domainIdRaw, $validDomainIds, true)) {
            $domainId = (int)$domainIdRaw;
        } else {
            $errors[] = 'Please choose a valid research domain.';
        }
    }

    if ($externalUrl !== '' && (!filter_var($externalUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $externalUrl))) {
        $errors[] = 'Please enter a valid http:// or https:// URL.';
    }

    $newFilePath   = $resource['file_path'];
    $oldFileToWipe = null;
    $hasNewFile    = isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!$errors && $hasNewFile) {
        $upload = validate_upload($_FILES['file'], ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'zip'], 15 * 1024 * 1024);
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        } else {
            $storedName = safe_filename($_FILES['file']['name']);
            $destPath   = __DIR__ . '/../uploads/resources/' . $storedName;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                error_log('repository-details.php: failed to move uploaded resource file to ' . $destPath);
                $errors[] = 'Could not save the uploaded file. Please try again.';
            } else {
                $oldFileToWipe = $resource['file_path'];
                $newFilePath   = 'resources/' . $storedName;
            }
        }
    }

    $finalExternalUrl = $externalUrl !== '' ? $externalUrl : null;

    if (!$newFilePath && !$finalExternalUrl) {
        $errors[] = 'The resource must have either a file or an external link.';
        // clean up a freshly uploaded replacement file we can no longer use
        if ($hasNewFile && $newFilePath && $newFilePath !== $resource['file_path']) {
            @unlink(__DIR__ . '/../uploads/' . $newFilePath);
        }
    }

    if ($errors) {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        redirect('/student/repository-details.php?id=' . $id);
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE research_resources
             SET title = ?, description = ?, resource_type = ?, author = ?, publication_year = ?, domain_id = ?, file_path = ?, external_url = ?
             WHERE id = ? AND uploaded_by = ?'
        );
        $stmt->execute([
            $title, $description, $resourceType, $author, $publicationYear, $domainId,
            $newFilePath, $finalExternalUrl, $id, $userId,
        ]);
        if ($oldFileToWipe) {
            @unlink(__DIR__ . '/../uploads/' . $oldFileToWipe);
        }
        log_activity($pdo, $userId, 'resource_updated', 'Updated resource: ' . $title, 'resource', $id);
        flash('success', 'Resource updated.');
    } catch (Throwable $e) {
        error_log('repository-details.php: failed to update research resource #' . $id . ': ' . $e->getMessage());
        // If a replacement file was already moved to disk but the DB write
        // failed, don't leave it orphaned (unreferenced) on disk.
        if ($newFilePath !== $resource['file_path']) {
            @unlink(__DIR__ . '/../uploads/' . $newFilePath);
        }
        flash('error', 'Something went wrong while updating the resource.');
    }
    redirect('/student/repository-details.php?id=' . $id);
}

// ---------------------------------------------------------------------
// Handle delete (owner-only, re-verified server-side)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf('/student/repository-details.php?id=' . $id);

    if (!$isOwner) {
        flash('error', 'You can only delete resources you uploaded.');
        redirect('/student/repository-details.php?id=' . $id);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM research_resources WHERE id = ? AND uploaded_by = ?');
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() > 0) {
            if (!empty($resource['file_path'])) {
                @unlink(__DIR__ . '/../uploads/' . $resource['file_path']);
            }
            log_activity($pdo, $userId, 'resource_deleted', 'Deleted resource: ' . $resource['title'], 'resource', $id);
            flash('success', 'Resource deleted.');
        } else {
            flash('error', 'Resource not found.');
        }
    } catch (Throwable $e) {
        error_log('repository-details.php: failed to delete research resource #' . $id . ': ' . $e->getMessage());
        flash('error', 'Something went wrong while deleting the resource.');
        redirect('/student/repository-details.php?id=' . $id);
    }
    redirect('/student/repository.php');
}

// ---------------------------------------------------------------------
// GET render
// ---------------------------------------------------------------------
$savedStmt = $pdo->prepare('SELECT 1 FROM saved_resources WHERE user_id = ? AND resource_id = ?');
$savedStmt->execute([$userId, $id]);
$isSaved = (bool)$savedStmt->fetchColumn();

$domains = all_research_domains($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($resource['title']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="dashboard-content-grid">
            <section class="dashboard-center">
                <a href="<?= e(url('/student/repository.php')) ?>" class="small text-decoration-none" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Repository</a>

                <div class="dashboard-section mt-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <span class="badge mb-2" style="background-color:var(--uiu-blue); color:#fff;"><?= e($resource['resource_type']) ?></span>
                            <h1 class="h4 fw-bold mb-1"><?= e($resource['title']) ?></h1>
                            <p class="text-muted small mb-0">
                                <?= e($resource['author'] ?: 'Unknown author') ?><?php if (!empty($resource['publication_year'])): ?> &middot; <?= (int)$resource['publication_year'] ?><?php endif; ?>
                            </p>
                        </div>
                        <form method="post" action="<?= e(url('/student/save-resource.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="<?= $isSaved ? 'background-color:var(--uiu-blue); border-color:var(--uiu-blue); color:#fff;' : 'color:var(--uiu-blue); border:1px solid var(--uiu-blue); background:#fff;' ?>">
                                <i class="bi <?= $isSaved ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i> <?= $isSaved ? 'Saved' : 'Save' ?>
                            </button>
                        </form>
                    </div>

                    <p class="mb-3"><?= nl2br(e($resource['description'] ?: 'No description provided.')) ?></p>

                    <ul class="list-unstyled small text-muted mb-3">
                        <li><i class="bi <?= e($typeIcons[$resource['resource_type']] ?? 'bi-file-earmark') ?> me-1"></i><strong>Domain:</strong> <?= e($resource['domain_name'] ?: 'General') ?></li>
                        <li><i class="bi bi-person me-1"></i><strong>Uploaded by:</strong> <?= e($resource['uploader_name']) ?></li>
                        <li><i class="bi bi-calendar3 me-1"></i><strong>Added:</strong> <?= format_date($resource['created_at']) ?></li>
                    </ul>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php if (!empty($resource['external_url'])): ?>
                            <a href="<?= e($resource['external_url']) ?>" target="_blank" rel="noopener" class="profile-button d-inline-flex" style="width:auto;padding:0 14px;"><i class="bi bi-box-arrow-up-right me-1"></i>Open External Link</a>
                        <?php endif; ?>
                        <?php if (!empty($resource['file_path'])): ?>
                            <a href="<?= e(url('/uploads/' . $resource['file_path'])) ?>" class="profile-button d-inline-flex" style="width:auto;padding:0 14px;"><i class="bi bi-download me-1"></i>Download File</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($isOwner): ?>
                        <div class="d-flex gap-2 mb-3">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editResourceForm">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="post" action="<?= e(url('/student/repository-details.php?id=' . $id)) ?>" data-confirm="Delete this resource? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        </div>

                        <div class="collapse" id="editResourceForm">
                            <div class="right-dashboard-card mb-3" style="max-width:520px;">
                                <h2 class="h6">Edit Resource</h2>
                                <form method="post" action="<?= e(url('/student/repository-details.php?id=' . $id)) ?>" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <div>
                                        <label class="form-label small mb-1">Title *</label>
                                        <input type="text" name="title" value="<?= e($resource['title']) ?>" class="form-control form-control-sm" required maxlength="250">
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">Description</label>
                                        <textarea name="description" class="form-control form-control-sm" rows="3"><?= e($resource['description']) ?></textarea>
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">Type *</label>
                                        <select name="resource_type" class="form-select form-select-sm" required>
                                            <?php foreach ($allowedTypes as $t): ?>
                                                <option value="<?= e($t) ?>" <?= $resource['resource_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">Author</label>
                                        <input type="text" name="author" value="<?= e($resource['author']) ?>" class="form-control form-control-sm" maxlength="200">
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">Publication Year</label>
                                        <input type="number" name="publication_year" value="<?= e((string)($resource['publication_year'] ?? '')) ?>" class="form-control form-control-sm" min="1900" max="<?= (int)date('Y') + 1 ?>">
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">Research Domain</label>
                                        <select name="domain_id" class="form-select form-select-sm">
                                            <option value="">General / None</option>
                                            <?php foreach ($domains as $d): ?>
                                                <option value="<?= (int)$d['id'] ?>" <?= (int)($resource['domain_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">Replace File</label>
                                        <input type="file" name="file" class="form-control form-control-sm">
                                        <div class="form-text">
                                            <?= $resource['file_path'] ? 'Leave blank to keep the current file.' : 'PDF, DOC(X), PPT(X), XLS(X), CSV, or ZIP — max 15MB.' ?>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1">External Link</label>
                                        <input type="url" name="external_url" value="<?= e($resource['external_url']) ?>" class="form-control form-control-sm" placeholder="https://...">
                                    </div>
                                    <button type="submit" class="update-profile-button border-0 mt-2">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
