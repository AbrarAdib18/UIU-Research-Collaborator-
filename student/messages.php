<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

$stmt = $pdo->prepare(
    "SELECT dc.id AS conversation_id, dc.last_message_at,
            u.id AS other_id, u.name AS other_name, u.role AS other_role,
            (SELECT message FROM direct_messages m WHERE m.conversation_id = dc.id ORDER BY m.id DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM direct_messages m WHERE m.conversation_id = dc.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count
     FROM direct_conversations dc
     JOIN users u ON u.id = (CASE WHEN dc.user_one_id = ? THEN dc.user_two_id ELSE dc.user_one_id END)
     WHERE dc.user_one_id = ? OR dc.user_two_id = ?
     ORDER BY dc.last_message_at IS NULL, dc.last_message_at DESC"
);
$stmt->execute([$userId, $userId, $userId, $userId]);
$conversations = $stmt->fetchAll();

$pageTitle = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:8px 18px;margin-bottom:18px}
        .conv-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-light,#eee);text-decoration:none;color:inherit}
        .conv-row:last-child{border-bottom:none}
        .avatar-sm{width:42px;height:42px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:14px;font-weight:700;flex-shrink:0}
        .app-empty-state{padding:30px;text-align:center;color:var(--text-light)}
        .app-empty-state i{font-size:30px;display:block;margin-bottom:8px}
        .unread-badge{background:var(--uiu-blue);color:#fff;border-radius:12px;padding:1px 8px;font-size:11px;font-weight:700}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;margin-bottom:14px;">Messages</h2>

        <div class="app-panel">
            <?php if (!$conversations): ?>
                <div class="app-empty-state"><i class="bi bi-chat-dots"></i><p>No conversations yet. Connect with a faculty member or get an advisor request accepted to start messaging.</p></div>
            <?php else: foreach ($conversations as $c): ?>
                <a class="conv-row" href="<?= e(url('/student/conversation.php?user=' . $c['other_id'])) ?>">
                    <span class="avatar-sm"><?= e(initials($c['other_name'])) ?></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= e($c['other_name']) ?> <span class="text-muted small fw-normal">(<?= e(ucfirst($c['other_role'])) ?>)</span></div>
                        <div class="text-muted small"><?= e(mb_strimwidth((string)($c['last_message'] ?? 'No messages yet'), 0, 60, '...')) ?></div>
                    </div>
                    <?php if ($c['last_message_at']): ?><div class="text-muted small"><?= e(time_ago($c['last_message_at'])) ?></div><?php endif; ?>
                    <?php if ((int)$c['unread_count'] > 0): ?><span class="unread-badge"><?= (int)$c['unread_count'] ?></span><?php endif; ?>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
