<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo     = db();
$adminId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_announcement') {
        require_csrf('/admin/notifications.php');

        $title    = trim($_POST['title'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $audience = $_POST['audience'] ?? '';
        $linkRaw  = trim($_POST['link'] ?? '');
        $selectedEmails = trim($_POST['selected_emails'] ?? '');

        $validAudiences = ['all', 'students', 'faculty', 'selected'];

        if ($title === '' || $message === '') {
            flash('error', 'Title and message are required.');
            redirect('/admin/notifications.php');
        }
        if (!in_array($audience, $validAudiences, true)) {
            flash('error', 'Invalid audience.');
            redirect('/admin/notifications.php');
        }
        if (mb_strlen($title) > 200) $title = mb_substr($title, 0, 200);
        if (mb_strlen($message) > 2000) $message = mb_substr($message, 0, 2000);

        $link = null;
        if ($linkRaw !== '') {
            $link = is_valid_http_url($linkRaw);
            if ($link === null) {
                flash('error', 'The optional link must be a valid http(s) URL.');
                redirect('/admin/notifications.php');
            }
            $message .= "\n\n" . $link;
        }

        $recipients = [];
        if ($audience === 'all') {
            $recipients = $pdo->query("SELECT id FROM users WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($audience === 'students') {
            $recipients = $pdo->query("SELECT id FROM users WHERE role='student' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($audience === 'faculty') {
            $recipients = $pdo->query("SELECT id FROM users WHERE role='faculty' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
        } else { // selected
            $emails = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $selectedEmails)));
            if ($emails) {
                $ph = implode(',', array_fill(0, count($emails), '?'));
                $stmt = $pdo->prepare("SELECT id FROM users WHERE status='active' AND email IN ($ph)");
                $stmt->execute($emails);
                $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        if (!$recipients) {
            flash('error', 'No matching active recipients found for that audience.');
            redirect('/admin/notifications.php');
        }

        try {
            $pdo->beginTransaction();
            foreach ($recipients as $uid) {
                create_notification($pdo, (int)$uid, 'announcement', $title, $message, 'announcement', null);
            }
            log_activity($pdo, $adminId, 'admin_announcement', "Sent announcement \"{$title}\" to " . count($recipients) . " user(s) ({$audience})");
            $pdo->commit();
            flash('success', 'Announcement sent to ' . count($recipients) . ' user(s).');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('admin notifications send_announcement: ' . $ex->getMessage());
            flash('error', 'Could not send announcement. Please try again.');
        }
        redirect('/admin/notifications.php');
    }

    if ($action === 'mark_read') {
        require_csrf('/admin/notifications.php');
        $nid = validate_id($_POST['notification_id'] ?? null);
        if ($nid) {
            $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?')->execute([$nid, $adminId]);
        }
        redirect('/admin/notifications.php');
    }
    if ($action === 'mark_all_read') {
        require_csrf('/admin/notifications.php');
        $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0')->execute([$adminId]);
        redirect('/admin/notifications.php');
    }
    redirect('/admin/notifications.php');
}

$perPage = 15; $page = current_page();
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?'); $countStmt->execute([$adminId]);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . paginate_offset($page, $perPage));
$stmt->execute([$adminId]);
$notifications = $stmt->fetchAll();

$typeStats = $pdo->query("SELECT type, COUNT(*) c FROM notifications GROUP BY type ORDER BY c DESC LIMIT 10")->fetchAll();

$pageTitle = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>.app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}.app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}</style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Notifications</h2>

        <div class="app-panel">
            <h3>Send Announcement</h3>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_announcement">
                <div class="mb-2"><label class="form-label small">Title</label><input type="text" name="title" class="form-control form-control-sm" maxlength="200" required></div>
                <div class="mb-2"><label class="form-label small">Message</label><textarea name="message" class="form-control form-control-sm" rows="3" maxlength="2000" required></textarea></div>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small">Audience</label>
                        <select name="audience" class="form-select form-select-sm" id="audienceSelect" onchange="document.getElementById('selectedEmailsWrap').hidden = this.value !== 'selected';">
                            <option value="all">All Users</option>
                            <option value="students">All Students</option>
                            <option value="faculty">All Faculty</option>
                            <option value="selected">Selected Users (by email)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small">Optional Link (http/https)</label>
                        <input type="url" name="link" class="form-control form-control-sm" placeholder="https://...">
                    </div>
                </div>
                <div class="mb-2" id="selectedEmailsWrap" hidden>
                    <label class="form-label small">Recipient Emails (one per line or comma-separated)</label>
                    <textarea name="selected_emails" class="form-control form-control-sm" rows="2" placeholder="student@example.com, faculty@example.com"></textarea>
                </div>
                <button type="submit" class="btn btn-sm btn-uiu" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;">Send Announcement</button>
            </form>
        </div>

        <div class="app-panel">
            <h3>Notification Type Breakdown (platform-wide)</h3>
            <?php foreach ($typeStats as $t): ?>
                <div class="d-flex justify-content-between py-1 border-bottom"><span><?= e($t['type'] ?: 'unspecified') ?></span><span class="fw-semibold"><?= (int)$t['c'] ?></span></div>
            <?php endforeach; ?>
        </div>

        <div class="app-panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="mb-0">My Notifications</h3>
                <?php if ($total > 0): ?>
                <form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-sm btn-outline-primary">Mark all as read</button></form>
                <?php endif; ?>
            </div>
            <?php if (!$notifications): ?><div class="app-empty-state"><i class="bi bi-bell"></i><p>No notifications yet.</p></div><?php endif; ?>
            <?php foreach ($notifications as $n): ?>
                <div class="d-flex justify-content-between align-items-start py-2 border-bottom <?= !$n['is_read'] ? 'fw-bold' : '' ?>">
                    <div><div><?= e($n['title']) ?></div><div class="text-muted small fw-normal"><?= e($n['message']) ?></div></div>
                    <span class="text-muted small fw-normal text-nowrap"><?= e(time_ago($n['created_at'])) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($totalPages > 1): ?>
                <nav class="app-pagination" aria-label="pagination">
                    <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1)) ?>">&laquo;</a><?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?><?php if ($p === $page): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= e(page_url($p)) ?>"><?= $p ?></a><?php endif; ?><?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1)) ?>">&raquo;</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
