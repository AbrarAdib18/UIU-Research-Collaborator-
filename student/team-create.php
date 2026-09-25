<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$defaultTeamSizeLimit = (int)get_platform_setting($pdo, 'default_team_size_limit', '6');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/student/team-create.php');

    $name         = trim((string)($_POST['name'] ?? ''));
    $description  = nullable_trim($_POST['description'] ?? '');
    $domainId     = ($_POST['research_domain_id'] ?? '') !== '' ? (int)$_POST['research_domain_id'] : null;
    $opportunityId = ($_POST['opportunity_id'] ?? '') !== '' ? (int)$_POST['opportunity_id'] : null;
    $teamSizeLimit = (int)($_POST['team_size_limit'] ?? $defaultTeamSizeLimit);

    $errors = [];
    if ($name === '') {
        $errors[] = 'Team name is required.';
    }
    if ($teamSizeLimit < 2 || $teamSizeLimit > 15) {
        $errors[] = 'Team size limit must be between 2 and 15.';
    }
    if ($domainId !== null) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM research_domains WHERE id = ?');
        $chk->execute([$domainId]);
        if (!$chk->fetchColumn()) {
            $domainId = null;
        }
    }
    if ($opportunityId !== null) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM research_opportunities WHERE id = ? AND status = 'Open'");
        $chk->execute([$opportunityId]);
        if (!$chk->fetchColumn()) {
            $opportunityId = null;
        }
    }

    if ($errors) {
        set_old($_POST);
        flash('error', implode(' ', $errors));
        redirect('/student/team-create.php');
    }

    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare(
            "INSERT INTO research_teams (name, description, created_by, opportunity_id, research_domain_id, team_size_limit, status)
             VALUES (?, ?, ?, ?, ?, ?, 'Forming')"
        );
        $ins->execute([$name, $description, $userId, $opportunityId, $domainId, $teamSizeLimit]);
        $teamId = (int)$pdo->lastInsertId();

        $memberIns = $pdo->prepare(
            "INSERT INTO team_members (team_id, user_id, role, status) VALUES (?, ?, 'Leader', 'Active')"
        );
        $memberIns->execute([$teamId, $userId]);

        $pdo->commit();

        log_activity($pdo, $userId, 'team_created', "Created the team \"{$name}\".", 'team', $teamId);

        flash('success', 'Team created successfully.');
        redirect('/student/team-details.php?id=' . $teamId);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('team-create.php: ' . $ex->getMessage());
        set_old($_POST);
        flash('error', 'Something went wrong while creating the team. Please try again.');
        redirect('/student/team-create.php');
    }
}

$domains = all_research_domains($pdo);
$openOpportunities = $pdo->query("SELECT id, title FROM research_opportunities WHERE status = 'Open' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Team || UIU ResearchCollab</title>
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
                <h2>Create a Team</h2>
                <p>Start a new research team and invite collaborators.</p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <div class="app-panel">
            <form action="<?= e(url('/student/team-create.php')) ?>" method="post">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Team Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="200" required value="<?= e(old('name')) ?>">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="What is this team researching?"><?= e(old('description')) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="research_domain_id" class="form-label">Research Domain</label>
                        <select class="form-select" id="research_domain_id" name="research_domain_id">
                            <option value="">— Not specified —</option>
                            <?php foreach ($domains as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= (string)old('research_domain_id') === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="team_size_limit" class="form-label">Team Size Limit</label>
                        <input type="number" class="form-control" id="team_size_limit" name="team_size_limit" min="2" max="15" value="<?= e(old('team_size_limit', $defaultTeamSizeLimit)) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="opportunity_id" class="form-label">Linked Opportunity (optional)</label>
                    <select class="form-select" id="opportunity_id" name="opportunity_id">
                        <option value="">— None —</option>
                        <?php foreach ($openOpportunities as $opp): ?>
                            <option value="<?= (int)$opp['id'] ?>" <?= (string)old('opportunity_id') === (string)$opp['id'] ? 'selected' : '' ?>><?= e($opp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-uiu"><i class="bi bi-check-lg"></i> Create Team</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
