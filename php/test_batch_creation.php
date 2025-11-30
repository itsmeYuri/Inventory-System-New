<?php
/**
 * Test Batch Creation
 * This script tests if batches are being created correctly
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Batch Creation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Batch Creation</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Checking database tables and batch creation...</div>';
            
            // Check if batches table exists
            $checkBatchesTable = mysqli_query($conn, "SHOW TABLES LIKE 'batches'");
            if (!$checkBatchesTable || mysqli_num_rows($checkBatchesTable) === 0) {
                echo '<div class="error">✗ Batches table does not exist. Please create it first.</div>';
            } else {
                echo '<div class="success">✓ Batches table exists.</div>';
            }
            
            // Check if batch_items table exists
            $checkBatchItemsTable = mysqli_query($conn, "SHOW TABLES LIKE 'batch_items'");
            if (!$checkBatchItemsTable || mysqli_num_rows($checkBatchItemsTable) === 0) {
                echo '<div class="error">✗ Batch_items table does not exist. Please create it first.</div>';
            } else {
                echo '<div class="success">✓ Batch_items table exists.</div>';
            }
            
            // Check if orders table exists and has orders
            $checkOrdersTable = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
            if (!$checkOrdersTable || mysqli_num_rows($checkOrdersTable) === 0) {
                echo '<div class="error">✗ Orders table does not exist.</div>';
            } else {
                echo '<div class="success">✓ Orders table exists.</div>';
                
                // Count orders
                $ordersCountQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders");
                $ordersCount = 0;
                if ($ordersCountQuery) {
                    $ordersCountRow = mysqli_fetch_assoc($ordersCountQuery);
                    $ordersCount = (int)($ordersCountRow['count'] ?? 0);
                }
                echo '<div class="info">Total orders in database: ' . $ordersCount . '</div>';
                
                // Show recent orders
                if ($ordersCount > 0) {
                    echo '<h2>Recent Orders:</h2>';
                    $recentOrdersQuery = mysqli_query($conn, "SELECT id, supplier_id, order_date, status, created_at FROM orders ORDER BY id DESC LIMIT 10");
                    if ($recentOrdersQuery) {
                        echo '<table>';
                        echo '<tr><th>Order ID</th><th>Supplier ID</th><th>Order Date</th><th>Status</th><th>Created At</th></tr>';
                        while ($order = mysqli_fetch_assoc($recentOrdersQuery)) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($order['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($order['supplier_id']) . '</td>';
                            echo '<td>' . htmlspecialchars($order['order_date']) . '</td>';
                            echo '<td>' . htmlspecialchars($order['status']) . '</td>';
                            echo '<td>' . htmlspecialchars($order['created_at']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                }
            }
            
            // Check batches
            $batchesCountQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM batches");
            $batchesCount = 0;
            if ($batchesCountQuery) {
                $batchesCountRow = mysqli_fetch_assoc($batchesCountQuery);
                $batchesCount = (int)($batchesCountRow['count'] ?? 0);
            }
            echo '<div class="info">Total batches in database: ' . $batchesCount . '</div>';
            
            if ($batchesCount > 0) {
                echo '<h2>Existing Batches:</h2>';
                $batchesQuery = mysqli_query($conn, "SELECT id, batch_number, order_id, supplier_id, created_date, status FROM batches ORDER BY id DESC LIMIT 10");
                if ($batchesQuery) {
                    echo '<table>';
                    echo '<tr><th>Batch ID</th><th>Batch Number</th><th>Order ID</th><th>Supplier ID</th><th>Created Date</th><th>Status</th></tr>';
                    while ($batch = mysqli_fetch_assoc($batchesQuery)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($batch['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($batch['batch_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($batch['order_id'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($batch['supplier_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($batch['created_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($batch['status']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            } else {
                echo '<div class="warning">⚠ No batches found in database.</div>';
            }
            
            // Check batch_items
            $batchItemsCountQuery = mysqli_query($conn, "SELECT COUNT(*) as count FROM batch_items");
            $batchItemsCount = 0;
            if ($batchItemsCountQuery) {
                $batchItemsCountRow = mysqli_fetch_assoc($batchItemsCountQuery);
                $batchItemsCount = (int)($batchItemsCountRow['count'] ?? 0);
            }
            echo '<div class="info">Total batch items in database: ' . $batchItemsCount . '</div>';
            
            // Test batch creation function
            echo '<h2>Testing Batch Creation Function:</h2>';
            require_once __DIR__ . '/order_batch_helper.php';
            
            if (function_exists('getOrCreateDailyBatch')) {
                echo '<div class="success">✓ getOrCreateDailyBatch function exists.</div>';
                
                // Test with today's date
                $testDate = date('Y-m-d');
                echo '<div class="info">Testing batch creation for date: ' . $testDate . '</div>';
                
                try {
                    $testBatchId = getOrCreateDailyBatch($conn, $testDate);
                    if ($testBatchId !== false && $testBatchId > 0) {
                        echo '<div class="success">✓ Batch created/retrieved successfully. Batch ID: ' . $testBatchId . '</div>';
                        
                        // Check the batch
                        $checkBatchQuery = mysqli_query($conn, "SELECT * FROM batches WHERE id = {$testBatchId}");
                        if ($checkBatchQuery && mysqli_num_rows($checkBatchQuery) > 0) {
                            $batchData = mysqli_fetch_assoc($checkBatchQuery);
                            echo '<div class="info">';
                            echo '<strong>Batch Details:</strong><br>';
                            echo 'Batch Number: ' . htmlspecialchars($batchData['batch_number']) . '<br>';
                            echo 'Supplier ID: ' . htmlspecialchars($batchData['supplier_id']) . '<br>';
                            echo 'Created Date: ' . htmlspecialchars($batchData['created_date']) . '<br>';
                            echo 'Status: ' . htmlspecialchars($batchData['status']) . '<br>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="error">✗ Failed to create/get batch. Returned: ' . var_export($testBatchId, true) . '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">✗ Exception: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                echo '<div class="error">✗ getOrCreateDailyBatch function not found.</div>';
            }
            
            // Check for orders without batches
            if ($ordersCount > 0) {
                echo '<h2>Orders Without Batches:</h2>';
                $ordersWithoutBatchesQuery = mysqli_query($conn, "
                    SELECT o.id, o.order_date, o.status, o.supplier_id
                    FROM orders o
                    WHERE NOT EXISTS (
                        SELECT 1 FROM batches b 
                        WHERE DATE(b.created_date) = DATE(o.order_date)
                    )
                    ORDER BY o.id DESC
                    LIMIT 10
                ");
                
                if ($ordersWithoutBatchesQuery && mysqli_num_rows($ordersWithoutBatchesQuery) > 0) {
                    echo '<div class="warning">⚠ Found orders without batches. These should have batches created.</div>';
                    echo '<table>';
                    echo '<tr><th>Order ID</th><th>Order Date</th><th>Status</th><th>Supplier ID</th><th>Action</th></tr>';
                    while ($order = mysqli_fetch_assoc($ordersWithoutBatchesQuery)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($order['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($order['order_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($order['status']) . '</td>';
                        echo '<td>' . htmlspecialchars($order['supplier_id']) . '</td>';
                        echo '<td><button onclick="createBatchForOrder(' . $order['id'] . ')">Create Batch</button></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="success">✓ All orders have batches (or no orders found).</div>';
                }
            }
            
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
                <li>Checks if batches and batch_items tables exist</li>
                <li>Shows count of orders and batches</li>
                <li>Displays recent orders and batches</li>
                <li>Tests the batch creation function</li>
                <li>Identifies orders without batches</li>
            </ul>
        </div>
    </div>
    
    <script>
        function createBatchForOrder(orderId) {
            if (confirm('Create batch for order ' + orderId + '?')) {
                // This would need a PHP endpoint to create batches for existing orders
                alert('This feature needs to be implemented. Please create a new order to test batch creation.');
            }
        }
    </script>
</body>
</html>

