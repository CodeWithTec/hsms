<?php
// =====================================================================
// DATABASE CONNECTION (PDO)
// =====================================================================

require_once __DIR__ . '/constants.php';

$DB_HOST = 'localhost';
$DB_NAME = 'hsms_db';
$DB_USER = 'root';        // change if different
$DB_PASS = '';            // XAMPP default is empty
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log this — do not echo.
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}