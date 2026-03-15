<?php
/**
 * PDO database connection.
 *
 * Connects via TCP to MySQL — works identically on localhost and GCE VM.
 * All queries throughout the app MUST use prepared statements via this $db handle.
 *
 * Usage:
 *   require_once __DIR__ . '/../config/database.php';
 *   $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
 *   $stmt->execute([':id' => $userId]);
 */

require_once __DIR__ . '/constants.php';
/** @var PDO|null $db */
$db = null;

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_NAME
    );

    $db = new PDO($dsn, DB_USER, DB_PASS, [
        // Throw exceptions on errors (never fail silently)
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        // Return associative arrays by default
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Use real prepared statements, not emulated (SQL injection protection)
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    $db = null;
    // Only halt for web requests, not CLI (test runner)
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        die('A database error occurred. Please try again later.');
    }
}