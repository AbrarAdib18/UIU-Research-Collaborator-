<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$oppId  = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM research_opportunities WHERE id = ? AND created_by = ?');
$stmt->execute([$oppId, $userId]);
$opportunity = $stmt->fetch();

if (!$opportunity) {
    flash('error', 'That opportunity does not exist or is not yours to edit.');
    redirect('/faculty/opportunities.php');
}

$domains = all_research_domains($pdo);
$domStmt = $pdo->prepare('SELECT domain_id FROM opportunity_domains WHERE opportunity_id = ?');
$domStmt->execute([$oppId]);
$selectedDomainIds = array_map('intval', $domStmt->fetchAll(PDO::FETCH_COLUMN));

$verificationRequired = get_platform_setting($pdo, 'faculty_verification_required', '0') === '1';
$verStmt = $pdo->prepare("SELECT status FROM faculty_verifications WHERE faculty_user_id = ?");
$verStmt->execute([$userId]);
$isVerified = $verStmt->fetchColumn() === 'verified';
$canPublish = !$verificationRequired || $isVerified;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/opportunity-edit.php?id=' . $oppId);

    $title       = trim($_POST['title'] ?? '');
    $description = nullable_trim($_POST['description'] ?? '');
    $problem     = nullable_trim($_POST['problem_statement'] ?? '');
    $requirements = nullable_trim($_POST['requirements'] ?? '');
    $projectType = nullable_trim($_POST['project_type'] ?? '');
    $teamMin     = max(1, (int)($_POST['team_size_min'] ?? 2));
    $teamMax     = max($teamMin, (int)($_POST['team_size_max'] ?? 6));
    $deadline    = trim($_POST['deadline'] ?? '');
    $deadline    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) ? $deadline : null;
    $status      = in_array($_POST['status'] ?? '', ['Draft', 'Open', 'Closed', 'Completed'], true) ? $_POST['status'] : $opportunity['status'];
    if ($status === 'Open' && !$canPublish) {
        $status = 'Draft';
        flash('warning', 'Your account is not yet verified, so this was saved as a Draft instead of Open.');
    }
    $visibility  = in_array($_POST['visibility'] ?? '', ['Public', 'Community', 'Private'], true) ? $_POST['visibility'] : 'Public';
    $selectedDomains = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['domains'] ?? [])))));

    if ($title === '') {
        flash('error', 'Title is required.');
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE research_opportunities SET title=?, description=?, problem_statement=?, requirements=?, project_type=?, team_size_min=?, team_size_max=?, deadline=?, status=?, visibility=? WHERE id=? AND created_by=?'
            )->execute([$title, $description, $problem, $requirements, $projectType, $teamMin, $teamMax, $deadline, $status, $visibility, $oppId, $userId]);

            $pdo->prepare('DELETE FROM opportunity_domains WHERE opportunity_id = ?')->execute([$oppId]);
            if ($selectedDomains) {
                $domIns = $pdo->prepare('INSERT IGNORE INTO opportunity_domains (opportunity_id, domain_id) VALUES (?, ?)');
                foreach ($selectedDomains as $dId) {
                    $domIns->execute([$oppId, $dId]);
                }
            }
            $pdo->commit();
            log_activity($pdo, $userId, 'opportunity_updated', 'Updated opportunity "' . $title . '"', 'opportunity', $oppId);
            flash('success', 'Opportunity updated.');
            redirect('/faculty/opportunity-details.php?id=' . $oppId);
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('faculty opportunity-edit: ' . $ex->getMessage());
            flash('error', 'Could not update opportunity. Please try again.');
        }
    }
    // Fall through to re-render with posted values on error.
    $opportunity = array_merge($opportunity, compact('title', 'description', 'projectType', 'teamMin', 'teamMax', 'deadline', 'status', 'visibility'));
    $opportunity['problem_statement'] = $problem;
    $opportunity['requirements'] = $requirements;
    $opportunity['project_type'] = $projectType;
    $opportunity['team_size_min'] = $teamMin;
    $opportunity['team_size_max'] = $teamMax;
    $selectedDomainIds = $selectedDomains;
}

$pageTitle = 'Edit Opportunity';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Opportunity || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:22px;margin-bottom:18px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .domain-check-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/faculty/opportunities.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Opportunities</a>

        <div class="app-panel">
            <h2 style="color:var(--uiu-blue);font-size:20px;font-weight:700;">Edit Opportunity</h2>
            <form method="post" action="<?= e(url('/faculty/opportunity-edit.php?id=' . $oppId)) ?>">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="<?= e($opportunity['title']) ?>" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e($opportunity['description']) ?></textarea></div>
                <div class="mb-3"><label class="form-label">Problem Statement</label><textarea name="problem_statement" class="form-control" rows="3"><?= e($opportunity['problem_statement']) ?></textarea></div>
                <div class="mb-3"><label class="form-label">Requirements</label><textarea name="requirements" class="form-control" rows="3"><?= e($opportunity['requirements']) ?></textarea></div>
                <div class="row mb-3">
                    <div class="col-md-3"><label class="form-label">Project Type</label><input type="text" name="project_type" class="form-control" value="<?= e($opportunity['project_type']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Team Size Min</label><input type="number" min="1" name="team_size_min" class="form-control" value="<?= (int)$opportunity['team_size_min'] ?>"></div>
                    <div class="col-md-3"><label class="form-label">Team Size Max</label><input type="number" min="1" name="team_size_max" class="form-control" value="<?= (int)$opportunity['team_size_max'] ?>"></div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['Draft', 'Open', 'Closed', 'Completed'] as $s): ?>
                                <option value="<?= e($s) ?>" <?= $opportunity['status'] === $s ? 'selected' : '' ?> <?= ($s === 'Open' && !$canPublish) ? 'disabled' : '' ?>><?= e($s) ?><?= ($s === 'Open' && !$canPublish) ? ' (requires verification)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$canPublish): ?><div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i> Your faculty account must be verified before this opportunity can be Open.</div><?php endif; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label">Application Deadline</label><input type="date" name="deadline" class="form-control" value="<?= e($opportunity['deadline']) ?>"></div>
                    <div class="col-md-6">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" class="form-select">
                            <?php foreach (['Public', 'Community', 'Private'] as $v): ?>
                                <option value="<?= e($v) ?>" <?= $opportunity['visibility'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Research Domains</label>
                    <div class="domain-check-grid">
                        <?php foreach ($domains as $d): ?>
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input" name="domains[]" value="<?= (int)$d['id'] ?>" <?= in_array((int)$d['id'], $selectedDomainIds, true) ? 'checked' : '' ?>>
                                <?= e($d['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-uiu"><i class="bi bi-check-lg"></i> Save Changes</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
