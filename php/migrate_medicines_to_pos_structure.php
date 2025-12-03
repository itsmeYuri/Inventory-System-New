<?php
/**
 * Migration Script: Restructure medicines table for POS integration
 * 
 * This script migrates the medicines table from the current structure
 * to the new POS-compatible structure while preserving old columns for reference.
 * 
 * NEW STRUCTURE:
 * - medicine_id (VARCHAR(50)) - Primary Key
 * - medicine_group (VARCHAR(100)) - Maps from category
 * - medicine_name (VARCHAR(150)) - Maps from name
 * - generic_name (VARCHAR(150)) - New field
 * - dosage (VARCHAR(50)) - Maps from dosage_form
 * - form (VARCHAR(50)) - Maps from dosage_form
 * - stock (INT(11)) - Maps from quantity
 * - price (DECIMAL(10,2)) - Stays the same
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/conn.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    $backupTableName = "medicines_backup_" . date('YmdHis');
    
    // Step 1: Create backup table
    echo "Step 1: Creating backup table...\n";
    $backupTableSql = "CREATE TABLE IF NOT EXISTS {$backupTableName} LIKE medicines";
    if (!mysqli_query($conn, $backupTableSql)) {
        throw new Exception("Failed to create backup table: " . mysqli_error($conn));
    }
    
    $backupDataSql = "INSERT INTO {$backupTableName} SELECT * FROM medicines";
    if (!mysqli_query($conn, $backupDataSql)) {
        throw new Exception("Failed to backup data: " . mysqli_error($conn));
    }
    echo "✓ Backup created: {$backupTableName}\n";

    // Step 2: Check current structure
    echo "\nStep 2: Checking current table structure...\n";
    $checkColumns = mysqli_query($conn, "SHOW COLUMNS FROM medicines");
    $existingColumns = [];
    while ($row = mysqli_fetch_assoc($checkColumns)) {
        $existingColumns[] = $row['Field'];
    }

    $hasNewStructure = in_array('medicine_id', $existingColumns) && 
                       in_array('medicine_name', $existingColumns) &&
                       in_array('medicine_group', $existingColumns) &&
                       in_array('generic_name', $existingColumns) &&
                       in_array('dosage', $existingColumns) &&
                       in_array('form', $existingColumns) &&
                       in_array('stock', $existingColumns);

    if ($hasNewStructure) {
        echo "✓ New structure already exists.\n";
        mysqli_commit($conn);
        sendJsonResponse(true, 'Table already has new POS structure', [
            'backup_table' => $backupTableName,
            'columns' => $existingColumns
        ]);
    }

    // Step 3: Add new columns
    echo "\nStep 3: Adding new columns...\n";
    
    $alterations = [];
    
    // Add medicine_id (will store the old id as string)
    if (!in_array('medicine_id', $existingColumns)) {
        $alterations[] = "ADD COLUMN medicine_id VARCHAR(50) NULL AFTER id";
    }
    
    // Add medicine_group (map from category)
    if (!in_array('medicine_group', $existingColumns)) {
        $alterations[] = "ADD COLUMN medicine_group VARCHAR(100) NULL";
    }
    
    // Add medicine_name (map from name)
    if (!in_array('medicine_name', $existingColumns)) {
        $alterations[] = "ADD COLUMN medicine_name VARCHAR(150) NULL";
    }
    
    // Add generic_name (new field)
    if (!in_array('generic_name', $existingColumns)) {
        $alterations[] = "ADD COLUMN generic_name VARCHAR(150) NULL";
    }
    
    // Add dosage (map from dosage_form)
    if (!in_array('dosage', $existingColumns)) {
        $alterations[] = "ADD COLUMN dosage VARCHAR(50) NULL";
    }
    
    // Add form (map from dosage_form)
    if (!in_array('form', $existingColumns)) {
        $alterations[] = "ADD COLUMN form VARCHAR(50) NULL";
    }
    
    // Add stock (map from quantity)
    if (!in_array('stock', $existingColumns)) {
        $alterations[] = "ADD COLUMN stock INT(11) NULL DEFAULT 0";
    }

    if (!empty($alterations)) {
        $alterSql = "ALTER TABLE medicines " . implode(", ", $alterations);
        if (!mysqli_query($conn, $alterSql)) {
            throw new Exception("Failed to add new columns: " . mysqli_error($conn));
        }
        echo "✓ New columns added\n";
    } else {
        echo "✓ All new columns already exist\n";
    }

    // Step 4: Migrate data from old columns to new columns
    echo "\nStep 4: Migrating data...\n";
    
    // Map old data to new columns
    $migrateSql = "UPDATE medicines SET
        medicine_id = CAST(id AS CHAR),
        medicine_group = COALESCE(category, 'Uncategorized'),
        medicine_name = COALESCE(name, ''),
        generic_name = COALESCE(generic_name, ''),
        dosage = COALESCE(dosage_form, ''),
        form = COALESCE(dosage_form, ''),
        stock = COALESCE(quantity, 0)
    WHERE medicine_id IS NULL OR medicine_name IS NULL OR medicine_group IS NULL";
    
    if (!mysqli_query($conn, $migrateSql)) {
        throw new Exception("Failed to migrate data: " . mysqli_error($conn));
    }
    
    // Count migrated records
    $countSql = "SELECT COUNT(*) as count FROM medicines WHERE medicine_id IS NOT NULL";
    $countResult = mysqli_query($conn, $countSql);
    $count = 0;
    if ($countResult) {
        $row = mysqli_fetch_assoc($countResult);
        $count = $row['count'];
    }
    echo "✓ Data migrated for {$count} records\n";

    // Step 5: Update columns to NOT NULL and set defaults
    echo "\nStep 5: Updating column constraints...\n";
    
    $constraintUpdates = [];
    
    // Make medicine_id NOT NULL
    $constraintUpdates[] = "MODIFY COLUMN medicine_id VARCHAR(50) NOT NULL DEFAULT ''";
    
    // Make medicine_group NOT NULL
    $constraintUpdates[] = "MODIFY COLUMN medicine_group VARCHAR(100) NOT NULL DEFAULT 'Uncategorized'";
    
    // Make medicine_name NOT NULL
    $constraintUpdates[] = "MODIFY COLUMN medicine_name VARCHAR(150) NOT NULL DEFAULT ''";
    
    // Make generic_name NOT NULL
    $constraintUpdates[] = "MODIFY COLUMN generic_name VARCHAR(150) NOT NULL DEFAULT ''";
    
    // Make dosage NOT NULL
    $constraintUpdates[] = "MODIFY COLUMN dosage VARCHAR(50) NOT NULL DEFAULT ''";
    
    // Make form NOT NULL
    $constraintUpdates[] = "MODIFY COLUMN form VARCHAR(50) NOT NULL DEFAULT ''";
    
    // Make stock NOT NULL
    $constraintUpdates[] = "MODIFY COLUMN stock INT(11) NOT NULL DEFAULT 0";
    
    // Make price NOT NULL (if it isn't already)
    if (in_array('price', $existingColumns)) {
        $constraintUpdates[] = "MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00";
    }

    if (!empty($constraintUpdates)) {
        $constraintSql = "ALTER TABLE medicines " . implode(", ", $constraintUpdates);
        if (!mysqli_query($conn, $constraintSql)) {
            throw new Exception("Failed to update constraints: " . mysqli_error($conn));
        }
        echo "✓ Constraints updated\n";
    }

    // Step 5.5: Remove old unique constraints that conflict with new structure
    echo "\nStep 5.5: Removing old unique constraints...\n";
    
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

    // Step 6: Set up primary key on medicine_id
    echo "\nStep 6: Setting up primary key...\n";
    
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
            // Drop old primary key
            if (!mysqli_query($conn, "ALTER TABLE medicines DROP PRIMARY KEY")) {
                throw new Exception("Failed to drop old primary key: " . mysqli_error($conn));
            }
        }
        
        // Add new primary key on medicine_id
        if (!mysqli_query($conn, "ALTER TABLE medicines ADD PRIMARY KEY (medicine_id)")) {
            throw new Exception("Failed to set primary key: " . mysqli_error($conn));
        }
        echo "✓ Primary key set on medicine_id\n";
    } else {
        echo "✓ Primary key already on medicine_id\n";
    }

    // Step 7: Commit transaction
    mysqli_commit($conn);
    
    echo "\n✓ Migration completed successfully!\n";
    echo "Backup table: {$backupTableName}\n";
    echo "\nNOTE: Old columns (id, ndc, name, category, quantity, dosage_form, etc.) are preserved for reference.\n";
    echo "You can remove them later after verifying the new structure works correctly.\n";
    
    sendJsonResponse(true, 'Migration completed successfully', [
        'backup_table' => $backupTableName,
        'migrated_records' => $count,
        'message' => 'Medicines table has been restructured for POS integration. Old columns are preserved for reference.'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Migration error: " . $e->getMessage());
    sendJsonResponse(false, 'Migration failed: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}
