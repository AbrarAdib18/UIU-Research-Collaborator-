<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/opportunities.php');
}

$pdo           = db();
$userId        = (int)$currentUser['id'];
$opportunityId = isset($_POST['opportunity_id']) ? (int)$_POST['opportunity_id'] : 0;
$action        = (string)($_POST['action'] ?? '');

$detailsUrl = $opportunityId > 0
    ? '/student/opportunity-details.php?id=' . $opportunityId
    : '/student/opportunities.php';

require_csrf($detailsUrl);

if ($opportunityId <= 0) {
    flash('error', 'Invalid opportunity.');
    redirect('/student/opportunities.php');
}

$oppStmt = $pdo->prepare('SELECT * FROM research_opportunities WHERE id = ? LIMIT 1');
$oppStmt->execute([$opportunityId]);
$opportunity = $oppStmt->fetch();

if (!$opportunity) {
    flash('error', 'That opportunity no longer exists.');
    redirect('/student/opportunities.php');
}

if ($action === 'apply') {
    $deadlinePassed = $opportunity['deadline'] !== null && $opportunity['deadline'] < date('Y-m-d');

    if ($opportunity['status'] !== 'Open' || $opportunity['visibility'] !== 'Public' || $deadlinePassed) {
        flash('error', 'This opportunity is no longer accepting applications.');
        redirect($detailsUrl);
    }

    // The UNIQUE key on (opportunity_id, user_id) allows only one row per
    // student per opportunity, in ANY status. A Pending/Accepted row blocks
    // a new application outright; a past Withdrawn/Rejected row is reopened
    // back to Pending instead (a student who withdrew, or was rejected while
    // the opportunity is still open, may reconsider/reapply) — this mirrors
    // the same reopen-not-reinsert pattern used for team join requests.
    $chk = $pdo->prepare(
        'SELECT id, status FROM opportunity_applications WHERE opportunity_id = ? AND user_id = ? LIMIT 1'
    );
    $chk->execute([$opportunityId, $userId]);
    $existing = $chk->fetch();

    if ($existing && in_array($existing['status'], ['Pending', 'Accepted'], true)) {
        flash('info', 'You already applied to this opportunity.');
        redirect($detailsUrl);
    }

    $message = trim((string)($_POST['message'] ?? ''));
    $message = $message === '' ? null : $message;

    try {
        if ($existing) {
            $reopen = $pdo->prepare(
                "UPDATE opportunity_applications
                 SET status = 'Pending', message = ?, applied_at = NOW(), reviewed_at = NULL
                 WHERE id = ?"
            );
            $reopen->execute([$message, $existing['id']]);
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO opportunity_applications (opportunity_id, user_id, message, status)
                 VALUES (?, ?, ?, 'Pending')"
            );
            $insert->execute([$opportunityId, $userId, $message]);
        }

        create_notification(
            $pdo,
            $userId,
            'application_submitted',
            'Application Submitted',
            "You applied to \"{$opportunity['title']}\".",
            'opportunity',
            $opportunityId
        );
        log_activity($pdo, $userId, 'opportunity_apply', "Applied to {$opportunity['title']}", 'opportunity', $opportunityId);

        flash('success', 'Your application has been submitted.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('info', 'You already applied to this opportunity.');
        } else {
            error_log('Opportunity apply failed: ' . $e->getMessage());
            flash('error', 'Something went wrong submitting your application. Please try again.');
        }
    }

    redirect($detailsUrl);
}

if ($action === 'withdraw') {
    $appStmt = $pdo->prepare('SELECT * FROM opportunity_applications WHERE opportunity_id = ? AND user_id = ? LIMIT 1');
    $appStmt->execute([$opportunityId, $userId]);
    $application = $appStmt->fetch();

    if (!$application || $application['status'] !== 'Pending') {
        flash('error', 'You have no pending application to withdraw.');
        redirect($detailsUrl);
    }

    try {
        $upd = $pdo->prepare(
            "UPDATE opportunity_applications SET status = 'Withdrawn', reviewed_at = NOW()
             WHERE opportunity_id = ? AND user_id = ?"
        );
        $upd->execute([$opportunityId, $userId]);

        log_activity($pdo, $userId, 'opportunity_withdraw', "Withdrew application from {$opportunity['title']}", 'opportunity', $opportunityId);

        flash('success', 'Your application has been withdrawn.');
    } catch (PDOException $e) {
        error_log('Opportunity withdraw failed: ' . $e->getMessage());
        flash('error', 'Something went wrong withdrawing your application. Please try again.');
    }

    redirect($detailsUrl);
}

flash('error', 'Unknown action.');
redirect($detailsUrl);
