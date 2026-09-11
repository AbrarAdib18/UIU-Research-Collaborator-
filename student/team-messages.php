<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    flash('error', 'That team could not be found.');
    redirect('/student/teams.php');
}
if (!is_team_member($pdo, $teamId, $userId)) {
    flash('error', "You don't have access to this team's workspace.");
    redirect('/student/teams.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $backUrl = '/student/team-messages.php?id=' . $teamId;
    require_csrf($backUrl);

    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        flash('error', 'Message cannot be empty.');
        redirect($backUrl);
    }
    if (mb_strlen($message) > 2000) {
        $message = mb_substr($message, 0, 2000);
    }

    try {
        $ins = $pdo->prepare('INSERT INTO team_messages (team_id, sender_id, message) VALUES (?, ?, ?)');
        $ins->execute([$teamId, $userId, $message]);

        $preview = mb_strimwidth($message, 0, 80, '...');
        $othersStmt = $pdo->prepare(
            "SELECT user_id FROM team_members WHERE team_id = ? AND status = 'Active' AND user_id != ?"
        );
        $othersStmt->execute([$teamId, $userId]);
        foreach ($othersStmt->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
            create_notification(
                $pdo,
                (int)$memberId,
                'team_message',
                'New Team Message',
                "{$currentUser['name']} in \"{$team['name']}\": {$preview}",
                'team_message',
                $teamId
            );
        }

        log_activity($pdo, $userId, 'message_sent', "Sent a message in \"{$team['name']}\".", 'team', $teamId);
    } catch (Throwable $ex) {
        error_log('team-messages.php send: ' . $ex->getMessage());
        flash('error', 'Could not send your message. Please try again.');
    }
    redirect($backUrl);
}

$messagesStmt = $pdo->prepare(
    "SELECT tmsg.*, u.name AS sender_name, sp.profile_photo
     FROM team_messages tmsg
     JOIN users u ON u.id = tmsg.sender_id
     LEFT JOIN student_profiles sp ON sp.user_id = u.id
     WHERE tmsg.team_id = ?
     ORDER BY tmsg.created_at DESC
     LIMIT 50"
);
$messagesStmt->execute([$teamId]);
$messages = array_reverse($messagesStmt->fetchAll());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?= e($team['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .team-subnav{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 20px;padding-bottom:14px;border-bottom:1px solid var(--border-color)}
        .team-subnav a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;background:#f0f4f9;color:var(--text-medium);font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
        .team-subnav a:hover{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .team-subnav a.active{background:var(--uiu-blue);color:#fff}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .chat-thread{max-height:520px;overflow-y:auto;padding:6px 4px;display:flex;flex-direction:column}
        .chat-row{display:flex;gap:10px;margin-bottom:14px;max-width:100%}
        .chat-row.mine{flex-direction:row-reverse;text-align:right}
        .avatar-sm{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:12px;font-weight:700;flex-shrink:0;object-fit:cover}
        .chat-bubble-wrap{max-width:70%}
        .chat-sender{font-size:11px;font-weight:700;color:var(--text-medium);margin-bottom:2px}
        .chat-bubble{padding:10px 14px;border-radius:14px;background:#f0f4f9;font-size:13px;display:inline-block;text-align:left;white-space:pre-wrap;word-break:break-word}
        .chat-row.mine .chat-bubble{background:var(--uiu-blue);color:#fff}
        .chat-meta{font-size:11px;color:var(--text-light);margin-top:2px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2><?= e($team['name']) ?> — Messages</h2>
                <p>Chat with your team members.</p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <nav class="team-subnav">
            <a href="<?= e(url('/student/team-details.php?id=' . $teamId)) ?>"><i class="bi bi-info-circle"></i> Overview</a>
            <a href="<?= e(url('/student/team-tasks.php?id=' . $teamId)) ?>"><i class="bi bi-list-check"></i> Tasks</a>
            <a href="<?= e(url('/student/team-milestones.php?id=' . $teamId)) ?>"><i class="bi bi-flag"></i> Milestones</a>
            <a href="<?= e(url('/student/team-files.php?id=' . $teamId)) ?>"><i class="bi bi-folder"></i> Files</a>
            <a class="active" href="<?= e(url('/student/team-messages.php?id=' . $teamId)) ?>"><i class="bi bi-chat-dots"></i> Messages</a>
        </nav>

        <div class="app-panel">
            <?php if ($messages): ?>
                <div class="chat-thread" id="chatThread">
                    <?php foreach ($messages as $msg): ?>
                        <?php $mine = (int)$msg['sender_id'] === $userId; ?>
                        <div class="chat-row <?= $mine ? 'mine' : '' ?>">
                            <?php if (!empty($msg['profile_photo'])): ?>
                                <img class="avatar-sm" src="<?= e(url('/uploads/avatars/' . $msg['profile_photo'])) ?>" alt="<?= e($msg['sender_name']) ?>">
                            <?php else: ?>
                                <span class="avatar-sm"><?= e(initials($msg['sender_name'])) ?></span>
                            <?php endif; ?>
                            <div class="chat-bubble-wrap">
                                <?php if (!$mine): ?><div class="chat-sender"><?= e($msg['sender_name']) ?></div><?php endif; ?>
                                <div class="chat-bubble"><?= e($msg['message']) ?></div>
                                <div class="chat-meta"><?= e(time_ago($msg['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-chat-dots"></i><p>No messages yet. Say hello to your team.</p></div>
            <?php endif; ?>

            <form action="<?= e(url('/student/team-messages.php?id=' . $teamId)) ?>" method="post" class="d-flex gap-2 mt-3">
                <?= csrf_field() ?>
                <textarea class="form-control" name="message" rows="2" maxlength="2000" placeholder="Write a message..." required></textarea>
                <button type="submit" class="btn btn-uiu align-self-end"><i class="bi bi-send"></i> Send</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
    var thread = document.getElementById('chatThread');
    if (thread) { thread.scrollTop = thread.scrollHeight; }
</script>
</body>
</html>
