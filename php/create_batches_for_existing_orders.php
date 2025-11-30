<?php
/**
 * Create Batches for Existing Orders
 * This script creates batches for orders that don't have batches yet
 */

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/order_batch_helper.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Batches for Existing Orders</title>
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
        <h1>Create Batches for Existing Orders</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            // First, ensure order_id column is nullable (fixes "Column 'order_id' cannot be null" error)
            echo '<h2>Step 1: Fixing Table Structure</h2>';
            $checkBatchesTable = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
            if ($checkBatchesTable && mysqli_num_rows($checkBatchesTable) > 0) {
                $checkOrderIdColumn = mysqli_query($conn, "SHOW COLUMNS FROM batches WHERE Field = 'order_id'");
                if ($checkOrderIdColumn) {
                    $orderIdColumn = mysqli_fetch_assoc($checkOrderIdColumn);
                    $isNullable = strtolower($orderIdColumn['Null'] ?? '') === 'yes';
                    
                    if (!$isNullable) {
                        echo '<div class="warning">⚠ Fixing order_id column to allow NULL...</div>';
                        $alterSql = "ALTER TABLE batches MODIFY COLUMN order_id INT UNSIGNED NULL DEFAULT NULL";
                        if (mysqli_query($conn, $alterSql)) {
                            echo '<div class="success">✓ Fixed order_id column to allow NULL.</div>';
                        } else {
                            echo '<div class="error">✗ Failed to fix order_id column: ' . mysqli_error($conn) . '</div>';
                            throw new Exception('Cannot proceed without fixing order_id column');
                        }
                    } else {
                        echo '<div class="success">✓ order_id column is already nullable.</div>';
                    }
                }
            }
            
            echo '<h2>Step 2: Finding Orders Without Batches</h2>';
            echo '<div class="info">Finding orders without batches...</div>';
            
            // Get unique order dates that don't have batches
            $ordersWithoutBatchesQuery = mysqli_query($conn, "
                SELECT DISTINCT DATE(o.order_date) as order_date, COUNT(*) as order_count
                FROM orders o
                WHERE NOT EXISTS (
                    SELECT 1 FROM batches b 
                    WHERE DATE(b.created_date) = DATE(o.order_date)
                )
                GROUP BY DATE(o.order_date)
                ORDER BY order_date DESC
            ");
            
            $batchesCreated = 0;
            $datesProcessed = [];
            
            if ($ordersWithoutBatchesQuery && mysqli_num_rows($ordersWithoutBatchesQuery) > 0) {
                echo '<div class="warning">Found orders without batches. Creating batches...</div>';
                
                while ($row = mysqli_fetch_assoc($ordersWithoutBatchesQuery)) {
                    $order_date = $row['order_date'];
                    $order_count = (int)$row['order_count'];
                    
                    if (in_array($order_date, $datesProcessed)) {
                        continue;
                    }
                    
                    echo '<div class="info">Processing date: ' . htmlspecialchars($order_date) . ' (' . $order_count . ' orders)</div>';
                    
                    // Create batch for this date
                    $batch_id = getOrCreateDailyBatch($conn, $order_date);
                    
                    if ($batch_id !== false && $batch_id > 0) {
                        $batchesCreated++;
                        $datesProcessed[] = $order_date;
                        echo '<div class="success">✓ Created batch ID ' . $batch_id . ' for date ' . htmlspecialchars($order_date) . '</div>';
                    } else {
                        echo '<div class="error">✗ Failed to create batch for date ' . htmlspecialchars($order_date) . '</div>';
                    }
                }
            } else {
                echo '<div class="success">✓ All order dates already have batches.</div>';
            }
            
            // Now add items from completed orders to batches
            echo '<h2>Adding Items from Completed Orders to Batches:</h2>';
            
            $completedOrdersQuery = mysqli_query($conn, "
                SELECT o.id, o.order_date, o.status
                FROM orders o
                WHERE o.status = 'completed'
                AND EXISTS (
                    SELECT 1 FROM order_items oi WHERE oi.order_id = o.id
                )
                ORDER BY o.id DESC
            ");
            
            $itemsAdded = 0;
            if ($completedOrdersQuery && mysqli_num_rows($completedOrdersQuery) > 0) {
                while ($order = mysqli_fetch_assoc($completedOrdersQuery)) {
                    $order_id = (int)$order['id'];
                    $order_date = $order['order_date'];
                    
                    // Get order items
                    $itemsQuery = mysqli_query($conn, "
                        SELECT oi.medicine_id, oi.quantity, m.expiration_date
                        FROM order_items oi
                        LEFT JOIN medicines m ON oi.medicine_id = m.id
                        WHERE oi.order_id = {$order_id}
                    ");
                    
                    $batchItems = [];
                    if ($itemsQuery) {
                        while ($item = mysqli_fetch_assoc($itemsQuery)) {
                            $batchItems[] = [
                                'medicine_id' => (int)$item['medicine_id'],
                                'quantity' => (int)$item['quantity'],
                                'expiration_date' => $item['expiration_date'],
                                'received_quantity' => (int)$item['quantity']
                            ];
                        }
                    }
                    
                    // Check if items are already in batch
                    $checkItemsQuery = mysqli_query($conn, "
                        SELECT COUNT(*) as count
                        FROM batch_items bi
                        INNER JOIN batches b ON bi.batch_id = b.id
                        WHERE DATE(b.created_date) = DATE('{$order_date}')
                        AND bi.medicine_id IN (" . implode(',', array_column($batchItems, 'medicine_id')) . ")
                    ");
                    
                    $alreadyInBatch = false;
                    if ($checkItemsQuery) {
                        $checkRow = mysqli_fetch_assoc($checkItemsQuery);
                        if ((int)$checkRow['count'] > 0) {
                            $alreadyInBatch = true;
                        }
                    }
                    
                    if (!$alreadyInBatch && !empty($batchItems)) {
                        $result = addOrderItemsToBatch($conn, $order_id, $order_date, $batchItems);
                        if ($result) {
                            $itemsAdded++;
                            echo '<div class="success">✓ Added items from order ' . $order_id . ' to batch</div>';
                        } else {
                            echo '<div class="error">✗ Failed to add items from order ' . $order_id . '</div>';
                        }
                    } else {
                        echo '<div class="info">Order ' . $order_id . ' items already in batch or no items</div>';
                    }
                }
            }
            
            echo '<div class="success" style="margin-top: 20px;">';
            echo '<strong>Summary:</strong><br>';
            echo 'Batches created: ' . $batchesCreated . '<br>';
            echo 'Orders processed for items: ' . $itemsAdded . '<br>';
            echo '</div>';
            
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
                <li>Finds all order dates that don't have batches</li>
                <li>Creates batches for those dates</li>
                <li>Adds items from completed orders to their respective batches</li>
                <li>Skips items that are already in batches</li>
            </ul>
        </div>
    </div>
</body>
</html>

