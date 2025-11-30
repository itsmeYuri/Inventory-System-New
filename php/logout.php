<?php
/**
 * User Logout System
 * Sets user status to 'offline' and destroys session
 */

session_start();
include __DIR__ . '/conn.php';

// Update user or supplier status to 'offline' before logging out
if (isset($conn) && $conn) {
    // Handle user logout
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] !== 'supplier') {
        $userId = $_SESSION['user_id'];
        
        // Check if status column exists
        $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
        if (mysqli_num_rows($checkStatus) > 0) {
            // Update status to offline (don't change if account is locked)
            $query = "UPDATE users SET status = 'offline' WHERE user_id = ? AND status != 'locked'";
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Handle supplier logout
    if (isset($_SESSION['supplier_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'supplier')) {
        $supplierId = $_SESSION['supplier_id'] ?? null;
        
        if ($supplierId) {
            // Check if suppliers table has status column
            $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'status'");
            if (mysqli_num_rows($checkStatus) > 0) {
                // Update status to inactive (don't change if account is locked)
                $query = "UPDATE suppliers SET status = 'inactive' WHERE id = ? AND status != 'locked'";
                $stmt = mysqli_prepare($conn, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $supplierId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: ../pages/login.html");
exit;
?>