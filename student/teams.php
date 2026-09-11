<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

// --- My Teams -----------------------------------------------------------
$myTeamsStmt = $pdo->prepare(
    "SELECT rt.*, tm.role,
            (SELECT COUNT(*) FROM team_members m2 WHERE m2.team_id = rt.id AND m2.status = 'Active') AS member_count,
            d.name AS domain_name
     FROM research_teams rt
     JOIN team_members tm ON tm.team_id = rt.id AND tm.user_id = ? AND tm.status = 'Active'
     LEFT JOIN research_domains d ON d.id = rt.research_domain_id
     ORDER BY rt.updated_at DESC"
);
$myTeamsStmt->execute([$userId]);
$myTeams = $myTeamsStmt->fetchAll();

// --- Discover Teams (Forming, not full, not already a member, no pending request) ---
$discoverStmt = $pdo->prepare(
    "SELECT rt.*, u.name AS creator_name, d.name AS domain_name,
            (SELECT COUNT(*) FROM team_members m2 WHERE m2.team_id = rt.id AND m2.status = 'Active') AS member_count,
            (SELECT COUNT(*) FROM team_members m3 WHERE m3.team_id = rt.id AND m3.user_id = ? AND m3.status = 'Active') AS is_member,
            (SELECT COUNT(*) FROM team_requests tr WHERE tr.team_id = rt.id AND tr.user_id = ? AND tr.status = 'Pending') AS has_pending_request
     FROM research_teams rt
     JOIN users u ON u.id = rt.created_by
     LEFT JOIN research_domains d ON d.id = rt.research_domain_id
     WHERE rt.status = 'Forming'
     HAVING is_member = 0 AND member_count < rt.team_size_limit
     ORDER BY rt.created_at DESC"
);
$discoverStmt->execute([$userId, $userId]);
$discoverTeams = $discoverStmt->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Teams || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px}
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
        .team-card{background:#e1efff;border-radius:10px;padding:16px;height:100%;display:flex;flex-direction:column}
        .team-card h3{color:#111;font-size:16px;font-weight:700;margin:0 0 4px}
        .team-card .team-meta{color:var(--text-light);font-size:12px;margin-bottom:8px}
        .team-card p.team-desc{color:#333;font-size:12px;line-height:18px;flex:1;margin-bottom:10px}
        .team-card-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:auto}
        .cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:15px}
        .request-join-form{display:flex;gap:6px}
        .request-join-form input[type=text]{font-size:12px;padding:4px 8px;border-radius:6px;border:1px solid var(--border-color);flex:1}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2>My Teams</h2>
                <p>Manage the research teams you belong to and discover new ones to join.</p>
            </div>
            <a href="<?= e(url('/student/team-create.php')) ?>" class="btn btn-uiu"><i class="bi bi-plus-lg"></i> Create Team</a>
        </div>

        <section class="dashboard-section">
            <div class="section-title-row"><h2>My Teams</h2></div>
            <?php if ($myTeams): ?>
                <div class="cards-grid">
                    <?php foreach ($myTeams as $team): ?>
                        <div class="team-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <h3><?= e($team['name']) ?></h3>
                                <span class="pill <?= $team['role'] === 'Leader' ? 'pill-purple' : 'pill-blue' ?>"><?= e($team['role']) ?></span>
                            </div>
                            <div class="team-meta">
                                <i class="bi bi-people"></i> <?= (int)$team['member_count'] ?>/<?= (int)$team['team_size_limit'] ?> members
                                <?php if ($team['domain_name']): ?> &middot; <i class="bi bi-bookmark"></i> <?= e($team['domain_name']) ?><?php endif; ?>
                            </div>
                            <p class="team-desc"><?= e(mb_strimwidth((string)($team['description'] ?? 'No description provided.'), 0, 140, '...')) ?></p>
                            <div class="team-card-footer">
                                <span class="<?= team_status_pill($team['status']) ?>"><?= e($team['status']) ?></span>
                                <a href="<?= e(url('/student/team-details.php?id=' . $team['id'])) ?>" class="profile-button" style="width:auto;padding:0 14px;">Open Workspace</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-people"></i><p>You're not part of any team yet. Create one or join a team below.</p></div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <div class="section-title-row"><h2>Discover Teams</h2></div>
            <?php if ($discoverTeams): ?>
                <div class="cards-grid">
                    <?php foreach ($discoverTeams as $team): ?>
                        <div class="team-card">
                            <h3><?= e($team['name']) ?></h3>
                            <div class="team-meta">
                                <i class="bi bi-people"></i> <?= (int)$team['member_count'] ?>/<?= (int)$team['team_size_limit'] ?> members
                                &middot; Led by <?= e($team['creator_name']) ?>
                                <?php if ($team['domain_name']): ?> &middot; <?= e($team['domain_name']) ?><?php endif; ?>
                            </div>
                            <p class="team-desc"><?= e(mb_strimwidth((string)($team['description'] ?? 'No description provided.'), 0, 140, '...')) ?></p>
                            <div class="team-card-footer">
                                <?php if ((int)$team['has_pending_request'] > 0): ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Request Pending</button>
                                <?php else: ?>
                                    <form action="<?= e(url('/student/team-requests.php')) ?>" method="post" class="request-join-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="create">
                                        <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                                        <input type="text" name="message" maxlength="255" placeholder="Optional note...">
                                        <button type="submit" class="btn btn-sm btn-uiu">Request to Join</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-search"></i><p>No teams are currently open for new members.</p></div>
            <?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
