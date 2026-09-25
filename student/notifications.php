<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

// ---------------------------------------------------------------------
// POST handling
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/student/notifications.php');

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        $backPage = (int)($_POST['page'] ?? 1);
        try {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, $userId]);
            flash('success', 'Notification marked as read.');
        } catch (Throwable $e) {
            error_log('Mark notification read failed: ' . $e->getMessage());
            flash('error', 'Something went wrong while updating the notification. Please try again.');
        }
        redirect('/student/notifications.php' . ($backPage > 1 ? '?page=' . $backPage : ''));
    }

    if ($action === 'mark_all_read') {
        try {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            flash('success', 'All notifications marked as read.');
        } catch (Throwable $e) {
            error_log('Mark all notifications read failed: ' . $e->getMessage());
            flash('error', 'Something went wrong while updating your notifications. Please try again.');
        }
        redirect('/student/notifications.php');
    }

    redirect('/student/notifications.php');
}

// ---------------------------------------------------------------------
// GET data fetching
// ---------------------------------------------------------------------
$perPage = 15;
$page    = current_page();
$offset  = paginate_offset($page, $perPage);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$countStmt->execute([$userId]);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = (int)max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$iconMap = [
    'team_invitation'        => 'bi-people-fill',
    'team_request'           => 'bi-person-plus-fill',
    'team'                   => 'bi-globe2',
    'team_message'           => 'bi-chat-dots-fill',
    'team_milestone'         => 'bi-flag-fill',
    'team_task'               => 'bi-list-check',
    'opportunity'             => 'bi-briefcase-fill',
    'opportunity_application' => 'bi-file-earmark-text-fill',
    'community'               => 'bi-people',
    'community_post'          => 'bi-chat-square-text-fill',
    'advisor_request'         => 'bi-person-check-fill',
    'advisor_assignment'      => 'bi-mortarboard-fill',
    'connection_request'      => 'bi-link-45deg',
    'direct_message'          => 'bi-chat-dots-fill',
];

$pageTitle = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications || UIU ResearchCollab</title>

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

        <section class="dashboard-section">
            <div class="section-title-row">
                <h2>Notifications</h2>
                <?php if ($totalCount > 0): ?>
                    <form method="post" action="<?= e(url('/student/notifications.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-check2-all"></i> Mark all as read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!$notifications): ?>
                <div class="app-empty-state">
                    <i class="bi bi-bell"></i>
                    <p>You have no notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $n): ?>
                        <?php
                            $isUnread = !(int)$n['is_read'];
                            $icon     = $iconMap[$n['type']] ?? 'bi-bell';
                            $link     = notification_link($n['related_type'], $n['related_id'] !== null ? (int)$n['related_id'] : null);
                        ?>
                        <div class="list-group-item d-flex align-items-start gap-3 py-3 <?= $isUnread ? 'fw-bold border-start border-3' : '' ?>"
                             <?= $isUnread ? 'style="border-color:#064b9b !important;"' : '' ?>>
                            <div class="stat-card-icon blue flex-shrink-0" style="width:42px;height:42px;font-size:18px;">
                                <i class="bi <?= e($icon) ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <h3 class="h6 mb-1"><?= e($n['title']) ?></h3>
                                    <span class="text-muted small text-nowrap fw-normal"><?= e(time_ago($n['created_at'])) ?></span>
                                </div>
                                <p class="mb-2 fw-normal text-medium"><?= e($n['message']) ?></p>
                                <div class="d-flex align-items-center gap-3">
                                    <a href="<?= e($link) ?>" class="small fw-normal">View <i class="bi bi-arrow-right"></i></a>
                                    <?php if ($isUnread): ?>
                                        <form method="post" action="<?= e(url('/student/notifications.php')) ?>" class="m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
                                            <input type="hidden" name="page" value="<?= (int)$page ?>">
                                            <button type="submit" class="btn btn-sm btn-link p-0 small fw-normal text-decoration-none">
                                                <i class="bi bi-check2"></i> Mark as read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="app-pagination" aria-label="Notifications pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= e(page_url($page - 1)) ?>"><i class="bi bi-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="active"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= e(page_url($p)) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= e(page_url($page + 1)) ?>"><i class="bi bi-chevron-right"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
