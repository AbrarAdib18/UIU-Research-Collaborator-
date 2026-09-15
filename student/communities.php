<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

// ---------------------------------------------------------------------
// Search / filter / pagination
// ---------------------------------------------------------------------
$q        = trim($_GET['q'] ?? '');
$domainId = (isset($_GET['domain_id']) && $_GET['domain_id'] !== '') ? (int)$_GET['domain_id'] : 0;

$domains = all_research_domains($pdo);

$perPage = 9;
$page    = current_page();
$offset  = paginate_offset($page, $perPage);

// Scope decision for this milestone: self-serve browse/join only covers
// Active, Public communities. Private communities aren't listed here.
$where  = ["c.status = 'Active'", "c.privacy = 'Public'"];
$params = [];

if ($q !== '') {
    $where[]  = '(c.name LIKE ? OR c.description LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($domainId > 0) {
    $where[]  = 'c.domain_id = ?';
    $params[] = $domainId;
}
$whereSql = implode(' AND ', $where);

$communities      = [];
$totalCommunities = 0;
$myCommunityIds   = [];

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM communities c WHERE $whereSql");
    $countStmt->execute($params);
    $totalCommunities = (int)$countStmt->fetchColumn();

    // $perPage / $offset are internally derived integers, safe to inline
    // (native prepares reject bound LIMIT/OFFSET params on this PDO config).
    $sql = "SELECT c.*, u.name AS creator_name, d.name AS domain_name,
                   (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id) AS member_count
            FROM communities c
            JOIN users u ON u.id = c.created_by
            LEFT JOIN research_domains d ON d.id = c.domain_id
            WHERE $whereSql
            ORDER BY c.created_at DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $communities = $stmt->fetchAll();

    if ($communities) {
        $ids = array_map(fn($c) => (int)$c['id'], $communities);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $memStmt = $pdo->prepare("SELECT community_id FROM community_members WHERE user_id = ? AND community_id IN ($in)");
        $memStmt->execute(array_merge([$userId], $ids));
        $myCommunityIds = array_map('intval', $memStmt->fetchAll(PDO::FETCH_COLUMN));
    }
} catch (PDOException $e) {
    error_log('communities.php list error: ' . $e->getMessage());
    flash('error', 'Something went wrong loading communities. Please try again.');
}

$totalPages = max(1, (int)ceil($totalCommunities / $perPage));

$currentQueryString = '?' . http_build_query($_GET);
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
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div class="welcome-section">
                <h2>Communities</h2>
                <p>Discover and join research communities that match your interests.</p>
            </div>
            <a href="<?= e(url('/student/community-create.php')) ?>" class="profile-button" style="width:auto;padding:0 16px;">
                <i class="bi bi-plus-lg"></i>&nbsp;Create Community
            </a>
        </div>

        <section class="dashboard-section">
            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted" for="q">Search</label>
                    <input type="text" id="q" name="q" class="form-control" placeholder="Search by name or description" value="<?= e($q) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted" for="domain_id">Research Domain</label>
                    <select id="domain_id" name="domain_id" class="form-select">
                        <option value="">All Domains</option>
                        <?php foreach ($domains as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $domainId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="profile-button" style="width:auto;padding:0 14px;">
                        <i class="bi bi-search"></i>&nbsp;Filter
                    </button>
                    <?php if ($q !== '' || $domainId > 0): ?>
                        <a href="<?= e(url('/student/communities.php')) ?>" class="update-profile-button d-inline-flex" style="width:auto;padding:0 14px;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="dashboard-section">
            <?php if ($communities): ?>
                <div class="collaborators-grid">
                    <?php foreach ($communities as $c): ?>
                        <?php $isMember = in_array((int)$c['id'], $myCommunityIds, true); ?>
                        <div class="collaborator-card">
                            <div class="collaborator-header">
                                <?php if (!empty($c['cover_image'])): ?>
                                    <img src="<?= e(url('/uploads/communities/' . $c['cover_image'])) ?>" alt="<?= e($c['name']) ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <span class="user-avatar avatar-initials" style="width:50px;height:50px;font-size:16px;border-radius:50%;"><?= e(initials($c['name'])) ?></span>
                                <?php endif; ?>
                                <div>
                                    <h3><?= e($c['name']) ?></h3>
                                    <p><?= e($c['domain_name'] ?: 'General') ?></p>
                                </div>
                            </div>
                            <p class="collaborator-interests"><?= e(mb_strimwidth((string)($c['description'] ?? ''), 0, 140, '...')) ?: 'No description provided.' ?></p>
                            <div class="collaborator-actions">
                                <span class="text-muted small"><i class="bi bi-people-fill"></i> <?= (int)$c['member_count'] ?> members</span>
                                <?php if ($isMember): ?>
                                    <span class="opportunity-badge fydp">Joined</span>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('/student/community-details.php')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="join">
                                        <input type="hidden" name="community_id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= e(url('/student/communities.php') . $currentQueryString) ?>">
                                        <button type="submit" class="profile-button" style="width:auto;padding:0 14px;">Join</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <a href="<?= e(url('/student/community-details.php?id=' . (int)$c['id'])) ?>" class="view-all-link mt-2">View Community</a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="app-pagination">
                        <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo; Prev</a><?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= e(page_url($i)) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1)) ?>">Next &raquo;</a><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="app-empty-state">
                    <i class="bi bi-diagram-3"></i>
                    <?php if ($q !== '' || $domainId > 0): ?>
                        <p>No communities found. Try a different search or filter.</p>
                    <?php else: ?>
                        <p>No communities yet. Be the first to create one!</p>
                        <a href="<?= e(url('/student/community-create.php')) ?>" class="profile-button d-inline-flex" style="width:auto;padding:0 16px;">
                            <i class="bi bi-plus-lg"></i>&nbsp;Create the First Community
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
