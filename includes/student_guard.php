<?php
/**
 * Include right after bootstrap.php on every page under /student/.
 * Guarantees a logged-in student and exposes $currentUser / $studentProfile.
 */

require_student();

$currentUser = current_user();
if (!$currentUser) {
    redirect('/login.php');
}

$studentProfile = get_student_profile(db(), (int)$currentUser['id']);
if (!$studentProfile) {
    // Should never happen (signup always creates one), but guard anyway.
    flash('error', 'Your student profile could not be loaded.');
    do_logout();
    redirect('/login.php');
}
