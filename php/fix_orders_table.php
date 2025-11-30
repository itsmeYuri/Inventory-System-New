<?php
/**
 * Fix Orders Table
 * This script fixes the orders table structure and AUTO_INCREMENT issues
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Orders Table</title>
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
        <h1>Fix Orders Table</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Checking orders table...</div>';
            
            // Check if table exists
            $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
            $hasTable = mysqli_num_rows($checkTable) > 0;
            
            if (!$hasTable) {
                echo '<div class="error">✗ Orders table does not exist. Please create it first.</div>';
            } else {
                echo '<div class="info">Table exists. Checking structure...</div>';
                
                // Step 1: Delete any rows with id=0
                $deleteZero = mysqli_query($conn, "DELETE FROM orders WHERE id = 0");
                if ($deleteZero) {
                    $deletedCount = mysqli_affected_rows($conn);
                    if ($deletedCount > 0) {
                        echo '<div class="warning">⚠ Deleted ' . $deletedCount . ' row(s) with id=0.</div>';
                    }
                }
                
                // Step 2: Get max ID
                $maxIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
                $maxId = 0;
                if ($maxIdQuery) {
                    $maxRow = mysqli_fetch_assoc($maxIdQuery);
                    $maxId = (int)($maxRow['max_id'] ?? 0);
                }
                $nextId = max(1, $maxId + 1);
                
                echo '<div class="info">Current max ID: ' . $maxId . ', Setting AUTO_INCREMENT to: ' . $nextId . '</div>';
                
                // Step 3: Fix AUTO_INCREMENT
                $fixAutoIncrement = "ALTER TABLE orders AUTO_INCREMENT = {$nextId}";
                if (mysqli_query($conn, $fixAutoIncrement)) {
                    echo '<div class="success">✓ Fixed AUTO_INCREMENT to ' . $nextId . '</div>';
                } else {
                    echo '<div class="error">✗ Failed to fix AUTO_INCREMENT: ' . mysqli_error($conn) . '</div>';
                }
                
                // Step 4: Verify id column has AUTO_INCREMENT
                $checkIdColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders WHERE Field = 'id'");
                if ($checkIdColumn) {
                    $idColumn = mysqli_fetch_assoc($checkIdColumn);
                    $hasAutoIncrement = strpos($idColumn['Extra'] ?? '', 'auto_increment') !== false;
                    $isPrimary = strpos($idColumn['Key'] ?? '', 'PRI') !== false;
                    
                    if (!$hasAutoIncrement) {
                        echo '<div class="warning">⚠ id column missing AUTO_INCREMENT. Fixing...</div>';
                        
                        // Check if it's already a PRIMARY KEY
                        if ($isPrimary) {
                            // Just add AUTO_INCREMENT, don't add PRIMARY KEY again
                            $fixColumn = "ALTER TABLE orders MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT";
                        } else {
                            // Add both AUTO_INCREMENT and PRIMARY KEY
                            $fixColumn = "ALTER TABLE orders MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
                        }
                        
                        if (mysqli_query($conn, $fixColumn)) {
                            echo '<div class="success">✓ Fixed id column to have AUTO_INCREMENT.</div>';
                        } else {
                            $error = mysqli_error($conn);
                            echo '<div class="error">✗ Failed to fix id column: ' . htmlspecialchars($error) . '</div>';
                            
                            // Try alternative approach - drop and recreate the column
                            if (strpos($error, 'Multiple primary key') !== false) {
                                echo '<div class="info">Attempting alternative fix (dropping and recreating column)...</div>';
                                
                                // First, drop the PRIMARY KEY constraint if it exists on another column
                                @mysqli_query($conn, "ALTER TABLE orders DROP PRIMARY KEY");
                                
                                // Now modify the id column
                                $fixColumn2 = "ALTER TABLE orders MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT";
                                if (mysqli_query($conn, $fixColumn2)) {
                                    // Add PRIMARY KEY back
                                    if (mysqli_query($conn, "ALTER TABLE orders ADD PRIMARY KEY (id)")) {
                                        echo '<div class="success">✓ Fixed id column using alternative method.</div>';
                                    } else {
                                        echo '<div class="error">✗ Failed to add PRIMARY KEY back: ' . mysqli_error($conn) . '</div>';
                                    }
                                } else {
                                    echo '<div class="error">✗ Alternative fix also failed: ' . mysqli_error($conn) . '</div>';
                                }
                            }
                        }
                    } else {
                        echo '<div class="success">✓ id column has AUTO_INCREMENT.</div>';
                    }
                }
            }
            
            // Display table structure
            echo '<h2>Current Table Structure:</h2>';
            $columnsQuery = "SHOW COLUMNS FROM orders";
            $columnsResult = mysqli_query($conn, $columnsQuery);
            
            if ($columnsResult) {
                echo '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background-color: #f8f9fa;"><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                while ($row = mysqli_fetch_assoc($columnsResult)) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($row['Field']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($row['Extra']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            
            // Display table status
            echo '<h2>Table Status:</h2>';
            $statusQuery = "SHOW TABLE STATUS LIKE 'orders'";
            $statusResult = mysqli_query($conn, $statusQuery);
            if ($statusResult) {
                $status = mysqli_fetch_assoc($statusResult);
                echo '<div class="info">';
                echo '<strong>AUTO_INCREMENT:</strong> ' . ($status['Auto_increment'] ?? 'NULL') . '<br>';
                echo '<strong>Rows:</strong> ' . ($status['Rows'] ?? '0') . '<br>';
                echo '<strong>Engine:</strong> ' . ($status['Engine'] ?? 'N/A') . '<br>';
                echo '</div>';
            }
            
            echo '<div class="success" style="margin-top: 20px;">✓ Table fix completed! You can now try creating orders again.</div>';
            
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
                <li>Checks if the <code>orders</code> table exists</li>
                <li>Deletes any rows with <code>id=0</code> that cause conflicts</li>
                <li>Fixes the AUTO_INCREMENT value to be correct</li>
                <li>Ensures the <code>id</code> column has AUTO_INCREMENT enabled</li>
                <li>Displays the current table structure and status</li>
            </ul>
        </div>
    </div>
</body>
</html>

