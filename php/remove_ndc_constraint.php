<?php
/**
 * Remove unique constraint on ndc_name to allow new POS structure
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conn.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    if (!isset($conn) || !$conn) {
        die("ERROR: Database connection failed\n");
    }

    echo "Checking for unique constraints on medicines table...\n\n";

    // Get all unique constraints
    $result = mysqli_query($conn, "SHOW INDEXES FROM medicines WHERE Non_unique = 0");
    
    $constraints = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyName = $row['Key_name'];
        if ($keyName !== 'PRIMARY') {
            if (!isset($constraints[$keyName])) {
                $constraints[$keyName] = [];
            }
            $constraints[$keyName][] = $row['Column_name'];
        }
    }

    echo "Found unique constraints:\n";
    foreach ($constraints as $keyName => $columns) {
        echo "  - {$keyName} on (" . implode(', ', $columns) . ")\n";
    }
    echo "\n";

    // Check for ndc_name constraint
    if (isset($constraints['ndc_name'])) {
        echo "Removing unique constraint 'ndc_name'...\n";
        
        $dropSql = "ALTER TABLE medicines DROP INDEX ndc_name";
        if (!mysqli_query($conn, $dropSql)) {
            throw new Exception("Failed to drop constraint: " . mysqli_error($conn));
        }
        
        echo "✓ Constraint 'ndc_name' removed successfully\n\n";
    } else {
        echo "✓ No 'ndc_name' constraint found (may have already been removed)\n\n";
    }

    // Also check for any other ndc-related unique constraints
    foreach ($constraints as $keyName => $columns) {
        if (in_array('ndc', $columns) && $keyName !== 'PRIMARY') {
            echo "Found another constraint involving 'ndc': {$keyName}\n";
            echo "  Columns: " . implode(', ', $columns) . "\n";
            echo "  Removing...\n";
            
            $dropSql = "ALTER TABLE medicines DROP INDEX {$keyName}";
            if (!mysqli_query($conn, $dropSql)) {
                echo "  ⚠ Warning: Could not remove {$keyName}: " . mysqli_error($conn) . "\n";
            } else {
                echo "  ✓ Removed {$keyName}\n";
            }
        }
    }

    echo "\n✓ Process completed!\n";
    echo "You can now add medicines using the new POS structure without NDC conflicts.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
