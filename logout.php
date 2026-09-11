<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in() && verify_csrf()) {
    log_activity(db(), (int)current_user_id(), 'logout', 'Logged out');
}

do_logout();

session_start();
flash('success', 'You have been logged out.');
redirect('/index.php');
