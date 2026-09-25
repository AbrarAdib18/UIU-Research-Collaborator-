<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo       = db();
$userId    = (int)$currentUser['id'];
$profileId = (int)$studentProfile['id'];

$completion = calculate_profile_completion($pdo, $userId);

$domainCount = count(get_profile_domain_ids($pdo, $profileId));
$skillCount  = count(get_profile_skill_ids($pdo, $profileId));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM opportunity_applications WHERE user_id = ? AND status = 'Pending'");
$stmt->execute([$userId]);
$activeApplications = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE user_id = ? AND status = 'Active'");
$stmt->execute([$userId]);
$teamCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM team_invitations WHERE invited_user_id = ? AND status = 'Pending'");
$stmt->execute([$userId]);
$pendingInvitations = (int)$stmt->fetchColumn();

$unreadCount = unread_notification_count($pdo, $userId);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM advisor_requests WHERE requested_by_user_id = ? AND requester_type = 'student' AND status IN ('pending','clarification_requested')");
$stmt->execute([$userId]);
$pendingAdvisorRequests = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM advisor_assignments WHERE student_user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$activeAdvisorCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM research_connections WHERE recipient_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingConnectionRequests = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM direct_messages dm JOIN direct_conversations dc ON dc.id = dm.conversation_id
     WHERE (dc.user_one_id = ? OR dc.user_two_id = ?) AND dm.sender_id != ? AND dm.is_read = 0"
);
$stmt->execute([$userId, $userId, $userId]);
$unreadDirectMessages = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT
        (SELECT COUNT(*) FROM saved_opportunities WHERE user_id = ?) +
        (SELECT COUNT(*) FROM saved_resources WHERE user_id = ?) AS total');
$stmt->execute([$userId, $userId]);
$savedCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(DISTINCT pub.id) FROM publications pub WHERE pub.profile_id = ?');
$stmt->execute([$profileId]);
$publicationCount = (int)$stmt->fetchColumn();

// --- Recommended collaborators (top 2 by match score) ---------------
$candidates = $pdo->prepare(
    "SELECT sp.*, u.name FROM student_profiles sp
     JOIN users u ON u.id = sp.user_id
     WHERE sp.user_id != ? AND u.status = 'active'
     ORDER BY sp.updated_at DESC
     LIMIT 25"
);
$candidates->execute([$userId]);
$candidateRows = $candidates->fetchAll();

