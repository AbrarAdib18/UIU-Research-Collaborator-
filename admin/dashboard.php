<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$userCounts = $pdo->query("SELECT role, status, COUNT(*) c FROM users GROUP BY role, status")->fetchAll();
$totalUsers = 0; $activeUsers = 0; $inactiveUsers = 0; $suspendedUsers = 0; $totalStudents = 0; $totalFaculty = 0;
foreach ($userCounts as $row) {
    $totalUsers += (int)$row['c'];
    if ($row['status'] === 'active') $activeUsers += (int)$row['c'];
    if ($row['status'] === 'inactive') $inactiveUsers += (int)$row['c'];
    if ($row['status'] === 'suspended') $suspendedUsers += (int)$row['c'];
    if ($row['role'] === 'student') $totalStudents += (int)$row['c'];
    if ($row['role'] === 'faculty') $totalFaculty += (int)$row['c'];
}

$pendingVerification = (int)$pdo->query("SELECT COUNT(*) FROM faculty_verifications WHERE status='pending'")->fetchColumn();
$activeOpportunities  = (int)$pdo->query("SELECT COUNT(*) FROM research_opportunities WHERE status='Open'")->fetchColumn();
$pendingApplications  = (int)$pdo->query("SELECT COUNT(*) FROM opportunity_applications WHERE status='Pending'")->fetchColumn();
$totalTeams           = (int)$pdo->query("SELECT COUNT(*) FROM research_teams")->fetchColumn();
$totalCommunities     = (int)$pdo->query("SELECT COUNT(*) FROM communities")->fetchColumn();
$privateCommunities   = (int)$pdo->query("SELECT COUNT(*) FROM communities WHERE privacy='Private'")->fetchColumn();
$totalResources       = (int)$pdo->query("SELECT COUNT(*) FROM research_resources")->fetchColumn();
$pendingAdvisorReqs   = (int)$pdo->query("SELECT COUNT(*) FROM advisor_requests WHERE status='pending'")->fetchColumn();
$activeAssignments    = (int)$pdo->query("SELECT COUNT(*) FROM advisor_assignments WHERE status='active'")->fetchColumn();
$pendingConnections   = (int)$pdo->query("SELECT COUNT(*) FROM research_connections WHERE status='pending'")->fetchColumn();
$unreadNotifications  = unread_notification_count($pdo, $userId);

$recentUsers = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentOpportunities = $pdo->query("SELECT o.id, o.title, o.status, o.created_at, u.name AS creator_name FROM research_opportunities o JOIN users u ON u.id = o.created_by ORDER BY o.created_at DESC LIMIT 5")->fetchAll();
$recentPosts = $pdo->query("SELECT cp.id, cp.title, cp.created_at, c.name AS community_name, u.name AS author_name FROM community_posts cp JOIN communities c ON c.id = cp.community_id JOIN users u ON u.id = cp.user_id ORDER BY cp.created_at DESC LIMIT 5")->fetchAll();
$recentAdvisorRequests = $pdo->query("SELECT ar.id, ar.request_type, ar.status, ar.created_at, u.name AS requester_name FROM advisor_requests ar JOIN users u ON u.id = ar.requested_by_user_id ORDER BY ar.created_at DESC LIMIT 5")->fetchAll();
$recentActivity = $pdo->query("SELECT al.*, u.name AS user_name FROM activity_logs al JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 8")->fetchAll();

