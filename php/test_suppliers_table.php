<?php
/**
 * Test Suppliers Table - Check if data is being stored correctly
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
    <title>Test Suppliers Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .highlight {
            background: #fff3cd;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Suppliers Table - Database Connection Check</h1>
        
        <?php
        try {
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            // Get current database name
            $dbResult = mysqli_query($conn, "SELECT DATABASE() as db_name");
            $dbRow = mysqli_fetch_assoc($dbResult);
            $currentDb = $dbRow['db_name'] ?? 'Unknown';
            
            echo '<div class="info">';
            echo '<strong>Current Database:</strong> ' . htmlspecialchars($currentDb) . '<br>';
            echo '<strong>Expected Database:</strong> Inventory_system_db<br>';
            echo '<strong>Server:</strong> ' . mysqli_get_server_info($conn);
            echo '</div>';
            
            if ($currentDb !== 'Inventory_system_db') {
                echo '<div class="error">';
                echo '<strong>⚠️ Warning!</strong><br>';
                echo 'You are connected to database: <strong>' . htmlspecialchars($currentDb) . '</strong><br>';
                echo 'But the system expects: <strong>Inventory_system_db</strong><br>';
                echo 'Please check your connection settings in php/conn.php';
                echo '</div>';
            }
            
            // Check if suppliers table exists
            $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'suppliers'");
            if (mysqli_num_rows($tableCheck) === 0) {
                echo '<div class="error">';
                echo '<strong>✗ Table Not Found!</strong><br>';
                echo 'The suppliers table does not exist in database: <strong>' . htmlspecialchars($currentDb) . '</strong><br>';
                echo 'Please run: <a href="create_suppliers_table.php">create_suppliers_table.php</a> to create it.';
                echo '</div>';
            } else {
                echo '<div class="success">';
                echo '<strong>✓ Table Exists!</strong><br>';
                echo 'The suppliers table exists in database: <strong>' . htmlspecialchars($currentDb) . '</strong>';
                echo '</div>';
                
                // Get table structure
                $describe = mysqli_query($conn, "DESCRIBE suppliers");
                if ($describe) {
                    echo '<h2>Table Structure:</h2>';
                    echo '<table>';
                    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                    while ($row = mysqli_fetch_assoc($describe)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['Field']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($row['Extra']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                
                // Get all suppliers
                $suppliersQuery = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY id DESC");
                $supplierCount = mysqli_num_rows($suppliersQuery);
                
                echo '<h2>Suppliers Data (' . $supplierCount . ' records):</h2>';
                
                if ($supplierCount > 0) {
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Name</th><th>Contact Person</th><th>Phone</th><th>Email</th><th>Address</th><th>Created At</th><th>Updated At</th></tr>';
                    while ($supplier = mysqli_fetch_assoc($suppliersQuery)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($supplier['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($supplier['name']) . '</td>';
                        echo '<td>' . htmlspecialchars($supplier['contact_person'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($supplier['phone'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($supplier['email'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($supplier['address'] ?? 'N/A') . '</td>';
                        echo '<td>' . htmlspecialchars($supplier['created_at']) . '</td>';
                        echo '<td>' . htmlspecialchars($supplier['updated_at']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="info">';
                    echo '<strong>No suppliers found in the table.</strong><br>';
                    echo 'The table exists but is empty. Try adding a supplier through the system.';
                    echo '</div>';
                }
                
                // Show all databases that might have suppliers table
                echo '<h2>Check All Databases:</h2>';
                echo '<div class="info">';
                echo 'Checking all databases for suppliers table...<br><br>';
                
                $allDbsQuery = mysqli_query($conn, "SHOW DATABASES");
                $databasesWithSuppliers = [];
                
                while ($dbRow = mysqli_fetch_assoc($allDbsQuery)) {
                    $dbName = $dbRow['Database'];
                    // Skip system databases
                    if (in_array($dbName, ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
                        continue;
                    }
                    
                    // Try to check if suppliers table exists in this database
                    $checkTableQuery = "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = 'suppliers'";
                    $checkStmt = mysqli_prepare($conn, $checkTableQuery);
                    if ($checkStmt) {
                        mysqli_stmt_bind_param($checkStmt, 's', $dbName);
                        mysqli_stmt_execute($checkStmt);
                        $result = mysqli_stmt_get_result($checkStmt);
                        $row = mysqli_fetch_assoc($result);
                        if ($row && $row['cnt'] > 0) {
                            $databasesWithSuppliers[] = $dbName;
                        }
                        mysqli_stmt_close($checkStmt);
                    }
                }
                
                if (count($databasesWithSuppliers) > 0) {
                    echo '<strong>Databases with suppliers table:</strong><br>';
                    foreach ($databasesWithSuppliers as $db) {
                        $highlight = ($db === $currentDb) ? ' class="highlight"' : '';
                        echo '<div' . $highlight . '>';
                        echo '• <strong>' . htmlspecialchars($db) . '</strong>';
                        if ($db === $currentDb) {
                            echo ' <em>(Currently Connected)</em>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo 'No suppliers table found in any database.';
                }
                echo '</div>';
                
                // Test insert (only if not deleting)
                if (!isset($_GET['delete_test'])) {
                    echo '<h2>Test Insert:</h2>';
                    echo '<div class="info">';
                    echo 'Testing if we can insert data...<br>';
                    
                    $testName = 'Test Supplier ' . date('Y-m-d H:i:s');
                    $testSql = "INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)";
                    $testStmt = mysqli_prepare($conn, $testSql);
                    
                    if ($testStmt) {
                        $contact = 'Test Contact';
                        $phone = '123-456-7890';
                        $email = 'test@example.com';
                        $address = 'Test Address';
                        
                        mysqli_stmt_bind_param($testStmt, 'sssss', $testName, $contact, $phone, $email, $address);
                        
                        if (mysqli_stmt_execute($testStmt)) {
                            $testId = mysqli_insert_id($conn);
                            echo '<strong>✓ Test insert successful!</strong> ID: ' . $testId . '<br>';
                            echo 'Test supplier created. Check phpMyAdmin now - you should see it.<br>';
                            echo '<a href="?delete_test=' . $testId . '">Delete test supplier</a>';
                        } else {
                            echo '<strong>✗ Test insert failed:</strong> ' . mysqli_stmt_error($testStmt);
                        }
                        mysqli_stmt_close($testStmt);
                    } else {
                        echo '<strong>✗ Prepare failed:</strong> ' . mysqli_error($conn);
                    }
                    echo '</div>';
                }
            }
            
            // Handle test deletion
            if (isset($_GET['delete_test'])) {
                $testId = (int)$_GET['delete_test'];
                $deleteSql = "DELETE FROM suppliers WHERE id = ?";
                $deleteStmt = mysqli_prepare($conn, $deleteSql);
                if ($deleteStmt) {
                    mysqli_stmt_bind_param($deleteStmt, 'i', $testId);
                    if (mysqli_stmt_execute($deleteStmt)) {
                        echo '<div class="success">Test supplier deleted successfully.</div>';
                        echo '<script>setTimeout(function(){ window.location.href = "test_suppliers_table.php"; }, 1000);</script>';
                    }
                    mysqli_stmt_close($deleteStmt);
                }
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
            <p><a href="create_suppliers_table.php">Create/Check Suppliers Table</a></p>
            <p><a href="test_connection.php">Test Database Connection</a></p>
            <p><a href="../pages/suppliers_management.html">Go to Suppliers Management Page</a></p>
        </div>
    </div>
</body>
</html>

