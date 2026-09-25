<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo         = db();
$userId      = (int)$currentUser['id'];
$otherUserId = isset($_GET['user']) ? (int)$_GET['user'] : 0;

$otherStmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = ?');
$otherStmt->execute([$otherUserId]);
$other = $otherStmt->fetch();

if (!$other || !can_message($pdo, $userId, $otherUserId)) {
    flash('error', 'You are not connected with this person, so you cannot message them yet.');
    redirect('/student/messages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/student/conversation.php?user=' . $otherUserId);
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message !== '') {
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }
        $conversationId = get_or_create_direct_conversation($pdo, $userId, $otherUserId);
        $pdo->prepare('INSERT INTO direct_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)')->execute([$conversationId, $userId, $message]);
        $pdo->prepare('UPDATE direct_conversations SET last_message_at = NOW() WHERE id = ?')->execute([$conversationId]);
        create_notification($pdo, $otherUserId, 'direct_message', 'New Message', $currentUser['name'] . ' sent you a message.', 'direct_message', $userId);
    }
    redirect('/student/conversation.php?user=' . $otherUserId);
}

$one = min($userId, $otherUserId);
$two = max($userId, $otherUserId);
$convStmt = $pdo->prepare('SELECT id FROM direct_conversations WHERE user_one_id = ? AND user_two_id = ?');
$convStmt->execute([$one, $two]);
$conversationId = $convStmt->fetchColumn();

$messages = [];
if ($conversationId) {
    $msgStmt = $pdo->prepare('SELECT * FROM direct_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 200');
    $msgStmt->execute([$conversationId]);
    $messages = $msgStmt->fetchAll();
    $pdo->prepare('UPDATE direct_messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND sender_id != ? AND is_read = 0')->execute([$conversationId, $userId]);
}
$lastId = $messages ? (int)end($messages)['id'] : 0;

$pageTitle = 'Conversation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= e($other['name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .chat-thread{max-height:520px;overflow-y:auto;padding:6px 4px;display:flex;flex-direction:column}
        .chat-row{display:flex;gap:10px;margin-bottom:14px;max-width:100%}
        .chat-row.mine{flex-direction:row-reverse;text-align:right}
        .avatar-sm{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:12px;font-weight:700;flex-shrink:0}
        .chat-bubble-wrap{max-width:70%}
        .chat-bubble{padding:10px 14px;border-radius:14px;background:#f0f4f9;font-size:13px;display:inline-block;text-align:left;white-space:pre-wrap;word-break:break-word}
        .chat-row.mine .chat-bubble{background:var(--uiu-blue);color:#fff}
        .chat-meta{font-size:11px;color:var(--text-light);margin-top:2px}
        .app-empty-state{padding:24px;text-align:center;color:var(--text-light)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/student/messages.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Messages</a>

        <div class="app-panel">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="avatar-sm"><?= e(initials($other['name'])) ?></span>
                <div><strong><?= e($other['name']) ?></strong> <span class="text-muted small">(<?= e(ucfirst($other['role'])) ?>)</span></div>
            </div>

            <?php if (!$messages): ?>
                <div class="app-empty-state" id="emptyState"><i class="bi bi-chat-dots"></i><p>No messages yet. Say hello.</p></div>
            <?php endif; ?>
            <div class="chat-thread" id="chatThread">
                <?php foreach ($messages as $msg): ?>
                    <?php $mine = (int)$msg['sender_id'] === $userId; ?>
                    <div class="chat-row <?= $mine ? 'mine' : '' ?>">
                        <span class="avatar-sm"><?= e(initials($mine ? $currentUser['name'] : $other['name'])) ?></span>
                        <div class="chat-bubble-wrap">
                            <div class="chat-bubble"><?= e($msg['message']) ?></div>
                            <div class="chat-meta"><?= e(time_ago($msg['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form id="chatForm" action="<?= e(url('/student/conversation.php?user=' . $otherUserId)) ?>" method="post" class="d-flex gap-2 mt-3">
                <?= csrf_field() ?>
                <textarea class="form-control" name="message" id="messageInput" rows="2" maxlength="2000" placeholder="Write a message..." required></textarea>
                <button type="submit" class="btn btn-uiu align-self-end" style="background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff;"><i class="bi bi-send"></i> Send</button>
            </form>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function () {
    var thread = document.getElementById('chatThread');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('messageInput');
    var emptyState = document.getElementById('emptyState');
    var otherUserId = <?= (int)$otherUserId ?>;
    var lastId = <?= (int)$lastId ?>;
    var csrfToken = document.querySelector('input[name=csrf_token]').value;
    var myName = <?= json_encode($currentUser['name']) ?>;
    var otherName = <?= json_encode($other['name']) ?>;

    if (thread) { thread.scrollTop = thread.scrollHeight; }

    function initials(name) {
        var parts = name.trim().split(/\s+/);
        var first = parts[0] ? parts[0][0] : '';
        var last = parts.length > 1 ? parts[parts.length - 1][0] : '';
        return (first + last).toUpperCase();
    }

    function appendMessage(m) {
        if (emptyState) { emptyState.remove(); emptyState = null; }
        var row = document.createElement('div');
        row.className = 'chat-row' + (m.mine ? ' mine' : '');
        var avatar = document.createElement('span');
        avatar.className = 'avatar-sm';
        avatar.textContent = initials(m.mine ? myName : otherName);
        var wrap = document.createElement('div');
        wrap.className = 'chat-bubble-wrap';
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = m.message;
        var meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.textContent = m.time_ago;
        wrap.appendChild(bubble);
        wrap.appendChild(meta);
        row.appendChild(avatar);
        row.appendChild(wrap);
        thread.appendChild(row);
        thread.scrollTop = thread.scrollHeight;
        lastId = Math.max(lastId, m.id);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = input.value.trim();
        if (!msg) return;
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('user_id', otherUserId);
        fd.append('message', msg);
        fetch('<?= e(url('/api/chat/send-message.php')) ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    appendMessage(data.message);
                    input.value = '';
                } else {
                    alert(data.error || 'Could not send message.');
                }
            })
            .catch(function () { alert('Network error while sending message.'); });
    });

    var pollTimer = null;
    function poll() {
        fetch('<?= e(url('/api/chat/get-messages.php')) ?>?user_id=' + otherUserId + '&since_id=' + lastId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok && data.messages && data.messages.length) {
                    data.messages.forEach(appendMessage);
                    var fd = new FormData();
                    fd.append('csrf_token', csrfToken);
                    fd.append('user_id', otherUserId);
                    fetch('<?= e(url('/api/chat/mark-read.php')) ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
                }
            })
            .catch(function () {});
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(poll, 4000);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stopPolling(); } else { poll(); startPolling(); }
    });
    startPolling();
})();
</script>
</body>
</html>
