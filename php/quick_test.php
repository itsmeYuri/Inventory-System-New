<?php
// Quick Database Connection Test
// This script will output results directly

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Database Connection Test</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;background:#d4edda;padding:10px;border-radius:5px;margin:10px 0;}";
echo ".error{color:red;background:#f8d7da;padding:10px;border-radius:5px;margin:10px 0;}";
echo ".info{color:#0c5460;background:#d1ecf1;padding:10px;border-radius:5px;margin:10px 0;}";
echo "table{border-collapse:collapse;width:100%;background:white;margin:10px 0;}";
echo "th,td{padding:8px;text-align:left;border:1px solid #ddd;}";
echo "th{background:#007bff;color:white;}</style></head><body>";
echo "<h1>Database Connection Test</h1>";

require_once __DIR__ . '/conn.php';

if ($conn) {
    echo "<div class='success'><strong>✓ Connection Successful!</strong></div>";
    
    // Get database name
    $dbQuery = mysqli_query($conn, "SELECT DATABASE() as db_name");
    $dbRow = mysqli_fetch_assoc($dbQuery);
    $actualDb = $dbRow['db_name'];
    
    echo "<div class='info'>";
    echo "<strong>Connection Details:</strong><br>";
    echo "Host: localhost<br>";
    echo "Username: root<br>";
    echo "Database (configured): Inventory_system_db<br>";
    echo "Database (actual): " . htmlspecialchars($actualDb) . "<br>";
    echo "Server Version: " . mysqli_get_server_info($conn) . "<br>";
    echo "Character Set: " . mysqli_character_set_name($conn) . "<br>";
    echo "</div>";
    
    // Check if database name matches
    if (strcasecmp($actualDb, 'Inventory_system_db') === 0) {
        echo "<div class='success'><strong>✓ Database name matches: Inventory_system_db</strong></div>";
    } else {
        echo "<div class='error'><strong>⚠ Database name mismatch! Expected: Inventory_system_db, Got: " . htmlspecialchars($actualDb) . "</strong></div>";
    }
    
    // Check tables
    echo "<h2>Tables Check</h2>";
    $tables = ['medicines', 'users'];
    echo "<table><tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";
    
    foreach ($tables as $table) {
        $check = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        if (mysqli_num_rows($check) > 0) {
            $countQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM {$table}");
            $countRow = mysqli_fetch_assoc($countQuery);
            $count = $countRow['cnt'];
            echo "<tr><td>{$table}</td><td style='color:green;'>✓ Exists</td><td>{$count} rows</td></tr>";
        } else {
            echo "<tr><td>{$table}</td><td style='color:red;'>✗ Not Found</td><td>-</td></tr>";
        }
    }
    echo "</table>";
    
    // Sample data from medicines
    $sampleQuery = mysqli_query($conn, "SELECT id, name, quantity, status FROM medicines LIMIT 5");
    if ($sampleQuery && mysqli_num_rows($sampleQuery) > 0) {
        echo "<h2>Sample Medicines Data</h2>";
        echo "<table><tr><th>ID</th><th>Name</th><th>Quantity</th><th>Status</th></tr>";
        while ($row = mysqli_fetch_assoc($sampleQuery)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['quantity']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No medicines data found in the database.</div>";
    }
    
} else {
    echo "<div class='error'><strong>✗ Connection Failed!</strong><br>";
    echo "Error: " . mysqli_connect_error() . "</div>";
    echo "<div class='info'>Please check:<br>";
    echo "1. MySQL/MariaDB service is running<br>";
    echo "2. Database 'Inventory_system_db' exists<br>";
    echo "3. Username 'root' has access to the database<br>";
    echo "4. No password is required (or update conn.php if password is set)</div>";
}

echo "<hr><p><small>Test completed at: " . date('Y-m-d H:i:s') . "</small></p>";
echo "</body></html>";
?>

