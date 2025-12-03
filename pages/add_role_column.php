<?php
// Script to add 'role' column to users table if it doesn't exist

require_once __DIR__ . '/../php/conn.php';

if (!isset($conn) || !$conn) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

// Check if role column exists
$result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");

if (!$result || mysqli_num_rows($result) == 0) {
    // Column doesn't exist, add it
    $alter_query = "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'employee' AFTER status";
    
    if (mysqli_query($conn, $alter_query)) {
        echo "✓ Role column added successfully to users table\n";
    } else {
        echo "✗ Error adding role column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ Role column already exists in users table\n";
}

mysqli_close($conn);
?>
