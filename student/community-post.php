<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

// This endpoint never renders HTML — it only processes a POST action
// (create_post / create_comment / delete_post / delete_comment) and
// redirects back to the relevant community-details.php page with a flash.

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/communities.php');
}

function community_details_url(int $communityId): string
{
    return '/student/community-details.php?id=' . $communityId;
}

function is_community_member(PDO $pdo, int $communityId, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM community_members WHERE community_id = ? AND user_id = ?');
    $stmt->execute([$communityId, $userId]);
    return (bool)$stmt->fetch();
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // -------------------------------------------------------------
    case 'create_post': {
        $communityId = (int)($_POST['community_id'] ?? 0);
        require_csrf($communityId > 0 ? community_details_url($communityId) : '/student/communities.php');

        if ($communityId <= 0) {
            flash('error', 'Community not found.');
            redirect('/student/communities.php');
        }

        $title   = nullable_trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        try {
            $chk = $pdo->prepare("SELECT id FROM communities WHERE id = ? AND status = 'Active'");
            $chk->execute([$communityId]);
            if (!$chk->fetch()) {
                flash('error', 'Community not found.');
                redirect('/student/communities.php');
            }

            if (!is_community_member($pdo, $communityId, $userId)) {
                flash('error', 'Join the community to post.');
                redirect(community_details_url($communityId));
            }

            if ($content === '') {
                flash('error', 'Post content is required.');
                redirect(community_details_url($communityId));
            }

            $ins = $pdo->prepare('INSERT INTO community_posts (community_id, user_id, title, content) VALUES (?, ?, ?, ?)');
            $ins->execute([$communityId, $userId, $title, $content]);

            log_activity($pdo, $userId, 'community_post', 'Posted in a community', 'community', $communityId);
            flash('success', 'Your post has been published.');
        } catch (PDOException $e) {
            error_log('community-post.php create_post error: ' . $e->getMessage());
            flash('error', 'Something went wrong posting. Please try again.');
        }

        redirect(community_details_url($communityId));
    }

    // -------------------------------------------------------------
    case 'create_comment': {
        require_csrf('/student/communities.php');

        $postId  = (int)($_POST['post_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($postId <= 0) {
            flash('error', 'Post not found.');
            redirect('/student/communities.php');
        }

        try {
            $postStmt = $pdo->prepare('SELECT id, community_id, user_id FROM community_posts WHERE id = ?');
            $postStmt->execute([$postId]);
            $post = $postStmt->fetch();

            if (!$post) {
                flash('error', 'Post not found.');
                redirect('/student/communities.php');
            }

            $communityId = (int)$post['community_id'];
            $redirectTo  = community_details_url($communityId);

            $communityChk = $pdo->prepare("SELECT id FROM communities WHERE id = ? AND status = 'Active'");
            $communityChk->execute([$communityId]);
            if (!$communityChk->fetch()) {
                flash('error', 'Community not found.');
                redirect('/student/communities.php');
            }

            if (!is_community_member($pdo, $communityId, $userId)) {
                flash('error', 'Join the community to comment.');
                redirect($redirectTo);
            }

            if ($comment === '') {
                flash('error', 'Comment cannot be empty.');
                redirect($redirectTo);
            }

            $ins = $pdo->prepare('INSERT INTO community_comments (post_id, user_id, comment) VALUES (?, ?, ?)');
            $ins->execute([$postId, $userId, $comment]);

            $postOwnerId = (int)$post['user_id'];
            if ($postOwnerId !== $userId) {
                $commenterName = (string)$currentUser['name'];
                create_notification(
                    $pdo,
                    $postOwnerId,
                    'community_post',
                    'New Comment',
                    "{$commenterName} commented on your post.",
                    'community',
                    $communityId
                );
            }

            log_activity($pdo, $userId, 'community_comment', 'Commented on a community post', 'community', $communityId);
            flash('success', 'Your comment has been posted.');
            redirect($redirectTo);
        } catch (PDOException $e) {
            error_log('community-post.php create_comment error: ' . $e->getMessage());
            flash('error', 'Something went wrong posting your comment. Please try again.');
            redirect('/student/communities.php');
        }
    }

    // -------------------------------------------------------------
    case 'delete_post': {
        require_csrf('/student/communities.php');

        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId <= 0) {
            flash('error', 'Post not found.');
            redirect('/student/communities.php');
        }

        try {
            $postStmt = $pdo->prepare('SELECT id, community_id, user_id FROM community_posts WHERE id = ?');
            $postStmt->execute([$postId]);
            $post = $postStmt->fetch();

            if (!$post) {
                flash('error', 'Post not found.');
                redirect('/student/communities.php');
            }

            $communityId = (int)$post['community_id'];
            $redirectTo  = community_details_url($communityId);

            if ((int)$post['user_id'] !== $userId) {
                flash('error', 'You can only delete your own posts.');
                redirect($redirectTo);
            }

            $del = $pdo->prepare('DELETE FROM community_posts WHERE id = ? AND user_id = ?');
            $del->execute([$postId, $userId]);

            flash('success', 'Post deleted.');
            redirect($redirectTo);
        } catch (PDOException $e) {
            error_log('community-post.php delete_post error: ' . $e->getMessage());
            flash('error', 'Something went wrong deleting the post. Please try again.');
            redirect('/student/communities.php');
        }
    }

    // -------------------------------------------------------------
    case 'delete_comment': {
        require_csrf('/student/communities.php');

        $commentId = (int)($_POST['comment_id'] ?? 0);
        if ($commentId <= 0) {
            flash('error', 'Comment not found.');
            redirect('/student/communities.php');
        }

        try {
            $commentStmt = $pdo->prepare(
                'SELECT cc.id, cc.user_id, cp.community_id
                 FROM community_comments cc
                 JOIN community_posts cp ON cp.id = cc.post_id
                 WHERE cc.id = ?'
            );
            $commentStmt->execute([$commentId]);
            $comment = $commentStmt->fetch();

            if (!$comment) {
                flash('error', 'Comment not found.');
                redirect('/student/communities.php');
            }

            $communityId = (int)$comment['community_id'];
            $redirectTo  = community_details_url($communityId);

            if ((int)$comment['user_id'] !== $userId) {
                flash('error', 'You can only delete your own comments.');
                redirect($redirectTo);
            }

            $del = $pdo->prepare('DELETE FROM community_comments WHERE id = ? AND user_id = ?');
            $del->execute([$commentId, $userId]);

            flash('success', 'Comment deleted.');
            redirect($redirectTo);
        } catch (PDOException $e) {
            error_log('community-post.php delete_comment error: ' . $e->getMessage());
            flash('error', 'Something went wrong deleting the comment. Please try again.');
            redirect('/student/communities.php');
        }
    }

    // -------------------------------------------------------------
    default:
        flash('error', 'Unknown action.');
        redirect('/student/communities.php');
}
