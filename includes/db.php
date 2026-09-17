<?php
/**
 * includes/db.php
 *
 * Role in system: opens the single shared mysqli database connection
 * ($conn) used by every customer, kitchen and admin script. Required
 * once, near the top, by any file that touches the database.
 *
 * Configuration surface: the 4 DB_* constants below are the only
 * environment-specific values in the whole codebase (no .env file,
 * no config framework - this is it).
 */

// ==========================================================
// Database connection - edit these 4 constants only.
// ==========================================================

// Use Render environment variables in production, fall back to local XAMPP defaults:
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'gorilla_cafe');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Rwanda runs on Central Africa Time (CAT, UTC+2, no daylight saving).
// Pinning both PHP and the MySQL session to it keeps order_time / NOW() /
// elapsed-time calculations in agreement.
date_default_timezone_set('Africa/Kigali');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+02:00'");
} catch (mysqli_sql_exception $e) {
    // Fail loudly but without leaking credentials to the browser.
    http_response_code(500);
    die('Database connection failed. Please check includes/db.php settings. Debug: ' . $e->getMessage());
}
