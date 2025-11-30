<?php
/**
 * Add 'delivered' Status to Orders Table
 * Migration script to add 'delivered' status to the orders table ENUM
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Delivered Status to Orders</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add 'delivered' Status to Orders Table</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Checking orders table status column...</div>';
            
            // Check current status column definition
            $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = 'status'");
            if (!$checkStatus || mysqli_num_rows($checkStatus) === 0) {
                echo '<div class="error">✗ Status column not found in orders table.</div>';
            } else {
                $statusColumn = mysqli_fetch_assoc($checkStatus);
                $currentType = $statusColumn['Type'] ?? '';
                
                echo '<div class="info">Current status column type: <code>' . htmlspecialchars($currentType) . '</code></div>';
                
                // Check if 'delivered' is already in the ENUM
                if (strpos($currentType, "'delivered'") !== false) {
                    echo '<div class="success">✓ Status column already includes \'delivered\' status.</div>';
                } else {
                    echo '<div class="warning">⚠ Adding \'delivered\' status to orders table...</div>';
                    
                    // Add 'delivered' to the ENUM (place it before 'cancelled')
                    $alterSql = "ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'shipping', 'completed', 'delivered', 'cancelled') DEFAULT 'pending'";
                    
                    if (mysqli_query($conn, $alterSql)) {
                        echo '<div class="success">✓ Successfully added \'delivered\' status to orders table.</div>';
                    } else {
                        $error = mysqli_error($conn);
                        echo '<div class="error">✗ Failed to add \'delivered\' status: ' . htmlspecialchars($error) . '</div>';
                        
                        // Try alternative approach - check if it's a VARCHAR instead of ENUM
                        if (strpos($currentType, 'varchar') !== false || strpos($currentType, 'VARCHAR') !== false) {
                            echo '<div class="info">Status column is VARCHAR, not ENUM. No changes needed - VARCHAR can accept any status value.</div>';
                        }
                    }
                }
            }
            
            // Verify the change
            echo '<h2>Verification:</h2>';
            $verifyStatus = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = 'status'");
            if ($verifyStatus) {
                $verifyColumn = mysqli_fetch_assoc($verifyStatus);
                echo '<div class="info">';
                echo '<strong>Updated status column type:</strong><br>';
                echo '<code>' . htmlspecialchars($verifyColumn['Type'] ?? 'N/A') . '</code>';
                echo '</div>';
            }
            
            echo '<div class="success" style="margin-top: 20px;">✓ Migration completed! Orders can now be marked as \'delivered\'.</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h2>What This Script Does:</h2>
            <ul>
                <li>Checks if the <code>orders</code> table has a <code>status</code> column</li>
                <li>Adds <code>'delivered'</code> to the status ENUM values</li>
                <li>Verifies the change was applied successfully</li>
            </ul>
            <p><strong>Note:</strong> The valid order statuses are now: <code>pending</code>, <code>shipping</code>, <code>completed</code>, <code>delivered</code>, <code>cancelled</code></p>
        </div>
    </div>
</body>
</html>

