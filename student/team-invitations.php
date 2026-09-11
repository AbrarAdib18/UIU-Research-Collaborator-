<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -----------------------------------------------------------------
    // action=send — fixed contract used by the Research Connect module's
    // "Invite" button on researcher-profile.php / research-connect.php.
    // Fields: team_id, invited_user_id, message (optional)
    // -----------------------------------------------------------------
    if ($action === 'send') {
        require_csrf('/student/teams.php');

        $teamId        = (int)($_POST['team_id'] ?? 0);
        $invitedUserId = (int)($_POST['invited_user_id'] ?? 0);
        $message       = nullable_trim($_POST['message'] ?? '');

        $teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
        $teamStmt->execute([$teamId]);
        $team = $teamStmt->fetch();

        if (!$team) {
            flash('error', 'That team could not be found.');
            redirect_back('/student/teams.php');
        }
        if (!is_team_leader($pdo, $teamId, $userId)) {
            flash('error', 'Only team leaders can send invitations.');
            redirect_back('/student/teams.php');
        }
        if ($invitedUserId === $userId) {
            flash('error', "You can't invite yourself.");
            redirect_back('/student/teams.php');
        }
        $userChk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND status = 'active'");
        $userChk->execute([$invitedUserId]);
        if (!$userChk->fetchColumn()) {
            flash('error', 'That user could not be found.');
            redirect_back('/student/teams.php');
        }
        if (is_team_member($pdo, $teamId, $invitedUserId)) {
            flash('error', 'This user is already a member of the team.');
            redirect_back('/student/teams.php');
        }
        if (team_member_count($pdo, $teamId) >= (int)$team['team_size_limit']) {
            flash('error', 'This team is already full.');
            redirect_back('/student/teams.php');
        }
        $dupChk = $pdo->prepare("SELECT COUNT(*) FROM team_invitations WHERE team_id = ? AND invited_user_id = ? AND status = 'Pending'");
        $dupChk->execute([$teamId, $invitedUserId]);
        if ($dupChk->fetchColumn()) {
            flash('error', 'An invitation is already pending for this user.');
            redirect_back('/student/teams.php');
        }

        try {
            $ins = $pdo->prepare(
                "INSERT INTO team_invitations (team_id, invited_by, invited_user_id, message, status)
                 VALUES (?, ?, ?, ?, 'Pending')"
            );
            $ins->execute([$teamId, $userId, $invitedUserId, $message]);
            $invitationId = (int)$pdo->lastInsertId();

            create_notification(
                $pdo,
                $invitedUserId,
                'team_invitation',
                'Team Invitation',
                "You've been invited to join \"{$team['name']}\".",
                'team_invitation',
                $invitationId
            );

            flash('success', 'Invitation sent.');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                flash('error', 'An invitation for this user already exists.');
            } else {
                error_log('team-invitations.php send: ' . $ex->getMessage());
                flash('error', 'Could not send the invitation. Please try again.');
            }
        }
        redirect_back('/student/teams.php');
    }

    // -----------------------------------------------------------------
    // action=respond — invitation_id, response=accept|reject
    // -----------------------------------------------------------------
    if ($action === 'respond') {
        require_csrf('/student/team-invitations.php');

        $invitationId = (int)($_POST['invitation_id'] ?? 0);
        $response     = $_POST['response'] ?? '';

        $invStmt = $pdo->prepare('SELECT * FROM team_invitations WHERE id = ?');
        $invStmt->execute([$invitationId]);
        $invitation = $invStmt->fetch();

        if (!$invitation || (int)$invitation['invited_user_id'] !== $userId || $invitation['status'] !== 'Pending') {
            flash('error', 'That invitation is no longer available.');
            redirect('/student/team-invitations.php');
        }

        $teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
        $teamStmt->execute([$invitation['team_id']]);
        $team = $teamStmt->fetch();

        if ($response === 'accept') {
            if (!$team || team_member_count($pdo, (int)$team['id']) >= (int)$team['team_size_limit']) {
                flash('error', 'This team is already full.');
                redirect('/student/team-invitations.php');
            }
            if (is_team_member($pdo, (int)$invitation['team_id'], $userId)) {
                flash('error', 'You are already a member of this team.');
                redirect('/student/team-invitations.php');
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
                $memberIns->execute([$invitation['team_id'], $userId]);

                $upd = $pdo->prepare("UPDATE team_invitations SET status = 'Accepted', responded_at = NOW() WHERE id = ?");
                $upd->execute([$invitationId]);

                $pdo->commit();

                create_notification(
                    $pdo,
                    (int)$invitation['invited_by'],
                    'team_invitation_accepted',
                    'Invitation Accepted',
                    "{$currentUser['name']} accepted your invitation to join \"{$team['name']}\".",
                    'team',
                    (int)$team['id']
                );

                flash('success', 'You have joined the team.');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($ex->getCode() === '23000') {
                    flash('error', 'You are already a member of this team.');
                } else {
                    error_log('team-invitations.php accept: ' . $ex->getMessage());
                    flash('error', 'Could not accept the invitation. Please try again.');
                }
            }
        } elseif ($response === 'reject') {
            $upd = $pdo->prepare("UPDATE team_invitations SET status = 'Rejected', responded_at = NOW() WHERE id = ?");
            $upd->execute([$invitationId]);

            if ($team) {
                create_notification(
                    $pdo,
                    (int)$invitation['invited_by'],
                    'team_invitation_rejected',
                    'Invitation Declined',
                    "{$currentUser['name']} declined your invitation to join \"{$team['name']}\".",
                    'team',
                    (int)$team['id']
                );
            }
            flash('success', 'Invitation declined.');
        } else {
            flash('error', 'Invalid response.');
        }
        redirect('/student/team-invitations.php');
    }

    flash('error', 'Unknown action.');
    redirect('/student/team-invitations.php');
}

