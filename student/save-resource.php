<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_guard.php';

/**
 * POST-only toggle: save/unsave a research resource for the current user.
 * No HTML output — mirrors the opportunities module's save-opportunity.php.
 */

require_csrf('/student/repository.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/repository.php');
}

$pdo        = db();
$userId     = (int)$currentUser['id'];
$resourceId = (int)($_POST['resource_id'] ?? 0);

if ($resourceId <= 0) {
    flash('error', 'Invalid resource.');
    redirect_back('/student/repository.php');
}

try {
    $check = $pdo->prepare('SELECT 1 FROM saved_resources WHERE user_id = ? AND resource_id = ?');
    $check->execute([$userId, $resourceId]);

    if ($check->fetchColumn()) {
        $del = $pdo->prepare('DELETE FROM saved_resources WHERE user_id = ? AND resource_id = ?');
        $del->execute([$userId, $resourceId]);
        flash('success', 'Removed from saved resources.');
    } else {
        $exists = $pdo->prepare('SELECT 1 FROM research_resources WHERE id = ?');
        $exists->execute([$resourceId]);
        if (!$exists->fetchColumn()) {
            flash('error', 'Resource not found.');
            redirect_back('/student/repository.php');
        }

        try {
            $ins = $pdo->prepare('INSERT INTO saved_resources (user_id, resource_id) VALUES (?, ?)');
            $ins->execute([$userId, $resourceId]);
            flash('success', 'Resource saved.');
        } catch (PDOException $e) {
            // 23000 = integrity constraint violation (race on the PK) — already saved, ignore.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }
} catch (Throwable $e) {
    error_log('save-resource.php: failed for user ' . $userId . ', resource ' . $resourceId . ': ' . $e->getMessage());
    flash('error', 'Something went wrong. Please try again.');
}

redirect_back('/student/repository.php');
