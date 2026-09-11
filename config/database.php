<?php
/**
 * PDO database connection for UIU ResearchCollab.
 * Defaults match a stock XAMPP install. Edit the constants below if your
 * local MySQL credentials differ.
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'uiu_researchcollab');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Service temporarily unavailable. Please try again later.');
        }
    }

    return $pdo;
}
