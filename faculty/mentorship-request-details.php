<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$reqId  = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

function mrd_load(PDO $pdo, int $reqId, int $facultyUserId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT ar.*, u.name AS requester_name, u.email AS requester_email,
                rt.name AS team_name, rt.id AS team_id_lookup,
                o.title AS opportunity_title, p.title AS project_title
         FROM advisor_requests ar
         JOIN users u ON u.id = ar.requested_by_user_id
         LEFT JOIN research_teams rt ON rt.id = ar.team_id
         LEFT JOIN research_opportunities o ON o.id = ar.opportunity_id
         LEFT JOIN projects p ON p.id = ar.project_id
         WHERE ar.id = ? AND ar.faculty_user_id = ?"
    );
    $stmt->execute([$reqId, $facultyUserId]);
    return $stmt->fetch() ?: null;
}

$request = mrd_load($pdo, $reqId, $userId);
if (!$request) {
    flash('error', 'That mentorship request does not exist.');
    redirect('/faculty/mentorship-requests.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/mentorship-request-details.php?id=' . $reqId);
    $action   = $_POST['action'] ?? '';
    $response = nullable_trim($_POST['faculty_response'] ?? '');

    if ($action === 'accept' && in_array($request['status'], ['pending', 'clarification_requested'], true)) {
        $remaining = faculty_capacity_remaining($pdo, $userId);
        $isTeam    = $request['requester_type'] === 'team';
        $studentUserId = $isTeam ? null : (int)$request['requested_by_user_id'];
        $teamId        = $isTeam ? (int)$request['team_id'] : null;

        if ($remaining !== null && $remaining <= 0) {
            flash('error', 'You have reached your maximum active mentee capacity. Increase it in your profile or complete an existing assignment first.');
        } elseif (has_active_advisor_assignment($pdo, $userId, $studentUserId, $teamId, $request['request_type'])) {
            flash('error', 'You already have an active assignment of this type for this student/team.');
        } else {
            try {
                $pdo->beginTransaction();

                $pdo->prepare("UPDATE advisor_requests SET status='accepted', faculty_response=?, responded_at=NOW() WHERE id=?")
                    ->execute([$response, $reqId]);

                $insAssign = $pdo->prepare(
                    'INSERT INTO advisor_assignments (advisor_request_id, faculty_user_id, student_user_id, team_id, project_id, opportunity_id, assignment_type, status) VALUES (?,?,?,?,?,?,?,\'active\')'
                );
                $insAssign->execute([$reqId, $userId, $studentUserId, $teamId, $request['project_id'], $request['opportunity_id'], $request['request_type']]);
                $assignmentId = (int)$pdo->lastInsertId();

                $requesterId = (int)$request['requested_by_user_id'];
                $connId = null;
                if (!has_existing_connection($pdo, $userId, $requesterId)) {
                    $pdo->prepare("INSERT INTO research_connections (requester_id, recipient_id, status, responded_at) VALUES (?,?,'accepted', NOW())")
                        ->execute([$userId, $requesterId]);
                    $connId = (int)$pdo->lastInsertId();
                } else {
                    $connLookup = $pdo->prepare("SELECT id FROM research_connections WHERE pair_key = ? AND status = 'accepted' LIMIT 1");
                    $connLookup->execute([min($userId, $requesterId) . '-' . max($userId, $requesterId)]);
                    $connId = $connLookup->fetchColumn() ?: null;
                }
                get_or_create_direct_conversation($pdo, $userId, $requesterId, $connId, $assignmentId);

                $pdo->commit();

                $target = $isTeam ? ('your team "' . $request['team_name'] . '"') : 'you';
                create_notification($pdo, $requesterId, 'advisor_request', 'Advisor Request Accepted', $currentUser['name'] . ' accepted the advisor request for ' . $target . '.', 'advisor_request', $reqId);

                if ($isTeam && $teamId) {
                    $members = $pdo->prepare("SELECT user_id FROM team_members WHERE team_id = ? AND status = 'Active' AND user_id != ?");
                    $members->execute([$teamId, $requesterId]);
                    foreach ($members->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
                        create_notification($pdo, (int)$memberId, 'advisor_assignment', 'Faculty Advisor Assigned', $currentUser['name'] . ' is now advising your team "' . $request['team_name'] . '".', 'team', $teamId);
                    }
                }

                log_activity($pdo, $userId, 'advisor_request_accepted', 'Accepted advisor request from ' . $request['requester_name'], 'advisor_request', $reqId);
                flash('success', 'Request accepted. You are now the assigned advisor.');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                error_log('mentorship-request-details accept: ' . $ex->getMessage());
                flash('error', 'Could not accept this request. Please try again.');
            }
        }
    } elseif ($action === 'decline' && in_array($request['status'], ['pending', 'clarification_requested'], true)) {
        try {
            $pdo->prepare("UPDATE advisor_requests SET status='declined', faculty_response=?, responded_at=NOW() WHERE id=?")->execute([$response, $reqId]);
            create_notification($pdo, (int)$request['requested_by_user_id'], 'advisor_request', 'Advisor Request Declined', $currentUser['name'] . ' declined your advisor request.' . ($response ? ' Reason: ' . $response : ''), 'advisor_request', $reqId);
            log_activity($pdo, $userId, 'advisor_request_declined', 'Declined advisor request from ' . $request['requester_name'], 'advisor_request', $reqId);
            flash('success', 'Request declined.');
        } catch (Throwable $ex) {
            error_log('mentorship-request-details decline: ' . $ex->getMessage());
            flash('error', 'Could not decline this request.');
        }
    } elseif ($action === 'clarify' && $request['status'] === 'pending') {
        if ($response === null) {
            flash('error', 'Please enter your question for the requester.');
        } else {
            try {
                $pdo->prepare("UPDATE advisor_requests SET status='clarification_requested', faculty_response=?, responded_at=NOW() WHERE id=?")->execute([$response, $reqId]);
                create_notification($pdo, (int)$request['requested_by_user_id'], 'advisor_request', 'Clarification Requested', $currentUser['name'] . ' asked a question about your advisor request: "' . $response . '"', 'advisor_request', $reqId);
                log_activity($pdo, $userId, 'advisor_request_clarify', 'Requested clarification from ' . $request['requester_name'], 'advisor_request', $reqId);
                flash('success', 'Clarification request sent.');
            } catch (Throwable $ex) {
                error_log('mentorship-request-details clarify: ' . $ex->getMessage());
                flash('error', 'Could not send clarification request.');
            }
        }
    } else {
        flash('error', 'That action is not available for this request.');
    }
    redirect('/faculty/mentorship-request-details.php?id=' . $reqId);
}

