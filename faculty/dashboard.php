<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$fpId   = (int)$facultyProfile['id'];

$completion = calculate_faculty_profile_completion($pdo, $userId);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM research_opportunities WHERE created_by = ? AND status = 'Open'");
$stmt->execute([$userId]);
$activeOpportunities = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM opportunity_applications oa
     JOIN research_opportunities o ON o.id = oa.opportunity_id
     WHERE o.created_by = ? AND oa.status = 'Pending'"
);
$stmt->execute([$userId]);
$pendingApplications = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM advisor_requests WHERE faculty_user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingAdvisorRequests = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM advisor_assignments WHERE faculty_user_id = ? AND status = 'active' AND student_user_id IS NOT NULL");
$stmt->execute([$userId]);
$activeStudents = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM advisor_assignments WHERE faculty_user_id = ? AND status = 'active' AND team_id IS NOT NULL");
$stmt->execute([$userId]);
$activeTeams = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM research_connections WHERE recipient_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingConnections = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM direct_messages dm
     JOIN direct_conversations dc ON dc.id = dm.conversation_id
     WHERE (dc.user_one_id = ? OR dc.user_two_id = ?) AND dm.sender_id != ? AND dm.is_read = 0"
);
$stmt->execute([$userId, $userId, $userId]);
$unreadMessages = (int)$stmt->fetchColumn();

$unreadCount = unread_notification_count($pdo, $userId);

// --- Recent applications -------------------------------------------------
$recentApplications = $pdo->prepare(
    "SELECT oa.*, o.title AS opportunity_title, u.name AS applicant_name
     FROM opportunity_applications oa
     JOIN research_opportunities o ON o.id = oa.opportunity_id
     JOIN users u ON u.id = oa.user_id
     WHERE o.created_by = ?
     ORDER BY oa.applied_at DESC LIMIT 4"
);
$recentApplications->execute([$userId]);
$recentApplications = $recentApplications->fetchAll();

// --- Recent advisor/mentor requests --------------------------------------
$recentRequests = $pdo->prepare(
    "SELECT ar.*, u.name AS requester_name, rt.name AS team_name
     FROM advisor_requests ar
     JOIN users u ON u.id = ar.requested_by_user_id
     LEFT JOIN research_teams rt ON rt.id = ar.team_id
     WHERE ar.faculty_user_id = ?
     ORDER BY ar.created_at DESC LIMIT 4"
);
$recentRequests->execute([$userId]);
$recentRequests = $recentRequests->fetchAll();

// --- Active opportunities -------------------------------------------------
$activeOpps = $pdo->prepare(
    "SELECT o.*, (SELECT COUNT(*) FROM opportunity_applications a WHERE a.opportunity_id = o.id) AS applicant_count
     FROM research_opportunities o
     WHERE o.created_by = ? AND o.status = 'Open'
     ORDER BY o.created_at DESC LIMIT 3"
);
$activeOpps->execute([$userId]);
$activeOpps = $activeOpps->fetchAll();

// --- Upcoming milestones from advised teams ------------------------------
$milestones = $pdo->prepare(
    "SELECT tm.*, rt.name AS team_name FROM team_milestones tm
     JOIN research_teams rt ON rt.id = tm.team_id
     JOIN advisor_assignments aa ON aa.team_id = rt.id AND aa.faculty_user_id = ? AND aa.status = 'active'
     WHERE tm.status IN ('Pending','In Progress')
     GROUP BY tm.id
     ORDER BY tm.due_date IS NULL, tm.due_date ASC
     LIMIT 3"
);
$milestones->execute([$userId]);
$milestoneRows = $milestones->fetchAll();

// --- Recent messages -------------------------------------------------------
$recentMessages = $pdo->prepare(
    "SELECT dm.*, u.name AS sender_name
     FROM direct_messages dm
     JOIN direct_conversations dc ON dc.id = dm.conversation_id
     JOIN users u ON u.id = dm.sender_id
     WHERE (dc.user_one_id = ? OR dc.user_two_id = ?) AND dm.sender_id != ?
     ORDER BY dm.created_at DESC LIMIT 3"
);
$recentMessages->execute([$userId, $userId, $userId]);
$recentMessages = $recentMessages->fetchAll();

