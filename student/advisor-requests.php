<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

$pdo       = db();
$userId    = (int)$currentUser['id'];
$profileId = (int)$studentProfile['id'];

$requestTypeLabels = [
    'research_advisor' => 'Research Advisor',
    'paper_advisor'     => 'Paper Advisor',
    'project_mentor'    => 'Project Mentor',
    'fydp_supervisor'   => 'FYDP Supervisor',
    'research_mentor'   => 'Research Mentor',
    'technical_mentor'  => 'Technical Mentor',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/student/advisor-requests.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $facultyUserId = (int)($_POST['faculty_id'] ?? 0);
        $requestType   = $_POST['request_type'] ?? '';
        $title         = nullable_trim($_POST['title'] ?? '');
        $message       = nullable_trim($_POST['message'] ?? '');
        $summary       = nullable_trim($_POST['research_summary'] ?? '');
        $commitment    = nullable_trim($_POST['expected_commitment'] ?? '');
        $frequency     = nullable_trim($_POST['preferred_meeting_frequency'] ?? '');
        $projectId     = (int)($_POST['project_id'] ?? 0) ?: null;
        $opportunityId = (int)($_POST['opportunity_id'] ?? 0) ?: null;

        $facultyStmt = $pdo->prepare("SELECT fp.* FROM faculty_profiles fp JOIN users u ON u.id = fp.user_id WHERE u.id = ? AND u.role = 'faculty'");
        $facultyStmt->execute([$facultyUserId]);
        $facultyProfileRow = $facultyStmt->fetch();

        if (!$facultyProfileRow || !array_key_exists($requestType, $requestTypeLabels)) {
            flash('error', 'Please choose a valid faculty member and request type.');
        } else {
            $prefs = get_faculty_preferences($pdo, (int)$facultyProfileRow['id']);
            $typeAllowed = true;
            if ($prefs) {
                if ($requestType === 'paper_advisor' && !$prefs['accepting_paper_advisory']) {
                    $typeAllowed = false;
                } elseif (!$prefs['accepting_mentees']) {
                    $typeAllowed = false;
                }
            }
            $remaining = faculty_capacity_remaining($pdo, $facultyUserId);

            if (!$typeAllowed) {
                flash('error', 'This faculty member is not currently accepting that type of request.');
            } elseif ($remaining !== null && $remaining <= 0) {
                flash('error', 'This faculty member has reached their mentee capacity.');
            } elseif (has_pending_advisor_request($pdo, $facultyUserId, $userId, null, $requestType)) {
                flash('error', 'You already have a pending request of this type with this faculty member.');
            } elseif (has_active_advisor_assignment($pdo, $facultyUserId, $userId, null, $requestType)) {
                flash('error', 'This faculty member is already your active advisor for this type.');
            } else {
                try {
                    $pdo->prepare(
                        "INSERT INTO advisor_requests (requester_type, requested_by_user_id, faculty_user_id, project_id, opportunity_id, request_type, title, message, research_summary, expected_commitment, preferred_meeting_frequency) VALUES ('student',?,?,?,?,?,?,?,?,?,?)"
                    )->execute([$userId, $facultyUserId, $projectId, $opportunityId, $requestType, $title, $message, $summary, $commitment, $frequency]);
                    $reqId = (int)$pdo->lastInsertId();

                    create_notification($pdo, $facultyUserId, 'advisor_request', 'New Advisor Request', $currentUser['name'] . ' requested you as a ' . $requestTypeLabels[$requestType] . '.', 'advisor_request', $reqId);
                    log_activity($pdo, $userId, 'advisor_request_sent', 'Sent an advisor request', 'advisor_request', $reqId);
                    flash('success', 'Advisor request sent.');
                } catch (Throwable $ex) {
                    error_log('student advisor-requests create: ' . $ex->getMessage());
                    flash('error', 'Could not send advisor request. Please try again.');
                }
            }
        }
    } elseif ($action === 'cancel') {
        $reqId = (int)($_POST['id'] ?? 0);
        $chk = $pdo->prepare("SELECT * FROM advisor_requests WHERE id = ? AND requested_by_user_id = ? AND status IN ('pending','clarification_requested')");
        $chk->execute([$reqId, $userId]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE advisor_requests SET status='cancelled', responded_at=NOW() WHERE id=?")->execute([$reqId]);
            flash('success', 'Request cancelled.');
        } else {
            flash('error', 'Request not found or cannot be cancelled.');
        }
    } elseif ($action === 'respond_clarification') {
        $reqId = (int)($_POST['id'] ?? 0);
        $reply = nullable_trim($_POST['reply'] ?? '');
        $chk = $pdo->prepare("SELECT * FROM advisor_requests WHERE id = ? AND requested_by_user_id = ? AND status = 'clarification_requested'");
        $chk->execute([$reqId, $userId]);
        $req = $chk->fetch();
        if (!$req) {
            flash('error', 'Request not found.');
        } elseif ($reply === null) {
            flash('error', 'Please write a response before submitting.');
        } else {
            $newMessage = trim(($req['message'] ?? '') . "\n\n[Reply] " . $reply);
            $pdo->prepare("UPDATE advisor_requests SET status='pending', message=? WHERE id=?")->execute([$newMessage, $reqId]);
            create_notification($pdo, (int)$req['faculty_user_id'], 'advisor_request', 'Clarification Response', $currentUser['name'] . ' responded to your question.', 'advisor_request', $reqId);
            flash('success', 'Your response was sent.');
        }
    }
    redirect('/student/advisor-requests.php');
}

