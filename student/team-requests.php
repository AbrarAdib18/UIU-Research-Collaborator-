<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -----------------------------------------------------------------
    // action=create — the Discover Teams "Request to Join" form (and the
    // team-details.php read-only preview) post here.
    // Fields: team_id, message (optional)
    // -----------------------------------------------------------------
    if ($action === 'create') {
        require_csrf('/student/teams.php');

        $teamId  = (int)($_POST['team_id'] ?? 0);
        $message = nullable_trim($_POST['message'] ?? '');

        $teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
        $teamStmt->execute([$teamId]);
        $team = $teamStmt->fetch();

        if (!$team) {
            flash('error', 'That team could not be found.');
            redirect_back('/student/teams.php');
        }
        if (is_team_member($pdo, $teamId, $userId)) {
            flash('error', "You're already a member of this team.");
            redirect_back('/student/teams.php');
        }
        if ($team['status'] !== 'Forming') {
            flash('error', "This team isn't accepting new members right now.");
            redirect_back('/student/teams.php');
        }
        if (team_member_count($pdo, $teamId) >= (int)$team['team_size_limit']) {
            flash('error', 'This team is full.');
            redirect_back('/student/teams.php');
        }
        // team_requests has a UNIQUE(team_id, user_id) key, so a user can only
        // ever have one row per team. Fetch any existing row (not just Pending
        // ones) so a prior Rejected/Cancelled/Accepted request can be revived
        // instead of hitting the unique key and permanently blocking re-requests.
        $existingStmt = $pdo->prepare('SELECT * FROM team_requests WHERE team_id = ? AND user_id = ? LIMIT 1');
        $existingStmt->execute([$teamId, $userId]);
        $existingRequest = $existingStmt->fetch();

        if ($existingRequest && $existingRequest['status'] === 'Pending') {
            flash('error', 'You already have a pending request for this team.');
            redirect_back('/student/teams.php');
        }

        try {
            if ($existingRequest) {
                $upd = $pdo->prepare(
                    "UPDATE team_requests SET message = ?, status = 'Pending', created_at = CURRENT_TIMESTAMP, responded_at = NULL WHERE id = ?"
                );
                $upd->execute([$message, $existingRequest['id']]);
                $requestId = (int)$existingRequest['id'];
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO team_requests (team_id, user_id, message, status) VALUES (?, ?, ?, 'Pending')"
                );
                $ins->execute([$teamId, $userId, $message]);
                $requestId = (int)$pdo->lastInsertId();
            }

            $leadersStmt = $pdo->prepare("SELECT user_id FROM team_members WHERE team_id = ? AND role = 'Leader' AND status = 'Active'");
            $leadersStmt->execute([$teamId]);
            foreach ($leadersStmt->fetchAll(PDO::FETCH_COLUMN) as $leaderId) {
                create_notification(
                    $pdo,
                    (int)$leaderId,
                    'team_request',
                    'Team Join Request',
                    "{$currentUser['name']} requested to join \"{$team['name']}\".",
                    'team_request',
                    $requestId
                );
            }

            flash('success', 'Your request to join has been sent.');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                flash('error', 'You already have a pending request for this team.');
            } else {
                error_log('team-requests.php create: ' . $ex->getMessage());
                flash('error', 'Could not send your request. Please try again.');
            }
        }
        redirect_back('/student/teams.php');
    }

    // -----------------------------------------------------------------
    // action=respond — request_id, response=accept|reject (leader only)
    // -----------------------------------------------------------------
    if ($action === 'respond') {
        require_csrf('/student/team-requests.php');

        $requestId = (int)($_POST['request_id'] ?? 0);
        $response  = $_POST['response'] ?? '';

        $reqStmt = $pdo->prepare('SELECT * FROM team_requests WHERE id = ?');
        $reqStmt->execute([$requestId]);
        $request = $reqStmt->fetch();

        if (!$request || $request['status'] !== 'Pending') {
            flash('error', 'That request is no longer available.');
            redirect('/student/team-requests.php');
        }
        if (!is_team_leader($pdo, (int)$request['team_id'], $userId)) {
            flash('error', 'Only team leaders can respond to join requests.');
            redirect('/student/team-requests.php');
        }

        $teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
        $teamStmt->execute([$request['team_id']]);
        $team = $teamStmt->fetch();

        $requesterStmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $requesterStmt->execute([$request['user_id']]);
        $requesterName = (string)$requesterStmt->fetchColumn();

        if ($response === 'accept') {
            if (!$team || team_member_count($pdo, (int)$team['id']) >= (int)$team['team_size_limit']) {
                flash('error', 'This team is already full.');
                redirect('/student/team-requests.php');
            }
            if (is_team_member($pdo, (int)$request['team_id'], (int)$request['user_id'])) {
                flash('error', 'This user is already a member of the team.');
                redirect('/student/team-requests.php');
            }

            try {
                $pdo->beginTransaction();

                // team_members has a UNIQUE(team_id, user_id) key. If this user
                // previously left/was removed, a plain INSERT would violate it —
                // upsert so rejoining always works.
                $memberIns = $pdo->prepare(
                    "INSERT INTO team_members (team_id, user_id, role, status) VALUES (?, ?, 'Member', 'Active')
                     ON DUPLICATE KEY UPDATE role = 'Member', status = 'Active', joined_at = CURRENT_TIMESTAMP"
                );
                $memberIns->execute([$request['team_id'], $request['user_id']]);

                $upd = $pdo->prepare("UPDATE team_requests SET status = 'Accepted', responded_at = NOW() WHERE id = ?");
                $upd->execute([$requestId]);

                $pdo->commit();

                create_notification(
                    $pdo,
                    (int)$request['user_id'],
                    'team_request_accepted',
                    'Request Accepted',
                    "Your request to join \"{$team['name']}\" was accepted.",
                    'team',
                    (int)$team['id']
                );

                flash('success', "{$requesterName} has been added to the team.");
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($ex->getCode() === '23000') {
                    flash('error', 'This user is already a member of the team.');
                } else {
                    error_log('team-requests.php accept: ' . $ex->getMessage());
                    flash('error', 'Could not accept the request. Please try again.');
                }
            }
        } elseif ($response === 'reject') {
            $upd = $pdo->prepare("UPDATE team_requests SET status = 'Rejected', responded_at = NOW() WHERE id = ?");
            $upd->execute([$requestId]);

            if ($team) {
                create_notification(
                    $pdo,
                    (int)$request['user_id'],
                    'team_request_rejected',
                    'Request Declined',
                    "Your request to join \"{$team['name']}\" was declined.",
                    'team',
                    (int)$team['id']
                );
            }
            flash('success', 'Request declined.');
        } else {
            flash('error', 'Invalid response.');
        }
        redirect('/student/team-requests.php');
    }

    flash('error', 'Unknown action.');
    redirect('/student/team-requests.php');
}

