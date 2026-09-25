<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$communityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT c.*, u.name AS creator_name FROM communities c JOIN users u ON u.id = c.created_by WHERE c.id = ? AND c.status='Active'");
$stmt->execute([$communityId]);
$community = $stmt->fetch();
if (!$community) {
    flash('error', 'Community not found.');
    redirect('/faculty/communities.php');
}

$memChk = $pdo->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ?');
$memChk->execute([$communityId, $userId]);
$isMember = (bool)$memChk->fetch();

if ($community['privacy'] === 'Private' && !$isMember) {
    flash('error', 'This is a private community.');
    redirect('/faculty/communities.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/community-details.php?id=' . $communityId);
    if (!$isMember) {
        flash('error', 'Join the community to post.');
        redirect('/faculty/community-details.php?id=' . $communityId);
    }
    $content = nullable_trim($_POST['content'] ?? '');
    if ($content === null) {
        flash('error', 'Post content cannot be empty.');
    } else {
        try {
            $pdo->prepare('INSERT INTO community_posts (community_id, user_id, title, content) VALUES (?,?,?,?)')
                ->execute([$communityId, $userId, nullable_trim($_POST['title'] ?? ''), $content]);
            log_activity($pdo, $userId, 'community_post', 'Posted in a community', 'community', $communityId);
            flash('success', 'Post published.');
        } catch (Throwable $ex) {
            error_log('faculty community-details post: ' . $ex->getMessage());
            flash('error', 'Could not publish your post.');
        }
    }
    redirect('/faculty/community-details.php?id=' . $communityId);
}

$posts = $pdo->prepare(
    "SELECT cp.*, u.name AS author_name, u.role AS author_role FROM community_posts cp JOIN users u ON u.id = cp.user_id
     WHERE cp.community_id = ? AND cp.is_hidden = 0 ORDER BY cp.created_at DESC LIMIT 20"
);
$posts->execute([$communityId]);
$posts = $posts->fetchAll();

$pageTitle = $community['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($community['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:14px}
        .post-item{padding:12px 0;border-bottom:1px solid var(--border-light,#eee)}
        .post-item:last-child{border-bottom:none}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/faculty/communities.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Communities</a>
        <h2 style="color:var(--uiu-blue);font-size:22px;font-weight:700;"><?= e($community['name']) ?></h2>
        <p class="text-muted"><?= e($community['description']) ?></p>

        <?php if ($isMember): ?>
        <div class="app-panel">
            <form method="post">
                <?= csrf_field() ?>
                <input type="text" name="title" class="form-control mb-2" placeholder="Title (optional)">
                <textarea name="content" class="form-control mb-2" rows="3" placeholder="Share something with this community..." required></textarea>
                <button class="btn btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Post</button>
            </form>
        </div>
        <?php else: ?>
        <div class="app-panel"><p class="text-muted mb-0">Join this community to post and comment.</p></div>
        <?php endif; ?>

        <div class="app-panel">
            <?php if (!$posts): ?><p class="text-muted mb-0">No posts yet.</p><?php endif; ?>
            <?php foreach ($posts as $p): ?>
                <div class="post-item">
                    <?php if ($p['title']): ?><strong><?= e($p['title']) ?></strong><br><?php endif; ?>
                    <span class="text-muted small"><?= e($p['author_name']) ?> (<?= e(ucfirst($p['author_role'])) ?>) &middot; <?= e(time_ago($p['created_at'])) ?></span>
                    <p class="mb-0"><?= nl2br(e($p['content'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
