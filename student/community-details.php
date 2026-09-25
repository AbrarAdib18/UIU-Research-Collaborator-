<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

/** Only allow same-app relative paths as a POST redirect target (no open redirects). */
function safe_local_path(?string $path, string $default): string
{
    if (!$path) {
        return $default;
    }
    $path = trim($path);
    if ($path === '' || $path[0] !== '/' || strpos($path, '//') === 0 || preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $path)) {
        return $default;
    }
    return $path;
}

// ---------------------------------------------------------------------
// Join / Leave (same-page POST action)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['join', 'leave'], true)) {
    $postedCommunityId = (int)($_POST['community_id'] ?? 0);
    $defaultRedirect   = '/student/community-details.php?id=' . $postedCommunityId;

    require_csrf($defaultRedirect);

    $redirectTo = safe_local_path($_POST['redirect'] ?? null, $defaultRedirect);
    $action     = $_POST['action'];

    if ($postedCommunityId <= 0) {
        flash('error', 'Community not found.');
        redirect('/student/communities.php');
    }

    try {
        $chk = $pdo->prepare("SELECT id, privacy FROM communities WHERE id = ? AND status = 'Active'");
        $chk->execute([$postedCommunityId]);
        $communityRow = $chk->fetch();
        if (!$communityRow) {
            flash('error', 'Community not found.');
            redirect('/student/communities.php');
        }

        if ($action === 'join') {
            $memChk = $pdo->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ?');
            $memChk->execute([$postedCommunityId, $userId]);
            if ($memChk->fetch()) {
                flash('info', "You're already a member of this community.");
            } elseif ($communityRow['privacy'] === 'Private') {
                // Self-serve join is only supported for Public communities; Private
                // communities require an invitation (not implemented in this module).
                flash('error', 'This is a private community. You need an invitation to join.');
            } else {
                $ins = $pdo->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'Member')");
                $ins->execute([$postedCommunityId, $userId]);
                log_activity($pdo, $userId, 'community_join', 'Joined a community', 'community', $postedCommunityId);
                flash('success', 'You have joined the community.');
            }
        } else { // leave
            $memberCountStmt = $pdo->prepare('SELECT COUNT(*) FROM community_members WHERE community_id = ?');
            $memberCountStmt->execute([$postedCommunityId]);
            $currentCount = (int)$memberCountStmt->fetchColumn();

            $isMemberStmt = $pdo->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ?');
            $isMemberStmt->execute([$postedCommunityId, $userId]);
            $memberRow = $isMemberStmt->fetch();

            if (!$memberRow) {
                flash('info', "You're not a member of this community.");
            } elseif ($currentCount <= 1) {
                flash('warning', "You're the only member — leaving isn't allowed yet.");
            } else {
                $del = $pdo->prepare('DELETE FROM community_members WHERE community_id = ? AND user_id = ?');
                $del->execute([$postedCommunityId, $userId]);
                log_activity($pdo, $userId, 'community_leave', 'Left a community', 'community', $postedCommunityId);
                flash('success', 'You have left the community.');
            }
        }
    } catch (PDOException $e) {
        error_log('community membership error: ' . $e->getMessage());
        if (($e->errorInfo[1] ?? null) === 1062) {
            flash('info', "You're already a member of this community.");
        } else {
            flash('error', 'Something went wrong. Please try again.');
        }
    }

    redirect($redirectTo);
}

// ---------------------------------------------------------------------
// Load community
// ---------------------------------------------------------------------
$communityId = (int)($_GET['id'] ?? 0);
if ($communityId <= 0) {
    flash('error', 'Community not found.');
    redirect('/student/communities.php');
}