$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .app-empty-state{padding:20px;text-align:center;color:var(--text-light)}
        .quick-actions-row{display:flex;flex-wrap:wrap;gap:12px}
        .quick-actions-row .quick-action{flex:1 1 150px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-orange{background:#ffe8cc;color:#b35a00}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="welcome-section">
            <h2>Welcome Back, <span class="user-first-name"><?= e(explode(' ', trim($currentUser['name']))[0]) ?></span>!</h2>
            <p>Platform overview and moderation queue.</p>
        </div>

        <div class="dashboard-statistics">
            <div class="dashboard-stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-people-fill"></i></div>
                <div class="stat-card-content"><strong><?= $totalUsers ?></strong><span>Total Users</span><a href="<?= e(url('/admin/users.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon green"><i class="bi bi-person-check-fill"></i></div>
                <div class="stat-card-content"><strong><?= $activeUsers ?></strong><span>Active Users</span><a href="<?= e(url('/admin/users.php?status=active')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-person-dash-fill"></i></div>
                <div class="stat-card-content"><strong><?= $inactiveUsers + $suspendedUsers ?></strong><span>Inactive/Suspended</span><a href="<?= e(url('/admin/users.php?status=suspended')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-patch-check-fill"></i></div>
                <div class="stat-card-content"><strong><?= $pendingVerification ?></strong><span>Pending Faculty Verification</span><a href="<?= e(url('/admin/faculty-verification.php')) ?>">Review</a></div>
            </div>
        </div>

        <div class="dashboard-statistics">
            <div class="dashboard-stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-mortarboard-fill"></i></div>
                <div class="stat-card-content"><strong><?= $totalStudents ?></strong><span>Students</span><a href="<?= e(url('/admin/students.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon green"><i class="bi bi-person-workspace"></i></div>
                <div class="stat-card-content"><strong><?= $totalFaculty ?></strong><span>Faculty</span><a href="<?= e(url('/admin/faculty.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-briefcase-fill"></i></div>
                <div class="stat-card-content"><strong><?= $activeOpportunities ?></strong><span>Active Opportunities</span><a href="<?= e(url('/admin/opportunities.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-inbox-fill"></i></div>
                <div class="stat-card-content"><strong><?= $pendingApplications ?></strong><span>Pending Applications</span><a href="<?= e(url('/admin/applications.php')) ?>">View All</a></div>
            </div>
        </div>

        <div class="dashboard-statistics">
            <div class="dashboard-stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-people"></i></div>
                <div class="stat-card-content"><strong><?= $totalTeams ?></strong><span>Research Teams</span><a href="<?= e(url('/admin/teams.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon green"><i class="bi bi-diagram-3"></i></div>
                <div class="stat-card-content"><strong><?= $totalCommunities ?></strong><span>Communities (<?= $privateCommunities ?> private)</span><a href="<?= e(url('/admin/communities.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-database-fill"></i></div>
                <div class="stat-card-content"><strong><?= $totalResources ?></strong><span>Repository Resources</span><a href="<?= e(url('/admin/repository.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-bell-fill"></i></div>
                <div class="stat-card-content"><strong><?= $unreadNotifications ?></strong><span>Notifications</span><a href="<?= e(url('/admin/notifications.php')) ?>">View All</a></div>
            </div>
        </div>

        <div class="dashboard-statistics">
            <div class="dashboard-stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-person-check-fill"></i></div>
                <div class="stat-card-content"><strong><?= $pendingAdvisorReqs ?></strong><span>Pending Advisor Requests</span><a href="<?= e(url('/admin/advisor-requests.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon green"><i class="bi bi-mortarboard"></i></div>
                <div class="stat-card-content"><strong><?= $activeAssignments ?></strong><span>Active Advisor Assignments</span><a href="<?= e(url('/admin/advisor-assignments.php')) ?>">View All</a></div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-link-45deg"></i></div>
                <div class="stat-card-content"><strong><?= $pendingConnections ?></strong><span>Pending Connections</span><a href="<?= e(url('/admin/connections.php')) ?>">View All</a></div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="app-panel">
                    <h3>Recent Registrations</h3>
                    <?php if (!$recentUsers): ?><div class="app-empty-state">No users yet.</div><?php endif; ?>
                    <?php foreach ($recentUsers as $u): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div><div class="fw-semibold"><?= e($u['name']) ?></div><div class="text-muted small"><?= e($u['email']) ?> &middot; <?= e(time_ago($u['created_at'])) ?></div></div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="pill pill-gray"><?= e(ucfirst($u['role'])) ?></span>
                                <a href="<?= e(url('/admin/user-details.php?id=' . $u['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="app-panel">
                    <h3>Recent Opportunities</h3>
                    <?php if (!$recentOpportunities): ?><div class="app-empty-state">No opportunities yet.</div><?php endif; ?>
                    <?php foreach ($recentOpportunities as $o): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div><div class="fw-semibold"><?= e($o['title']) ?></div><div class="text-muted small">by <?= e($o['creator_name']) ?> &middot; <?= e(time_ago($o['created_at'])) ?></div></div>
                            <a href="<?= e(url('/admin/opportunity-details.php?id=' . $o['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="app-panel">
                    <h3>Recent Advisor Requests</h3>
                    <?php if (!$recentAdvisorRequests): ?><div class="app-empty-state">No advisor requests yet.</div><?php endif; ?>
                    <?php foreach ($recentAdvisorRequests as $r): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div><div class="fw-semibold"><?= e($r['requester_name']) ?></div><div class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $r['request_type']))) ?> &middot; <?= e(time_ago($r['created_at'])) ?></div></div>
                            <span class="pill pill-gray"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="app-panel">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions-row">
                        <a href="<?= e(url('/admin/users.php')) ?>" class="quick-action"><i class="bi bi-people-fill"></i><span>Manage Users</span></a>
                        <a href="<?= e(url('/admin/faculty-verification.php')) ?>" class="quick-action"><i class="bi bi-patch-check-fill"></i><span>Verify Faculty</span></a>
                        <a href="<?= e(url('/admin/domains.php')) ?>" class="quick-action"><i class="bi bi-plus-circle-fill"></i><span>Add Research Domain</span></a>
                        <a href="<?= e(url('/admin/opportunities.php')) ?>" class="quick-action"><i class="bi bi-briefcase-fill"></i><span>Review Opportunities</span></a>
                        <a href="<?= e(url('/admin/communities.php')) ?>" class="quick-action"><i class="bi bi-diagram-3"></i><span>Review Communities</span></a>
                        <a href="<?= e(url('/admin/repository.php')) ?>" class="quick-action"><i class="bi bi-database-fill"></i><span>Review Resources</span></a>
                        <a href="<?= e(url('/admin/advisor-requests.php')) ?>" class="quick-action"><i class="bi bi-person-check-fill"></i><span>Advisor Requests</span></a>
                        <a href="<?= e(url('/admin/activity-logs.php')) ?>" class="quick-action"><i class="bi bi-clock-history"></i><span>Activity Logs</span></a>
                    </div>
                </div>

                <div class="app-panel">
                    <h3>Recent Community Posts</h3>
                    <?php if (!$recentPosts): ?><p class="text-muted small mb-0">No posts yet.</p><?php endif; ?>
                    <?php foreach ($recentPosts as $p): ?>
                        <div class="recent-message">
                            <span class="user-avatar avatar-initials" style="width:32px;height:32px;font-size:11px;border-radius:50%;"><i class="bi bi-chat-square-text"></i></span>
                            <div class="message-content"><h3><?= e($p['title'] ?: 'Untitled post') ?></h3><p><?= e($p['author_name']) ?> in <?= e($p['community_name']) ?></p></div>
                            <div class="message-meta"><span><?= e(time_ago($p['created_at'])) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="app-panel">
                    <h3>Recent Activity</h3>
                    <?php if (!$recentActivity): ?><p class="text-muted small mb-0">No recent activity.</p><?php endif; ?>
                    <?php foreach ($recentActivity as $a): ?>
                        <div class="recent-message">
                            <span class="user-avatar avatar-initials" style="width:32px;height:32px;font-size:11px;border-radius:50%;"><i class="bi bi-activity"></i></span>
                            <div class="message-content"><h3><?= e($a['user_name']) ?></h3><p><?= e($a['description']) ?></p></div>
                            <div class="message-meta"><span><?= e(time_ago($a['created_at'])) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                    <a href="<?= e(url('/admin/activity-logs.php')) ?>" class="view-all-link">View All Logs</a>
                </div>
            </div>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
