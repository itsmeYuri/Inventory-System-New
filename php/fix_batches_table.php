<?php
// Fix Batches Table - Remove id=0 and fix AUTO_INCREMENT
// Run this script to fix the batches table

require_once __DIR__ . '/conn.php';

echo "<h1>Fix Batches Table</h1>";

try {
    // Step 1: Delete batch with id = 0
    echo "<h2>Step 1: Removing batch with id = 0</h2>";
    $deleteResult = mysqli_query($conn, "DELETE FROM batches WHERE id = 0");
    if ($deleteResult) {
        $deleted = mysqli_affected_rows($conn);
        echo "<p style='color: green;'>✓ Deleted {$deleted} batch(es) with id = 0</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No batches with id = 0 found</p>";
    }
    
    // Step 2: Get max ID
    echo "<h2>Step 2: Checking current max ID</h2>";
    $maxQuery = mysqli_query($conn, "SELECT MAX(id) as max_id FROM batches");
    $maxId = 0;
    if ($maxQuery) {
        $maxRow = mysqli_fetch_assoc($maxQuery);
        $maxId = (int)($maxRow['max_id'] ?? 0);
        echo "<p>Current max ID: {$maxId}</p>";
    }
    
    // Step 3: Set AUTO_INCREMENT
    echo "<h2>Step 3: Setting AUTO_INCREMENT</h2>";
    $nextId = max(1, $maxId + 1);
    $alterResult = mysqli_query($conn, "ALTER TABLE batches AUTO_INCREMENT = {$nextId}");
    if ($alterResult) {
        echo "<p style='color: green;'>✓ AUTO_INCREMENT set to {$nextId}</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to set AUTO_INCREMENT: " . mysqli_error($conn) . "</p>";
    }
    
    // Step 4: Verify AUTO_INCREMENT
    echo "<h2>Step 4: Verifying AUTO_INCREMENT</h2>";
    $statusQuery = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'batches'");
    if ($statusQuery) {
        $statusRow = mysqli_fetch_assoc($statusQuery);
        $autoInc = (int)($statusRow['Auto_increment'] ?? 0);
        echo "<p>Current AUTO_INCREMENT value: {$autoInc}</p>";
        if ($autoInc > $maxId) {
            echo "<p style='color: green;'>✓ AUTO_INCREMENT is correctly set</p>";
        } else {
            echo "<p style='color: orange;'>⚠ AUTO_INCREMENT may need adjustment</p>";
        }
    }
    
    // Step 5: Check for batches with NULL order_id
    echo "<h2>Step 5: Checking batches with NULL order_id</h2>";
    $nullOrderQuery = mysqli_query($conn, "SELECT id, batch_number, order_id, supplier_id, created_date FROM batches WHERE order_id IS NULL OR order_id = 0");
    if ($nullOrderQuery) {
        $nullCount = mysqli_num_rows($nullOrderQuery);
        echo "<p>Found {$nullCount} batch(es) with NULL or 0 order_id:</p>";
        if ($nullCount > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Batch Number</th><th>Order ID</th><th>Supplier ID</th><th>Created Date</th></tr>";
            while ($row = mysqli_fetch_assoc($nullOrderQuery)) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['batch_number']}</td>";
                echo "<td>" . ($row['order_id'] ?? 'NULL') . "</td>";
                echo "<td>{$row['supplier_id']}</td>";
                echo "<td>{$row['created_date']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p style='color: orange;'>⚠ These batches need to be linked to orders when orders are marked as delivered.</p>";
        } else {
            echo "<p style='color: green;'>✓ All batches have order_id set</p>";
        }
    }
    
    echo "<h2>Done!</h2>";
    echo "<p style='color: green;'>Batches table has been fixed.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

?>