try {
    $stmt = $pdo->prepare(
        "SELECT c.*, u.name AS creator_name, d.name AS domain_name
         FROM communities c
         JOIN users u ON u.id = c.created_by
         LEFT JOIN research_domains d ON d.id = c.domain_id
         WHERE c.id = ? AND (c.status = 'Active' OR c.created_by = ?)"
    );
    $stmt->execute([$communityId, $userId]);
    $community = $stmt->fetch();
} catch (PDOException $e) {
    error_log('community-details.php load error: ' . $e->getMessage());
    $community = null;
}

if (!$community) {
    flash('error', 'Community not found.');
    redirect('/student/communities.php');
}

$memberCount = 0;
$membership  = null;
try {
    $memberCountStmt = $pdo->prepare('SELECT COUNT(*) FROM community_members WHERE community_id = ?');
    $memberCountStmt->execute([$communityId]);
    $memberCount = (int)$memberCountStmt->fetchColumn();

    $membershipStmt = $pdo->prepare('SELECT role FROM community_members WHERE community_id = ? AND user_id = ?');
    $membershipStmt->execute([$communityId, $userId]);
    $membership = $membershipStmt->fetch();
} catch (PDOException $e) {
    error_log('community-details.php membership error: ' . $e->getMessage());
    flash('error', 'Something went wrong loading this community. Please try again.');
}
$isMember = (bool)$membership;

// Scope decision mirrors communities.php: self-serve browse/join and full
// post content are only available for Public communities. A Private
// community is still reachable if a member (or an outsider) has the direct
// URL, but non-members only get the name + a "private" notice — never the
// posts/comments feed or the post/comment forms.
$isPrivateCommunity = (($community['privacy'] ?? 'Public') === 'Private');
$canViewContent     = $isMember || !$isPrivateCommunity;

// ---------------------------------------------------------------------
// Posts feed (paginated, newest first)
// ---------------------------------------------------------------------
$perPage = 10;
$page    = current_page();
$offset  = paginate_offset($page, $perPage);

$posts       = [];
$commentsMap = [];
$totalPosts  = 0;

