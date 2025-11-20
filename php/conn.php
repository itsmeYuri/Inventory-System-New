<?php
// Turn off error display, but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Inventory_system_db';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    $error_msg = mysqli_connect_error();
    error_log("Database connection failed: " . $error_msg);
    
    // Only output JSON if headers haven't been sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    // Try to clean output buffer if it exists
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $error_msg
    ], JSON_UNESCAPED_UNICODE));
}

mysqli_set_charset($conn, 'utf8mb4');
?>