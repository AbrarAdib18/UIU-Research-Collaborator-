<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

/**
 * Research Repository — browse/search/filter Public + Published resources
 * and add a new resource. Students-Only / Private / Draft resources are
 * intentionally out of scope for this public browse list (a simple rule
 * for this milestone: only fully public, published resources are listed).
 */

$pdo    = db();
$userId = (int)$currentUser['id'];

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

// ---------------------------------------------------------------------
// Handle "Add Resource" submission
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_resource') {
    require_csrf('/student/repository.php');

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
        $externalUrl = '';
    }

    $hasFile = isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!$hasFile && $externalUrl === '') {
        $errors[] = 'Provide either a file upload or an external link.';
    }

    $storedRelativePath = null;
    if (!$errors && $hasFile) {
        $upload = validate_upload($_FILES['file'], ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'zip'], 15 * 1024 * 1024);
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        } else {
            $storedName = safe_filename($_FILES['file']['name']);
            $destPath   = __DIR__ . '/../uploads/resources/' . $storedName;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                error_log('repository.php: failed to move uploaded resource file to ' . $destPath);
                $errors[] = 'Could not save the uploaded file. Please try again.';
            } else {
                $storedRelativePath = 'resources/' . $storedName;
            }
        }
    }

    if ($errors) {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        set_old($_POST);
        redirect('/student/repository.php');
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO research_resources
                (uploaded_by, title, description, resource_type, author, publication_year, domain_id, file_path, external_url, visibility, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Public', 'Published')"
        );
        $stmt->execute([
            $userId,
            $title,
            $description,
            $resourceType,
            $author,
            $publicationYear,
            $domainId,
            $storedRelativePath,
            $externalUrl !== '' ? $externalUrl : null,
        ]);
        $newId = (int)$pdo->lastInsertId();
        log_activity($pdo, $userId, 'resource_uploaded', 'Added resource: ' . $title, 'resource', $newId);
        flash('success', 'Resource added to the repository.');
    } catch (Throwable $e) {
        error_log('repository.php: failed to create research resource: ' . $e->getMessage());
        if ($storedRelativePath) {
            @unlink(__DIR__ . '/../uploads/' . $storedRelativePath);
        }
        flash('error', 'Something went wrong while saving the resource. Please try again.');
    }

    redirect('/student/repository.php');
}

// ---------------------------------------------------------------------
// Filters + listing
// ---------------------------------------------------------------------
$q            = trim($_GET['q'] ?? '');
$typeFilter   = $_GET['resource_type'] ?? '';
$yearFilter   = trim($_GET['publication_year'] ?? '');
$domainFilter = trim($_GET['domain_id'] ?? '');

$where  = ["r.status = 'Published'", "r.visibility = 'Public'"];
$params = [];

if ($q !== '') {
    $where[]  = '(r.title LIKE ? OR r.author LIKE ? OR r.description LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($typeFilter !== '' && in_array($typeFilter, $allowedTypes, true)) {
    $where[]  = 'r.resource_type = ?';
    $params[] = $typeFilter;
}
if ($yearFilter !== '' && ctype_digit($yearFilter)) {
    $where[]  = 'r.publication_year = ?';
    $params[] = (int)$yearFilter;
}
if ($domainFilter !== '' && ctype_digit($domainFilter)) {
    $where[]  = 'r.domain_id = ?';
    $params[] = (int)$domainFilter;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM research_resources r WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$perPage    = 12;
$page       = current_page();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = paginate_offset($page, $perPage);

$sql = "SELECT r.*, d.name AS domain_name,
               EXISTS(SELECT 1 FROM saved_resources sr WHERE sr.user_id = ? AND sr.resource_id = r.id) AS is_saved
        FROM research_resources r
        LEFT JOIN research_domains d ON d.id = r.domain_id
        WHERE $whereSql
        ORDER BY r.created_at DESC
        LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$userId], $params));
$resources = $stmt->fetchAll();

$years = $pdo->query(
    "SELECT DISTINCT publication_year FROM research_resources
     WHERE status = 'Published' AND visibility = 'Public' AND publication_year IS NOT NULL
     ORDER BY publication_year DESC"
)->fetchAll(PDO::FETCH_COLUMN);

