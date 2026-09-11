<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/opportunities.php');
}

require_csrf('/student/opportunities.php');

$pdo           = db();
$userId        = (int)$currentUser['id'];
$opportunityId = isset($_POST['opportunity_id']) ? (int)$_POST['opportunity_id'] : 0;

if ($opportunityId <= 0) {
    flash('error', 'Invalid opportunity.');
    redirect_back('/student/opportunities.php');
}

$oppStmt = $pdo->prepare('SELECT id FROM research_opportunities WHERE id = ? LIMIT 1');
$oppStmt->execute([$opportunityId]);
if (!$oppStmt->fetch()) {
    flash('error', 'That opportunity no longer exists.');
    redirect_back('/student/opportunities.php');
}

$chk = $pdo->prepare('SELECT 1 FROM saved_opportunities WHERE user_id = ? AND opportunity_id = ? LIMIT 1');
$chk->execute([$userId, $opportunityId]);

if ($chk->fetch()) {
    try {
        $del = $pdo->prepare('DELETE FROM saved_opportunities WHERE user_id = ? AND opportunity_id = ?');
        $del->execute([$userId, $opportunityId]);
        flash('success', 'Removed from saved opportunities.');
    } catch (PDOException $e) {
        error_log('Unsave opportunity failed: ' . $e->getMessage());
        flash('error', 'Something went wrong. Please try again.');
    }
} else {
    try {
        $ins = $pdo->prepare('INSERT INTO saved_opportunities (user_id, opportunity_id) VALUES (?, ?)');
        $ins->execute([$userId, $opportunityId]);
        flash('success', 'Opportunity saved.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // Already saved (race condition) — not an error worth showing.
            flash('success', 'Opportunity saved.');
        } else {
            error_log('Save opportunity failed: ' . $e->getMessage());
            flash('error', 'Something went wrong. Please try again.');
        }
    }
}

redirect_back('/student/opportunities.php');