// -----------------------------------------------------------------
// GET: list + optional create form
// -----------------------------------------------------------------
$requests = $pdo->prepare(
    "SELECT ar.*, u.name AS faculty_name, fp.designation, fp.department
     FROM advisor_requests ar JOIN users u ON u.id = ar.faculty_user_id
     LEFT JOIN faculty_profiles fp ON fp.user_id = ar.faculty_user_id
     WHERE ar.requester_type = 'student' AND ar.requested_by_user_id = ?
     ORDER BY FIELD(ar.status,'pending','clarification_requested','accepted','declined','cancelled','completed'), ar.created_at DESC"
);
$requests->execute([$userId]);
$requests = $requests->fetchAll();

$preselectFacultyId = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;
$facultyOptions = $pdo->query(
    "SELECT fp.id AS fp_id, u.id AS user_id, u.name, fp.designation, fp.department
     FROM faculty_profiles fp JOIN users u ON u.id = fp.user_id WHERE u.status = 'active' ORDER BY u.name"
)->fetchAll();

$myProjects = $pdo->prepare('SELECT id, title FROM projects WHERE profile_id = ? ORDER BY title');
$myProjects->execute([$profileId]);
$myProjects = $myProjects->fetchAll();

function ar_status_pill(string $status): string
{
    return match ($status) {
        'pending', 'clarification_requested' => 'pill pill-orange',
        'accepted', 'completed'              => 'pill pill-green',
        'declined', 'cancelled'              => 'pill pill-red',
        default                              => 'pill pill-gray',
    };
}

$pageTitle = 'My Advisor Requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Advisor Requests || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .app-panel{background:#fff;border:1px solid var(--border-color);border-radius:10px;padding:18px;margin-bottom:18px}
        .app-panel h3{color:var(--uiu-blue);font-size:16px;font-weight:700;margin:0 0 12px}
        .pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pill-green{background:#d6f5e0;color:#0f8a3c}
        .pill-orange{background:#ffe8cc;color:#b35a00}
        .pill-red{background:#fde0e0;color:#c62828}
        .pill-gray{background:#e9e9e9;color:#555}
        .app-empty-state{padding:20px;text-align:center;color:var(--text-light)}
        .btn-uiu{background:var(--uiu-blue);border-color:var(--uiu-blue);color:#fff}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <h2 style="color:var(--uiu-blue);font-size:23px;font-weight:700;">My Advisor Requests</h2>
        <p class="text-muted">Requests you've sent to faculty asking them to be your advisor or mentor.</p>

        <div class="app-panel">
            <h3>New Request</h3>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Faculty Member</label>
                        <select name="faculty_id" class="form-select" required>
                            <option value="">Select a faculty member...</option>
                            <?php foreach ($facultyOptions as $f): ?>
                                <option value="<?= (int)$f['user_id'] ?>" <?= $preselectFacultyId === (int)$f['user_id'] ? 'selected' : '' ?>><?= e($f['name']) ?> — <?= e($f['designation'] ?: 'Faculty') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Request Type</label>
                        <select name="request_type" class="form-select" required>
                            <?php foreach ($requestTypeLabels as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-2"><label class="form-label">Title</label><input type="text" name="title" class="form-control" placeholder="e.g. Advisor request for FYDP project"></div>
                <div class="mb-2"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="3" placeholder="Introduce yourself and explain why you'd like this faculty member as your advisor..."></textarea></div>
                <div class="mb-2"><label class="form-label">Research Summary</label><textarea name="research_summary" class="form-control" rows="2"></textarea></div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6"><label class="form-label">Expected Commitment</label><input type="text" name="expected_commitment" class="form-control" placeholder="e.g. 5 hrs/week"></div>
                    <div class="col-md-6"><label class="form-label">Preferred Meeting Frequency</label><input type="text" name="preferred_meeting_frequency" class="form-control" placeholder="e.g. Weekly"></div>
                </div>
                <?php if ($myProjects): ?>
                <div class="mb-3">
                    <label class="form-label">Link a Project (optional)</label>
                    <select name="project_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($myProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-uiu"><i class="bi bi-send"></i> Send Request</button>
            </form>
        </div>

        <div class="app-panel">
            <h3>My Requests (<?= count($requests) ?>)</h3>
            <?php if (!$requests): ?><div class="app-empty-state">No advisor requests yet.</div><?php endif; ?>
            <?php foreach ($requests as $r): ?>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 py-2 border-bottom">
                    <div>
                        <div class="fw-semibold"><?= e($r['faculty_name']) ?> <span class="pill pill-gray"><?= e($requestTypeLabels[$r['request_type']] ?? $r['request_type']) ?></span></div>
                        <div class="text-muted small"><?= e($r['title'] ?: 'No title') ?> &middot; <?= e(time_ago($r['created_at'])) ?></div>
                        <?php if ($r['status'] === 'clarification_requested' && $r['faculty_response']): ?>
                            <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">Question: <?= e($r['faculty_response']) ?></div>
                            <form method="post" class="d-flex gap-2 mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="respond_clarification">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="text" name="reply" class="form-control form-control-sm" placeholder="Your response..." required>
                                <button class="btn btn-sm btn-outline-primary">Send</button>
                            </form>
                        <?php elseif ($r['status'] === 'declined' && $r['faculty_response']): ?>
                            <div class="text-muted small mt-1">Faculty note: <?= e($r['faculty_response']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="<?= ar_status_pill($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
                        <?php if (in_array($r['status'], ['pending', 'clarification_requested'], true)): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Cancel</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
