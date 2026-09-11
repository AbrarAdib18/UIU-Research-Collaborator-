<?php
/**
 * Include this one file at the very top of every page:
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 * (adjust the relative path depth for files in student/).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak raw PHP/SQL errors to the browser
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/auth.php';