$domains = all_research_domains($pdo);
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
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="dashboard-content-grid">
            <section class="dashboard-center">

                <div class="welcome-section">
                    <h2>Research Repository</h2>
                    <p>Browse papers, datasets, and other resources shared by fellow researchers.</p>
                </div>

                <section class="dashboard-section">
                    <div class="section-title-row">
                        <h2>Resources</h2>
                        <span class="text-muted small"><?= $total ?> resource<?= $total === 1 ? '' : 's' ?></span>
                    </div>

                    <form method="get" action="<?= e(url('/student/repository.php')) ?>" class="row g-2 mb-3">
                        <div class="col-12 col-md-4">
                            <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Search by title, author, or description">
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="resource_type" class="form-select form-select-sm">
                                <option value="">All Types</option>
                                <?php foreach ($allowedTypes as $t): ?>
                                    <option value="<?= e($t) ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="publication_year" class="form-select form-select-sm">
                                <option value="">All Years</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?= e((string)$y) ?>" <?= $yearFilter === (string)$y ? 'selected' : '' ?>><?= e((string)$y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="domain_id" class="form-select form-select-sm">
                                <option value="">All Domains</option>
                                <?php foreach ($domains as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>" <?= $domainFilter === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-sm flex-fill" style="background-color: var(--uiu-blue); border-color: var(--uiu-blue); color:#fff;">Filter</button>
                            <a href="<?= e(url('/student/repository.php')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                        </div>
                    </form>

                    <?php if ($resources): ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                            <?php foreach ($resources as $r): ?>
                                <?php
                                    $metaParts = [];
                                    if (!empty($r['author'])) {
                                        $metaParts[] = $r['author'];
                                    }
                                    if (!empty($r['publication_year'])) {
                                        $metaParts[] = (string)$r['publication_year'];
                                    }
                                    $meta = $metaParts ? implode(' · ', $metaParts) : 'Unknown author';
                                ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge" style="background-color: var(--uiu-blue); color:#fff;"><?= e($r['resource_type']) ?></span>
                                                <form method="post" action="<?= e(url('/student/save-resource.php')) ?>" class="m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="resource_id" value="<?= (int)$r['id'] ?>">
                                                    <button type="submit" class="btn btn-link p-0 border-0" style="color: var(--uiu-blue);" aria-label="<?= $r['is_saved'] ? 'Unsave' : 'Save' ?>" title="<?= $r['is_saved'] ? 'Unsave' : 'Save' ?>">
                                                        <i class="bi <?= $r['is_saved'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>" style="font-size:18px;"></i>
                                                    </button>
                                                </form>
                                            </div>

                                            <?php if (!empty($r['thumbnail'])): ?>
                                                <img src="<?= e(url('/uploads/resources/' . $r['thumbnail'])) ?>" alt="" class="mb-2 rounded" style="width:100%; height:110px; object-fit:cover;">
                                            <?php else: ?>
                                                <div class="mb-2 d-flex align-items-center justify-content-center rounded" style="height:80px; background-color: var(--uiu-light-blue);">
                                                    <i class="bi <?= e($typeIcons[$r['resource_type']] ?? 'bi-file-earmark') ?>" style="font-size:32px; color: var(--uiu-blue);"></i>
                                                </div>
                                            <?php endif; ?>

                                            <h3 class="h6 fw-bold mb-1"><?= e($r['title']) ?></h3>
                                            <p class="text-muted small mb-1 text-truncate"><?= e($meta) ?></p>
                                            <p class="text-muted small mb-2"><?= e($r['domain_name'] ?: 'General') ?></p>

                                            <div class="mt-auto">
                                                <a href="<?= e(url('/student/repository-details.php?id=' . $r['id'])) ?>" class="profile-button d-inline-flex">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="app-pagination" aria-label="Repository pagination">
                                <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo; Prev</a><?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p === $page): ?>
                                        <span class="active"><?= $p ?></span>
                                    <?php else: ?>
                                        <a href="<?= e(page_url($p)) ?>"><?= $p ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1)) ?>">Next &raquo;</a><?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="app-empty-state">
                            <i class="bi bi-database"></i>
                            <p>No resources match your filters yet.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <!-- RIGHT SIDEBAR -->
            <aside class="dashboard-right-sidebar">
                <div class="right-dashboard-card">
                    <h2>Add a Resource</h2>
                    <form method="post" action="<?= e(url('/student/repository.php')) ?>" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_resource">

                        <div>
                            <label class="form-label small mb-1">Title *</label>
                            <input type="text" name="title" value="<?= e(old('title')) ?>" class="form-control form-control-sm" required maxlength="250">
                        </div>
                        <div>
                            <label class="form-label small mb-1">Description</label>
                            <textarea name="description" class="form-control form-control-sm" rows="3"><?= e(old('description')) ?></textarea>
                        </div>
                        <div>
                            <label class="form-label small mb-1">Type *</label>
                            <select name="resource_type" class="form-select form-select-sm" required>
                                <option value="">Choose...</option>
                                <?php foreach ($allowedTypes as $t): ?>
                                    <option value="<?= e($t) ?>" <?= old('resource_type') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1">Author</label>
                            <input type="text" name="author" value="<?= e(old('author')) ?>" class="form-control form-control-sm" maxlength="200">
                        </div>
                        <div>
                            <label class="form-label small mb-1">Publication Year</label>
                            <input type="number" name="publication_year" value="<?= e(old('publication_year')) ?>" class="form-control form-control-sm" min="1900" max="<?= (int)date('Y') + 1 ?>">
                        </div>
                        <div>
                            <label class="form-label small mb-1">Research Domain</label>
                            <select name="domain_id" class="form-select form-select-sm">
                                <option value="">General / None</option>
                                <?php foreach ($domains as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>" <?= old('domain_id') === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1">File Upload</label>
                            <input type="file" name="file" class="form-control form-control-sm">
                            <div class="form-text">PDF, DOC(X), PPT(X), XLS(X), CSV, or ZIP — max 15MB.</div>
                        </div>
                        <div class="text-center text-muted small">— or —</div>
                        <div>
                            <label class="form-label small mb-1">External Link</label>
                            <input type="url" name="external_url" value="<?= e(old('external_url')) ?>" class="form-control form-control-sm" placeholder="https://...">
                        </div>
                        <button type="submit" class="update-profile-button border-0 mt-2">Add Resource</button>
                    </form>
                </div>
            </aside>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
