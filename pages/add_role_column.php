<?php
// Script to add 'role' column to users table if it doesn't exist

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'Inventory_system_db';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Check if role column exists
$result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'role'");

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    $alter_query = "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user' AFTER status";
    
    if ($mysqli->query($alter_query)) {
        echo "✓ Role column added successfully to users table\n";
    } else {
        echo "✗ Error adding role column: " . $mysqli->error . "\n";
    }
} else {
    echo "✓ Role column already exists in users table\n";
}

$mysqli->close();
?>
