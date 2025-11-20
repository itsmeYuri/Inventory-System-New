<?php
/**
 * Create Orders Tables Script
 * Run this script once to create the orders and order_items tables
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
    <title>Create Orders Tables</title>
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
        <h1>Create Orders Tables</h1>
        
        <?php
        try {
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Connected to database: <strong>' . mysqli_get_server_info($conn) . '</strong></div>';
            
            // Read SQL file
            $sqlFile = __DIR__ . '/create_orders_tables.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception('SQL file not found: ' . $sqlFile);
            }
            
            $sql = file_get_contents($sqlFile);
            
            // Check if tables already exist
            $checkOrders = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
            $checkOrderItems = mysqli_query($conn, "SHOW TABLES LIKE 'order_items'");
            
            if (mysqli_num_rows($checkOrders) > 0 && mysqli_num_rows($checkOrderItems) > 0) {
                echo '<div class="info">';
                echo '<strong>Tables already exist!</strong><br>';
                echo 'The orders and order_items tables already exist in the database.';
                echo '</div>';
            } else {
                // Execute SQL to create tables
                echo '<div class="info">Creating orders tables...</div>';
                
                // Split SQL by semicolons and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (empty($statement) || strpos($statement, '--') === 0) {
                        continue;
                    }
                    
                    if (!mysqli_query($conn, $statement)) {
                        throw new Exception('Error executing SQL: ' . mysqli_error($conn));
                    }
                }
                
                echo '<div class="success">';
                echo '<strong>✓ Success!</strong><br>';
                echo 'The orders and order_items tables have been created successfully.';
                echo '</div>';
            }
            
            // Check if medicines table has supplier_id column
            $checkSupplierId = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'supplier_id'");
            if (mysqli_num_rows($checkSupplierId) === 0) {
                echo '<div class="info">';
                echo '<strong>Adding supplier_id column to medicines table...</strong><br>';
                $alterSql = "ALTER TABLE medicines ADD COLUMN supplier_id INT NULL AFTER batch_number, ADD INDEX idx_supplier (supplier_id), ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL";
                if (mysqli_query($conn, $alterSql)) {
                    echo '✓ supplier_id column added successfully.';
                } else {
                    echo '✗ Error adding supplier_id: ' . mysqli_error($conn);
                }
                echo '</div>';
            } else {
                echo '<div class="info">';
                echo '<strong>✓ Medicines table already has supplier_id column.</strong>';
                echo '</div>';
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

