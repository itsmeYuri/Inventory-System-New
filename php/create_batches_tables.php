<?php
/**
 * Create Batches Tables
 * Creates the batches and batch_items tables for order batch management
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conn.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Batches Tables</title>
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #004085;
            background: #cce5ff;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Batches Tables</h1>
        
        <?php
        if (!isset($conn) || !$conn) {
            echo '<div class="error">Database connection failed!</div>';
            exit;
        }

        // Read SQL file
        $sqlFile = __DIR__ . '/create_batches_tables.sql';
        if (!file_exists($sqlFile)) {
            echo '<div class="error">SQL file not found: ' . $sqlFile . '</div>';
            exit;
        }

        $sql = file_get_contents($sqlFile);
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($statements as $statement) {
            if (empty(trim($statement))) continue;
            
            // Check if it's a CREATE TABLE statement
            if (stripos($statement, 'CREATE TABLE') !== false) {
                // Extract table name
                preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                
                // Check if table already exists
                $checkTable = mysqli_query($conn, "SHOW TABLES LIKE '{$tableName}'");
                if (mysqli_num_rows($checkTable) > 0) {
                    echo "<div class='info'>Table '{$tableName}' already exists. Skipping...</div>";
                    continue;
                }
            }
            
            if (mysqli_query($conn, $statement)) {
                $successCount++;
            } else {
                $errorCount++;
                $error = mysqli_error($conn);
                $errors[] = $error;
                echo "<div class='error'>Error executing statement: {$error}</div>";
            }
        }

        if ($errorCount === 0) {
            echo "<div class='success'>✓ Successfully created batches tables! ({$successCount} statements executed)</div>";
            
            // Verify tables were created
            echo "<h2>Verification</h2>";
            $tables = ['batches', 'batch_items'];
            foreach ($tables as $table) {
                $result = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
                if (mysqli_num_rows($result) > 0) {
                    echo "<div class='success'>✓ Table '{$table}' exists</div>";
                    
                    // Show table structure
                    $columns = mysqli_query($conn, "SHOW COLUMNS FROM {$table}");
                    echo "<h3>Table: {$table}</h3>";
                    echo "<pre>";
                    echo "Columns:\n";
                    while ($col = mysqli_fetch_assoc($columns)) {
                        echo "  - {$col['Field']} ({$col['Type']})\n";
                    }
                    echo "</pre>";
                } else {
                    echo "<div class='error'>✗ Table '{$table}' not found</div>";
                }
            }
        } else {
            echo "<div class='error'>✗ Errors occurred. Please check the errors above.</div>";
        }
        ?>
    </div>
</body>
</html>

