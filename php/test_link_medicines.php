<?php
/**
 * Test Link Medicines - Debug Script
 * This script helps debug the link_medicines_to_supplier.php issue
 */

require_once __DIR__ . '/conn.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Link Medicines</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Link Medicines to Supplier</h1>
        
        <?php
        try {
            if (!isset($conn) || !$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Testing supplier_medicines table operations...</div>';
            
            // Test 1: Check if table exists
            $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_medicines'");
            $hasTable = mysqli_num_rows($checkTable) > 0;
            
            if (!$hasTable) {
                echo '<div class="error">✗ supplier_medicines table does not exist. Creating it...</div>';
                $createTableSql = "CREATE TABLE supplier_medicines (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    supplier_id INT UNSIGNED NOT NULL,
                    medicine_id INT UNSIGNED NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_supplier_medicine (supplier_id, medicine_id),
                    INDEX idx_supplier_id (supplier_id),
                    INDEX idx_medicine_id (medicine_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1";
                
                if (mysqli_query($conn, $createTableSql)) {
                    echo '<div class="success">✓ Table created successfully.</div>';
                } else {
                    throw new Exception('Failed to create table: ' . mysqli_error($conn));
                }
            } else {
                echo '<div class="success">✓ Table exists.</div>';
            }
            
            // Test 2: Check table structure
            echo '<h2>Table Structure:</h2>';
            $columnsQuery = "SHOW COLUMNS FROM supplier_medicines";
            $columnsResult = mysqli_query($conn, $columnsQuery);
            
            if ($columnsResult) {
                echo '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
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
            
            // Test 3: Fix AUTO_INCREMENT
            echo '<h2>Fixing AUTO_INCREMENT:</h2>';
            
            // Delete id=0 rows
            $deleted = mysqli_query($conn, "DELETE FROM supplier_medicines WHERE id = 0");
            $deletedCount = mysqli_affected_rows($conn);
            if ($deletedCount > 0) {
                echo '<div class="info">Deleted ' . $deletedCount . ' row(s) with id=0.</div>';
            }
            
            // Get max ID
            $maxIdQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM supplier_medicines");
            $maxId = 0;
            if ($maxIdQuery) {
                $maxRow = mysqli_fetch_assoc($maxIdQuery);
                $maxId = (int)($maxRow['max_id'] ?? 0);
            }
            $nextId = max(1, $maxId + 1);
            
            echo '<div class="info">Max ID: ' . $maxId . ', Setting AUTO_INCREMENT to: ' . $nextId . '</div>';
            
            // Set AUTO_INCREMENT
            $fixAutoIncrement = "ALTER TABLE supplier_medicines AUTO_INCREMENT = {$nextId}";
            if (mysqli_query($conn, $fixAutoIncrement)) {
                echo '<div class="success">✓ AUTO_INCREMENT set to ' . $nextId . '</div>';
            } else {
                echo '<div class="error">✗ Failed to set AUTO_INCREMENT: ' . mysqli_error($conn) . '</div>';
            }
            
            // Test 4: Test insert
            echo '<h2>Testing Insert:</h2>';
            
            // Get a test supplier and medicine
            $testSupplier = mysqli_query($conn, "SELECT id FROM suppliers LIMIT 1");
            $testMedicine = mysqli_query($conn, "SELECT id FROM medicines LIMIT 1");
            
            if ($testSupplier && $testMedicine && mysqli_num_rows($testSupplier) > 0 && mysqli_num_rows($testMedicine) > 0) {
                $supplierRow = mysqli_fetch_assoc($testSupplier);
                $medicineRow = mysqli_fetch_assoc($testMedicine);
                $testSupplierId = (int)$supplierRow['id'];
                $testMedicineId = (int)$medicineRow['id'];
                
                echo '<div class="info">Testing with Supplier ID: ' . $testSupplierId . ', Medicine ID: ' . $testMedicineId . '</div>';
                
                // Delete existing test link
                mysqli_query($conn, "DELETE FROM supplier_medicines WHERE supplier_id = {$testSupplierId} AND medicine_id = {$testMedicineId}");
                
                // Try insert
                $testInsert = "INSERT IGNORE INTO supplier_medicines (supplier_id, medicine_id) VALUES ({$testSupplierId}, {$testMedicineId})";
                if (mysqli_query($conn, $testInsert)) {
                    $insertedId = mysqli_insert_id($conn);
                    $affectedRows = mysqli_affected_rows($conn);
                    echo '<div class="success">✓ Test insert successful! Inserted ID: ' . $insertedId . ', Affected rows: ' . $affectedRows . '</div>';
                    
                    // Clean up test data
                    mysqli_query($conn, "DELETE FROM supplier_medicines WHERE supplier_id = {$testSupplierId} AND medicine_id = {$testMedicineId}");
                    echo '<div class="info">Test data cleaned up.</div>';
                } else {
                    $error = mysqli_error($conn);
                    $errorCode = mysqli_errno($conn);
                    echo '<div class="error">✗ Test insert failed: ' . $error . ' (Code: ' . $errorCode . ')</div>';
                }
            } else {
                echo '<div class="error">✗ No suppliers or medicines found in database to test with.</div>';
            }
            
            // Test 5: Check table status
            echo '<h2>Table Status:</h2>';
            $statusQuery = "SHOW TABLE STATUS LIKE 'supplier_medicines'";
            $statusResult = mysqli_query($conn, $statusQuery);
            if ($statusResult) {
                $status = mysqli_fetch_assoc($statusResult);
                echo '<div class="info">';
                echo '<strong>AUTO_INCREMENT:</strong> ' . ($status['Auto_increment'] ?? 'NULL') . '<br>';
                echo '<strong>Rows:</strong> ' . ($status['Rows'] ?? '0') . '<br>';
                echo '<strong>Engine:</strong> ' . ($status['Engine'] ?? 'N/A') . '<br>';
                echo '<strong>Collation:</strong> ' . ($status['Collation'] ?? 'N/A') . '<br>';
                echo '</div>';
            }
            
            echo '<div class="success" style="margin-top: 20px;">✓ All tests completed! The table should now be ready for linking medicines.</div>';
            
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
                <li>If all tests passed, try linking medicines to a supplier again</li>
                <li>If there are errors, check the error messages above</li>
                <li>Check your PHP error log for more details</li>
            </ol>
        </div>
    </div>
</body>
</html>

