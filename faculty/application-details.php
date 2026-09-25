<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/faculty_guard.php';

$pdo    = db();
$userId = (int)$currentUser['id'];
$appId  = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

function fac_load_application(PDO $pdo, int $appId, int $facultyUserId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT oa.*, o.title AS opportunity_title, o.id AS opportunity_id, u.name AS applicant_name, u.id AS applicant_user_id, u.email AS applicant_email,
                sp.department, sp.program, sp.semester, sp.bio, sp.profile_photo, sp.linkedin_url, sp.github_url, sp.id AS student_profile_id
         FROM opportunity_applications oa
         JOIN research_opportunities o ON o.id = oa.opportunity_id
         JOIN users u ON u.id = oa.user_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         WHERE oa.id = ? AND o.created_by = ?"
    );
    $stmt->execute([$appId, $facultyUserId]);
    return $stmt->fetch() ?: null;
}

$application = fac_load_application($pdo, $appId, $userId);
if (!$application) {
    flash('error', 'That application does not exist or is not for one of your opportunities.');
    redirect('/faculty/applications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/faculty/application-details.php?id=' . $appId);
    $action = $_POST['action'] ?? '';
    $notes  = nullable_trim($_POST['review_notes'] ?? '');

    $actionToStatus = ['shortlist' => 'Shortlisted', 'accept' => 'Accepted', 'reject' => 'Rejected'];

    if (isset($actionToStatus[$action])) {
        $newStatus = $actionToStatus[$action];
        try {
            $pdo->prepare('UPDATE opportunity_applications SET status = ?, review_notes = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$newStatus, $notes, $appId]);

            $studentMessage = match ($newStatus) {
                'Shortlisted' => 'You have been shortlisted for "' . $application['opportunity_title'] . '".',
                'Accepted'    => 'Congratulations! Your application for "' . $application['opportunity_title'] . '" was accepted.',
                'Rejected'    => 'Your application for "' . $application['opportunity_title'] . '" was not selected this time.',
            };
            create_notification(
                $pdo, (int)$application['applicant_user_id'], 'opportunity_application', 'Application ' . $newStatus,
                $studentMessage, 'opportunity', (int)$application['opportunity_id']
            );
            log_activity($pdo, $userId, 'application_reviewed', 'Set application from "' . $application['applicant_name'] . '" to ' . $newStatus, 'opportunity_application', $appId);
            flash('success', 'Application marked as ' . $newStatus . '.');
        } catch (Throwable $ex) {
            error_log('faculty application-details review: ' . $ex->getMessage());
            flash('error', 'Could not update the application. Please try again.');
        }
    } elseif ($action === 'save_notes') {
        try {
            $pdo->prepare('UPDATE opportunity_applications SET review_notes = ? WHERE id = ?')->execute([$notes, $appId]);
            flash('success', 'Notes saved.');
        } catch (Throwable $ex) {
            error_log('faculty application-details save_notes: ' . $ex->getMessage());
            flash('error', 'Could not save notes.');
        }
    }
    redirect('/faculty/application-details.php?id=' . $appId);
}

$viewerCanSeeProfile = can_view_student_profile($pdo, $userId, ['user_id' => $application['applicant_user_id'], 'id' => $application['student_profile_id']]);

function fac_app_status_badge2(string $status): string
{
    return match ($status) {
        'Accepted'    => 'bg-success-subtle text-success-emphasis',
        'Shortlisted' => 'bg-info-subtle text-info-emphasis',
        'Rejected'    => 'bg-danger-subtle text-danger-emphasis',
        'Withdrawn'   => 'bg-secondary-subtle text-secondary-emphasis',
        default       => 'bg-warning-subtle text-warning-emphasis',
    };
}

$pageTitle = 'Application Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application — <?= e($application['applicant_name']) ?> || UIU ResearchCollab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <style>
        .detail-card{background-color:var(--card-background);border:1px solid var(--border-color);border-radius:10px;padding:20px;margin-bottom:18px}
        .detail-card h2{color:var(--uiu-blue);font-size:18px;font-weight:700;margin-bottom:10px}
        .avatar-lg{width:64px;height:64px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--uiu-blue);color:#fff;font-size:22px;font-weight:700;flex-shrink:0;object-fit:cover}
    </style>
</head>
<body>
<?php require __DIR__ . '/../includes/faculty_header.php'; ?>
<?php require __DIR__ . '/../includes/faculty_sidebar.php'; ?>
    <main class="dashboard-main">
        <?php render_flashes(); ?>
        <a href="<?= e(url('/faculty/applications.php')) ?>" class="d-inline-block mb-2" style="color:var(--uiu-blue);"><i class="bi bi-arrow-left"></i> Back to Applications</a>

        <div class="detail-card d-flex align-items-center gap-3 flex-wrap">
            <?php if (!empty($application['profile_photo'])): ?>
                <img class="avatar-lg" src="<?= e(url('/uploads/avatars/' . $application['profile_photo'])) ?>" alt="<?= e($application['applicant_name']) ?>">
            <?php else: ?>
                <span class="avatar-lg"><?= e(initials($application['applicant_name'])) ?></span>
            <?php endif; ?>
            <div class="flex-grow-1">
                <h1 style="font-size:20px;color:var(--uiu-blue);font-weight:700;margin:0;"><?= e($application['applicant_name']) ?></h1>
                <p class="text-muted mb-1">Applied for <a href="<?= e(url('/faculty/opportunity-details.php?id=' . $application['opportunity_id'])) ?>"><?= e($application['opportunity_title']) ?></a></p>
                <span class="badge rounded-pill <?= fac_app_status_badge2($application['status']) ?>"><?= e($application['status']) ?></span>
                <span class="text-muted small ms-2">Applied <?= e(time_ago($application['applied_at'])) ?></span>
            </div>
        </div>

        <?php if ($viewerCanSeeProfile): ?>
        <div class="detail-card">
            <h2>Applicant Profile</h2>
            <p class="mb-1"><strong>Department:</strong> <?= e($application['department'] ?: 'Not set') ?></p>
            <p class="mb-1"><strong>Program:</strong> <?= e($application['program'] ?: 'Not set') ?> &middot; <strong>Semester:</strong> <?= e($application['semester'] ?: 'Not set') ?></p>
            <?php if ($application['bio']): ?><p class="mb-1"><strong>Bio:</strong> <?= nl2br(e($application['bio'])) ?></p><?php endif; ?>
            <?php if ($application['linkedin_url']): ?><p class="mb-1"><a href="<?= e($application['linkedin_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-linkedin"></i> LinkedIn</a></p><?php endif; ?>
            <?php if ($application['github_url']): ?><p class="mb-0"><a href="<?= e($application['github_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-github"></i> GitHub</a></p><?php endif; ?>
            <a href="<?= e(url('/faculty/student-profile.php?id=' . $application['applicant_user_id'])) ?>" class="btn btn-sm btn-outline-primary mt-2">View Full Profile</a>
        </div>
        <?php else: ?>
        <div class="detail-card"><p class="text-muted mb-0">This student has limited their profile visibility.</p></div>
        <?php endif; ?>

        <?php if ($application['message']): ?>
        <div class="detail-card"><h2>Message from Applicant</h2><p style="white-space:pre-line;color:var(--text-medium);"><?= e($application['message']) ?></p></div>
        <?php endif; ?>

        <div class="detail-card">
            <h2>Review Notes <small class="text-muted fw-normal">(internal — never shown to the student)</small></h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_notes">
                <textarea name="review_notes" class="form-control mb-2" rows="3" placeholder="Internal notes about this applicant..."><?= e($application['review_notes'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Save Notes</button>
            </form>
        </div>

        <div class="detail-card">
            <h2>Decision</h2>
            <?php if (in_array($application['status'], ['Accepted', 'Rejected', 'Withdrawn'], true)): ?>
                <p class="text-muted mb-0">This application is <strong><?= e($application['status']) ?></strong>. No further action is needed.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($application['status'] !== 'Shortlisted'): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="shortlist"><input type="hidden" name="review_notes" value="<?= e($application['review_notes'] ?? '') ?>">
                        <button class="btn btn-info text-white"><i class="bi bi-star"></i> Shortlist</button>
                    </form>
                    <?php endif; ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="accept"><input type="hidden" name="review_notes" value="<?= e($application['review_notes'] ?? '') ?>">
                        <button class="btn btn-success"><i class="bi bi-check-lg"></i> Accept</button>
                    </form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="review_notes" value="<?= e($application['review_notes'] ?? '') ?>">
                        <button class="btn btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
