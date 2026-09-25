<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo        = db();
$adminId    = (int)$currentUser['id'];
$communityId = validate_id($_GET['id'] ?? null);

if (!$communityId) {
    flash('error', 'Invalid community.');
    redirect('/admin/communities.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/community-details.php?id=' . $communityId);
    $action = $_POST['action'] ?? '';
    $postId = validate_id($_POST['post_id'] ?? null);

    if (in_array($action, ['hide_post', 'restore_post'], true) && $postId) {
        $hide = $action === 'hide_post' ? 1 : 0;
        $chk = $pdo->prepare('SELECT * FROM community_posts WHERE id = ? AND community_id = ?');
        $chk->execute([$postId, $communityId]);
        $post = $chk->fetch();
        if ($post) {
            $pdo->prepare('UPDATE community_posts SET is_hidden = ? WHERE id = ?')->execute([$hide, $postId]);
            log_activity($pdo, $adminId, 'admin_post_moderate', ($hide ? 'Hid' : 'Restored') . ' a community post', 'community_post', $postId);
            if ($hide) {
                create_notification($pdo, (int)$post['user_id'], 'community_post', 'Post Hidden', 'A post you made was hidden by an administrator.', 'community', $communityId);
            }
            flash('success', $hide ? 'Post hidden.' : 'Post restored.');
        }
    } elseif ($action === 'delete_post' && $postId) {
        $pdo->prepare('DELETE FROM community_posts WHERE id = ? AND community_id = ?')->execute([$postId, $communityId]);
        log_activity($pdo, $adminId, 'admin_post_delete', 'Deleted a community post', 'community_post', $postId);
        flash('success', 'Post deleted.');
    }
    redirect('/admin/community-details.php?id=' . $communityId);
}

$stmt = $pdo->prepare('SELECT c.*, u.name AS creator_name, d.name AS domain_name FROM communities c JOIN users u ON u.id = c.created_by LEFT JOIN research_domains d ON d.id = c.domain_id WHERE c.id = ?');
$stmt->execute([$communityId]);
$community = $stmt->fetch();
if (!$community) {
    flash('error', 'Community not found.');
    redirect('/admin/communities.php');
}

$stmt2 = $pdo->prepare('SELECT COUNT(*) FROM community_members WHERE community_id = ?'); $stmt2->execute([$communityId]); $memberCount = (int)$stmt2->fetchColumn();

$posts = $pdo->prepare(
    "SELECT cp.*, u.name AS author_name FROM community_posts cp JOIN users u ON u.id = cp.user_id WHERE cp.community_id = ? ORDER BY cp.created_at DESC LIMIT 30"
);
$posts->execute([$communityId]);
$posts = $posts->fetchAll();

$pageTitle = $community['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($community['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>.detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}.detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}.post-item{padding:12px 0;border-bottom:1px solid var(--border-light,#eee)}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/admin/communities.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Communities</a>

        <div class="detail-card">
            <h1 style="font-size:20px;color:var(--uiu-blue);font-weight:700;"><?= e($community['name']) ?> <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($community['status']) ?></span> <span class="badge bg-light text-dark border"><?= e($community['privacy']) ?></span></h1>
            <p class="text-muted small">by <?= e($community['creator_name']) ?> &middot; <?= $memberCount ?> members &middot; <?= e($community['domain_name'] ?: 'General') ?></p>
            <p><?= nl2br(e($community['description'] ?: 'No description.')) ?></p>
        </div>

        <div class="detail-card">
            <h2>Moderation</h2>
            <form method="post" class="d-flex flex-wrap gap-2 align-items-center mb-2" action="<?= e(url('/admin/communities.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $communityId ?>">
                <input type="text" name="reason" class="form-control form-control-sm" style="max-width:220px;" placeholder="Reason (optional)">
                <?php if ($community['status'] === 'Active'): ?><button name="action" value="suspend" class="btn btn-sm btn-outline-warning">Suspend Community</button><?php else: ?><button name="action" value="restore" class="btn btn-sm btn-outline-success">Restore Community</button><?php endif; ?>
            </form>
            <form method="post" action="<?= e(url('/admin/communities.php')) ?>" onsubmit="return confirm('Delete this community permanently? This cannot be undone.');">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $communityId ?>"><input type="hidden" name="action" value="delete">
                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Deletion reason (optional)">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete Community</button>
            </form>
        </div>

        <div class="detail-card">
            <h2>Posts (<?= count($posts) ?>)</h2>
            <?php if (!$posts): ?><p class="text-muted mb-0">No posts yet.</p><?php endif; ?>
            <?php foreach ($posts as $p): ?>
                <div class="post-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <?php if ($p['title']): ?><strong><?= e($p['title']) ?></strong><br><?php endif; ?>
                            <span class="text-muted small"><?= e($p['author_name']) ?> &middot; <?= e(time_ago($p['created_at'])) ?><?php if ($p['is_hidden']): ?> &middot; <span class="badge bg-warning-subtle text-warning-emphasis">Hidden</span><?php endif; ?></span>
                        </div>
                        <div class="d-flex gap-1">
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="<?= $p['is_hidden'] ? 'restore_post' : 'hide_post' ?>"><button class="btn btn-sm btn-outline-secondary"><?= $p['is_hidden'] ? 'Restore' : 'Hide' ?></button></form>
                            <form method="post" onsubmit="return confirm('Delete this post permanently?');"><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="delete_post"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </div>
                    </div>
                    <p class="mb-0 mt-1"><?= nl2br(e(mb_strimwidth($p['content'], 0, 300, '...'))) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
