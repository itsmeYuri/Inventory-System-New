<?php
/**
 * Add 'cancelled' status to orders table
 * This script modifies the status ENUM to include 'cancelled'
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Cancelled Status to Orders</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add 'Cancelled' Status to Orders Table</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Connected to database: <strong>' . mysqli_get_server_info($conn) . '</strong></div>';
            
            // Check current status column definition
            $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = 'status'");
            if (!$checkStatus) {
                throw new Exception('Error checking status column: ' . mysqli_error($conn));
            }
            
            $statusColumn = mysqli_fetch_assoc($checkStatus);
            $currentType = $statusColumn['Type'] ?? '';
            
            echo '<div class="info">Current status column type: <strong>' . htmlspecialchars($currentType) . '</strong></div>';
            
            // Check if 'cancelled' is already in the ENUM
            if (strpos($currentType, "'cancelled'") !== false) {
                echo '<div class="success">✓ Status column already includes \'cancelled\'. No changes needed.</div>';
            } else {
                echo '<div class="info">Adding \'cancelled\' to status ENUM...</div>';
                
                // Modify the ENUM to include 'cancelled'
                // The new ENUM should be: 'pending', 'shipping', 'completed', 'cancelled'
                $alterSql = "ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'shipping', 'completed', 'cancelled') DEFAULT 'pending'";
                
                if (mysqli_query($conn, $alterSql)) {
                    echo '<div class="success">✓ Successfully added \'cancelled\' status to orders table.</div>';
                    
                    // Verify the change
                    $verifyStatus = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = 'status'");
                    if ($verifyStatus) {
                        $verifyColumn = mysqli_fetch_assoc($verifyStatus);
                        $newType = $verifyColumn['Type'] ?? '';
                        echo '<div class="info">Updated status column type: <strong>' . htmlspecialchars($newType) . '</strong></div>';
                        
                        if (strpos($newType, "'cancelled'") !== false) {
                            echo '<div class="success">✓ Verification successful! The \'cancelled\' status is now available.</div>';
                        } else {
                            echo '<div class="error">⚠ Warning: Verification shows \'cancelled\' may not have been added. Please check manually.</div>';
                        }
                    }
                } else {
                    $error = mysqli_error($conn);
                    echo '<div class="error">✗ Error modifying status column: ' . htmlspecialchars($error) . '</div>';
                    
                    // Provide manual SQL if needed
                    echo '<div class="info">';
                    echo '<strong>Manual SQL to run:</strong><br>';
                    echo '<code style="background: #f4f4f4; padding: 10px; display: block; margin-top: 10px;">';
                    echo htmlspecialchars($alterSql);
                    echo '</code>';
                    echo '</div>';
                }
            }
            
        } catch (Exception $e) {
            echo '<div class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p><strong>Note:</strong> After running this script, you can cancel orders and they will be properly marked with the 'cancelled' status.</p>
            <p><a href="../pages/orders_management.html" style="color: #4CAF50; text-decoration: none;">← Back to Orders Management</a></p>
        </div>
    </div>
</body>
</html>