$request = mrd_load($pdo, $reqId, $userId);
$remaining = faculty_capacity_remaining($pdo, $userId);

function mrd_status_pill(string $status): string
{
    return match ($status) {
        'pending', 'clarification_requested' => 'badge bg-warning-subtle text-warning-emphasis',
        'accepted', 'completed'              => 'badge bg-success-subtle text-success-emphasis',
        'declined', 'cancelled'              => 'badge bg-danger-subtle text-danger-emphasis',
        default                              => 'badge bg-secondary-subtle text-secondary-emphasis',
    };
}

$pageTitle = 'Mentorship Request';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorship Request || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/faculty/mentorship-requests.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Requests</a>

        <div class="detail-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h1 style="font-size:20px;color:var(--uiu-blue);font-weight:700;margin:0;">
                        <?= $request['requester_type'] === 'team' ? e($request['team_name']) . ' (Team)' : e($request['requester_name']) ?>
                    </h1>
                    <p class="text-muted mb-0"><?= e(ucwords(str_replace('_', ' ', $request['request_type']))) ?> request &middot; <?= e(time_ago($request['created_at'])) ?></p>
                </div>
                <span class="<?= mrd_status_pill($request['status']) ?>"><?= e(str_replace('_', ' ', ucfirst($request['status']))) ?></span>
            </div>
        </div>

        <div class="detail-card">
            <h2><?= e($request['title'] ?: 'Request Details') ?></h2>
            <?php if ($request['message']): ?><p class="mb-2"><strong>Message:</strong><br><?= nl2br(e($request['message'])) ?></p><?php endif; ?>
            <?php if ($request['research_summary']): ?><p class="mb-2"><strong>Research Summary:</strong><br><?= nl2br(e($request['research_summary'])) ?></p><?php endif; ?>
            <?php if ($request['expected_commitment']): ?><p class="mb-1"><strong>Expected Commitment:</strong> <?= e($request['expected_commitment']) ?></p><?php endif; ?>
            <?php if ($request['preferred_meeting_frequency']): ?><p class="mb-1"><strong>Preferred Meeting Frequency:</strong> <?= e($request['preferred_meeting_frequency']) ?></p><?php endif; ?>
            <?php if ($request['opportunity_title']): ?><p class="mb-1"><strong>Linked Opportunity:</strong> <?= e($request['opportunity_title']) ?></p><?php endif; ?>
            <?php if ($request['project_title']): ?><p class="mb-0"><strong>Linked Project:</strong> <?= e($request['project_title']) ?></p><?php endif; ?>
        </div>

        <?php if ($request['faculty_response']): ?>
        <div class="detail-card"><h2>Your Previous Response</h2><p class="mb-0"><?= nl2br(e($request['faculty_response'])) ?></p></div>
        <?php endif; ?>

        <div class="detail-card">
            <h2>Your Capacity</h2>
            <p class="mb-0"><?= $remaining === null ? 'Unlimited (no cap set).' : $remaining . ' mentee slot(s) remaining.' ?></p>
        </div>

        <div class="detail-card">
            <h2>Decision</h2>
            <?php if (in_array($request['status'], ['accepted', 'declined', 'cancelled', 'completed'], true)): ?>
                <p class="text-muted mb-0">This request is <strong><?= e($request['status']) ?></strong>. No further action needed.</p>
            <?php else: ?>
                <form method="post" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $reqId ?>">
                    <div class="mb-2">
                        <label class="form-label">Response / notes (optional for accept/decline, required for clarification)</label>
                        <textarea name="faculty_response" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button name="action" value="accept" class="btn btn-success"><i class="bi bi-check-lg"></i> Accept</button>
                        <button name="action" value="decline" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i> Decline</button>
                        <?php if ($request['status'] === 'pending'): ?>
                            <button name="action" value="clarify" class="btn btn-outline-secondary"><i class="bi bi-question-circle"></i> Request Clarification</button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
