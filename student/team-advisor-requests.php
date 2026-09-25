<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/teams.php');
}

$teamId = (int)($_POST['team_id'] ?? 0);
$backUrl = '/student/team-details.php?id=' . $teamId;
require_csrf($backUrl);

$teamStmt = $pdo->prepare('SELECT * FROM research_teams WHERE id = ?');
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    flash('error', 'Team not found.');
    redirect('/student/teams.php');
}
if (!is_team_leader($pdo, $teamId, $userId)) {
    flash('error', 'Only the team leader can manage advisor requests.');
    redirect($backUrl);
}

$action = $_POST['action'] ?? '';
$requestTypeLabels = [
    'research_advisor' => 'Research Advisor',
    'paper_advisor'     => 'Paper Advisor',
    'project_mentor'    => 'Project Mentor',
    'fydp_supervisor'   => 'FYDP Supervisor',
    'research_mentor'   => 'Research Mentor',
    'technical_mentor'  => 'Technical Mentor',
];

if ($action === 'create') {
    $facultyUserId = (int)($_POST['faculty_id'] ?? 0);
    $requestType   = $_POST['request_type'] ?? '';
    $title         = nullable_trim($_POST['title'] ?? '');
    $message       = nullable_trim($_POST['message'] ?? '');
    $summary       = nullable_trim($_POST['research_summary'] ?? '');
    $opportunityId = $team['opportunity_id'] ?: null;

    $facultyStmt = $pdo->prepare("SELECT fp.* FROM faculty_profiles fp JOIN users u ON u.id = fp.user_id WHERE u.id = ? AND u.role = 'faculty'");
    $facultyStmt->execute([$facultyUserId]);
    $facultyProfileRow = $facultyStmt->fetch();

    if (is_team_advisor($pdo, $teamId, $facultyUserId)) {
        flash('error', 'This faculty member is already your team\'s advisor.');
    } elseif (!$facultyProfileRow || !array_key_exists($requestType, $requestTypeLabels)) {
        flash('error', 'Please choose a valid faculty member and request type.');
    } else {
        $prefs = get_faculty_preferences($pdo, (int)$facultyProfileRow['id']);
        if ($prefs && !$prefs['accepting_team_advisory']) {
            flash('error', 'This faculty member is not currently accepting team advisory requests.');
        } elseif (has_pending_advisor_request($pdo, $facultyUserId, null, $teamId, $requestType)) {
            flash('error', 'Your team already has a pending request of this type with this faculty member.');
        } elseif (has_active_advisor_assignment($pdo, $facultyUserId, null, $teamId, $requestType)) {
            flash('error', 'This faculty member is already an active advisor for your team.');
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO advisor_requests (requester_type, requested_by_user_id, faculty_user_id, team_id, opportunity_id, request_type, title, message, research_summary) VALUES ('team',?,?,?,?,?,?,?,?)"
                )->execute([$userId, $facultyUserId, $teamId, $opportunityId, $requestType, $title, $message, $summary]);
                $reqId = (int)$pdo->lastInsertId();

                create_notification($pdo, $facultyUserId, 'advisor_request', 'New Team Advisor Request', 'Team "' . $team['name'] . '" requested you as a ' . $requestTypeLabels[$requestType] . '.', 'advisor_request', $reqId);
                log_activity($pdo, $userId, 'advisor_request_sent', 'Sent a team advisor request', 'advisor_request', $reqId);
                flash('success', 'Advisor request sent on behalf of your team.');
            } catch (Throwable $ex) {
                error_log('team-advisor-requests create: ' . $ex->getMessage());
                flash('error', 'Could not send advisor request. Please try again.');
            }
        }
    }
} elseif ($action === 'cancel') {
    $reqId = (int)($_POST['id'] ?? 0);
    $chk = $pdo->prepare("SELECT * FROM advisor_requests WHERE id = ? AND team_id = ? AND status IN ('pending','clarification_requested')");
    $chk->execute([$reqId, $teamId]);
    if ($chk->fetch()) {
        $pdo->prepare("UPDATE advisor_requests SET status='cancelled', responded_at=NOW() WHERE id=?")->execute([$reqId]);
        flash('success', 'Request cancelled.');
    } else {
        flash('error', 'Request not found or cannot be cancelled.');
    }
}

redirect($backUrl);