$recommended = [];
foreach ($candidateRows as $candidate) {
    $visibility = get_profile_visibility($pdo, (int)$candidate['id']);
    if ($visibility['profile_visibility'] === 'Private') {
        continue;
    }
    $candidate['match_score'] = calculate_match_score($pdo, $studentProfile, $candidate);
    $recommended[] = $candidate;
}
usort($recommended, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
$recommended = array_slice($recommended, 0, 2);

// --- Latest open opportunities ---------------------------------------
$opportunities = $pdo->query(
    "SELECT o.*, GROUP_CONCAT(d.name SEPARATOR '||') AS domain_names
     FROM research_opportunities o
     LEFT JOIN opportunity_domains od ON od.opportunity_id = o.id
     LEFT JOIN research_domains d ON d.id = od.domain_id
     WHERE o.status = 'Open'
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 2"
)->fetchAll();

// --- Upcoming milestones across my teams ------------------------------
$milestones = $pdo->prepare(
    "SELECT tm.*, rt.name AS team_name FROM team_milestones tm
     JOIN research_teams rt ON rt.id = tm.team_id
     JOIN team_members mem ON mem.team_id = rt.id AND mem.user_id = ? AND mem.status = 'Active'
     WHERE tm.status IN ('Pending','In Progress')
     ORDER BY tm.due_date IS NULL, tm.due_date ASC
     LIMIT 2"
);
$milestones->execute([$userId]);
$milestoneRows = $milestones->fetchAll();

// --- Recent activity ---------------------------------------------------
$activity = $pdo->prepare('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$activity->execute([$userId]);
$activityRows = $activity->fetchAll();

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard || UIU ResearchCollab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="dashboard-content-grid">
            <section class="dashboard-center">

                <div class="welcome-section">
                    <h2>Welcome Back, <span class="user-first-name"><?= e(explode(' ', trim($currentUser['name']))[0]) ?></span>!</h2>
                    <p>Here's what's happening in your research journey today.</p>
                </div>

                <div class="dashboard-statistics">
                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon blue"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $pendingInvitations ?></strong>
                            <span>Team Invitations</span>
                            <a href="<?= e(url('/student/team-invitations.php')) ?>">View All</a>
                        </div>
                    </div>

                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon purple"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $publicationCount ?></strong>
                            <span>My Publications</span>
                            <a href="<?= e(url('/student/profile.php')) ?>#publications-section">View All</a>
                        </div>
                    </div>

                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon green"><i class="bi bi-briefcase-fill"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $activeApplications ?></strong>
                            <span>Active Applications</span>
                            <a href="<?= e(url('/student/saved-items.php')) ?>">View All</a>
                        </div>
                    </div>

                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon orange"><i class="bi bi-globe2"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $teamCount ?></strong>
                            <span>My Teams</span>
                            <a href="<?= e(url('/student/teams.php')) ?>">View All</a>
                        </div>
                    </div>
                </div>

                <div class="dashboard-statistics">
                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon blue"><i class="bi bi-person-check-fill"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $pendingAdvisorRequests ?></strong>
                            <span>Pending Advisor Requests</span>
                            <a href="<?= e(url('/student/advisor-requests.php')) ?>">View All</a>
                        </div>
                    </div>
                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon green"><i class="bi bi-mortarboard-fill"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $activeAdvisorCount ?></strong>
                            <span>Active Advisors</span>
                            <a href="<?= e(url('/student/advisor-requests.php')) ?>">View All</a>
                        </div>
                    </div>
                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon purple"><i class="bi bi-link-45deg"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $pendingConnectionRequests ?></strong>
                            <span>Connection Requests</span>
                            <a href="<?= e(url('/student/faculty-connections.php')) ?>">View All</a>
                        </div>
                    </div>
                    <div class="dashboard-stat-card">
                        <div class="stat-card-icon orange"><i class="bi bi-chat-dots-fill"></i></div>
                        <div class="stat-card-content">
                            <strong><?= $unreadDirectMessages ?></strong>
                            <span>Unread Messages</span>
                            <a href="<?= e(url('/student/messages.php')) ?>">View All</a>
                        </div>
                    </div>
                </div>

                <!-- RECOMMENDED COLLABORATORS -->
                <section class="dashboard-section">
                    <div class="section-title-row">
                        <h2>Top Recommended Collaborators</h2>
                        <a href="<?= e(url('/student/research-connect.php')) ?>">View All Matches</a>
                    </div>
                    <?php if ($recommended): ?>
                        <div class="collaborators-grid">
                            <?php foreach ($recommended as $person): ?>
                                <?php $domainIds = get_profile_domain_ids($pdo, (int)$person['id']);
                                      $domainNames = [];
                                      if ($domainIds) {
                                          foreach (all_research_domains($pdo) as $d) {
                                              if (in_array((int)$d['id'], $domainIds, true)) { $domainNames[] = $d['name']; }
                                          }
                                      }
                                ?>
                                <div class="collaborator-card">
                                    <div class="collaborator-header">
                                        <?php if (!empty($person['profile_photo'])): ?>
                                            <img src="<?= e(url('/uploads/avatars/' . $person['profile_photo'])) ?>" alt="<?= e($person['name']) ?>">
                                        <?php else: ?>
                                            <span class="user-avatar avatar-initials" style="width:52px;height:52px;font-size:18px;border-radius:50%;"><?= e(initials($person['name'])) ?></span>
                                        <?php endif; ?>
                                        <div>
                                            <h3><?= e($person['name']) ?></h3>
                                            <p><?= e($person['department'] ?: 'Department not set') ?></p>
                                        </div>
                                    </div>
                                    <div class="match-score"><span><?= (int)$person['match_score'] ?>% Match</span></div>
                                    <p class="collaborator-interests">
                                        <?= $domainNames ? e(implode(', ', array_slice($domainNames, 0, 4))) : 'No research interests added yet' ?>
                                    </p>
                                    <div class="collaborator-actions">
                                        <a href="<?= e(url('/student/researcher-profile.php?id=' . $person['user_id'])) ?>" class="invite-button text-decoration-none text-center">
                                            <i class="bi bi-person-plus"></i> Invite
                                        </a>
                                        <a href="<?= e(url('/student/researcher-profile.php?id=' . $person['user_id'])) ?>" class="profile-button">View Profile</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="app-empty-state"><i class="bi bi-people"></i><p>No recommended collaborators yet — complete your profile to get matched.</p></div>
                    <?php endif; ?>
                </section>

                <!-- RESEARCH OPPORTUNITIES -->
                <section class="dashboard-section opportunities-dashboard-section">
                    <div class="section-title-row">
                        <h2>Latest Research Opportunities</h2>
                        <a href="<?= e(url('/student/opportunities.php')) ?>">View All Opportunities</a>
                    </div>
                    <?php if ($opportunities): ?>
                        <div class="dashboard-opportunity-grid">
                            <?php foreach ($opportunities as $opp): ?>
                                <?php $tags = $opp['domain_names'] ? explode('||', $opp['domain_names']) : []; ?>
                                <div class="dashboard-opportunity-card">
                                    <span class="opportunity-badge fydp"><?= e($opp['project_type'] ?: 'Research') ?></span>
                                    <h3><?= e($opp['title']) ?></h3>
                                    <div class="opportunity-members"><i class="bi bi-people-fill"></i> Need <?= (int)$opp['team_size_min'] ?> - <?= (int)$opp['team_size_max'] ?> Members</div>
                                    <div class="opportunity-tags">
                                        <?php foreach (array_slice($tags, 0, 3) as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?>
                                    </div>
                                    <div class="opportunity-card-footer">
                                        <span>Deadline: <?= format_date($opp['deadline']) ?></span>
                                        <a href="<?= e(url('/student/opportunity-details.php?id=' . $opp['id'])) ?>">View Details<i class="bi bi-arrow-right"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="app-empty-state"><i class="bi bi-briefcase"></i><p>No open opportunities right now — check back soon.</p></div>
                    <?php endif; ?>
                </section>

                <!-- QUICK ACTIONS -->
                <section class="quick-actions-section">
                    <div class="section-title-row">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="quick-actions">
                        <a href="<?= e(url('/student/research-connect.php')) ?>" class="quick-action"><i class="bi bi-people-fill"></i><span>Find Collaborators</span></a>
                        <a href="<?= e(url('/student/repository.php')) ?>" class="quick-action"><i class="bi bi-file-earmark-plus-fill"></i><span>Browse Repository</span></a>
                        <a href="<?= e(url('/student/team-create.php')) ?>" class="quick-action"><i class="bi bi-globe2"></i><span>Create Team</span></a>
                    </div>
                </section>
            </section>

            <!-- RIGHT SIDEBAR -->
            <aside class="dashboard-right-sidebar">
                <div class="right-dashboard-card profile-completion-card">
                    <h2>Profile Completion</h2>
                    <div class="profile-completion-content">
                        <div class="completion-circle"><span><?= $completion ?>%</span></div>
                        <div class="completion-message">
                            <h3><?= $completion >= 100 ? 'Profile Complete!' : ($completion >= 60 ? 'Almost There!' : 'Get Started') ?></h3>
                            <p>Complete your profile to get better recommendations.</p>
                        </div>
                    </div>
                    <a href="<?= e(url('/student/profile.php')) ?>" class="update-profile-button">Update Profile</a>
                </div>

                <div class="right-dashboard-card events-card">
                    <h2>Upcoming Milestones</h2>
                    <?php if ($milestoneRows): ?>
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
                    <?php else: ?>
                        <p class="text-muted small mb-0">No upcoming milestones.</p>
                    <?php endif; ?>
                    <a href="<?= e(url('/student/teams.php')) ?>" class="view-all-link">View All Teams</a>
                </div>

                <div class="right-dashboard-card messages-card">
                    <h2>Recent Activity</h2>
                    <?php if ($activityRows): ?>
                        <?php foreach ($activityRows as $a): ?>
                            <div class="recent-message">
                                <span class="user-avatar avatar-initials" style="width:32px;height:32px;font-size:11px;border-radius:50%;"><i class="bi bi-activity"></i></span>
                                <div class="message-content">
                                    <h3><?= e(ucwords(str_replace('_', ' ', $a['activity_type'] ?? ''))) ?></h3>
                                    <p><?= e($a['description']) ?></p>
                                </div>
                                <div class="message-meta"><span><?= e(time_ago($a['created_at'])) ?></span></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No recent activity yet.</p>
                    <?php endif; ?>
                    <a href="<?= e(url('/student/notifications.php')) ?>" class="view-all-link">View Notifications (<?= $unreadCount ?> unread, <?= $savedCount ?> saved)</a>
                </div>
            </aside>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
