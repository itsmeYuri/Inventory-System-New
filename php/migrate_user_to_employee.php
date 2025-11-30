<?php
/**
 * Migration Script: Change 'user' role to 'employee' in users table
 * 
 * This script updates all users with role 'user' to 'employee' in the database.
 * Run this script once to migrate existing data.
 * 
 * Access via browser: http://localhost/php/migrate_user_to_employee.php
 * Or run via command line: php php/migrate_user_to_employee.php
 */

// Set content type for web browser
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/conn.php';

try {
    echo "=== User Role Migration Script ===\n";
    echo "Changing 'user' role to 'employee' in users table...\n\n";
    
    // Check if role column exists
    $checkRole = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
    if (mysqli_num_rows($checkRole) === 0) {
        echo "Role column does not exist. Creating it with default value 'employee'...\n";
        $alterQuery = "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'employee'";
        if (mysqli_query($conn, $alterQuery)) {
            echo "✓ Role column created successfully\n";
        } else {
            throw new Exception("Error creating role column: " . mysqli_error($conn));
        }
    } else {
        echo "Role column exists. Checking for users with 'user' role...\n";
        
        // Count users with 'user' role before update
        $countQuery = "SELECT COUNT(*) AS count FROM users WHERE role = 'user'";
        $countResult = mysqli_query($conn, $countQuery);
        $countRow = mysqli_fetch_assoc($countResult);
        $userCount = (int)$countRow['count'];
        
        if ($userCount > 0) {
            echo "Found {$userCount} user(s) with 'user' role. Updating to 'employee'...\n";
            
            // Update all 'user' roles to 'employee'
            $updateQuery = "UPDATE users SET role = 'employee' WHERE role = 'user'";
            if (mysqli_query($conn, $updateQuery)) {
                $affectedRows = mysqli_affected_rows($conn);
                echo "✓ Successfully updated {$affectedRows} user(s) from 'user' to 'employee' role\n";
            } else {
                throw new Exception("Error updating roles: " . mysqli_error($conn));
            }
        } else {
            echo "No users found with 'user' role. All users already have correct roles.\n";
        }
        
        // Also update the default value for the column
        $alterDefaultQuery = "ALTER TABLE users MODIFY COLUMN role VARCHAR(20) DEFAULT 'employee'";
        if (mysqli_query($conn, $alterDefaultQuery)) {
            echo "✓ Updated default role value to 'employee'\n";
        } else {
            echo "⚠ Warning: Could not update default value (this is okay if column doesn't support DEFAULT): " . mysqli_error($conn) . "\n";
        }
    }
    
    // Show summary
    echo "\n=== Migration Summary ===\n";
    $summaryQuery = "SELECT 
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) AS employee_count,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin_count,
        SUM(CASE WHEN role = 'supplier' THEN 1 ELSE 0 END) AS supplier_count,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) AS old_user_count
    FROM users";
    $summaryResult = mysqli_query($conn, $summaryQuery);
    if ($summaryResult) {
        $summary = mysqli_fetch_assoc($summaryResult);
        echo "Total users: " . $summary['total_users'] . "\n";
        echo "Employees: " . $summary['employee_count'] . "\n";
        echo "Admins: " . $summary['admin_count'] . "\n";
        echo "Suppliers: " . $summary['supplier_count'] . "\n";
        if ($summary['old_user_count'] > 0) {
            echo "⚠ Warning: " . $summary['old_user_count'] . " user(s) still have 'user' role\n";
        }
    }
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit(1);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}

?>

