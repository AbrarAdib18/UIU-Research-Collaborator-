<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$teamStmt = $pdo->prepare(
    "SELECT rt.*, u.name AS creator_name, d.name AS domain_name
     FROM research_teams rt
     JOIN users u ON u.id = rt.created_by
     LEFT JOIN research_domains d ON d.id = rt.research_domain_id
     WHERE rt.id = ?"
);
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    flash('error', 'That team could not be found.');
    redirect('/student/teams.php');
}

$isMember     = is_team_member($pdo, $teamId, $userId);
$memberCount  = team_member_count($pdo, $teamId);
$previewMode  = false;

if (!$isMember) {
    $canPreview = $team['status'] === 'Forming' && $memberCount < (int)$team['team_size_limit'];
    if (!$canPreview) {
        flash('error', "You don't have access to this team's workspace.");
        redirect('/student/teams.php');
    }
    $previewMode = true;
}

$isLeader = $isMember ? is_team_leader($pdo, $teamId, $userId) : false;

// My pending join request (for preview mode button state)
$myPendingRequest = false;
if ($previewMode) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM team_requests WHERE team_id = ? AND user_id = ? AND status = 'Pending'");
    $chk->execute([$teamId, $userId]);
    $myPendingRequest = (bool)$chk->fetchColumn();
}

$members = [];
if ($isMember) {
    $memStmt = $pdo->prepare(
        "SELECT tm.*, u.name, u.email, sp.profile_photo, sp.department
         FROM team_members tm
         JOIN users u ON u.id = tm.user_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE tm.team_id = ? AND tm.status = 'Active'
         ORDER BY FIELD(tm.role, 'Leader', 'Member'), tm.joined_at ASC"
    );
    $memStmt->execute([$teamId]);
    $members = $memStmt->fetchAll();
}

$taskCount = $milestoneCount = $fileCount = $messageCount = 0;
if ($isMember) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM team_tasks WHERE team_id = ?');
    $stmt->execute([$teamId]);
    $taskCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM team_milestones WHERE team_id = ?');
    $stmt->execute([$teamId]);
    $milestoneCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM team_files WHERE team_id = ?');
    $stmt->execute([$teamId]);
    $fileCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM team_messages WHERE team_id = ?');
    $stmt->execute([$teamId]);
    $messageCount = (int)$stmt->fetchColumn();
}

function team_status_pill(string $status): string
{
    return match ($status) {
        'Forming'   => 'pill pill-blue',
        'Active'    => 'pill pill-green',
        'Completed' => 'pill pill-purple',
        'Archived'  => 'pill pill-gray',
        default     => 'pill pill-gray',
    };
}

$currentFile = 'team-details.php';
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
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-blue{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-orange{background:#ffe8cc;color:#b35a00}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .pill-purple{background:#ece1fb;color:#5b21a6}
        .team-subnav{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 20px;padding-bottom:14px;border-bottom:1px solid var(--border-color)}
        .team-subnav a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
        .team-subnav a:hover{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .team-subnav a.active{background:var(--uiu-blue);color:#fff}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .mini-stat{background:#f5f8fc;border-radius:10px;padding:12px;text-align:center}
        .mini-stat strong{display:block;font-size:20px;color:var(--uiu-blue)}
        .mini-stat span{font-size:12px;color:var(--text-light)}
        .member-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light,#eee)}
        .member-row:last-child{border-bottom:none}
        .avatar-sm{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:13px;font-weight:700;flex-shrink:0;object-fit:cover}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2><?= e($team['name']) ?> <span class="<?= team_status_pill($team['status']) ?>"><?= e($team['status']) ?></span></h2>
                <p><i class="bi bi-people"></i> <?= $memberCount ?>/<?= (int)$team['team_size_limit'] ?> members &middot; Led by <?= e($team['creator_name']) ?><?php if ($team['domain_name']): ?> &middot; <?= e($team['domain_name']) ?><?php endif; ?></p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <?php if (!$previewMode): ?>
        <nav class="team-subnav">
            <a class="active" href="<?= e(url('/student/team-details.php?id=' . $teamId)) ?>"><i class="bi bi-info-circle"></i> Overview</a>
            <a href="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>"><i class="bi bi-list-check"></i> Tasks <?= $taskCount ? "($taskCount)" : '' ?></a>
            <a href="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>"><i class="bi bi-flag"></i> Milestones <?= $milestoneCount ? "($milestoneCount)" : '' ?></a>
            <a href="<?= e(url('/student/team-files.php?id=' . $teamId)) ?>"><i class="bi bi-folder"></i> Files <?= $fileCount ? "($fileCount)" : '' ?></a>
            <a href="<?= e(url('/student/team-messages.php?id=' . $teamId)) ?>"><i class="bi bi-chat-dots"></i> Messages <?= $messageCount ? "($messageCount)" : '' ?></a>
        </nav>
        <?php endif; ?>

        <?php if ($previewMode): ?>
            <div class="app-panel">
                <h3>About This Team</h3>
                <p><?= nl2br(e($team['description'] ?: 'No description provided.')) ?></p>
                <p class="text-muted small mb-0">
                    This team is still forming and open to new members. Join the team to access tasks, milestones, files and messages.
                </p>
            </div>

            <div class="app-panel">
                <h3>Request to Join</h3>
                <?php if ($myPendingRequest): ?>
                    <p class="text-muted mb-0"><i class="bi bi-hourglass-split"></i> Your request to join this team is pending approval from the team leader.</p>
                <?php else: ?>
                    <form action="<?= e(url('/student/team-requests.php')) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Message to the team leader (optional)</label>
                            <textarea class="form-control" name="message" rows="3" maxlength="500" placeholder="Tell them why you'd like to join..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-uiu"><i class="bi bi-send"></i> Request to Join</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= $taskCount ?></strong><span>Tasks</span></div></div>
                <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= $milestoneCount ?></strong><span>Milestones</span></div></div>
                <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= $fileCount ?></strong><span>Files</span></div></div>
                <div class="col-6 col-md-3"><div class="mini-stat"><strong><?= $messageCount ?></strong><span>Messages</span></div></div>
            </div>

            <div class="row">
                <div class="col-lg-7">
                    <div class="app-panel">
                        <h3>About This Team</h3>
                        <p><?= nl2br(e($team['description'] ?: 'No description provided.')) ?></p>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="app-panel">
                        <h3>Members (<?= count($members) ?>)</h3>
                        <?php foreach ($members as $m): ?>
                            <div class="member-row">
                                <?php if (!empty($m['profile_photo'])): ?>
                                    <img class="avatar-sm" src="<?= e(url('/uploads/avatars/' . $m['profile_photo'])) ?>" alt="<?= e($m['name']) ?>">
                                <?php else: ?>
                                    <span class="avatar-sm"><?= e(initials($m['name'])) ?></span>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= e($m['name']) ?></div>
                                    <div class="text-muted small"><?= e($m['department'] ?: 'Student') ?></div>
                                </div>
                                <span class="pill <?= $m['role'] === 'Leader' ? 'pill-purple' : 'pill-blue' ?>"><?= e($m['role']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
