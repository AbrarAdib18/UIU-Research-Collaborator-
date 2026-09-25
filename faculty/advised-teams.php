<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// -----------------------------------------------------------------
// Single team workspace view
// -----------------------------------------------------------------
if ($teamId > 0) {
    if (!is_team_advisor($pdo, $teamId, $userId)) {
        flash('error', "You are not the assigned advisor for this team, so you can't access its workspace.");
        redirect('/faculty/advised-teams.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf('/faculty/advised-teams.php?team_id=' . $teamId);
        $content = nullable_trim($_POST['content'] ?? '');
        if ($content === null) {
            flash('error', 'Please write your guidance before posting.');
        } else {
            try {
                $assignStmt = $pdo->prepare("SELECT id FROM advisor_assignments WHERE team_id=? AND faculty_user_id=? AND status='active' LIMIT 1");
                $assignStmt->execute([$teamId, $userId]);
                $assignmentId = (int)$assignStmt->fetchColumn();

                $pdo->prepare('INSERT INTO advisor_feedback (assignment_id, faculty_user_id, team_id, title, content, visibility) VALUES (?,?,?,?,?,\'team\')')
                    ->execute([$assignmentId, $userId, $teamId, nullable_trim($_POST['title'] ?? ''), $content]);

                $members = $pdo->prepare("SELECT user_id FROM team_members WHERE team_id = ? AND status = 'Active'");
                $members->execute([$teamId]);
                foreach ($members->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
                    create_notification($pdo, (int)$memberId, 'advisor_assignment', 'New Advisor Feedback', $currentUser['name'] . ' posted guidance for your team.', 'team', $teamId);
                }
                log_activity($pdo, $userId, 'advisor_feedback_posted', 'Posted guidance for team', 'team', $teamId);
                flash('success', 'Guidance posted.');
            } catch (Throwable $ex) {
                error_log('advised-teams post feedback: ' . $ex->getMessage());
                flash('error', 'Could not post guidance.');
            }
        }
        redirect('/faculty/advised-teams.php?team_id=' . $teamId);
    }

    $teamStmt = $pdo->prepare('SELECT rt.*, d.name AS domain_name FROM research_teams rt LEFT JOIN research_domains d ON d.id = rt.research_domain_id WHERE rt.id = ?');
    $teamStmt->execute([$teamId]);
    $team = $teamStmt->fetch();
    if (!$team) {
        flash('error', 'Team not found.');
        redirect('/faculty/advised-teams.php');
    }

    $memStmt = $pdo->prepare(
        "SELECT tm.*, u.name, sp.department FROM team_members tm JOIN users u ON u.id = tm.user_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id WHERE tm.team_id = ? AND tm.status='Active' ORDER BY FIELD(tm.role,'Leader','Member')"
    );
    $memStmt->execute([$teamId]);
    $members = $memStmt->fetchAll();

    $taskStmt = $pdo->prepare("SELECT * FROM team_tasks WHERE team_id = ? ORDER BY due_date IS NULL, due_date ASC LIMIT 8");
    $taskStmt->execute([$teamId]);
    $tasks = $taskStmt->fetchAll();

    $milestoneStmt = $pdo->prepare("SELECT * FROM team_milestones WHERE team_id = ? ORDER BY due_date IS NULL, due_date ASC LIMIT 8");
    $milestoneStmt->execute([$teamId]);
    $milestones = $milestoneStmt->fetchAll();

    $feedbackStmt = $pdo->prepare("SELECT af.*, u.name AS faculty_name FROM advisor_feedback af JOIN users u ON u.id = af.faculty_user_id WHERE af.team_id = ? ORDER BY af.created_at DESC LIMIT 20");
    $feedbackStmt->execute([$teamId]);
    $feedbackRows = $feedbackStmt->fetchAll();

    $pageTitle = $team['name'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($team['name']) ?> || UIU ResearchCollab</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="../CSS/dashboard.css">
        <style>
            .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:16px}
            .app-panel h3{color:var(--uiu-blue);font-size:15px;font-weight:700;margin:0 0 10px}
            .member-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light,#eee)}
            .member-row:last-child{border-bottom:none}
            .avatar-sm{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:12px;font-weight:700;flex-shrink:0}
            .feedback-item{padding:10px 0;border-bottom:1px solid var(--border-light,#eee)}
            .feedback-item:last-child{border-bottom:none}
        </style>
    </head>
    <body>
    <?php require __DIR__ . '/../includes/faculty_header.php'; ?>
    <?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
        <main class="dashboard-main">
            <?php render_flashes(); ?>
            <a href="<?= e(url('/faculty/advised-teams.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Advised Teams</a>
            <h2 style="color:var(--uiu-blue);font-size:22px;font-weight:700;"><?= e($team['name']) ?></h2>
            <p class="text-muted"><?= e($team['domain_name'] ?: 'No domain set') ?> &middot; <?= count($members) ?> member(s) &middot; Status: <?= e($team['status']) ?></p>

            <div class="row">
                <div class="col-lg-7">
                    <div class="app-panel"><h3>About</h3><p class="mb-0"><?= nl2br(e($team['description'] ?: 'No description provided.')) ?></p></div>

                    <div class="app-panel">
                        <h3>Tasks</h3>
                        <?php if (!$tasks): ?><p class="text-muted small mb-0">No tasks yet.</p><?php endif; ?>
                        <?php foreach ($tasks as $t): ?>
                            <div class="d-flex justify-content-between border-bottom py-1"><span><?= e($t['title']) ?></span><span class="badge bg-light text-dark border"><?= e($t['status']) ?></span></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="app-panel">
                        <h3>Milestones</h3>
                        <?php if (!$milestones): ?><p class="text-muted small mb-0">No milestones yet.</p><?php endif; ?>
                        <?php foreach ($milestones as $m): ?>
                            <div class="d-flex justify-content-between border-bottom py-1"><span><?= e($m['title']) ?></span><span class="text-muted small">Due <?= format_date($m['due_date']) ?></span></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="app-panel">
                        <h3>Post Guidance</h3>
                        <form method="post">
                            <?= csrf_field() ?>
                            <div class="mb-2"><input type="text" name="title" class="form-control" placeholder="Title (optional)"></div>
                            <div class="mb-2"><textarea name="content" class="form-control" rows="3" placeholder="Share feedback or next steps with the team..." required></textarea></div>
                            <button type="submit" class="btn btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Post to Team</button>
                        </form>
                        <hr>
                        <?php if (!$feedbackRows): ?><p class="text-muted small mb-0">No guidance posted yet.</p><?php endif; ?>
                        <?php foreach ($feedbackRows as $f): ?>
                            <div class="feedback-item">
                                <strong><?= e($f['title'] ?: 'Guidance') ?></strong> <span class="text-muted small">by <?= e($f['faculty_name']) ?> &middot; <?= e(time_ago($f['created_at'])) ?></span>
                                <p class="mb-0"><?= nl2br(e($f['content'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="app-panel">
                        <h3>Members (<?= count($members) ?>)</h3>
                        <?php foreach ($members as $m): ?>
                            <div class="member-row">
                                <span class="avatar-sm"><?= e(initials($m['name'])) ?></span>
                                <div class="flex-grow-1"><div class="fw-semibold"><?= e($m['name']) ?></div><div class="text-muted small"><?= e($m['department'] ?: 'Student') ?></div></div>
                                <span class="badge bg-light text-dark border"><?= e($m['role']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------------------------
// List view
// -----------------------------------------------------------------
$statusFilter = (string)($_GET['status'] ?? 'active');
if (!in_array($statusFilter, ['active', 'completed', 'cancelled', ''], true)) {
    $statusFilter = 'active';
}
$where  = ['aa.faculty_user_id = ?', 'aa.team_id IS NOT NULL'];
$params = [$userId];
if ($statusFilter !== '') {
    $where[] = 'aa.status = ?';
    $params[] = $statusFilter;
}
$stmt = $pdo->prepare(
    "SELECT aa.*, rt.name AS team_name, rt.status AS team_status,
            (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = rt.id AND tm.status='Active') AS member_count
     FROM advisor_assignments aa JOIN research_teams rt ON rt.id = aa.team_id
     WHERE " . implode(' AND ', $where) . " ORDER BY aa.assigned_at DESC"
);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$pageTitle = 'My Advised Teams';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Advised Teams || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px 18px;margin-bottom:12px}
        .filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">My Advised Teams</h2>

        <form method="get" class="filter-bar">
            <select name="status" class="form-select" style="max-width:180px;">
                <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', '' => 'All'] as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-primary">Filter</button>
        </form>

        <?php if (!$assignments): ?>
            <div class="app-panel app-empty-state"><i class="bi bi-people"></i><p>No advised teams yet.</p></div>
        <?php endif; ?>

        <?php foreach ($assignments as $a): ?>
            <div class="app-panel d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold"><?= e($a['team_name']) ?></div>
                    <div class="text-muted small"><?= (int)$a['member_count'] ?> member(s) &middot; <?= e(ucwords(str_replace('_', ' ', $a['assignment_type']))) ?> &middot; Since <?= format_date($a['assigned_at']) ?></div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-<?= $a['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $a['status'] === 'active' ? 'success' : 'secondary' ?>-emphasis"><?= e(ucfirst($a['status'])) ?></span>
                    <a href="<?= e(url('/faculty/advised-teams.php?team_id=' . $a['team_id'])) ?>" class="btn btn-sm btn-outline-primary">Open Workspace</a>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