// --- GET: received (Pending) + sent (teams I lead) -----------------------
$receivedStmt = $pdo->prepare(
    "SELECT ti.*, rt.name AS team_name, rt.status AS team_status, u.name AS inviter_name
     FROM team_invitations ti
     JOIN research_teams rt ON rt.id = ti.team_id
     JOIN users u ON u.id = ti.invited_by
     WHERE ti.invited_user_id = ? AND ti.status = 'Pending'
     ORDER BY ti.created_at DESC"
);
$receivedStmt->execute([$userId]);
$received = $receivedStmt->fetchAll();

$ledTeamsStmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? AND role = 'Leader' AND status = 'Active'");
$ledTeamsStmt->execute([$userId]);
$ledTeamIds = array_map('intval', $ledTeamsStmt->fetchAll(PDO::FETCH_COLUMN));

$sent = [];
if ($ledTeamIds) {
    $placeholders = implode(',', array_fill(0, count($ledTeamIds), '?'));
    $sentStmt = $pdo->prepare(
        "SELECT ti.*, rt.name AS team_name, u.name AS invited_name
         FROM team_invitations ti
         JOIN research_teams rt ON rt.id = ti.team_id
         JOIN users u ON u.id = ti.invited_user_id
         WHERE ti.team_id IN ($placeholders)
         ORDER BY ti.created_at DESC"
    );
    $sentStmt->execute($ledTeamIds);
    $sent = $sentStmt->fetchAll();
}

function invitation_status_pill(string $status): string
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
    <title>Team Invitations || UIU ResearchCollab</title>
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
                <h2>Team Invitations</h2>
                <p>Respond to invitations you've received, and track invitations sent to your teams.</p>
            </div>
            <a href="<?= e(url('/student/teams.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Teams</a>
        </div>

        <div class="app-panel">
            <h3>Received</h3>
            <?php if ($received): ?>
                <?php foreach ($received as $inv): ?>
                    <div class="list-row">
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($inv['team_name']) ?></div>
                            <div class="text-muted small">Invited by <?= e($inv['inviter_name']) ?> &middot; <?= e(time_ago($inv['created_at'])) ?></div>
                            <?php if ($inv['message']): ?><div class="small mt-1">"<?= e($inv['message']) ?>"</div><?php endif; ?>
                        </div>
                        <form action="<?= e(url('/student/team-invitations.php')) ?>" method="post" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="respond">
                            <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                            <button type="submit" name="response" value="accept" class="btn btn-sm btn-uiu"><i class="bi bi-check-lg"></i> Accept</button>
                            <button type="submit" name="response" value="reject" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-envelope"></i><p>No pending invitations.</p></div>
            <?php endif; ?>
        </div>

        <div class="app-panel">
            <h3>Sent (from teams you lead)</h3>
            <?php if ($sent): ?>
                <?php foreach ($sent as $inv): ?>
                    <div class="list-row">
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($inv['invited_name']) ?> &middot; <span class="text-muted"><?= e($inv['team_name']) ?></span></div>
                            <div class="text-muted small"><?= e(time_ago($inv['created_at'])) ?></div>
                        </div>
                        <span class="<?= invitation_status_pill($inv['status']) ?>"><?= e($inv['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="app-empty-state"><i class="bi bi-send"></i><p>You haven't sent any invitations from your teams yet.</p></div>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
