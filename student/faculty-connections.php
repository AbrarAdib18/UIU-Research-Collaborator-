<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/student/faculty-connections.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $recipientId = (int)($_POST['recipient_id'] ?? 0);
        $message     = nullable_trim($_POST['request_message'] ?? '');
        if ($recipientId <= 0 || $recipientId === $userId) {
            flash('error', 'Invalid recipient.');
        } elseif (has_existing_connection($pdo, $userId, $recipientId)) {
            flash('error', 'A connection request already exists with this person.');
        } else {
            try {
                $pdo->prepare('INSERT INTO research_connections (requester_id, recipient_id, request_message) VALUES (?,?,?)')
                    ->execute([$userId, $recipientId, $message]);
                create_notification($pdo, $recipientId, 'connection_request', 'New Connection Request', $currentUser['name'] . ' wants to connect with you.', 'connection_request', $userId);
                log_activity($pdo, $userId, 'connection_sent', 'Sent a connection request');
                flash('success', 'Connection request sent.');
            } catch (Throwable $ex) {
                error_log('student faculty-connections send: ' . $ex->getMessage());
                flash('error', 'Could not send connection request.');
            }
        }
    } elseif (in_array($action, ['accept', 'decline', 'cancel'], true)) {
        $connId = (int)($_POST['connection_id'] ?? 0);
        $chk = $pdo->prepare('SELECT * FROM research_connections WHERE id = ? AND (requester_id = ? OR recipient_id = ?)');
        $chk->execute([$connId, $userId, $userId]);
        $conn = $chk->fetch();
        if (!$conn) {
            flash('error', 'Connection request not found.');
        } else {
            try {
                if ($action === 'accept' && (int)$conn['recipient_id'] === $userId && $conn['status'] === 'pending') {
                    $pdo->prepare("UPDATE research_connections SET status='accepted', responded_at=NOW() WHERE id=?")->execute([$connId]);
                    create_notification($pdo, (int)$conn['requester_id'], 'connection_request', 'Connection Accepted', $currentUser['name'] . ' accepted your connection request.', 'connection_request', $userId);
                    flash('success', 'Connection accepted. You can now message each other.');
                } elseif ($action === 'decline' && (int)$conn['recipient_id'] === $userId && $conn['status'] === 'pending') {
                    $pdo->prepare("UPDATE research_connections SET status='declined', responded_at=NOW() WHERE id=?")->execute([$connId]);
                    flash('success', 'Connection declined.');
                } elseif ($action === 'cancel' && (int)$conn['requester_id'] === $userId && $conn['status'] === 'pending') {
                    $pdo->prepare("UPDATE research_connections SET status='cancelled', responded_at=NOW() WHERE id=?")->execute([$connId]);
                    flash('success', 'Connection request cancelled.');
                } else {
                    flash('error', 'That action is not available for this request.');
                }
            } catch (Throwable $ex) {
                error_log('student faculty-connections ' . $action . ': ' . $ex->getMessage());
                flash('error', 'Could not update connection request.');
            }
        }
    }
    redirect('/student/faculty-connections.php');
}

$incoming = $pdo->prepare("SELECT rc.*, u.name, u.role FROM research_connections rc JOIN users u ON u.id = rc.requester_id WHERE rc.recipient_id = ? AND rc.status = 'pending' ORDER BY rc.created_at DESC");
$incoming->execute([$userId]);
$incoming = $incoming->fetchAll();

$outgoing = $pdo->prepare("SELECT rc.*, u.name, u.role FROM research_connections rc JOIN users u ON u.id = rc.recipient_id WHERE rc.requester_id = ? AND rc.status = 'pending' ORDER BY rc.created_at DESC");
$outgoing->execute([$userId]);
$outgoing = $outgoing->fetchAll();

$accepted = $pdo->prepare(
    "SELECT rc.*, u.name, u.role, u.id AS other_id
     FROM research_connections rc
     JOIN users u ON u.id = (CASE WHEN rc.requester_id = ? THEN rc.recipient_id ELSE rc.requester_id END)
     WHERE (rc.requester_id = ? OR rc.recipient_id = ?) AND rc.status = 'accepted'
     ORDER BY rc.responded_at DESC"
);
$accepted->execute([$userId, $userId, $userId]);
$accepted = $accepted->fetchAll();

$pageTitle = 'Faculty Connections';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Faculty Connections || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .conn-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light,#eee)}
        .conn-row:last-child{border-bottom:none}
        .avatar-sm{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:13px;font-weight:700;flex-shrink:0}
        .app-empty-state{padding:20px;text-align:center;color:var(--text-light)}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">My Faculty Connections</h2>

        <div class="app-panel">
            <h3>Incoming Requests (<?= count($incoming) ?>)</h3>
            <?php if (!$incoming): ?><div class="app-empty-state">No incoming connection requests.</div><?php endif; ?>
            <?php foreach ($incoming as $c): ?>
                <div class="conn-row">
                    <span class="avatar-sm"><?= e(initials($c['name'])) ?></span>
                    <div class="flex-grow-1"><div class="fw-semibold"><?= e($c['name']) ?></div><div class="text-muted small"><?= e(ucfirst($c['role'])) ?> &middot; <?= e(time_ago($c['created_at'])) ?><?php if ($c['request_message']): ?> &middot; "<?= e($c['request_message']) ?>"<?php endif; ?></div></div>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="connection_id" value="<?= (int)$c['id'] ?>">
                        <button name="action" value="accept" class="btn btn-sm btn-success">Accept</button>
                        <button name="action" value="decline" class="btn btn-sm btn-outline-danger">Decline</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="app-panel">
            <h3>Outgoing Requests (<?= count($outgoing) ?>)</h3>
            <?php if (!$outgoing): ?><div class="app-empty-state">No outgoing connection requests.</div><?php endif; ?>
            <?php foreach ($outgoing as $c): ?>
                <div class="conn-row">
                    <span class="avatar-sm"><?= e(initials($c['name'])) ?></span>
                    <div class="flex-grow-1"><div class="fw-semibold"><?= e($c['name']) ?></div><div class="text-muted small">Pending &middot; <?= e(time_ago($c['created_at'])) ?></div></div>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="connection_id" value="<?= (int)$c['id'] ?>">
                        <button name="action" value="cancel" class="btn btn-sm btn-outline-secondary">Cancel</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="app-panel">
            <h3>Accepted Connections (<?= count($accepted) ?>)</h3>
            <?php if (!$accepted): ?><div class="app-empty-state">No accepted connections yet.</div><?php endif; ?>
            <?php foreach ($accepted as $c): ?>
                <div class="conn-row">
                    <span class="avatar-sm"><?= e(initials($c['name'])) ?></span>
                    <div class="flex-grow-1"><div class="fw-semibold"><?= e($c['name']) ?></div><div class="text-muted small"><?= e(ucfirst($c['role'])) ?> &middot; Connected <?= e(time_ago($c['responded_at'])) ?></div></div>
                    <a href="<?= e(url('/student/conversation.php?user=' . $c['other_id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Message</a>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
