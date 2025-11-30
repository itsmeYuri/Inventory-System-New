<?php
/**
 * Check and Fix Batches Table
 * This script checks if batches table exists and creates it if needed
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check and Fix Batches Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Check and Fix Batches Table</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            // Check if batches table exists
            $checkBatchesTable = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
            if (!$checkBatchesTable || mysqli_num_rows($checkBatchesTable) === 0) {
                echo '<div class="warning">⚠ Batches table does not exist. Creating it...</div>';
                
                $createBatchesTable = "CREATE TABLE IF NOT EXISTS batches (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    batch_number VARCHAR(50) NOT NULL UNIQUE,
                    order_id INT UNSIGNED NULL DEFAULT NULL,
                    supplier_id INT UNSIGNED NOT NULL,
                    created_date DATE NOT NULL,
                    status ENUM('active', 'expired', 'completed') DEFAULT 'active',
                    notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_batch_number (batch_number),
                    INDEX idx_order_id (order_id),
                    INDEX idx_supplier_id (supplier_id),
                    INDEX idx_created_date (created_date),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1";
                
                if (mysqli_query($conn, $createBatchesTable)) {
                    echo '<div class="success">✓ Batches table created successfully.</div>';
                } else {
                    throw new Exception('Failed to create batches table: ' . mysqli_error($conn));
                }
            } else {
                echo '<div class="success">✓ Batches table exists.</div>';
            }
            
            // Check if batch_items table exists
            $checkBatchItemsTable = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
            if (!$checkBatchItemsTable || mysqli_num_rows($checkBatchItemsTable) === 0) {
                echo '<div class="warning">⚠ Batch_items table does not exist. Creating it...</div>';
                
                $createBatchItemsTable = "CREATE TABLE IF NOT EXISTS batch_items (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    batch_id INT UNSIGNED NOT NULL,
                    medicine_id INT UNSIGNED NOT NULL,
                    quantity INT UNSIGNED NOT NULL DEFAULT 0,
                    expiration_date DATE NULL,
                    received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
                    is_expired TINYINT(1) DEFAULT 0,
                    expired_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_batch_id (batch_id),
                    INDEX idx_medicine_id (medicine_id),
                    INDEX idx_expiration_date (expiration_date),
                    INDEX idx_is_expired (is_expired),
                    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
                    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1";
                
                if (mysqli_query($conn, $createBatchItemsTable)) {
                    echo '<div class="success">✓ Batch_items table created successfully.</div>';
                } else {
                    // Try without foreign keys if they fail
                    $createBatchItemsTableNoFK = "CREATE TABLE IF NOT EXISTS batch_items (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        batch_id INT UNSIGNED NOT NULL,
                        medicine_id INT UNSIGNED NOT NULL,
                        quantity INT UNSIGNED NOT NULL DEFAULT 0,
                        expiration_date DATE NULL,
                        received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
                        is_expired TINYINT(1) DEFAULT 0,
                        expired_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_batch_id (batch_id),
                        INDEX idx_medicine_id (medicine_id),
                        INDEX idx_expiration_date (expiration_date),
                        INDEX idx_is_expired (is_expired)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1";
                    
                    if (mysqli_query($conn, $createBatchItemsTableNoFK)) {
                        echo '<div class="success">✓ Batch_items table created successfully (without foreign keys).</div>';
                    } else {
                        throw new Exception('Failed to create batch_items table: ' . mysqli_error($conn));
                    }
                }
            } else {
                echo '<div class="success">✓ Batch_items table exists.</div>';
            }
            
            // Check and fix order_id column to allow NULL
            echo '<h2>Checking and Fixing Table Structure:</h2>';
            $checkOrderIdColumn = mysqli_query($conn, "SHOW COLUMNS FROM batches WHERE Field = 'order_id'");
            if ($checkOrderIdColumn) {
                $orderIdColumn = mysqli_fetch_assoc($checkOrderIdColumn);
                $isNullable = strtolower($orderIdColumn['Null'] ?? '') === 'yes';
                
                if (!$isNullable) {
                    echo '<div class="warning">⚠ order_id column is NOT NULL. Making it nullable...</div>';
                    $alterSql = "ALTER TABLE batches MODIFY COLUMN order_id INT UNSIGNED NULL DEFAULT NULL";
                    if (mysqli_query($conn, $alterSql)) {
                        echo '<div class="success">✓ Made order_id column nullable.</div>';
                    } else {
                        echo '<div class="error">✗ Failed to make order_id nullable: ' . mysqli_error($conn) . '</div>';
                    }
                } else {
                    echo '<div class="success">✓ order_id column is already nullable.</div>';
                }
            }
            
            // Fix AUTO_INCREMENT issues
            echo '<h2>Fixing AUTO_INCREMENT Issues:</h2>';
            
            // Fix batches table
            @mysqli_query($conn, "DELETE FROM batches WHERE id = 0");
            $maxBatchIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batches");
            $maxBatchId = 0;
            if ($maxBatchIdQuery) {
                $maxBatchRow = mysqli_fetch_assoc($maxBatchIdQuery);
                $maxBatchId = (int)($maxBatchRow['max_id'] ?? 0);
            }
            $nextBatchId = max(1, $maxBatchId + 1);
            if (mysqli_query($conn, "ALTER TABLE batches AUTO_INCREMENT = {$nextBatchId}")) {
                echo '<div class="success">✓ Fixed batches AUTO_INCREMENT to ' . $nextBatchId . '</div>';
            }
            
            // Fix batch_items table
            @mysqli_query($conn, "DELETE FROM batch_items WHERE id = 0");
            $maxBatchItemIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batch_items");
            $maxBatchItemId = 0;
            if ($maxBatchItemIdQuery) {
                $maxBatchItemRow = mysqli_fetch_assoc($maxBatchItemIdQuery);
                $maxBatchItemId = (int)($maxBatchItemRow['max_id'] ?? 0);
            }
            $nextBatchItemId = max(1, $maxBatchItemId + 1);
            if (mysqli_query($conn, "ALTER TABLE batch_items AUTO_INCREMENT = {$nextBatchItemId}")) {
                echo '<div class="success">✓ Fixed batch_items AUTO_INCREMENT to ' . $nextBatchItemId . '</div>';
            }
            
            // Show current batch count
            $batchesCountQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM batches");
            $batchesCount = 0;
            if ($batchesCountQuery) {
                $batchesCountRow = mysqli_fetch_assoc($batchesCountQuery);
                $batchesCount = (int)($batchesCountRow['count'] ?? 0);
            }
            echo '<div class="info">Current batches in database: ' . $batchesCount . '</div>';
            
            echo '<div class="success" style="margin-top: 20px;">✓ Table check and fix completed!</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h2>Next Steps:</h2>
            <ol>
                <li>Run this script to ensure tables exist: <code>php/check_and_fix_batches.php</code></li>
                <li>Run this script to create batches for existing orders: <code>php/create_batches_for_existing_orders.php</code></li>
                <li>Create a new order to test batch creation</li>
                <li>Confirm an order to test adding items to batches</li>
            </ol>
        </div>
    </div>
</body>
</html>

