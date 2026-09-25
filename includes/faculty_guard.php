<?php
/**
 * Include right after bootstrap.php on every page under /faculty/.
 * Guarantees a logged-in faculty member and exposes $currentUser / $facultyProfile.
 */

require_faculty();

$currentUser = current_user();
if (!$currentUser) {
    redirect('/login.php');
}

$facultyProfile = get_faculty_profile(db(), (int)$currentUser['id']);
if (!$facultyProfile) {
    // Faculty accounts are provisioned with a profile already; this should
    // never happen, but guard anyway rather than let pages fatal on null.
    flash('error', 'Your faculty profile could not be loaded.');
    do_logout();
    redirect('/login.php');
}
