<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['join', 'leave'], true)) {
    require_csrf('/faculty/communities.php');
    $communityId = (int)($_POST['community_id'] ?? 0);
    $action      = $_POST['action'];

    $chk = $pdo->prepare("SELECT id, privacy FROM communities WHERE id = ? AND status = 'Active'");
    $chk->execute([$communityId]);
    $community = $chk->fetch();

    if (!$community) {
        flash('error', 'Community not found.');
    } elseif ($action === 'join') {
        if ($community['privacy'] === 'Private') {
            flash('error', 'This is a private community. You need an invitation to join.');
        } else {
            try {
                $pdo->prepare("INSERT IGNORE INTO community_members (community_id, user_id, role) VALUES (?, ?, 'Member')")->execute([$communityId, $userId]);
                log_activity($pdo, $userId, 'community_join', 'Joined a community', 'community', $communityId);
                flash('success', 'You have joined the community.');
            } catch (Throwable $ex) {
                error_log('faculty communities join: ' . $ex->getMessage());
                flash('error', 'Could not join this community.');
            }
        }
    } else {
        try {
            $pdo->prepare('DELETE FROM community_members WHERE community_id = ? AND user_id = ?')->execute([$communityId, $userId]);
            flash('success', 'You have left the community.');
        } catch (Throwable $ex) {
            error_log('faculty communities leave: ' . $ex->getMessage());
            flash('error', 'Could not leave this community.');
        }
    }
    redirect('/faculty/communities.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$where  = ["c.status = 'Active'", "c.privacy = 'Public'"];
$params = [];
if ($q !== '') {
    $where[] = '(c.name LIKE ? OR c.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$stmt = $pdo->prepare(
    "SELECT c.*, u.name AS creator_name, d.name AS domain_name, (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id) AS member_count
     FROM communities c JOIN users u ON u.id = c.created_by LEFT JOIN research_domains d ON d.id = c.domain_id
     WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC LIMIT 30"
);
$stmt->execute($params);
$communities = $stmt->fetchAll();

$myIdsStmt = $pdo->prepare('SELECT community_id FROM community_members WHERE user_id = ?');
$myIdsStmt->execute([$userId]);
$myCommunityIds = array_map('intval', $myIdsStmt->fetchAll(PDO::FETCH_COLUMN));

$pageTitle = 'Communities';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communities || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .comm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
        .comm-card{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:16px}
        .comm-card h3{font-size:15px;color:var(--uiu-blue);font-weight:700;margin-bottom:4px}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;margin-bottom:14px;">Communities</h2>
        <form method="get" class="mb-3"><input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width:280px;" placeholder="Search communities..."></form>

        <?php if (!$communities): ?>
            <div class="app-empty-state"><i class="bi bi-diagram-3"></i><p>No communities found.</p></div>
        <?php endif; ?>
        <div class="comm-grid">
            <?php foreach ($communities as $c): ?>
                <?php $isMember = in_array((int)$c['id'], $myCommunityIds, true); ?>
                <div class="comm-card">
                    <h3><?= e($c['name']) ?></h3>
                    <p class="text-muted small"><?= e($c['domain_name'] ?: 'General') ?> &middot; <?= (int)$c['member_count'] ?> members</p>
                    <p class="small"><?= e(mb_strimwidth((string)$c['description'], 0, 100, '...')) ?></p>
                    <div class="d-flex gap-2">
                        <a href="<?= e(url('/faculty/community-details.php?id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="community_id" value="<?= (int)$c['id'] ?>">
                            <?php if ($isMember): ?>
                                <button name="action" value="leave" class="btn btn-sm btn-outline-secondary">Leave</button>
                            <?php else: ?>
                                <button name="action" value="join" class="btn btn-sm btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Join</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
