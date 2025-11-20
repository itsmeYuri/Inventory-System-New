<?php
/**
 * Create Supplier Medicines Table Script
 * Run this script once to create the supplier_medicines junction table
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
    <title>Create Supplier Medicines Table</title>
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
        <h1>Create Supplier Medicines Table</h1>
        
        <?php
        try {
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            echo '<div class="info">Connected to database: <strong>' . mysqli_get_server_info($conn) . '</strong></div>';
            
            // Read SQL file
            $sqlFile = __DIR__ . '/create_supplier_medicines_table.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception('SQL file not found: ' . $sqlFile);
            }
            
            $sql = file_get_contents($sqlFile);
            
            // Check if table already exists
            $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'supplier_medicines'");
            if (mysqli_num_rows($checkTable) > 0) {
                echo '<div class="info">';
                echo '<strong>Table already exists!</strong><br>';
                echo 'The supplier_medicines table already exists in the database.';
                echo '</div>';
                
                // Show table structure
                $describe = mysqli_query($conn, "DESCRIBE supplier_medicines");
                if ($describe) {
                    echo '<h2>Current Table Structure:</h2>';
                    echo '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
                    echo '<tr style="background: #f8f9fa;"><th style="padding: 10px; border: 1px solid #ddd;">Field</th><th style="padding: 10px; border: 1px solid #ddd;">Type</th><th style="padding: 10px; border: 1px solid #ddd;">Null</th><th style="padding: 10px; border: 1px solid #ddd;">Key</th><th style="padding: 10px; border: 1px solid #ddd;">Default</th><th style="padding: 10px; border: 1px solid #ddd;">Extra</th></tr>';
                    while ($row = mysqli_fetch_assoc($describe)) {
                        echo '<tr>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($row['Field']) . '</td>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($row['Type']) . '</td>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($row['Null']) . '</td>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($row['Key']) . '</td>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($row['Extra']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            } else {
                // Execute SQL to create table
                echo '<div class="info">Creating supplier_medicines table...</div>';
                
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
                echo 'The supplier_medicines table has been created successfully.';
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
            <p><a href="../pages/suppliers_management.html">Go to Suppliers Management Page</a></p>
            <p><a href="test_connection.php">Test Database Connection</a></p>
        </div>
    </div>
</body>
</html>

