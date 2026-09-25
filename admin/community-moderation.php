<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/community-moderation.php');
    $action = $_POST['action'] ?? '';
    $postId = validate_id($_POST['post_id'] ?? null);
    $commentId = validate_id($_POST['comment_id'] ?? null);

    if (in_array($action, ['hide_post', 'restore_post']) && $postId) {
        $hide = $action === 'hide_post' ? 1 : 0;
        $chk = $pdo->prepare('SELECT user_id FROM community_posts WHERE id = ?'); $chk->execute([$postId]);
        $ownerId = $chk->fetchColumn();
        $pdo->prepare('UPDATE community_posts SET is_hidden = ? WHERE id = ?')->execute([$hide, $postId]);
        log_activity($pdo, $adminId, 'admin_post_moderate', ($hide ? 'Hid' : 'Restored') . ' a community post', 'community_post', $postId);
        if ($hide && $ownerId) {
            create_notification($pdo, (int)$ownerId, 'community_post', 'Post Hidden', 'A post you made was hidden by an administrator.', 'community_post', $postId);
        }
        flash('success', $hide ? 'Post hidden.' : 'Post restored.');
    } elseif (in_array($action, ['hide_comment', 'restore_comment']) && $commentId) {
        $hide = $action === 'hide_comment' ? 1 : 0;
        $pdo->prepare('UPDATE community_comments SET is_hidden = ? WHERE id = ?')->execute([$hide, $commentId]);
        log_activity($pdo, $adminId, 'admin_comment_moderate', ($hide ? 'Hid' : 'Restored') . ' a community comment', 'community_comment', $commentId);
        flash('success', $hide ? 'Comment hidden.' : 'Comment restored.');
    } elseif ($action === 'delete_comment' && $commentId) {
        $pdo->prepare('DELETE FROM community_comments WHERE id = ?')->execute([$commentId]);
        log_activity($pdo, $adminId, 'admin_comment_delete', 'Deleted a community comment', 'community_comment', $commentId);
        flash('success', 'Comment deleted.');
    }
    redirect('/admin/community-moderation.php');
}

$posts = $pdo->query(
    "SELECT cp.*, u.name AS author_name, c.name AS community_name, c.id AS community_id
     FROM community_posts cp JOIN users u ON u.id = cp.user_id JOIN communities c ON c.id = cp.community_id
     ORDER BY cp.created_at DESC LIMIT 40"
)->fetchAll();

$comments = $pdo->query(
    "SELECT cc.*, u.name AS author_name, cp.title AS post_title, cp.id AS post_id
     FROM community_comments cc JOIN users u ON u.id = cc.user_id JOIN community_posts cp ON cp.id = cc.post_id
     ORDER BY cc.created_at DESC LIMIT 40"
)->fetchAll();

$pageTitle = 'Community Moderation Queue';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Moderation || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:14px}
        .type-tabs{display:flex;gap:8px;margin-bottom:16px}
        .type-tabs a{padding:8px 18px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none}
        .type-tabs a.active{background:var(--uiu-blue);color:#fff}
        .post-item{padding:10px 0;border-bottom:1px solid var(--border-light,#eee)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Community Moderation Queue</h2>
        <div class="type-tabs">
            <a href="<?= e(url('/admin/communities.php')) ?>">All Communities</a>
            <a href="<?= e(url('/admin/community-moderation.php')) ?>" class="active">Moderation Queue</a>
        </div>

        <div class="app-panel">
            <h3 style="font-size:15px;color:var(--uiu-blue);font-weight:700;">Recent Posts (all communities)</h3>
            <?php if (!$posts): ?><p class="text-muted mb-0">No posts yet.</p><?php endif; ?>
            <?php foreach ($posts as $p): ?>
                <div class="post-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= e($p['title'] ?: 'Untitled post') ?></strong>
                            <span class="text-muted small"> by <?= e($p['author_name']) ?> in <a href="<?= e(url('/admin/community-details.php?id=' . $p['community_id'])) ?>"><?= e($p['community_name']) ?></a> &middot; <?= e(time_ago($p['created_at'])) ?><?php if ($p['is_hidden']): ?> &middot; <span class="badge bg-warning-subtle text-warning-emphasis">Hidden</span><?php endif; ?></span>
                        </div>
                        <div class="d-flex gap-1">
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="<?= $p['is_hidden'] ? 'restore_post' : 'hide_post' ?>"><button class="btn btn-sm btn-outline-secondary"><?= $p['is_hidden'] ? 'Restore' : 'Hide' ?></button></form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="app-panel">
            <h3 style="font-size:15px;color:var(--uiu-blue);font-weight:700;">Recent Comments (all communities)</h3>
            <?php if (!$comments): ?><p class="text-muted mb-0">No comments yet.</p><?php endif; ?>
            <?php foreach ($comments as $c): ?>
                <div class="post-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="small"><?= e(mb_strimwidth($c['comment'], 0, 100, '...')) ?></span><br>
                            <span class="text-muted small">by <?= e($c['author_name']) ?> on "<?= e($c['post_title'] ?: 'Untitled post') ?>" &middot; <?= e(time_ago($c['created_at'])) ?><?php if ($c['is_hidden']): ?> &middot; <span class="badge bg-warning-subtle text-warning-emphasis">Hidden</span><?php endif; ?></span>
                        </div>
                        <div class="d-flex gap-1">
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="<?= $c['is_hidden'] ? 'restore_comment' : 'hide_comment' ?>"><button class="btn btn-sm btn-outline-secondary"><?= $c['is_hidden'] ? 'Restore' : 'Hide' ?></button></form>
                            <form method="post" onsubmit="return confirm('Delete this comment?');"><?= csrf_field() ?><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="delete_comment"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
