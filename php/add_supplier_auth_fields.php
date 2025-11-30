<?php
/**
 * Migration Script: Add Authentication Fields to Suppliers Table
 * Adds username, password_hash, and status fields for supplier login
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "<h2>Adding Authentication Fields to Suppliers Table</h2>\n";
echo "<pre>\n";

try {
    // Check if username column exists
    $checkUsername = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'username'");
    if (!$checkUsername || mysqli_num_rows($checkUsername) == 0) {
        $sql = "ALTER TABLE suppliers ADD COLUMN username VARCHAR(100) NULL UNIQUE AFTER email";
        if (mysqli_query($conn, $sql)) {
            echo "✓ Added 'username' column\n";
        } else {
            echo "✗ Error adding 'username' column: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ 'username' column already exists\n";
    }

    // Check if password_hash column exists
    $checkPassword = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'password_hash'");
    if (!$checkPassword || mysqli_num_rows($checkPassword) == 0) {
        $sql = "ALTER TABLE suppliers ADD COLUMN password_hash VARCHAR(255) NULL AFTER username";
        if (mysqli_query($conn, $sql)) {
            echo "✓ Added 'password_hash' column\n";
        } else {
            echo "✗ Error adding 'password_hash' column: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ 'password_hash' column already exists\n";
    }

    // Check if status column exists
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'status'");
    if (!$checkStatus || mysqli_num_rows($checkStatus) == 0) {
        $sql = "ALTER TABLE suppliers ADD COLUMN status ENUM('active', 'inactive', 'locked') DEFAULT 'active' AFTER password_hash";
        if (mysqli_query($conn, $sql)) {
            echo "✓ Added 'status' column\n";
        } else {
            echo "✗ Error adding 'status' column: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ 'status' column already exists\n";
    }

    // Add index on username for faster lookups
    $checkIndex = mysqli_query($conn, "SHOW INDEX FROM suppliers WHERE Key_name = 'idx_username'");
    if (!$checkIndex || mysqli_num_rows($checkIndex) == 0) {
        $sql = "CREATE INDEX idx_username ON suppliers(username)";
        if (mysqli_query($conn, $sql)) {
            echo "✓ Added index on 'username' column\n";
        } else {
            echo "⚠ Could not add index on 'username' (may already exist): " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ Index on 'username' already exists\n";
    }

    echo "\n✅ Migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Set username and password for existing suppliers\n";
    echo "2. Access supplier login at: pages/supplier_login.html\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>