// Private communities: never even fetch post content for a non-member who
// reached this page via a guessed/shared URL — the feed stays empty and the
// UI below shows a "private" notice instead.
if ($canViewContent) {
try {
    $totalPostsStmt = $pdo->prepare("SELECT COUNT(*) FROM community_posts WHERE community_id = ? AND is_hidden = 0");
    $totalPostsStmt->execute([$communityId]);
    $totalPosts = (int)$totalPostsStmt->fetchColumn();

    $postsStmt = $pdo->prepare(
        "SELECT p.*, u.name AS author_name
         FROM community_posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.community_id = ? AND p.is_hidden = 0
         ORDER BY p.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $postsStmt->execute([$communityId]);
    $posts = $postsStmt->fetchAll();

    if ($posts) {
        $postIds = array_map(fn($p) => (int)$p['id'], $posts);
        $in      = implode(',', array_fill(0, count($postIds), '?'));
        $commentsStmt = $pdo->prepare(
            "SELECT cc.*, u.name AS author_name
             FROM community_comments cc
             JOIN users u ON u.id = cc.user_id
             WHERE cc.post_id IN ($in) AND cc.is_hidden = 0
             ORDER BY cc.created_at ASC"
        );
        $commentsStmt->execute($postIds);
        foreach ($commentsStmt->fetchAll() as $comment) {
            $commentsMap[(int)$comment['post_id']][] = $comment;
        }
    }
} catch (PDOException $e) {
    error_log('community-details.php posts error: ' . $e->getMessage());
    flash('error', 'Something went wrong loading posts. Please try again.');
}
}

$totalPages = max(1, (int)ceil($totalPosts / $perPage));

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
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <!-- COMMUNITY HEADER -->
        <section class="dashboard-section">
            <?php if (!empty($community['cover_image'])): ?>
                <div style="height:160px;border-radius:13px;overflow:hidden;margin-bottom:14px;">
                    <img src="<?= e(url('/uploads/communities/' . $community['cover_image'])) ?>" alt="<?= e($community['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                </div>
            <?php else: ?>
                <div style="height:120px;border-radius:13px;margin-bottom:14px;background:linear-gradient(135deg, var(--uiu-blue), var(--uiu-dark-blue));display:flex;align-items:center;gap:14px;padding:0 24px;color:#ffffff;">
                    <span class="avatar-initials" style="width:56px;height:56px;border-radius:50%;font-size:20px;background-color:rgba(255,255,255,0.18);flex-shrink:0;"><?= e(initials($community['name'])) ?></span>
                    <div>
                        <h2 style="color:#ffffff;margin:0;font-size:22px;"><?= e($community['name']) ?></h2>
                        <p style="margin:2px 0 0;font-size:12px;opacity:0.9;">
                            <i class="bi bi-<?= $isPrivateCommunity ? 'lock-fill' : 'globe2' ?>"></i> <?= $isPrivateCommunity ? 'Private Community' : 'Public Community' ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($community['cover_image'])): ?>
                <h2 style="color:var(--uiu-blue);margin:0 0 4px;"><?= e($community['name']) ?></h2>
            <?php endif; ?>

            <p class="text-muted mb-2"><?= nl2br(e($community['description'] ?: 'No description provided.')) ?></p>

            <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                <span class="text-muted small"><i class="bi bi-people-fill"></i> <?= $memberCount ?> member<?= $memberCount === 1 ? '' : 's' ?></span>
                <span class="text-muted small"><i class="bi bi-tag-fill"></i> <?= e($community['domain_name'] ?: 'General') ?></span>
                <span class="text-muted small"><i class="bi bi-person-circle"></i> Created by <?= e($community['creator_name']) ?></span>
                <?php if ($isMember): ?>
                    <span class="opportunity-badge fydp">You're a member<?= $membership['role'] !== 'Member' ? ' (' . e($membership['role']) . ')' : '' ?></span>
                <?php endif; ?>
            </div>

            <?php if ($isMember): ?>
                <form method="post" action="<?= e(url('/student/community-details.php')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="community_id" value="<?= $communityId ?>">
                    <input type="hidden" name="redirect" value="<?= e(url('/student/community-details.php?id=' . $communityId)) ?>">
                    <input type="hidden" name="action" value="leave">
                    <button type="submit" class="update-profile-button d-inline-flex" style="width:auto;padding:0 16px;">
                        <i class="bi bi-box-arrow-right"></i>&nbsp;Leave Community
                    </button>
                </form>
            <?php elseif ($isPrivateCommunity): ?>
                <p class="text-muted small mb-0"><i class="bi bi-lock-fill"></i> This is a private community. You need an invitation to join.</p>
            <?php else: ?>
                <form method="post" action="<?= e(url('/student/community-details.php')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="community_id" value="<?= $communityId ?>">
                    <input type="hidden" name="redirect" value="<?= e(url('/student/community-details.php?id=' . $communityId)) ?>">
                    <input type="hidden" name="action" value="join">
                    <button type="submit" class="profile-button" style="width:auto;padding:0 16px;">
                        <i class="bi bi-plus-circle"></i>&nbsp;Join Community
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <!-- NEW POST -->
        <section class="dashboard-section">
            <div class="section-title-row">
                <h2>Community Posts</h2>
            </div>

            <?php if ($isMember): ?>
                <form method="post" action="<?= e(url('/student/community-post.php')) ?>" class="right-dashboard-card mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_post">
                    <input type="hidden" name="community_id" value="<?= $communityId ?>">
                    <div class="mb-2">
                        <input type="text" name="title" class="form-control" placeholder="Title (optional)" maxlength="250">
                    </div>
                    <div class="mb-2">
                        <textarea name="content" class="form-control" rows="3" placeholder="Share something with the community..." required></textarea>
                    </div>
                    <button type="submit" class="profile-button" style="width:auto;padding:0 16px;">Post</button>
                </form>
            <?php elseif ($isPrivateCommunity): ?>
                <div class="app-empty-state">
                    <i class="bi bi-lock-fill"></i>
                    <p>This is a private community. Its posts are only visible to members.</p>
                </div>
            <?php else: ?>
                <div class="app-empty-state">
                    <i class="bi bi-lock-fill"></i>
                    <p>Join this community to post.</p>
                </div>
            <?php endif; ?>

            <!-- POSTS FEED -->
            <?php if (!$canViewContent): ?>
                <!-- Private community, non-member: post content intentionally withheld above. -->
            <?php elseif ($posts): ?>
                <?php foreach ($posts as $post): ?>
                    <?php $postComments = $commentsMap[(int)$post['id']] ?? []; ?>
                    <div class="right-dashboard-card mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center gap-2">
                                <span class="user-avatar avatar-initials" style="width:34px;height:34px;font-size:12px;border-radius:50%;"><?= e(initials($post['author_name'])) ?></span>
                                <div>
                                    <div style="font-weight:700;font-size:13px;color:#111111;"><?= e($post['author_name']) ?></div>
                                    <div class="text-muted" style="font-size:10px;"><?= e(time_ago($post['created_at'])) ?></div>
                                </div>
                            </div>
                            <?php if ((int)$post['user_id'] === $userId): ?>
                                <form method="post" action="<?= e(url('/student/community-post.php')) ?>" onsubmit="return confirm('Delete this post?');" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                    <button type="submit" class="border-0 bg-transparent text-danger p-0" style="font-size:12px;" aria-label="Delete post">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($post['title'])): ?>
                            <h3 style="font-size:15px;margin:8px 0 4px;color:#111111;"><?= e($post['title']) ?></h3>
                        <?php endif; ?>
                        <p style="font-size:13px;color:#333333;white-space:pre-wrap;margin:6px 0 10px;"><?= nl2br(e($post['content'])) ?></p>

                        <!-- COMMENTS -->
                        <?php if (!$postComments): ?>
                            <p class="text-muted small" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:6px;">No comments yet. Be the first to reply.</p>
                        <?php else: ?>
                            <div style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:6px;">
                                <?php foreach ($postComments as $comment): ?>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-start gap-2">
                                            <span class="user-avatar avatar-initials" style="width:26px;height:26px;font-size:10px;border-radius:50%;flex-shrink:0;"><?= e(initials($comment['author_name'])) ?></span>
                                            <div>
                                                <span style="font-weight:700;font-size:12px;color:#111111;"><?= e($comment['author_name']) ?></span>
                                                <span class="text-muted" style="font-size:10px;"> &middot; <?= e(time_ago($comment['created_at'])) ?></span>
                                                <div style="font-size:12px;color:#333333;white-space:pre-wrap;"><?= nl2br(e($comment['comment'])) ?></div>
                                            </div>
                                        </div>
                                        <?php if ((int)$comment['user_id'] === $userId): ?>
                                            <form method="post" action="<?= e(url('/student/community-post.php')) ?>" onsubmit="return confirm('Delete this comment?');" class="m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                                                <button type="submit" class="border-0 bg-transparent text-danger p-0" style="font-size:11px;" aria-label="Delete comment">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isMember): ?>
                            <form method="post" action="<?= e(url('/student/community-post.php')) ?>" class="d-flex gap-2 mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="create_comment">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <input type="text" name="comment" class="form-control form-control-sm" placeholder="Write a comment..." required maxlength="1000">
                                <button type="submit" class="profile-button" style="width:auto;padding:0 12px;flex-shrink:0;">Reply</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

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
                    <i class="bi bi-chat-square-text"></i>
                    <p>No posts yet in this community. Be the first to share something.</p>
                </div>
            <?php endif; ?>
        </section>

        <a href="<?= e(url('/student/communities.php')) ?>" class="view-all-link">&laquo; Back to Communities</a>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
