<?php
/**
 * Add Missing Columns Script
 * Adds total_amount to orders table and supplier_id to medicines table if missing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Missing Columns</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #bee5eb;
        }
        a {
            color: #4CAF50;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Missing Database Columns</h1>
        
        <?php
        try {
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Connected to database: <strong>' . mysqli_get_server_info($conn) . '</strong></div>';
            
            // Check and add total_amount to orders table
            $checkTotalAmount = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'total_amount'");
            if (mysqli_num_rows($checkTotalAmount) === 0) {
                echo '<div class="info">Adding total_amount column to orders table...</div>';
                $alterSql = "ALTER TABLE orders ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total order amount' AFTER status";
                if (mysqli_query($conn, $alterSql)) {
                    echo '<div class="success">✓ total_amount column added to orders table.</div>';
                    
                    // Update existing orders with calculated totals
                    $updateSql = "UPDATE orders o 
                                  SET o.total_amount = (
                                      SELECT COALESCE(SUM(oi.quantity * oi.price), 0)
                                      FROM order_items oi
                                      WHERE oi.order_id = o.id
                                  )";
                    if (mysqli_query($conn, $updateSql)) {
                        echo '<div class="success">✓ Updated existing orders with calculated totals.</div>';
                    }
                } else {
                    echo '<div class="error">✗ Error adding total_amount: ' . mysqli_error($conn) . '</div>';
                }
            } else {
                echo '<div class="info">✓ orders table already has total_amount column.</div>';
            }
            
            // Check and add notes to orders table
            $checkNotes = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'notes'");
            if (mysqli_num_rows($checkNotes) === 0) {
                echo '<div class="info">Adding notes column to orders table...</div>';
                $alterSql = "ALTER TABLE orders ADD COLUMN notes TEXT NULL COMMENT 'Additional notes about the order' AFTER total_amount";
                if (mysqli_query($conn, $alterSql)) {
                    echo '<div class="success">✓ notes column added to orders table.</div>';
                } else {
                    echo '<div class="error">✗ Error adding notes: ' . mysqli_error($conn) . '</div>';
                }
            } else {
                echo '<div class="info">✓ orders table already has notes column.</div>';
            }
            
            // Check and add supplier_id to medicines table
            $checkSupplierId = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'supplier_id'");
            if (mysqli_num_rows($checkSupplierId) === 0) {
                echo '<div class="info">Adding supplier_id column to medicines table...</div>';
                $alterSql = "ALTER TABLE medicines 
                             ADD COLUMN supplier_id INT NULL AFTER batch_number,
                             ADD INDEX idx_supplier (supplier_id)";
                if (mysqli_query($conn, $alterSql)) {
                    echo '<div class="success">✓ supplier_id column added to medicines table.</div>';
                    
                    // Add foreign key if possible
                    $fkSql = "ALTER TABLE medicines 
                              ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL";
                    if (mysqli_query($conn, $fkSql)) {
                        echo '<div class="success">✓ Foreign key constraint added.</div>';
                    } else {
                        echo '<div class="info">Note: Foreign key could not be added (may already exist or table has data conflicts).</div>';
                    }
                } else {
                    echo '<div class="error">✗ Error adding supplier_id: ' . mysqli_error($conn) . '</div>';
                }
            } else {
                echo '<div class="info">✓ medicines table already has supplier_id column.</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        } catch (Error $e) {
            echo '<div class="error">';
            echo '<strong>✗ Fatal Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p><a href="../pages/orders_management.html">Go to Orders Management Page</a></p>
            <p><a href="test_connection.php">Test Database Connection</a></p>
        </div>
    </div>
</body>
</html>

