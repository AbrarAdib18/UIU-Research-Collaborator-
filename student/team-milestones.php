<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    flash('error', 'That team could not be found.');
    redirect('/student/teams.php');
}
if (!is_team_member($pdo, $teamId, $userId)) {
    flash('error', "You don't have access to this team's workspace.");
    redirect('/student/teams.php');
}
$isLeader = is_team_leader($pdo, $teamId, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $backUrl = '/student/team-milestones.php?id=' . $teamId;

    if ($action === 'create') {
        require_csrf($backUrl);

        $title       = trim((string)($_POST['title'] ?? ''));
        $description = nullable_trim($_POST['description'] ?? '');
        $dueDate     = nullable_trim($_POST['due_date'] ?? '');

        if ($dueDate !== null && !strtotime($dueDate)) {
            $dueDate = null;
        }
        if ($title === '') {
            flash('error', 'Milestone title is required.');
            redirect($backUrl);
        }

        try {
            $ins = $pdo->prepare(
                "INSERT INTO team_milestones (team_id, title, description, due_date, status)
                 VALUES (?, ?, ?, ?, 'Pending')"
            );
            $ins->execute([$teamId, $title, $description, $dueDate]);

            log_activity($pdo, $userId, 'milestone_created', "Created milestone \"{$title}\" in \"{$team['name']}\".", 'team', $teamId);
            flash('success', 'Milestone created.');
        } catch (Throwable $ex) {
            error_log('team-milestones.php create: ' . $ex->getMessage());
            flash('error', 'Could not create the milestone. Please try again.');
        }
        redirect($backUrl);
    }

    if ($action === 'update-status') {
        require_csrf($backUrl);

        $milestoneId = (int)($_POST['milestone_id'] ?? 0);
        $status      = $_POST['status'] ?? 'Pending';

        $msChk = $pdo->prepare('SELECT * FROM team_milestones WHERE id = ? AND team_id = ?');
        $msChk->execute([$milestoneId, $teamId]);
        $milestone = $msChk->fetch();

        if (!$milestone) {
            flash('error', 'That milestone could not be found.');
            redirect($backUrl);
        }
        if (!in_array($status, ['Pending', 'In Progress', 'Completed', 'Delayed'], true)) {
            $status = $milestone['status'];
        }

        $upd = $pdo->prepare('UPDATE team_milestones SET status = ? WHERE id = ?');
        $upd->execute([$status, $milestoneId]);

        log_activity($pdo, $userId, 'milestone_updated', "Updated milestone \"{$milestone['title']}\" in \"{$team['name']}\".", 'team', $teamId);
        flash('success', 'Milestone updated.');
        redirect($backUrl);
    }

    // Schema-driven decision: team_milestones has no created_by column, so
    // (per spec) any active team member may delete a milestone — small
    // teams, low stakes.
    if ($action === 'delete') {
        require_csrf($backUrl);

        $milestoneId = (int)($_POST['milestone_id'] ?? 0);
        $msChk = $pdo->prepare('SELECT * FROM team_milestones WHERE id = ? AND team_id = ?');
        $msChk->execute([$milestoneId, $teamId]);
        $milestone = $msChk->fetch();

        if (!$milestone) {
            flash('error', 'That milestone could not be found.');
            redirect($backUrl);
        }

        $del = $pdo->prepare('DELETE FROM team_milestones WHERE id = ?');
        $del->execute([$milestoneId]);

        log_activity($pdo, $userId, 'milestone_deleted', "Deleted milestone \"{$milestone['title']}\" in \"{$team['name']}\".", 'team', $teamId);
        flash('success', 'Milestone deleted.');
        redirect($backUrl);
    }

    flash('error', 'Unknown action.');
    redirect($backUrl);
}

$milestonesStmt = $pdo->prepare(
    "SELECT * FROM team_milestones WHERE team_id = ? ORDER BY due_date IS NULL, due_date ASC, created_at DESC"
);
$milestonesStmt->execute([$teamId]);
$milestones = $milestonesStmt->fetchAll();

function milestone_status_pill(string $status): string
{
    return match ($status) {
        'Pending'     => 'pill pill-gray',
        'In Progress' => 'pill pill-blue',
        'Completed'   => 'pill pill-green',
        'Delayed'     => 'pill pill-red',
        default       => 'pill pill-gray',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milestones - <?= e($team['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-blue{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .team-subnav{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 20px;padding-bottom:14px;border-bottom:1px solid var(--border-color)}
        .team-subnav a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
        .team-subnav a:hover{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .team-subnav a.active{background:var(--uiu-blue);color:#fff}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .timeline-item{position:relative;padding-left:24px;padding-bottom:18px;border-left:2px solid var(--border-color);margin-left:6px}
        .timeline-item:last-child{border-color:transparent;padding-bottom:0}
        .timeline-dot{position:absolute;left:-7px;top:2px;width:12px;height:12px;border-radius:50%;background:var(--uiu-blue)}
        .timeline-content .ms-title{font-weight:700;font-size:14px}
        .timeline-content .ms-meta{font-size:12px;color:var(--text-light);margin:2px 0 6px}
        .ms-inline-form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:6px}
        .ms-inline-form select{font-size:12px;padding:3px 6px;border-radius:6px;border:1px solid var(--border-color)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2><?= e($team['name']) ?> — Milestones</h2>
                <p>Track major goals and deadlines for this team.</p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <nav class="team-subnav">
            <a href="<?= e(url('/student/team-details.php?id=' . $teamId)) ?>"><i class="bi bi-info-circle"></i> Overview</a>
            <a href="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>"><i class="bi bi-list-check"></i> Tasks</a>
            <a class="active" href="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>"><i class="bi bi-flag"></i> Milestones</a>
            <a href="<?= e(url('/student/team-files.php?id=' . $teamId)) ?>"><i class="bi bi-folder"></i> Files</a>
            <a href="<?= e(url('/student/team-messages.php?id=' . $teamId)) ?>"><i class="bi bi-chat-dots"></i> Messages</a>
        </nav>

        <div class="app-panel">
            <h3>New Milestone</h3>
            <form action="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="row g-2">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="title" maxlength="200" placeholder="Milestone title" required>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="due_date">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="description" maxlength="500" placeholder="Description (optional)">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-uiu w-100"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="app-panel">
            <h3>Milestones (<?= count($milestones) ?>)</h3>
            <?php if ($milestones): ?>
                <?php foreach ($milestones as $ms): ?>
                    <div class="timeline-item">
                        <span class="timeline-dot"></span>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="ms-title"><?= e($ms['title']) ?></div>
                                    <div class="ms-meta">Due <?= format_date($ms['due_date']) ?></div>
                                    <?php if ($ms['description']): ?><p class="small mb-0"><?= nl2br(e($ms['description'])) ?></p><?php endif; ?>
                                </div>
                                <span class="<?= milestone_status_pill($ms['status']) ?>"><?= e($ms['status']) ?></span>
                            </div>

                            <form action="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>" method="post" class="ms-inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update-status">
                                <input type="hidden" name="milestone_id" value="<?= (int)$ms['id'] ?>">
                                <select name="status">
                                    <?php foreach (['Pending', 'In Progress', 'Completed', 'Delayed'] as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $ms['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                            </form>
                            <form action="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this milestone?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="milestone_id" value="<?= (int)$ms['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger mt-1"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-flag"></i><p>No milestones yet. Add one above to get started.</p></div>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
