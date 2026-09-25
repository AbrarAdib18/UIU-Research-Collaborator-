<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$domains = all_research_domains($pdo);

$verificationRequired = get_platform_setting($pdo, 'faculty_verification_required', '0') === '1';
$verStmt = $pdo->prepare("SELECT status FROM faculty_verifications WHERE faculty_user_id = ?");
$verStmt->execute([$userId]);
$isVerified = $verStmt->fetchColumn() === 'verified';
$canPublish = !$verificationRequired || $isVerified;

$old = [
    'title' => '', 'description' => '', 'problem_statement' => '', 'requirements' => '',
    'project_type' => '', 'team_size_min' => 2, 'team_size_max' => 6, 'deadline' => '',
    'visibility' => 'Public', 'domains' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/opportunity-create.php');

    $title       = trim($_POST['title'] ?? '');
    $description = nullable_trim($_POST['description'] ?? '');
    $problem     = nullable_trim($_POST['problem_statement'] ?? '');
    $requirements = nullable_trim($_POST['requirements'] ?? '');
    $projectType = nullable_trim($_POST['project_type'] ?? '');
    $teamMin     = max(1, (int)($_POST['team_size_min'] ?? 2));
    $teamMax     = max($teamMin, (int)($_POST['team_size_max'] ?? 6));
    $deadline    = trim($_POST['deadline'] ?? '');
    $deadline    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) ? $deadline : null;
    $visibility  = in_array($_POST['visibility'] ?? '', ['Public', 'Community', 'Private'], true) ? $_POST['visibility'] : 'Public';
    $publishNow  = isset($_POST['publish_now']) && $canPublish;
    $selectedDomains = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['domains'] ?? [])))));

    $old = compact('title', 'description', 'problem', 'requirements', 'projectType', 'teamMin', 'teamMax', 'deadline', 'visibility');
    $old['domains'] = $selectedDomains;

    if ($title === '') {
        flash('error', 'Title is required.');
    } else {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                'INSERT INTO research_opportunities (created_by, title, description, problem_statement, requirements, project_type, team_size_min, team_size_max, deadline, status, visibility) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([$userId, $title, $description, $problem, $requirements, $projectType, $teamMin, $teamMax, $deadline, $publishNow ? 'Open' : 'Draft', $visibility]);
            $oppId = (int)$pdo->lastInsertId();

            if ($selectedDomains) {
                $domIns = $pdo->prepare('INSERT IGNORE INTO opportunity_domains (opportunity_id, domain_id) VALUES (?, ?)');
                foreach ($selectedDomains as $dId) {
                    $domIns->execute([$oppId, $dId]);
                }
            }
            $pdo->commit();

            log_activity($pdo, $userId, 'opportunity_created', 'Created opportunity "' . $title . '"', 'opportunity', $oppId);
            flash('success', 'Opportunity created' . ($publishNow ? ' and published.' : ' as a draft.'));
            redirect('/faculty/opportunity-details.php?id=' . $oppId);
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('faculty opportunity-create: ' . $ex->getMessage());
            flash('error', 'Could not create opportunity. Please try again.');
        }
    }
}

$pageTitle = 'Create Opportunity';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Opportunity || UIU ResearchCollab</title>
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
            <h2 style="color:var(--uiu-blue);font-size:20px;font-weight:700;">Create Research Opportunity</h2>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="<?= e($old['title']) ?>" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e($old['description']) ?></textarea></div>
                <div class="mb-3"><label class="form-label">Problem Statement</label><textarea name="problem_statement" class="form-control" rows="3"><?= e($old['problem_statement'] ?? '') ?></textarea></div>
                <div class="mb-3"><label class="form-label">Requirements</label><textarea name="requirements" class="form-control" rows="3"><?= e($old['requirements'] ?? '') ?></textarea></div>
                <div class="row mb-3">
                    <div class="col-md-4"><label class="form-label">Project Type</label><input type="text" name="project_type" class="form-control" placeholder="e.g. FYDP, Research Paper" value="<?= e($old['project_type'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Team Size Min</label><input type="number" min="1" name="team_size_min" class="form-control" value="<?= (int)($old['team_size_min'] ?? $old['teamMin'] ?? 2) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Team Size Max</label><input type="number" min="1" name="team_size_max" class="form-control" value="<?= (int)($old['team_size_max'] ?? $old['teamMax'] ?? 6) ?>"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label">Application Deadline</label><input type="date" name="deadline" class="form-control" value="<?= e($old['deadline'] ?? '') ?>"></div>
                    <div class="col-md-6">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" class="form-select">
                            <?php foreach (['Public', 'Community', 'Private'] as $v): ?>
                                <option value="<?= e($v) ?>" <?= ($old['visibility'] ?? 'Public') === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Research Domains</label>
                    <div class="domain-check-grid">
                        <?php foreach ($domains as $d): ?>
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input" name="domains[]" value="<?= (int)$d['id'] ?>" <?= in_array((int)$d['id'], $old['domains'] ?? [], true) ? 'checked' : '' ?>>
                                <?= e($d['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" name="publish_now" id="publishNow" value="1" <?= !$canPublish ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="publishNow">Publish immediately (otherwise saved as a draft)</label>
                    <?php if (!$canPublish): ?>
                        <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i> Your faculty account must be verified by an administrator before you can publish opportunities. You can still save this as a draft.</div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-uiu"><i class="bi bi-check-lg"></i> Save Opportunity</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
