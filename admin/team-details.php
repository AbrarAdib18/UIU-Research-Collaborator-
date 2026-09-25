<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];
$teamId  = validate_id($_GET['id'] ?? null);

if (!$teamId) {
    flash('error', 'Invalid team.');
    redirect('/admin/teams.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_member') {
    require_csrf('/admin/team-details.php?id=' . $teamId);
    $memberUserId = validate_id($_POST['user_id'] ?? null);
    $reason = nullable_trim($_POST['reason'] ?? '');

    $memStmt = $pdo->prepare("SELECT tm.*, u.name FROM team_members tm JOIN users u ON u.id = tm.user_id WHERE tm.team_id = ? AND tm.user_id = ? AND tm.status='Active'");
    $memStmt->execute([$teamId, $memberUserId]);
    $member = $memStmt->fetch();

    if (!$member) {
        flash('error', 'Member not found on this team.');
    } elseif ($member['role'] === 'Leader') {
        flash('error', 'Cannot remove the team leader — archive or delete the team instead.');
    } else {
        $pdo->prepare("UPDATE team_members SET status='Removed' WHERE id=?")->execute([$member['id']]);
        $teamNameStmt = $pdo->prepare('SELECT name FROM research_teams WHERE id=?'); $teamNameStmt->execute([$teamId]);
        $teamName = $teamNameStmt->fetchColumn();
        log_activity($pdo, $adminId, 'admin_team_remove_member', "Removed {$member['name']} from team \"{$teamName}\"" . ($reason ? " — {$reason}" : ''), 'team', $teamId);
        create_notification($pdo, $memberUserId, 'team', 'Removed from Team', 'You were removed from "' . $teamName . '" by an administrator.' . ($reason ? ' Reason: ' . $reason : ''), 'team', $teamId);
        flash('success', $member['name'] . ' removed from the team.');
    }
    redirect('/admin/team-details.php?id=' . $teamId);
}

$stmt = $pdo->prepare('SELECT rt.*, u.name AS creator_name, d.name AS domain_name FROM research_teams rt JOIN users u ON u.id = rt.created_by LEFT JOIN research_domains d ON d.id = rt.research_domain_id WHERE rt.id = ?');
$stmt->execute([$teamId]);
$team = $stmt->fetch();
if (!$team) {
    flash('error', 'Team not found.');
    redirect('/admin/teams.php');
}

$members = $pdo->prepare("SELECT tm.*, u.id AS user_id, u.name FROM team_members tm JOIN users u ON u.id = tm.user_id WHERE tm.team_id = ? AND tm.status='Active' ORDER BY FIELD(tm.role,'Leader','Member')");
$members->execute([$teamId]);
$members = $members->fetchAll();

$stmt2 = $pdo->prepare('SELECT COUNT(*) FROM team_tasks WHERE team_id = ?'); $stmt2->execute([$teamId]); $taskCount = (int)$stmt2->fetchColumn();
$stmt2 = $pdo->prepare('SELECT COUNT(*) FROM team_milestones WHERE team_id = ?'); $stmt2->execute([$teamId]); $milestoneCount = (int)$stmt2->fetchColumn();
$stmt2 = $pdo->prepare('SELECT COUNT(*) FROM team_files WHERE team_id = ?'); $stmt2->execute([$teamId]); $fileCount = (int)$stmt2->fetchColumn();

$advisor = get_team_advisor($pdo, $teamId);

$oppLink = null;
if ($team['opportunity_id']) {
    $oppStmt = $pdo->prepare('SELECT id, title FROM research_opportunities WHERE id = ?');
    $oppStmt->execute([$team['opportunity_id']]);
    $oppLink = $oppStmt->fetch();
}

$pageTitle = $team['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($team['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}
        .member-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light,#eee)}
        .avatar-sm{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:12px;font-weight:700;flex-shrink:0}
        .mini-stat{background:#f5f8fc;border-radius:10px;padding:12px;text-align:center}
        .mini-stat strong{display:block;font-size:20px;color:var(--uiu-blue)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/admin/teams.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Teams</a>

        <div class="detail-card">
            <h1 style="font-size:20px;color:var(--uiu-blue);font-weight:700;"><?= e($team['name']) ?> <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($team['status']) ?></span></h1>
            <p class="text-muted small">Led by <?= e($team['creator_name']) ?> &middot; <?= e($team['domain_name'] ?: 'No domain') ?><?php if ($oppLink): ?> &middot; Linked to <a href="<?= e(url('/admin/opportunity-details.php?id=' . $oppLink['id'])) ?>"><?= e($oppLink['title']) ?></a><?php endif; ?></p>
            <p><?= nl2br(e($team['description'] ?: 'No description.')) ?></p>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= count($members) ?></strong>Members</div></div>
            <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= $taskCount ?></strong>Tasks</div></div>
            <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= $milestoneCount ?></strong>Milestones</div></div>
            <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= $fileCount ?></strong>Files</div></div>
        </div>

        <?php if ($advisor): ?>
        <div class="detail-card"><h2>Faculty Advisor</h2><p class="mb-0"><?= e($advisor['faculty_name']) ?> — <?= e($advisor['designation'] ?: 'Faculty') ?></p></div>
        <?php endif; ?>

        <div class="detail-card">
            <h2>Members</h2>
            <?php foreach ($members as $m): ?>
                <div class="member-row">
                    <span class="avatar-sm"><?= e(initials($m['name'])) ?></span>
                    <div class="flex-grow-1"><?= e($m['name']) ?> <span class="badge bg-light text-dark border"><?= e($m['role']) ?></span></div>
                    <?php if ($m['role'] !== 'Leader'): ?>
                    <form method="post" onsubmit="return confirm('Remove this member from the team?');">
                        <?= csrf_field() ?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                        <input type="text" name="reason" class="form-control form-control-sm d-inline-block" style="width:140px;" placeholder="Reason (optional)">
                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="detail-card">
            <h2>Moderation</h2>
            <form method="post" action="<?= e(url('/admin/teams.php')) ?>" class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $teamId ?>">
                <input type="text" name="reason" class="form-control form-control-sm" style="max-width:220px;" placeholder="Reason (optional)">
                <?php if ($team['status'] !== 'Archived'): ?><button name="action" value="archive" class="btn btn-sm btn-outline-secondary">Archive</button><?php else: ?><button name="action" value="restore" class="btn btn-sm btn-outline-success">Restore</button><?php endif; ?>
            </form>
            <form method="post" action="<?= e(url('/admin/teams.php')) ?>" onsubmit="return confirm('Delete this team permanently? This cannot be undone.');">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $teamId ?>"><input type="hidden" name="action" value="delete">
                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Deletion reason (optional)">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete Team</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
