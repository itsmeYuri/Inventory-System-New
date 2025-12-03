<?php
/**
 * System Health Check Script
 * Run this file to quickly test if the system is working correctly
 * 
 * Usage: Navigate to http://localhost/test_system.php (or your server path)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check - Inventory Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .test-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
        }
        .warning {
            background: #fef3c7;
            color: #92400e;
        }
        .info {
            background: #dbeafe;
            color: #1e40af;
        }
        .icon {
            font-size: 20px;
            margin-right: 10px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-ok {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-fail {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-warn {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 System Health Check</h1>
        <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <?php
        // Test 1: Database Connection
        echo "<h2>1. Database Connection</h2>";
        try {
            require_once __DIR__ . '/php/conn.php';
            if (isset($conn) && $conn) {
                echo '<div class="test-item success"><span class="icon">✅</span> Database connection: <strong>SUCCESS</strong></div>';
                $dbConnected = true;
            } else {
                echo '<div class="test-item error"><span class="icon">❌</span> Database connection: <strong>FAILED</strong></div>';
                $dbConnected = false;
            }
        } catch (Exception $e) {
            echo '<div class="test-item error"><span class="icon">❌</span> Database connection: <strong>ERROR</strong> - ' . htmlspecialchars($e->getMessage()) . '</div>';
            $dbConnected = false;
        }

        if ($dbConnected) {
            // Test 2: Critical Tables
            echo "<h2>2. Database Tables</h2>";
            $criticalTables = [
                'users' => 'User accounts',
                'medicines' => 'Medicine inventory',
                'suppliers' => 'Supplier information',
                'orders' => 'Order records',
                'order_items' => 'Order items',
                'batches' => 'Batch tracking',
                'batch_items' => 'Batch items',
                'supplier_medicines' => 'Supplier-medicine links',
                'supplier_notifications' => 'Supplier notifications'
            ];
            
            $optionalTables = [
                'archived_expired_items' => 'Archived expired items',
                'archived_orders' => 'Archived orders',
                'archived_order_items' => 'Archived order items',
                'archived_medicines' => 'Archived medicines',
                'archived_suppliers' => 'Archived suppliers',
                'return_tracking' => 'Return tracking',
                'sample_tracking' => 'Sample tracking'
            ];
            
            echo "<h3>Critical Tables</h3>";
            $missingCritical = [];
            foreach ($criticalTables as $table => $description) {
                $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
                if ($result && mysqli_num_rows($result) > 0) {
                    $count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM $table");
                    $row = mysqli_fetch_assoc($count);
                    echo '<div class="test-item success"><span class="icon">✅</span> <strong>' . $table . '</strong> - ' . $description . ' (Records: ' . $row['cnt'] . ')</div>';
                } else {
                    echo '<div class="test-item error"><span class="icon">❌</span> <strong>' . $table . '</strong> - MISSING!</div>';
                    $missingCritical[] = $table;
                }
            }
            
            echo "<h3>Optional Tables</h3>";
            foreach ($optionalTables as $table => $description) {
                $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
                if ($result && mysqli_num_rows($result) > 0) {
                    $count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM $table");
                    $row = mysqli_fetch_assoc($count);
                    echo '<div class="test-item success"><span class="icon">✅</span> <strong>' . $table . '</strong> - ' . $description . ' (Records: ' . $row['cnt'] . ')</div>';
                } else {
                    echo '<div class="test-item warning"><span class="icon">⚠️</span> <strong>' . $table . '</strong> - Not found (optional)</div>';
                }
            }
            
            // Test 3: Missing Columns
            echo "<h2>3. Database Columns</h2>";
            $missingColumns = [];
            
            // Check medicines.unit
            $check = mysqli_query($conn, "SHOW COLUMNS FROM medicines LIKE 'unit'");
            if (mysqli_num_rows($check) == 0) {
                $missingColumns[] = ['table' => 'medicines', 'column' => 'unit', 'impact' => 'Low'];
            }
            
            // Check suppliers.website
            $check = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'website'");
            if (mysqli_num_rows($check) == 0) {
                $missingColumns[] = ['table' => 'suppliers', 'column' => 'website', 'impact' => 'Medium'];
            }
            
            // Check suppliers.notes
            $check = mysqli_query($conn, "SHOW COLUMNS FROM suppliers LIKE 'notes'");
            if (mysqli_num_rows($check) == 0) {
                $missingColumns[] = ['table' => 'suppliers', 'column' => 'notes', 'impact' => 'Medium'];
            }
            
            // Check orders.total_amount
            $check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'total_amount'");
            if (mysqli_num_rows($check) == 0) {
                $missingColumns[] = ['table' => 'orders', 'column' => 'total_amount', 'impact' => 'Low'];
            }
            
            // Check orders.notes
            $check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'notes'");
            if (mysqli_num_rows($check) == 0) {
                $missingColumns[] = ['table' => 'orders', 'column' => 'notes', 'impact' => 'Medium'];
            }
            
            if (empty($missingColumns)) {
                echo '<div class="test-item success"><span class="icon">✅</span> All expected columns are present!</div>';
            } else {
                echo '<div class="test-item warning"><span class="icon">⚠️</span> <strong>Missing Columns Found:</strong></div>';
                echo '<table>';
                echo '<tr><th>Table</th><th>Column</th><th>Impact</th></tr>';
                foreach ($missingColumns as $col) {
                    $impactClass = $col['impact'] == 'High' ? 'badge-fail' : ($col['impact'] == 'Medium' ? 'badge-warn' : 'badge-ok');
                    echo '<tr>';
                    echo '<td><strong>' . $col['table'] . '</strong></td>';
                    echo '<td>' . $col['column'] . '</td>';
                    echo '<td><span class="status-badge ' . $impactClass . '">' . $col['impact'] . '</span></td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            
            // Test 4: Sample Data
            echo "<h2>4. Sample Data</h2>";
            $dataChecks = [
                'users' => 'SELECT COUNT(*) as cnt FROM users',
                'medicines' => 'SELECT COUNT(*) as cnt FROM medicines',
                'suppliers' => 'SELECT COUNT(*) as cnt FROM suppliers',
                'orders' => 'SELECT COUNT(*) as cnt FROM orders',
                'order_items' => 'SELECT COUNT(*) as cnt FROM order_items'
            ];
            
            foreach ($dataChecks as $table => $query) {
                $result = mysqli_query($conn, $query);
                if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $count = $row['cnt'];
                    if ($count > 0) {
                        echo '<div class="test-item success"><span class="icon">✅</span> <strong>' . $table . '</strong>: ' . $count . ' records</div>';
                    } else {
                        echo '<div class="test-item warning"><span class="icon">⚠️</span> <strong>' . $table . '</strong>: No records found (empty table)</div>';
                    }
                }
            }
            
            // Test 5: File Structure
            echo "<h2>5. File Structure</h2>";
            $criticalFiles = [
                'php/conn.php' => 'Database connection',
                'php/login.php' => 'Login handler',
                'pages/login.html' => 'Login page',
                'pages/dashboard.html' => 'Dashboard page',
                'php/get_medicines.php' => 'Medicines API',
                'php/get_orders.php' => 'Orders API',
                'php/get_suppliers.php' => 'Suppliers API'
            ];
            
            foreach ($criticalFiles as $file => $description) {
                if (file_exists(__DIR__ . '/' . $file)) {
                    echo '<div class="test-item success"><span class="icon">✅</span> <strong>' . $file . '</strong> - ' . $description . '</div>';
                } else {
                    echo '<div class="test-item error"><span class="icon">❌</span> <strong>' . $file . '</strong> - MISSING!</div>';
                }
            }
            
            // Test 6: PHP Configuration
            echo "<h2>6. PHP Configuration</h2>";
            $phpChecks = [
                'PHP Version' => phpversion(),
                'mysqli Extension' => extension_loaded('mysqli') ? 'Loaded' : 'Not Loaded',
                'Session Support' => function_exists('session_start') ? 'Enabled' : 'Disabled',
                'Error Reporting' => ini_get('display_errors') ? 'Enabled' : 'Disabled'
            ];
            
            echo '<table>';
            echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';
            foreach ($phpChecks as $setting => $value) {
                $status = ($setting == 'mysqli Extension' && $value == 'Loaded') || 
                         ($setting == 'Session Support' && $value == 'Enabled') ||
                         ($setting == 'PHP Version' && version_compare($value, '7.4', '>=')) ||
                         ($setting == 'Error Reporting' && ini_get('display_errors') == 0) ? 'OK' : 'Check';
                $badgeClass = $status == 'OK' ? 'badge-ok' : 'badge-warn';
                echo '<tr>';
                echo '<td><strong>' . $setting . '</strong></td>';
                echo '<td>' . $value . '</td>';
                echo '<td><span class="status-badge ' . $badgeClass . '">' . $status . '</span></td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Summary
            echo "<h2>7. Summary</h2>";
            $totalTests = count($criticalTables) + count($criticalFiles);
            $passedTests = $totalTests - count($missingCritical);
            
            if (empty($missingCritical)) {
                echo '<div class="test-item success">';
                echo '<span class="icon">✅</span>';
                echo '<strong>System Status: HEALTHY</strong><br>';
                echo 'All critical components are working correctly.';
                echo '</div>';
            } else {
                echo '<div class="test-item error">';
                echo '<span class="icon">❌</span>';
                echo '<strong>System Status: ISSUES FOUND</strong><br>';
                echo 'Missing critical tables: ' . implode(', ', $missingCritical);
                echo '</div>';
            }
            
            if (!empty($missingColumns)) {
                echo '<div class="test-item warning">';
                echo '<span class="icon">⚠️</span>';
                echo '<strong>Recommendations:</strong><br>';
                echo 'Consider adding missing columns for full functionality. See DATABASE_COMPLETE_ANALYSIS.md for details.';
                echo '</div>';
            }
        }
        ?>
        
        <hr style="margin: 30px 0;">
        <p style="text-align: center; color: #666;">
            <strong>Next Steps:</strong><br>
            1. Review any warnings or errors above<br>
            2. Test authentication (login/logout)<br>
            3. Test core features (add medicine, create order)<br>
            4. Check browser console for JavaScript errors<br>
            5. Review PHP error logs<br>
            <br>
            For detailed testing instructions, see <strong>SYSTEM_TESTING_GUIDE.md</strong>
        </p>
    </div>
</body>
</html>









