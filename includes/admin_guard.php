<?php
/**
 * Include right after bootstrap.php on every page under /admin/.
 * Guarantees a logged-in admin and exposes $currentUser.
 */

require_admin();

$currentUser = current_user();
if (!$currentUser) {
    redirect('/login.php');
}