// --- GET: my requests + requests to teams I lead -------------------------
$myRequestsStmt = $pdo->prepare(
    "SELECT tr.*, rt.name AS team_name
     FROM team_requests tr
     JOIN research_teams rt ON rt.id = tr.team_id
     WHERE tr.user_id = ?
     ORDER BY tr.created_at DESC"
);
$myRequestsStmt->execute([$userId]);
$myRequests = $myRequestsStmt->fetchAll();

$ledTeamsStmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? AND role = 'Leader' AND status = 'Active'");
$ledTeamsStmt->execute([$userId]);
$ledTeamIds = array_map('intval', $ledTeamsStmt->fetchAll(PDO::FETCH_COLUMN));

$incoming = [];
if ($ledTeamIds) {
    $placeholders = implode(',', array_fill(0, count($ledTeamIds), '?'));
    $incomingStmt = $pdo->prepare(
        "SELECT tr.*, rt.name AS team_name, u.name AS requester_name
         FROM team_requests tr
         JOIN research_teams rt ON rt.id = tr.team_id
         JOIN users u ON u.id = tr.user_id
         WHERE tr.status = 'Pending' AND tr.team_id IN ($placeholders)
         ORDER BY tr.created_at ASC"
    );
    $incomingStmt->execute($ledTeamIds);
    $incoming = $incomingStmt->fetchAll();
}

function request_status_pill(string $status): string
{
    return match ($status) {
        'Pending'   => 'pill pill-blue',
        'Accepted'  => 'pill pill-green',
        'Rejected'  => 'pill pill-red',
        'Cancelled' => 'pill pill-gray',
        default     => 'pill pill-gray',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Join Requests || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px}
        .app-page-header h2{margin:0;color:var(--uiu-blue);font-size:23px;font-weight:700}
        .app-page-header p{margin:2px 0 0;color:var(--text-light);font-size:14px}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
        .btn-uiu:hover{background:#003d80;border-color:#003d80;color:#fff}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-blue{background:var(--uiu-light-blue);color:var(--uiu-dark-blue)}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .list-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-light,#eee);flex-wrap:wrap}
        .list-row:last-child{border-bottom:none}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>

        <div class="app-page-header">
            <div>
                <h2>Team Join Requests</h2>
                <p>Track requests you've sent, and approve or decline requests to your teams.</p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <div class="app-panel">
            <h3>Requests to My Teams</h3>
            <?php if ($incoming): ?>
                <?php foreach ($incoming as $req): ?>
                    <div class="list-row">
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($req['requester_name']) ?> &middot; <span class="text-muted"><?= e($req['team_name']) ?></span></div>
                            <div class="text-muted small"><?= e(time_ago($req['created_at'])) ?></div>
                            <?php if ($req['message']): ?><div class="small mt-1">"<?= e($req['message']) ?>"</div><?php endif; ?>
                        </div>
                        <form action="<?= e(url('/student/team-requests.php')) ?>" method="post" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="respond">
                            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                            <button type="submit" name="response" value="accept" class="btn btn-sm btn-uiu"><i class="bi bi-check-lg"></i> Accept</button>
                            <button type="submit" name="response" value="reject" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-person-check"></i><p>No pending join requests for your teams.</p></div>
            <?php endif; ?>
        </div>

        <div class="app-panel">
            <h3>My Requests</h3>
            <?php if ($myRequests): ?>
                <?php foreach ($myRequests as $req): ?>
                    <div class="list-row">
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($req['team_name']) ?></div>
                            <div class="text-muted small"><?= e(time_ago($req['created_at'])) ?></div>
                        </div>
                        <span class="<?= request_status_pill($req['status']) ?>"><?= e($req['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-send"></i><p>You haven't requested to join any teams yet.</p></div>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
