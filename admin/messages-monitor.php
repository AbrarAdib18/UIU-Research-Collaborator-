<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$pdo = db();

/*
 * Deliberate design decision: message CONTENT is never shown here.
 * There is no report/flagging system in this build (see README §
 * "Admin Portal" for why one was not added), so there is no documented
 * moderation basis on which to view private conversation content —
 * only metadata is exposed. See includes/functions.php's can_message()
 * for the same privacy boundary enforced on the student/faculty side.
 */
$conversations = $pdo->query(
    "SELECT dc.id, dc.created_at, dc.last_message_at,
            u1.name AS user_one_name, u1.role AS user_one_role,
            u2.name AS user_two_name, u2.role AS user_two_role,
            (SELECT COUNT(*) FROM direct_messages dm WHERE dm.conversation_id = dc.id) AS message_count
     FROM direct_conversations dc
     JOIN users u1 ON u1.id = dc.user_one_id
     JOIN users u2 ON u2.id = dc.user_two_id
     ORDER BY dc.last_message_at IS NULL, dc.last_message_at DESC"
)->fetchAll();

$pageTitle = 'Messages / Moderation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages / Moderation || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:14px 18px;margin-bottom:12px}
        .table thead th{color:var(--uiu-blue);font-size:12px;text-transform:uppercase;border-bottom:2px solid var(--border-color)}
        .table td{vertical-align:middle;font-size:13px}
        .table-responsive-wrap{overflow-x:auto}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">Messages / Moderation</h2>
        <div class="alert alert-info small">
            <i class="bi bi-shield-lock"></i> For privacy, message <strong>content</strong> is never shown here — only conversation metadata (participants, timing, message count). Use <a href="<?= e(url('/admin/connections.php')) ?>">Connections</a> to block a connection if a conversation needs to be stopped.
        </div>

        <div class="app-panel">
            <?php if (!$conversations): ?>
                <div class="app-empty-state"><i class="bi bi-chat-dots"></i><p>No conversations yet.</p></div>
            <?php else: ?>
            <div class="table-responsive-wrap">
            <table class="table table-hover">
                <thead><tr><th>Participants</th><th>Started</th><th>Last Message</th><th>Message Count</th></tr></thead>
                <tbody>
                <?php foreach ($conversations as $c): ?>
                    <tr>
                        <td><?= e($c['user_one_name']) ?> (<?= e(ucfirst($c['user_one_role'])) ?>) &harr; <?= e($c['user_two_name']) ?> (<?= e(ucfirst($c['user_two_role'])) ?>)</td>
                        <td><?= format_date($c['created_at']) ?></td>
                        <td><?= $c['last_message_at'] ? e(time_ago($c['last_message_at'])) : '—' ?></td>
                        <td><?= (int)$c['message_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