function fac_status_pill(string $status): string
{
    return match (strtolower($status)) {
        'pending', 'clarification_requested' => 'pill pill-orange',
        'accepted', 'active'                 => 'pill pill-green',
        'declined', 'rejected', 'cancelled'   => 'pill pill-red',
        default                               => 'pill pill-gray',
    };
}

$pageTitle = 'Faculty Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard || UIU ResearchCollab</title>
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
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .app-empty-state{padding:24px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:28px;display:block;margin-bottom:8px}
        .quick-actions-row{display:flex;flex-wrap:wrap;gap:12px}
        .quick-actions-row .quick-action{flex:1 1 150px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="welcome-section">
            <h2>Welcome Back, <span class="user-first-name"><?= e(explode(' ', trim($currentUser['name']))[0]) ?></span>!</h2>
            <p><?= e($facultyProfile['designation'] ?: 'Faculty') ?><?= $facultyProfile['department'] ? ', ' . e($facultyProfile['department']) : '' ?> &middot; Profile <?= $completion ?>% complete</p>
        </div>

        <div class="dashboard-statistics">
            <div class="dashboard-stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-briefcase-fill"></i></div>
                <div class="stat-card-content">
                    <strong><?= $activeOpportunities ?></strong>
                    <span>Active Opportunities</span>
                    <a href="<?= e(url('/faculty/opportunities.php')) ?>">View All</a>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-inbox-fill"></i></div>
                <div class="stat-card-content">
                    <strong><?= $pendingApplications ?></strong>
                    <span>Pending Applications</span>
                    <a href="<?= e(url('/faculty/applications.php')) ?>">Review</a>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-person-check-fill"></i></div>
                <div class="stat-card-content">
                    <strong><?= $pendingAdvisorRequests ?></strong>
                    <span>Mentor Requests</span>
                    <a href="<?= e(url('/faculty/mentorship-requests.php')) ?>">Review</a>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon green"><i class="bi bi-mortarboard-fill"></i></div>
                <div class="stat-card-content">
                    <strong><?= $activeStudents ?></strong>
                    <span>Advised Students</span>
                    <a href="<?= e(url('/faculty/advised-students.php')) ?>">View All</a>
                </div>
            </div>
        </div>

        <div class="dashboard-statistics">
            <div class="dashboard-stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-people-fill"></i></div>
                <div class="stat-card-content">
                    <strong><?= $activeTeams ?></strong>
                    <span>Advised Teams</span>
                    <a href="<?= e(url('/faculty/advised-teams.php')) ?>">View All</a>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-link-45deg"></i></div>
                <div class="stat-card-content">
                    <strong><?= $pendingConnections ?></strong>
                    <span>Connection Requests</span>
                    <a href="<?= e(url('/faculty/faculty-connections.php')) ?>">View All</a>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-chat-dots-fill"></i></div>
                <div class="stat-card-content">
                    <strong><?= $unreadMessages ?></strong>
                    <span>Unread Messages</span>
                    <a href="<?= e(url('/faculty/messages.php')) ?>">View All</a>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon green"><i class="bi bi-bell-fill"></i></div>
                <div class="stat-card-content">
                    <strong><?= $unreadCount ?></strong>
                    <span>Notifications</span>
                    <a href="<?= e(url('/faculty/notifications.php')) ?>">View All</a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="app-panel">
                    <h3>Recent Applications</h3>
                    <?php if (!$recentApplications): ?>
                        <div class="app-empty-state"><i class="bi bi-inbox"></i><p>No applications yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($recentApplications as $a): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <div class="fw-semibold"><?= e($a['applicant_name']) ?></div>
                                    <div class="text-muted small"><?= e($a['opportunity_title']) ?> &middot; <?= e(time_ago($a['applied_at'])) ?></div>
                                </div>
                                <a href="<?= e(url('/faculty/application-details.php?id=' . $a['id'])) ?>" class="<?= fac_status_pill($a['status']) ?> text-decoration-none"><?= e($a['status']) ?></a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="app-panel">
                    <h3>Recent Advisor / Mentor Requests</h3>
                    <?php if (!$recentRequests): ?>
                        <div class="app-empty-state"><i class="bi bi-person-check"></i><p>No mentorship requests yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $r): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <div class="fw-semibold"><?= e($r['team_name'] ?: $r['requester_name']) ?><?= $r['team_name'] ? ' (team)' : '' ?></div>
                                    <div class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $r['request_type']))) ?> &middot; <?= e(time_ago($r['created_at'])) ?></div>
                                </div>
                                <a href="<?= e(url('/faculty/mentorship-request-details.php?id=' . $r['id'])) ?>" class="<?= fac_status_pill($r['status']) ?> text-decoration-none"><?= e(str_replace('_', ' ', $r['status'])) ?></a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="app-panel">
                    <h3>Active Opportunities</h3>
                    <?php if (!$activeOpps): ?>
                        <div class="app-empty-state"><i class="bi bi-briefcase"></i><p>You have no open opportunities. <a href="<?= e(url('/faculty/opportunity-create.php')) ?>">Create one</a>.</p></div>
                    <?php else: ?>
                        <?php foreach ($activeOpps as $o): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <div class="fw-semibold"><?= e($o['title']) ?></div>
                                    <div class="text-muted small"><?= (int)$o['applicant_count'] ?> applicant(s) &middot; Deadline <?= format_date($o['deadline']) ?></div>
                                </div>
                                <a href="<?= e(url('/faculty/opportunity-details.php?id=' . $o['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="app-panel">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions-row">
                        <a href="<?= e(url('/faculty/opportunity-create.php')) ?>" class="quick-action"><i class="bi bi-plus-circle-fill"></i><span>Create Opportunity</span></a>
                        <a href="<?= e(url('/faculty/applications.php')) ?>" class="quick-action"><i class="bi bi-inbox-fill"></i><span>Review Applications</span></a>
                        <a href="<?= e(url('/faculty/mentorship-requests.php')) ?>" class="quick-action"><i class="bi bi-person-check-fill"></i><span>Review Mentor Requests</span></a>
                        <a href="<?= e(url('/faculty/research-connect.php')) ?>" class="quick-action"><i class="bi bi-people-fill"></i><span>Browse Students</span></a>
                        <a href="<?= e(url('/faculty/messages.php')) ?>" class="quick-action"><i class="bi bi-chat-dots-fill"></i><span>View Messages</span></a>
                    </div>
                </div>

                <div class="app-panel">
                    <h3>Upcoming Milestones (Advised Teams)</h3>
                    <?php if (!$milestoneRows): ?>
                        <p class="text-muted small mb-0">No upcoming milestones.</p>
                    <?php else: ?>
                        <?php foreach ($milestoneRows as $m): ?>
                            <div class="event-item">
                                <div class="event-icon"><i class="bi bi-flag-fill"></i></div>
                                <div class="event-content">
                                    <h3><?= e($m['title']) ?></h3>
                                    <p><i class="bi bi-people"></i> <?= e($m['team_name']) ?></p>
                                    <p><i class="bi bi-calendar3"></i> Due <?= format_date($m['due_date']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="app-panel">
                    <h3>Recent Messages</h3>
                    <?php if (!$recentMessages): ?>
                        <p class="text-muted small mb-0">No recent messages.</p>
                    <?php else: ?>
                        <?php foreach ($recentMessages as $m): ?>
                            <div class="recent-message">
                                <span class="user-avatar avatar-initials" style="width:32px;height:32px;font-size:11px;border-radius:50%;"><?= e(initials($m['sender_name'])) ?></span>
                                <div class="message-content">
                                    <h3><?= e($m['sender_name']) ?></h3>
                                    <p><?= e(mb_strimwidth($m['message'], 0, 60, '...')) ?></p>
                                </div>
                                <div class="message-meta"><span><?= e(time_ago($m['created_at'])) ?></span></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="<?= e(url('/faculty/messages.php')) ?>" class="view-all-link">View All Messages</a>
                </div>
            </div>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
