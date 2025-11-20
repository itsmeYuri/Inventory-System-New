<?php
/**
 * Database Connection File
 * MySQL connection for OTP-based password reset system
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'inventory_system_db';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    $error_msg = mysqli_connect_error();
    error_log("Database connection failed: " . $error_msg);
    die("Database connection failed. Please try again later.");
}

// Set charset to UTF-8
mysqli_set_charset($conn, 'utf8mb4');
?>

