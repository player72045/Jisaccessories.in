<?php
/**
 * includes/db.php
 * Database connection + session bootstrap.
 * Every page that needs DB or auth includes this FIRST, before any output.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Connection settings ─────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jisaccessories');

// Base path auto-detect (mirrors assets/js/main.js logic) so PHP-generated
// links work identically on localhost (/jis/...) and on a VPS deployed to root (/).
define('BASE_PATH', (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/jisaccessories') === 0) ? '/jisaccessories' : '');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:40px;background:#0a0a0f;color:#f0f0f5;">
        <h2 style="color:#f59e0b;">Database connection failed</h2>
        <p>Please make sure MySQL is running and the <code>jisaccessories</code> database has been imported from <code>database_setup.sql</code>.</p>
        </div>');
}
$conn->set_charset('utf8mb4');
