<?php
/**
 * Quick migration - Add POS structure columns
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    if (!isset($conn) || !$conn) {
        die("ERROR: Database connection failed\n");
    }

    echo "Starting migration...\n\n";

    // Check existing columns
    $checkColumns = mysqli_query($conn, "SHOW COLUMNS FROM medicines");
    $existingColumns = [];
    while ($row = mysqli_fetch_assoc($checkColumns)) {
        $existingColumns[] = $row['Field'];
    }

    echo "Current columns: " . implode(', ', $existingColumns) . "\n\n";

    // Check if new structure exists
    $hasNewStructure = in_array('medicine_id', $existingColumns) && 
                       in_array('medicine_name', $existingColumns) &&
                       in_array('medicine_group', $existingColumns);

    if ($hasNewStructure) {
        echo "✓ New POS structure already exists!\n";
        exit(0);
    }

    echo "Adding new columns...\n";

    // Add new columns
    $alterations = [];
    
    if (!in_array('medicine_id', $existingColumns)) {
        $alterations[] = "ADD COLUMN medicine_id VARCHAR(50) NULL AFTER id";
    }
    if (!in_array('medicine_group', $existingColumns)) {
        $alterations[] = "ADD COLUMN medicine_group VARCHAR(100) NULL";
    }
    if (!in_array('medicine_name', $existingColumns)) {
        $alterations[] = "ADD COLUMN medicine_name VARCHAR(150) NULL";
    }
    if (!in_array('generic_name', $existingColumns)) {
        $alterations[] = "ADD COLUMN generic_name VARCHAR(150) NULL";
    }
    if (!in_array('dosage', $existingColumns)) {
        $alterations[] = "ADD COLUMN dosage VARCHAR(50) NULL";
    }
    if (!in_array('form', $existingColumns)) {
        $alterations[] = "ADD COLUMN form VARCHAR(50) NULL";
    }
    if (!in_array('stock', $existingColumns)) {
        $alterations[] = "ADD COLUMN stock INT(11) NULL DEFAULT 0";
    }

    if (!empty($alterations)) {
        $alterSql = "ALTER TABLE medicines " . implode(", ", $alterations);
        echo "Executing: $alterSql\n\n";
        
        if (!mysqli_query($conn, $alterSql)) {
            throw new Exception("Failed to add columns: " . mysqli_error($conn));
        }
        echo "✓ Columns added successfully\n\n";
    }

    // Migrate data
    echo "Migrating data...\n";
    
    $migrateSql = "UPDATE medicines SET 
        medicine_id = CAST(id AS CHAR),
        medicine_group = COALESCE(category, 'Uncategorized'),
        medicine_name = COALESCE(name, ''),
        generic_name = COALESCE(generic_name, ''),
        dosage = COALESCE(dosage_form, ''),
        form = COALESCE(dosage_form, ''),
        stock = COALESCE(quantity, 0)
    WHERE medicine_id IS NULL OR medicine_name IS NULL";
    
    if (!mysqli_query($conn, $migrateSql)) {
        throw new Exception("Failed to migrate data: " . mysqli_error($conn));
    }
    
    $count = mysqli_affected_rows($conn);
    echo "✓ Migrated $count records\n\n";

    // Remove old unique constraints that conflict with new structure
    echo "Removing old unique constraints...\n";
    
    $indexResult = mysqli_query($conn, "SHOW INDEXES FROM medicines WHERE Non_unique = 0 AND Key_name != 'PRIMARY'");
    $constraintsToRemove = [];
    while ($row = mysqli_fetch_assoc($indexResult)) {
        $keyName = $row['Key_name'];
        if (!in_array($keyName, $constraintsToRemove)) {
            $constraintsToRemove[] = $keyName;
        }
    }
    
    foreach ($constraintsToRemove as $constraint) {
        // Check if it involves ndc or name (old structure columns)
        $checkResult = mysqli_query($conn, "SHOW INDEXES FROM medicines WHERE Key_name = '{$constraint}'");
        $involvesOldColumns = false;
        while ($checkRow = mysqli_fetch_assoc($checkResult)) {
            if (in_array($checkRow['Column_name'], ['ndc', 'name'])) {
                $involvesOldColumns = true;
                break;
            }
        }
        
        if ($involvesOldColumns) {
            if (!mysqli_query($conn, "ALTER TABLE medicines DROP INDEX {$constraint}")) {
                echo "  ⚠ Warning: Could not remove constraint {$constraint}: " . mysqli_error($conn) . "\n";
            } else {
                echo "  ✓ Removed constraint: {$constraint}\n";
            }
        }
    }
    echo "\n";

    // Set primary key
    echo "Setting primary key...\n";
    
    // Check current primary key
    $pkCheck = mysqli_query($conn, "SHOW KEYS FROM medicines WHERE Key_name = 'PRIMARY'");
    $hasPk = mysqli_num_rows($pkCheck) > 0;
    $currentPkColumn = null;
    
    if ($hasPk) {
        $pkRow = mysqli_fetch_assoc($pkCheck);
        $currentPkColumn = $pkRow['Column_name'];
    }
    
    if ($currentPkColumn !== 'medicine_id') {
        if ($hasPk) {
            if (!mysqli_query($conn, "ALTER TABLE medicines DROP PRIMARY KEY")) {
                throw new Exception("Failed to drop old primary key: " . mysqli_error($conn));
            }
        }
        
        if (!mysqli_query($conn, "ALTER TABLE medicines ADD PRIMARY KEY (medicine_id)")) {
            throw new Exception("Failed to set primary key: " . mysqli_error($conn));
        }
        echo "✓ Primary key set on medicine_id\n\n";
    }

    echo "✓ Migration completed successfully!\n";
    echo "You can now add medicines using the new POS structure.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
